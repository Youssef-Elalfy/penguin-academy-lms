<?php

namespace App\Providers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use App\Policies\AcademyReportPolicy;
use App\Policies\ApiTokenPolicy;
use App\Policies\CoursePolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\LessonCompletionPolicy;
use App\Policies\LessonPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(PersonalAccessToken::class, ApiTokenPolicy::class);
        Gate::define('academy-report', [AcademyReportPolicy::class, 'viewAny']);
        RateLimiter::for('api-token', fn (Request $request) => [
            Limit::perMinute(5)->by('token:'.hash('sha256', mb_strtolower(trim(is_string($request->input('email')) ? $request->input('email') : ''))).':'.$request->ip()),
            Limit::perMinute(30)->by('token-ip:'.$request->ip()),
        ]);
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by('api:'.$request->user()->id));
        Gate::policy(Course::class, CoursePolicy::class);
        Gate::policy(Lesson::class, LessonPolicy::class);
        Gate::policy(Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(LessonCompletion::class, LessonCompletionPolicy::class);
        Password::defaults(fn (): Password => Password::min(12)->mixedCase()->numbers());
        Validator::extend('password_bytes', fn (string $attribute, mixed $value): bool => is_string($value) && strlen($value) <= 72, 'The :attribute must be at most 72 bytes.');
        Paginator::useBootstrapFive();
        RateLimiter::for('academy-write', fn (Request $request) => Limit::perMinute(60)->by('academy:'.$request->user()->id));
    }
}
