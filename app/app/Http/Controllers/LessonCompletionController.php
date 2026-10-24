<?php

namespace App\Http\Controllers;

use App\Actions\Academy\CompletionActions;
use App\Http\Requests\ChangeLessonCompletionRequest;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;

class LessonCompletionController extends Controller
{
    public function store(ChangeLessonCompletionRequest $request, Course $course, Lesson $lesson, CompletionActions $actions): RedirectResponse
    {
        $actions->setCompleted($request->user(), $course, $lesson, true);

        return to_route('courses.lessons.show', [$course, $lesson])->with('status', 'Lesson marked complete. Your progress has been saved.');
    }

    public function destroy(ChangeLessonCompletionRequest $request, Course $course, Lesson $lesson, CompletionActions $actions): RedirectResponse
    {
        $actions->setCompleted($request->user(), $course, $lesson, false);

        return to_route('courses.lessons.show', [$course, $lesson])->with('status', 'Completion undone. Your progress has been updated.');
    }
}
