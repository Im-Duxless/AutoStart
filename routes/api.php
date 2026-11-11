<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CarController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\QuestionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StudyGroupController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('health', [SchoolController::class, 'health'])->name('health');
Route::get('school', [SchoolController::class, 'show'])->name('school.show');
Route::middleware('throttle:10,1')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('login', [AuthController::class, 'login'])->name('login');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('me', [AuthController::class, 'me'])->name('me.show');
    Route::patch('me', [AuthController::class, 'update'])->name('me.update');

    Route::middleware('role:student,instructor')->group(function () {
        Route::get('lessons/summary', [LessonController::class, 'summary'])->name('lessons.summary');
    });

    Route::apiResource('lessons', LessonController::class)->only(['index', 'show']);
    Route::post('lessons/{lesson}/cancel', [LessonController::class, 'cancel'])->name('lessons.cancel');

    Route::middleware('role:student,admin')->group(function () {
        Route::apiResource('study-groups', StudyGroupController::class)->only(['index', 'show']);
        Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::get('exams/history', [ExamController::class, 'history'])->name('exams.history');
        Route::get('exams/history/{exam_attempt}', [ExamController::class, 'show'])->name('exams.show');
        Route::apiResource('payments', PaymentController::class)->only(['index']);
    });

    Route::middleware('role:student')->group(function () {
        Route::post('study-groups/{study_group}/enroll', [EnrollmentController::class, 'enroll'])->name('study-groups.enroll');
        Route::post('lessons/{lesson}/book', [LessonController::class, 'book'])->name('lessons.book');
        Route::get('tickets', [ExamController::class, 'tickets'])->name('tickets.index');
        Route::get('tickets/{number}', [ExamController::class, 'ticket'])->whereNumber('number')->name('tickets.show');
        Route::post('exams/submit', [ExamController::class, 'submit'])->name('exams.submit');
        Route::get('payments/balance', [PaymentController::class, 'balance'])->name('payments.balance');
    });

    Route::get('payments/{payment}', [PaymentController::class, 'show'])->middleware('role:student,admin')->name('payments.show');

    Route::middleware('role:instructor,admin')->group(function () {
        Route::apiResource('cars', CarController::class)->only(['index', 'show']);
        Route::apiResource('lessons', LessonController::class)->only(['store', 'update']);
        Route::post('lessons/{lesson}/complete', [LessonController::class, 'complete'])->name('lessons.complete');
    });

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('study-groups', StudyGroupController::class)->except(['index', 'show']);
        Route::apiResource('cars', CarController::class)->except(['index', 'show']);
        Route::apiResource('lessons', LessonController::class)->only(['destroy']);
        Route::apiResource('questions', QuestionController::class);
        Route::apiResource('payments', PaymentController::class)->except(['index', 'show']);
        Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::patch('enrollments/{enrollment}', [EnrollmentController::class, 'update'])->whereNumber('enrollment')->name('enrollments.update');
        Route::get('reports/summary', [ReportController::class, 'summary'])->name('reports.summary');
        Route::get('reports/instructors', [ReportController::class, 'instructors'])->name('reports.instructors');
    });
});
