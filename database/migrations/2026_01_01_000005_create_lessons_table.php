<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->foreignId('study_group_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('instructor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('car_id')->nullable()->constrained()->restrictOnDelete();
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 12);
            $table->timestamps();
            $table->index(['type', 'status', 'start_at']);
            $table->index(['instructor_id', 'start_at']);
            $table->index(['car_id', 'start_at']);
            $table->index(['student_id', 'start_at']);
            $table->index(['study_group_id', 'start_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
