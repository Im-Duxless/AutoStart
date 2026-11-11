<?php

namespace App\Services;

use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\Car;
use App\Models\Lesson;
use App\Models\StudyGroup;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class LessonService
{
    public function save(User $actor, array $data, ?Lesson $existing = null): Lesson
    {
        return DB::transaction(function () use ($actor, $data, $existing) {
            $lesson = $existing ? Lesson::query()->lockForUpdate()->findOrFail($existing->id) : new Lesson;
            if ($lesson->exists) {
                Gate::forUser($actor)->authorize('update', $lesson);
                if (! in_array($lesson->status, [LessonStatus::Free, LessonStatus::Scheduled], true) || ! $lesson->start_at->isFuture()) {
                    throw new BusinessException('Изменить можно только предстоящее занятие без записи курсанта.');
                }
            }

            $type = $data['type'] ?? $lesson->type;
            if ($lesson->exists && $type !== $lesson->type) {
                throw ValidationException::withMessages(['type' => 'Тип существующего занятия изменить нельзя.']);
            }
            $start = Carbon::parse($data['start_at'] ?? $lesson->start_at)->setTimezone(config('app.timezone'));
            $end = Carbon::parse($data['end_at'] ?? $lesson->end_at)->setTimezone(config('app.timezone'));
            if ($end->lte($start)) {
                throw ValidationException::withMessages(['end_at' => 'Окончание должно быть позже начала занятия.']);
            }
            if (! $start->isFuture()) {
                throw new BusinessException('Выберите время начала в будущем.');
            }

            $lesson->fill(['type' => $type, 'start_at' => $start, 'end_at' => $end]);

            if ($type === 'theory') {
                abort_unless($actor->role === UserRole::Admin, 403);
                $this->scheduleTheory($lesson, $data);
            } else {
                $this->scheduleDriving($lesson, $data, $actor);
            }

            $lesson->save();

            return $lesson;
        }, 5);
    }

    public function book(Lesson $lesson, User $actor): Lesson
    {
        return DB::transaction(function () use ($lesson, $actor) {
            // Блокировка слота не позволяет двум курсантам записаться одновременно.
            $slot = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            $student = User::query()->lockForUpdate()->findOrFail($actor->id);
            abort_unless($student->role === UserRole::Student, 403);

            if ($slot->type !== 'driving' || ! $slot->status->canChangeTo(LessonStatus::Booked) || $slot->student_id !== null) {
                throw new BusinessException('Это занятие уже недоступно для записи.');
            }
            if (! $slot->start_at->isFuture()) {
                throw new BusinessException('Время записи на это занятие истекло.');
            }

            $car = $this->lockDrivingResources($slot->instructor_id, $slot->car_id);
            if (! $student->approvedGroups()->where('category', $car->category->value)->exists()) {
                throw new BusinessException('Для записи нужна подтверждённая группа категории '.$car->category->value.'.');
            }
            $this->checkDrivingConflicts($slot);
            if ($this->studentHasOverlap($student, $slot->start_at, $slot->end_at)) {
                throw new BusinessException('У вас уже есть занятие в это время.');
            }

            $slot->update(['student_id' => $student->id, 'status' => LessonStatus::Booked]);

            return $slot;
        }, 5);
    }

    public function cancel(Lesson $lesson, User $actor): Lesson
    {
        return DB::transaction(function () use ($lesson, $actor) {
            $slot = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            Gate::forUser($actor)->authorize('cancel', $slot);

            $status = $actor->role === UserRole::Student ? LessonStatus::Free : LessonStatus::Cancelled;
            if (! $slot->start_at->isFuture() || ! $slot->status->canChangeTo($status)) {
                throw new BusinessException('Отменить можно только предстоящее занятие.');
            }

            $slot->status = $status;
            if ($status === LessonStatus::Free) {
                $slot->student_id = null;
            }
            $slot->save();

            return $slot;
        }, 5);
    }

    public function complete(Lesson $lesson, User $actor): Lesson
    {
        return DB::transaction(function () use ($lesson, $actor) {
            $slot = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            Gate::forUser($actor)->authorize('complete', $slot);

            if ($slot->type !== 'driving' || ! $slot->status->canChangeTo(LessonStatus::Completed) || $slot->student_id === null) {
                throw new BusinessException('Завершить можно только вождение с записанным курсантом.');
            }
            if ($slot->end_at->isFuture()) {
                throw new BusinessException('Отметить занятие можно после его окончания.');
            }

            $slot->update(['status' => LessonStatus::Completed]);

            return $slot;
        }, 5);
    }

    public function delete(Lesson $lesson, User $actor): void
    {
        DB::transaction(function () use ($lesson, $actor) {
            $slot = Lesson::query()->lockForUpdate()->findOrFail($lesson->id);
            Gate::forUser($actor)->authorize('delete', $slot);

            if ($slot->student_id !== null || $slot->status === LessonStatus::Completed
                || (! $slot->start_at->isFuture() && $slot->status !== LessonStatus::Cancelled)) {
                throw new BusinessException('Это занятие сохраняется в истории. Предстоящее занятие с записью можно отменить.');
            }

            $slot->delete();
        }, 5);
    }

    public function studentHasOverlap(User $student, CarbonInterface $start, CarbonInterface $end, ?int $skipId = null): bool
    {
        return Lesson::query()->overlapping($start, $end)
            ->when($skipId, fn (Builder $query) => $query->where('id', '!=', $skipId))
            ->where(function (Builder $query) use ($student) {
                $query->where('student_id', $student->id)->orWhereIn(
                    'study_group_id', $student->approvedGroups()->select('study_groups.id')->getQuery(),
                );
            })
            ->exists();
    }

    private function scheduleTheory(Lesson $lesson, array $data): void
    {
        $groupId = array_key_exists('study_group_id', $data) ? $data['study_group_id'] : $lesson->study_group_id;
        if (! $groupId) {
            throw ValidationException::withMessages(['study_group_id' => 'Выберите группу для теории.']);
        }

        $group = StudyGroup::query()->lockForUpdate()->findOrFail($groupId);
        $students = $group->approvedStudents()->orderBy('users.id')->lockForUpdate()->get();
        foreach ($students as $student) {
            if ($this->studentHasOverlap($student, $lesson->start_at, $lesson->end_at, $lesson->id)) {
                throw new BusinessException('У курсанта этой группы уже есть занятие в это время.');
            }
        }
        if ($group->lessons()->overlapping($lesson->start_at, $lesson->end_at)
            ->when($lesson->exists, fn (Builder $query) => $query->where('id', '!=', $lesson->id))->exists()) {
            throw new BusinessException('У этой группы уже есть занятие в это время.');
        }

        $lesson->fill([
            'study_group_id' => $group->id,
            'instructor_id' => null,
            'car_id' => null,
            'status' => LessonStatus::Scheduled,
        ]);
    }

    private function scheduleDriving(Lesson $lesson, array $data, User $actor): void
    {
        $instructorId = array_key_exists('instructor_id', $data) ? $data['instructor_id'] : $lesson->instructor_id;
        if ($actor->role === UserRole::Instructor) {
            abort_if($instructorId !== null && (int) $instructorId !== $actor->id, 403);
            $instructorId = $actor->id;
        }
        $carId = array_key_exists('car_id', $data) ? $data['car_id'] : $lesson->car_id;
        if (! $instructorId || ! $carId) {
            throw ValidationException::withMessages([
                ...(! $instructorId ? ['instructor_id' => 'Выберите инструктора.'] : []),
                ...(! $carId ? ['car_id' => 'Выберите транспорт.'] : []),
            ]);
        }

        $this->lockDrivingResources($instructorId, $carId);
        $lesson->fill([
            'instructor_id' => $instructorId,
            'car_id' => $carId,
            'study_group_id' => null,
            'status' => LessonStatus::Free,
        ]);
        $this->checkDrivingConflicts($lesson);
    }

    private function lockDrivingResources(int $instructorId, int $carId): Car
    {
        $instructor = User::query()->lockForUpdate()->find($instructorId);
        $car = Car::query()->lockForUpdate()->find($carId);
        if ($instructor?->role !== UserRole::Instructor || ! $car?->is_active) {
            throw new BusinessException('Инструктор или транспорт больше недоступен.');
        }

        return $car;
    }

    private function checkDrivingConflicts(Lesson $lesson): void
    {
        $overlap = Lesson::query()->overlapping($lesson->start_at, $lesson->end_at)
            ->when($lesson->exists, fn (Builder $query) => $query->where('id', '!=', $lesson->id));
        if ((clone $overlap)->where('instructor_id', $lesson->instructor_id)->exists()) {
            throw new BusinessException('У инструктора уже есть занятие в это время.');
        }
        if ($overlap->where('car_id', $lesson->car_id)->exists()) {
            throw new BusinessException('Транспорт уже занят в это время.');
        }
    }
}
