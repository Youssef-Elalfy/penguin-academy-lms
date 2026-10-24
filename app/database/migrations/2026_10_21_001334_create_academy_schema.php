<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', ['admin', 'teacher', 'student'])->default('student')->index();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
            $table->index(['role', 'is_active']);
        });

        Schema::table('sessions', function (Blueprint $table): void {
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['teacher_id', 'deleted_at', 'published_at']);
            $table->index(['deleted_at', 'published_at']);
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content');
            $table->unsignedInteger('position');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
            $table->unique(['course_id', 'position']);
            $table->unique(['id', 'course_id']);
            $table->index(['course_id', 'is_published']);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'course_id']);
            $table->unique(['id', 'course_id']);
            $table->index(['course_id', 'withdrawn_at']);
            $table->index(['student_id', 'withdrawn_at']);
        });

        Schema::create('lesson_completions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('enrollment_id');
            $table->unsignedBigInteger('lesson_id');
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();
            $table->unique(['enrollment_id', 'lesson_id']);
            $table->foreign(['enrollment_id', 'course_id'], 'completion_enrollment_course_fk')
                ->references(['id', 'course_id'])->on('enrollments')->cascadeOnDelete();
            $table->foreign(['lesson_id', 'course_id'], 'completion_lesson_course_fk')
                ->references(['id', 'course_id'])->on('lessons')->cascadeOnDelete();
            $table->index('course_id');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('courses');
        Schema::table('sessions', function (Blueprint $table): void {
            $table->dropForeign(['user_id']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role', 'is_active']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'is_active', 'must_change_password']);
        });
    }
};
