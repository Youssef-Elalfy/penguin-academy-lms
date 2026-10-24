<?php

use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../../app/vendor/autoload.php';
$app = require __DIR__.'/../../app/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (DB::connection()->getDatabaseName() !== 'penguin_academy_test' || config('database.connections.mysql.username') !== 'penguin_test') {
    throw new RuntimeException('Performance fixtures require the dedicated test database and user.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$prefix = $input['prefix'];
if (! preg_match('/^m6-[a-f0-9]{12}$/', $prefix)) {
    throw new RuntimeException('Invalid QA fixture namespace.');
}
if ($input['operation'] === 'seed') {
    if (User::where('email', 'like', $prefix.'-%')->exists()) {
        throw new RuntimeException('Refusing to overwrite existing fixtures.');
    }
    $result = DB::transaction(function () use ($input, $prefix): array {
        $password = Hash::make($input['password']);
        $now = now()->format('Y-m-d H:i:s');
        $admin = User::factory()->admin()->create(['name' => 'Performance Admin', 'email' => $prefix.'-admin@example.test', 'password' => $password]);
        $teachers = [];
        for ($i = 0; $i < 5; $i++) {
            $teachers[] = User::factory()->teacher()->create(['name' => 'Performance Teacher '.$i, 'email' => $prefix.'-teacher'.$i.'@example.test', 'password' => $password]);
        }
        $students = [];
        for ($i = 0; $i < 300; $i++) {
            $students[] = ['name' => 'Performance Student '.$i, 'email' => $prefix.'-student'.$i.'@example.test', 'password' => $password, 'role' => 'student', 'is_active' => true, 'must_change_password' => false, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('users')->insert($students);
        $studentIds = User::where('email', 'like', $prefix.'-student%@example.test')->orderBy('id')->pluck('id')->all();
        $completionCount = 0;
        $enrollmentCount = 0;
        $courseIds = [];
        for ($c = 0; $c < 30; $c++) {
            $course = Course::factory()->published()->create(['title' => 'Performance Course '.$c, 'slug' => $prefix.'-course'.$c, 'teacher_id' => $teachers[$c % 5]->id]);
            $courseIds[] = $course->id;
            $lessons = [];
            for ($l = 1; $l <= 20; $l++) {
                $lessons[] = ['course_id' => $course->id, 'title' => 'Performance Lesson '.$l, 'content' => str_repeat('Synthetic performance fixture. ', 30), 'position' => $l, 'is_published' => true, 'created_at' => $now, 'updated_at' => $now];
            }
            DB::table('lessons')->insert($lessons);
            $lessonIds = DB::table('lessons')->where('course_id', $course->id)->orderBy('position')->pluck('id')->all();
            $enrollments = [];
            foreach ($studentIds as $i => $student) {
                if ($i % 3 === $c % 3) {
                    $enrollments[] = ['course_id' => $course->id, 'student_id' => $student, 'enrolled_at' => $now, 'withdrawn_at' => null, 'created_at' => $now, 'updated_at' => $now];
                }
            }
            DB::table('enrollments')->insert($enrollments);
            $enrollmentCount += count($enrollments);
            $completions = [];
            foreach (DB::table('enrollments')->where('course_id', $course->id)->orderBy('student_id')->get() as $i => $enrollment) {
                for ($l = 0; $l < $i % 21; $l++) {
                    $completions[] = ['enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'lesson_id' => $lessonIds[$l], 'completed_at' => $now];
                }
            }
            foreach (array_chunk($completions, 500) as $chunk) {
                DB::table('lesson_completions')->insert($chunk);
            }
            $completionCount += count($completions);
        }

        return ['counts' => ['admins' => 1, 'teachers' => 5, 'students' => 300, 'courses' => 30, 'lessons' => 600, 'enrollments' => $enrollmentCount, 'completions' => $completionCount],
            'admin_id' => $admin->id, 'course_id' => $courseIds[0], 'teacher_id' => $teachers[0]->id,
            'student_id' => $studentIds[0], 'enrollment_id' => DB::table('enrollments')->where('student_id', $studentIds[0])->orderBy('id')->value('id')];
    });
} elseif ($input['operation'] === 'measure') {
    $queries = [];
    DB::listen(function ($query) use (&$queries): void {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings, 'ms' => $query->time];
    });
    $request = Request::create($input['uri'], 'GET', server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$input['token']]);
    $kernel = $app->make(HttpKernel::class);
    $started = hrtime(true);
    $response = $kernel->handle($request);
    $elapsed = (hrtime(true) - $started) / 1e6;
    $kernel->terminate($request, $response);
    $captured = $queries;
    $plans = [];
    foreach ($captured as $query) {
        if (str_starts_with(strtolower($query['sql']), 'select') && str_contains($query['sql'], 'eligible_lessons_count')) {
            $plans[] = ['sql_template' => $query['sql'], 'plan' => DB::select('EXPLAIN '.$query['sql'], $query['bindings'])];
        }
    }
    $json = json_decode($response->getContent(), true);
    $result = ['uri' => $input['uri'], 'status' => $response->getStatusCode(), 'duration_ms' => round($elapsed, 3),
        'query_count' => count($captured),
        'business_read_query_count' => count(array_filter($captured, fn (array $q): bool => str_starts_with($q['sql'], 'select')
            && ! str_contains($q['sql'], '`cache`') && ! str_contains($q['sql'], '`personal_access_tokens`')
            && $q['sql'] !== 'select * from `users` where `users`.`id` = ? limit 1')), 'query_ms' => round(array_sum(array_column($captured, 'ms')), 3),
        'peak_php_memory_bytes' => memory_get_peak_usage(true), 'response_bytes' => strlen($response->getContent()),
        'returned_rows' => isset($json['data']) && array_is_list($json['data']) ? count($json['data']) : null,
        'query_templates' => array_map(fn (array $q): array => ['sql' => $q['sql'], 'ms' => $q['ms']], $captured), 'explain' => $plans];
} else {
    throw new RuntimeException('Unsupported performance operation.');
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
