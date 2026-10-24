<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Academy\CompletionActions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangeCompletionRequest;
use App\Http\Resources\Api\ProgressResource;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Services\Learning\ProgressService;
use Illuminate\Support\Facades\Gate;

class ProgressController extends Controller
{
    public function show(Enrollment $enrollment, ProgressService $progress): ProgressResource
    {
        Gate::authorize('view', $enrollment);

        return new ProgressResource($progress->forEnrollment($enrollment));
    }

    public function update(ChangeCompletionRequest $request, Enrollment $enrollment, Lesson $lesson, CompletionActions $actions, ProgressService $progress): ProgressResource
    {
        $actions->setCompleted($request->user(), $enrollment->course, $lesson, $request->isMethod('PUT'));

        return new ProgressResource($progress->forEnrollment($enrollment));
    }
}
