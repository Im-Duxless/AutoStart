<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\LessonStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentService
{
    public function __construct(private LessonService $lessons) {}

    public function apply(int $studentId, int $groupId): int
    {
        return DB::transaction(function () use ($studentId, $groupId) {
            $group = StudyGroup::query()->lockForUpdate()->findOrFail($groupId);
            $student = User::query()->lockForUpdate()->findOrFail($studentId);

            if ($student->role !== UserRole::Student) {
                throw new BusinessException('Заявку можно оформить только для курсанта.');
            }
            if ($group->status !== 'open') {
                throw new BusinessException('Набор в эту группу уже закрыт.');
            }
            $this->checkCapacity($group);

            $existing = $student->studyGroups()->whereKey($groupId)->first()?->pivot;
            $hasActive = $student->studyGroups()->where('category', $group->category->value)
                ->where('study_groups.status', '!=', 'closed')
                ->wherePivotIn('status', [EnrollmentStatus::Pending->value, EnrollmentStatus::Approved->value])->exists();
            if ($hasActive) {
                throw new BusinessException('У курсанта уже есть действующая заявка или группа этой категории.');
            }

            if ($existing) {
                $student->studyGroups()->updateExistingPivot($groupId, ['status' => EnrollmentStatus::Pending->value]);
            } else {
                $student->studyGroups()->attach($groupId, ['status' => EnrollmentStatus::Pending->value]);
            }

            return (int) $student->studyGroups()->whereKey($groupId)->firstOrFail()->pivot->id;
        }, 5);
    }

    public function decide(int $id, EnrollmentStatus $status): void
    {
        DB::transaction(function () use ($id, $status) {
            $application = DB::table('study_group_user')->where('id', $id)->first();
            abort_if($application === null, 404);
            $group = StudyGroup::query()->lockForUpdate()->findOrFail($application->study_group_id);
            $student = User::query()->lockForUpdate()->findOrFail($application->user_id);
            $application = DB::table('study_group_user')->where('id', $id)->lockForUpdate()->first();
            abort_if($application === null, 404);

            if ($application->status !== EnrollmentStatus::Pending->value) {
                throw new BusinessException('По этой заявке уже принято решение.');
            }
            if ($status === EnrollmentStatus::Approved) {
                if ($group->status === 'closed' || $student->role !== UserRole::Student) {
                    throw new BusinessException('Подтвердить заявку в эту группу сейчас нельзя.');
                }
                $this->checkCapacity($group);
                $alreadyApproved = $student->approvedGroups()->where('category', $group->category->value)
                    ->where('study_groups.status', '!=', 'closed')->exists();
                if ($alreadyApproved) {
                    throw new BusinessException('Курсант уже зачислен в группу этой категории.');
                }

                $theory = $group->lessons()->where('status', LessonStatus::Scheduled->value)
                    ->where('end_at', '>', now())->get();
                foreach ($theory as $lesson) {
                    if ($this->lessons->studentHasOverlap($student, $lesson->start_at, $lesson->end_at)) {
                        throw new BusinessException('Расписание группы пересекается с занятием курсанта.');
                    }
                }
            }

            $student->studyGroups()->updateExistingPivot($group->id, ['status' => $status->value]);
        }, 5);
    }

    private function checkCapacity(StudyGroup $group): void
    {
        if ($group->approvedStudents()->count() >= $group->capacity) {
            throw new BusinessException('В группе больше нет свободных мест.');
        }
    }
}
