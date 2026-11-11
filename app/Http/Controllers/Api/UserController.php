<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(ListRequest $request): AnonymousResourceCollection
    {
        $query = User::query()
            ->when($request->validated('role'), fn (Builder $query, $role) => $query->where('role', $role))
            ->when($request->filled('email'), fn (Builder $query) => $query->where('email', 'like', '%'.$request->validated('email').'%'))
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->validated('search');
                $query->where(fn (Builder $fields) => $fields->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")->orWhere('phone', 'like', "%$search%"));
            });

        return UserResource::collection($request->paginate($query));
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function store(UserRequest $request): UserResource
    {
        return new UserResource(User::create($request->validated()));
    }

    public function update(UserRequest $request, User $user): UserResource
    {
        $updated = DB::transaction(function () use ($request, $user) {
            $target = $this->lockAccountChanges($request->user(), $user);
            $data = $request->validated();
            $roleChanges = isset($data['role']) && $data['role'] !== $target->role->value;
            if ($roleChanges && ($target->is($request->user()) || $this->hasRecords($target))) {
                throw new BusinessException('Роль своего аккаунта или пользователя со связанными записями изменить нельзя.');
            }

            $target->update($data);
            if ($roleChanges || isset($data['password'])) {
                $target->tokens()->delete();
            }

            return $target;
        }, 5);

        return new UserResource($updated);
    }

    public function destroy(Request $request, User $user): Response
    {
        DB::transaction(function () use ($request, $user) {
            $target = $this->lockAccountChanges($request->user(), $user);
            if ($target->is($request->user()) || $this->hasRecords($target)) {
                throw new BusinessException('Свой аккаунт и пользователей со связанными записями удалить нельзя.');
            }

            $target->tokens()->delete();
            $target->delete();
        }, 5);

        return response()->noContent();
    }

    private function lockAccountChanges(User $actor, User $target): User
    {
        $users = User::whereKey([$actor->id, $target->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_unless($users->get($actor->id)?->role === UserRole::Admin, 403);

        return $users->get($target->id) ?? abort(404);
    }

    private function hasRecords(User $user): bool
    {
        return $user->studyGroups()->exists() || $user->instructedLessons()->exists()
            || $user->drivingLessons()->exists() || $user->examAttempts()->exists() || $user->payments()->exists();
    }
}
