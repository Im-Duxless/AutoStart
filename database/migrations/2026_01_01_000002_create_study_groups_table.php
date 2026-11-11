<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('category', 2);
            $table->unsignedSmallInteger('capacity');
            $table->string('status', 16)->default('open');
            $table->date('start_date');
            $table->decimal('tuition', 10, 2)->nullable();
            $table->timestamps();
            $table->index(['category', 'status', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_groups');
    }
};
