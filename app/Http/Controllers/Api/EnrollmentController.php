<?php

namespace App\Http\Controllers\Api;

use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\EnrollmentRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\StudyGroup;
use App\Services\EnrollmentService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class EnrollmentController extends Controller
{
    public function __construct(private EnrollmentService $enrollments) {}

    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = $this->applications()
            ->when($request->user()->role === UserRole::Student, fn (Builder $query) => $query->where('user_id', $request->user()->id))
            ->when($request->validated('study_group_id'), fn (Builder $query, $id) => $query->where('study_group_id', $id))
            ->when($request->validated('category'), fn (Builder $query, $category) => $query->where('study_groups.category', $category))
            ->when($request->validated('status'), fn (Builder $query, $status) => $query->where('study_group_user.status', $status))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->validated('search');
                $query->where(fn (Builder $fields) => $fields->where('users.name', 'like', "%$search%")
                    ->orWhere('study_groups.name', 'like', "%$search%"));
            });

        return EnrollmentResource::collection($request->paginate($query, prefix: 'study_group_user.'));
    }

    public function enroll(Request $request, StudyGroup $studyGroup): JsonResponse
    {
        $id = $this->enrollments->apply($request->user()->id, $studyGroup->id);

        return $this->resource($id)->response()->setStatusCode(201);
    }

    public function store(EnrollmentRequest $request): JsonResponse
    {
        $id = $this->enrollments->apply($request->validated('user_id'), $request->validated('study_group_id'));

        return $this->resource($id)->response()->setStatusCode(201);
    }

    public function update(EnrollmentRequest $request, int $enrollment): EnrollmentResource
    {
        $this->enrollments->decide($enrollment, EnrollmentStatus::from($request->validated('status')));

        return $this->resource($enrollment);
    }

    private function resource(int $id): EnrollmentResource
    {
        return new EnrollmentResource($this->applications()->where('study_group_user.id', $id)->firstOrFail());
    }

    private function applications(): Builder
    {
        return DB::table('study_group_user')
            ->join('study_groups', 'study_groups.id', '=', 'study_group_user.study_group_id')
            ->join('users', 'users.id', '=', 'study_group_user.user_id')
            ->select('study_group_user.*', 'study_groups.name as group_name', 'study_groups.category', 'users.name as student_name');
    }
}
