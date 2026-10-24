<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Admin\TeacherController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\LearningReportController;
use App\Http\Controllers\LessonCompletionController;
use App\Http\Controllers\LessonController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'auth.session'])->group(function (): void {
    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])->middleware('throttle:account-write')->name('account.password.update');

    Route::middleware('password.changed')->group(function (): void {
        Route::get('/my-courses', [EnrollmentController::class, 'mine'])->name('my-courses');
        Route::get('/admin/enrollments', [EnrollmentController::class, 'overview'])->name('admin.enrollments.index');
        Route::resource('courses', CourseController::class)->middlewareFor(['store', 'update', 'destroy'], 'throttle:academy-write');
        Route::scopeBindings()->prefix('courses/{course}')->name('courses.')->group(function (): void {
            Route::put('/publication', [CourseController::class, 'publish'])->middleware('throttle:academy-write')->name('publish');
            Route::put('/teacher', [CourseController::class, 'assign'])->middleware('throttle:academy-write')->name('assign');
            Route::get('/progress', [LearningReportController::class, 'index'])->name('progress.index');
            Route::get('/enrollments/{enrollment}/progress', [LearningReportController::class, 'show'])->name('progress.show');
            Route::put('/lessons/{lesson}/completion', [LessonCompletionController::class, 'store'])->middleware('throttle:academy-write')->name('lessons.completion.store');
            Route::delete('/lessons/{lesson}/completion', [LessonCompletionController::class, 'destroy'])->middleware('throttle:academy-write')->name('lessons.completion.destroy');
            Route::get('/lessons/order', [LessonController::class, 'order'])->name('lessons.order');
            Route::put('/lessons/order', [LessonController::class, 'reorder'])->middleware('throttle:academy-write')->name('lessons.reorder');
            Route::resource('lessons', LessonController::class)->except('index')->middlewareFor(['store', 'update', 'destroy'], 'throttle:academy-write');
            Route::put('/lessons/{lesson}/publication', [LessonController::class, 'publish'])->middleware('throttle:academy-write')->name('lessons.publish');
            Route::get('/enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
            Route::post('/enrollments', [EnrollmentController::class, 'store'])->middleware('throttle:academy-write')->name('enrollments.store');
            Route::delete('/enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->middleware('throttle:academy-write')->name('enrollments.destroy');
        });
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->can('viewAdminDashboard', User::class)->name('admin.dashboard');
        Route::get('/teacher/dashboard', [DashboardController::class, 'teacher'])->can('viewTeacherDashboard', User::class)->name('teacher.dashboard');
        Route::get('/student/dashboard', [DashboardController::class, 'student'])->can('viewStudentDashboard', User::class)->name('student.dashboard');
        Route::prefix('admin')->name('admin.')->middleware('can:viewAny,'.User::class)->group(function (): void {
            Route::resource('teachers', TeacherController::class)->only(['index', 'create', 'store', 'edit', 'update'])
                ->middlewareFor(['store', 'update'], 'throttle:account-write');
        });
    });
});
