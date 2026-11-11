<?php

namespace App\Http\Controllers\Api;

use App\Enums\LicenseCategory;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\StudyGroup;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SchoolController extends Controller
{
    public function show(): JsonResponse
    {
        $groups = StudyGroup::where('status', 'open')->withCount('approvedStudents')->orderBy('start_date')->get();

        return response()->json(['data' => [
            'name' => config('app.name'),
            'timezone' => config('app.timezone'),
            'currency' => config('autostart.currency'),
            'contacts' => config('autostart.contacts'),
            'categories' => array_map(function (LicenseCategory $category) use ($groups) {
                $next = $groups->first(fn (StudyGroup $group) => $group->category === $category
                    && $group->approved_students_count < $group->capacity);

                return [
                    'code' => $category->value,
                    'label' => $category->label(),
                    'next_start' => $next?->start_date->toDateString(),
                    'tuition' => $next?->tuition,
                ];
            }, LicenseCategory::cases()),
            'instructors' => User::where('role', UserRole::Instructor->value)->orderBy('name')->get(['id', 'name']),
        ]]);
    }

    public function health(): JsonResponse
    {
        try {
            DB::select('select 1');
            $available = true;
        } catch (QueryException $error) {
            report($error);
            $available = false;
        }

        return response()->json([
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
            'database' => $available ? 'ok' : 'unavailable',
        ], $available ? 200 : 503);
    }
}
