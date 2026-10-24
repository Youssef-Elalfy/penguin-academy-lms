<?php

use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\LessonController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('tokens', [TokenController::class, 'store'])->middleware('throttle:api-token')->name('tokens.store');

    Route::middleware(['auth:sanctum', 'api.account', 'throttle:api'])->group(function (): void {
        Route::get('tokens', [TokenController::class, 'index'])->middleware('abilities:tokens:read')->name('tokens.index');
        Route::delete('tokens/current', [TokenController::class, 'current'])->middleware(['abilities:tokens:revoke', 'throttle:academy-write'])->name('tokens.current');
        Route::delete('tokens/{token}', [TokenController::class, 'destroy'])->whereNumber('token')->middleware(['abilities:tokens:revoke', 'throttle:academy-write'])->name('tokens.destroy');

        Route::get('courses', [CourseController::class, 'index'])->middleware('abilities:courses:read')->name('courses.index');
        Route::get('courses/{course}', [CourseController::class, 'show'])->middleware('abilities:courses:read')->name('courses.show');
        Route::middleware(['abilities:courses:write', 'throttle:academy-write'])->group(function (): void {
            Route::post('courses', [CourseController::class, 'store'])->name('courses.store');
            Route::patch('courses/{course}', [CourseController::class, 'update'])->name('courses.update');
            Route::delete('courses/{course}', [CourseController::class, 'destroy'])->name('courses.destroy');
            Route::put('courses/{course}/publication', [CourseController::class, 'publish'])->name('courses.publish');
        });

        Route::scopeBindings()->prefix('courses/{course}/lessons')->name('lessons.')->group(function (): void {
            Route::get('/', [LessonController::class, 'index'])->middleware('abilities:lessons:read')->name('index');
            Route::middleware(['abilities:lessons:write', 'throttle:academy-write'])->group(function (): void {
                Route::post('/', [LessonController::class, 'store'])->name('store');
                Route::put('order', [LessonController::class, 'reorder'])->name('reorder');
                Route::patch('{lesson}', [LessonController::class, 'update'])->name('update');
                Route::delete('{lesson}', [LessonController::class, 'destroy'])->name('destroy');
                Route::put('{lesson}/publication', [LessonController::class, 'publish'])->name('publish');
            });
            Route::get('{lesson}', [LessonController::class, 'show'])->whereNumber('lesson')->middleware('abilities:lessons:read')->name('show');
        });

        Route::get('enrollments', [EnrollmentController::class, 'index'])->middleware('abilities:enrollments:read')->name('enrollments.index');
        Route::post('enrollments', [EnrollmentController::class, 'store'])->middleware(['abilities:enrollments:write', 'throttle:academy-write'])->name('enrollments.store');
        Route::delete('enrollments/{enrollment}', [EnrollmentController::class, 'destroy'])->middleware(['abilities:enrollments:write', 'throttle:academy-write'])->name('enrollments.destroy');
        Route::get('enrollments/{enrollment}/progress', [ProgressController::class, 'show'])->middleware('abilities:progress:read')->name('progress.show');
        Route::match(['put', 'delete'], 'enrollments/{enrollment}/lessons/{lesson}/completion', [ProgressController::class, 'update'])
            ->middleware(['abilities:progress:write', 'throttle:academy-write'])->name('progress.update');
        Route::get('reports/overview', [ReportController::class, 'index'])->middleware('abilities:reports:read')->name('reports.index');
    });
});
