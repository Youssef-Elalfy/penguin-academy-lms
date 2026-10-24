<section class="surface mb-4">
    <div class="section-heading"><div><h2>Course learning activity</h2><p>Current enrollments; only published, non-archived lessons count toward progress.</p></div><a class="text-link" href="{{ route('courses.index') }}">Manage courses ↗</a></div>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Course enrollment and learning summaries — scroll to view all columns"><table class="table academy-table mb-0">
        <caption class="visually-hidden">Course enrollment and learning summaries</caption>
        <thead><tr><th scope="col">Course</th><th scope="col">Lessons</th><th scope="col">Enrollments</th><th scope="col">Average progress</th><th scope="col">Finished</th><th scope="col">Latest completion (UTC)</th></tr></thead>
        <tbody>@forelse($courses as $course)<tr>
            <td><a class="table-name" href="{{ route('courses.show', $course) }}">{{ $course->title }}</a><span class="table-secondary">{{ $course->isPublished() ? 'Published' : 'Draft / scheduled' }}</span><a class="text-link small" href="{{ route('courses.progress.index', $course) }}">Progress report</a></td>
            <td>{{ $course->eligible_lessons_count }} published<span class="table-secondary">{{ $course->lessons_count }} total</span></td>
            <td>{{ $course->enrollments_count }}</td>
            <td><strong>{{ $course->average_progress }}%</strong></td>
            <td>{{ $course->completed_enrollments_count }}<span class="table-secondary">{{ $course->completion_rate }}% of enrollments</span></td>
            <td><span class="small">{{ $course->last_completed_at ?? 'No eligible completions yet' }}</span></td>
        </tr>@empty<tr><td colspan="6"><div class="empty-state"><h3>No course activity yet.</h3><p>Course summaries will appear when a course is created.</p><a class="btn btn-outline-primary btn-sm" href="{{ route('courses.create') }}">Create a course</a></div></td></tr>@endforelse</tbody>
    </table></div><div class="pagination-wrap">{{ $courses->links() }}</div>
</section>
