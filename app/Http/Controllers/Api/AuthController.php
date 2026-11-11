<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ProfileRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::create($request->validated() + ['role' => UserRole::Student]);

            return $this->tokenResponse($user, 201);
        });
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return DB::transaction(function () use ($request) {
            $user = User::where('email', mb_strtolower($request->validated('email')))->lockForUpdate()->first();
            if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
                return response()->json(['message' => 'Неверный email или пароль.'], 401);
            }

            return $this->tokenResponse($user);
        }, 5);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(ProfileRequest $request): UserResource
    {
        $request->user()->update($request->validated());

        return new UserResource($request->user());
    }

    private function tokenResponse(User $user, int $status = 200): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($user),
            'token' => $user->createToken('api')->plainTextToken,
            'token_type' => 'Bearer',
        ], $status);
    }
}
