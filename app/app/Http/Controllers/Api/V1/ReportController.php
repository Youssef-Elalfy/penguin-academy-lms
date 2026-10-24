<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReportRequest;
use App\Http\Resources\Api\ReportResource;
use App\Models\Course;
use App\Models\User;
use App\Services\Learning\AcademyAnalytics;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReportController extends Controller
{
    public function index(ReportRequest $request, AcademyAnalytics $analytics): AnonymousResourceCollection
    {
        $courses = Course::query();
        $admin = $request->user()->hasRole(UserRole::Admin);
        if (! $admin) {
            $courses->where('teacher_id', $request->user()->id);
        }
        $totals = $analytics->totals(clone $courses);
        if ($admin) {
            $totals['active_student_accounts'] = User::where('role', UserRole::Student)->where('is_active', true)->count();
            $totals['active_teacher_accounts'] = User::where('role', UserRole::Teacher)->where('is_active', true)->count();
        }

        return ReportResource::collection($analytics->courses($courses)->orderByDesc('courses.id')
            ->paginate($request->pageSize())->appends($request->validated()))
            ->additional(['summary' => $totals, 'scope' => $admin ? 'academy' : 'assigned_courses']);
    }
}
