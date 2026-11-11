<?php

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StudyGroupRequest;
use App\Http\Resources\StudyGroupResource;
use App\Models\StudyGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class StudyGroupController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = StudyGroup::withCount('approvedStudents')
            ->when($user->role === UserRole::Student, function (Builder $query) use ($user) {
                $query->where(fn (Builder $groups) => $groups->where('status', 'open')
                    ->orWhereHas('approvedStudents', fn (Builder $students) => $students->whereKey($user->id)));
            })
            ->when($request->validated('category'), fn (Builder $query, $category) => $query->where('category', $category))
            ->when($request->validated('status'), fn (Builder $query, $status) => $query->where('status', $status))
            ->when($request->filled('search'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->validated('search').'%'));

        return StudyGroupResource::collection($request->paginate($query, 'start_date', 'asc'));
    }

    public function show(Request $request, StudyGroup $studyGroup): StudyGroupResource
    {
        abort_unless($request->user()->role === UserRole::Admin || $studyGroup->status === 'open'
            || $studyGroup->approvedStudents()->whereKey($request->user()->id)->exists(), 403);

        return new StudyGroupResource($studyGroup->loadCount('approvedStudents'));
    }

    public function store(StudyGroupRequest $request): StudyGroupResource
    {
        return new StudyGroupResource(StudyGroup::create($request->validated())->loadCount('approvedStudents'));
    }

    public function update(StudyGroupRequest $request, StudyGroup $studyGroup): StudyGroupResource
    {
        $group = DB::transaction(function () use ($request, $studyGroup) {
            $group = StudyGroup::query()->lockForUpdate()->findOrFail($studyGroup->id);
            $data = $request->validated();
            if (isset($data['capacity']) && $data['capacity'] < $group->approvedStudents()->count()) {
                throw new BusinessException('Вместимость не может быть меньше числа зачисленных курсантов.');
            }
            if (isset($data['category']) && $data['category'] !== $group->category->value
                && ($group->students()->exists() || $group->lessons()->exists())) {
                throw new BusinessException('Категорию группы с заявками или расписанием изменить нельзя.');
            }

            $group->update($data);

            return $group;
        }, 5);

        return new StudyGroupResource($group->loadCount('approvedStudents'));
    }

    public function destroy(StudyGroup $studyGroup): Response
    {
        DB::transaction(function () use ($studyGroup) {
            $group = StudyGroup::query()->lockForUpdate()->findOrFail($studyGroup->id);
            if ($group->lessons()->exists() || $group->students()->wherePivotIn(
                'status', [EnrollmentStatus::Approved->value, EnrollmentStatus::Pending->value],
            )->exists()) {
                throw new BusinessException('Группу с занятиями, курсантами или активными заявками удалить нельзя.');
            }
            $group->delete();
        }, 5);

        return response()->noContent();
    }
}
