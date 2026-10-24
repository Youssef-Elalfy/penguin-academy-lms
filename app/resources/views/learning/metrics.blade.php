<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3"><x-stat label="Current enrollments" :value="$metrics['enrollments']" hint="Withdrawal excluded" /></div>
    <div class="col-sm-6 col-xl-3"><x-stat label="Completed lessons" :value="$metrics['completed_lessons']" hint="Published, current lessons" /></div>
    <div class="col-sm-6 col-xl-3"><x-stat label="Completed enrollments" :value="$metrics['completed_enrollments']" :hint="$metrics['completion_rate'].'% of current enrollments'" /></div>
    <div class="col-sm-6 col-xl-3"><x-stat label="Average progress" :value="$metrics['average_progress']" suffix="%" :decimals="2" hint="One equal share per enrollment" /></div>
</div>
