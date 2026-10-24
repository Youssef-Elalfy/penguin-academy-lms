<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../app/vendor/autoload.php';
$app = require __DIR__.'/../../app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection()->getDatabaseName();
if (! in_array($database, ['penguin_academy', 'penguin_academy_test'], true)) {
    throw new RuntimeException('QA is restricted to the existing local academy databases.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$operation = $input['operation'];
$tables = ['users', 'courses', 'lessons', 'enrollments', 'lesson_completions'];
if ($operation === 'snapshot') {
    $private = __DIR__.'/../../.runtime/m6-preservation';
    if (! is_dir($private)) {
        mkdir($private, 0700, true);
    }
    $result = ['database' => $database, 'username' => config('database.connections.mysql.username'),
        'authenticated_as' => DB::selectOne('SELECT CURRENT_USER() AS identity')->identity, 'tables' => []];
    $otherDatabase = $database === 'penguin_academy' ? 'penguin_academy_test' : 'penguin_academy';
    try {
        DB::select('SELECT COUNT(*) FROM '.$otherDatabase.'.users');
        $result['cross_database_read_denied'] = false;
        throw new RuntimeException('Application database accounts must not access the other environment.');
    } catch (QueryException $exception) {
        $result['cross_database_read_denied'] = in_array($exception->errorInfo[1] ?? null, [1044, 1142], true);
        if (! $result['cross_database_read_denied']) {
            throw new RuntimeException('Cross-database permission check did not produce the expected access denial.');
        }
    }
    foreach ($tables as $table) {
        $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $path = $private.'/'.$database.'-'.$table.'.json';
        if ($input['phase'] === 'before' || ($input['phase'] === 'ensure' && ! file_exists($path))) {
            if (file_exists($path)) {
                throw new RuntimeException('Refusing to replace an existing preservation baseline.');
            }
            file_put_contents($path, json_encode($rows, JSON_THROW_ON_ERROR));
            chmod($path, 0600);
        }
        $original = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $map = array_column($rows, null, 'id');
        $unchanged = true;
        foreach ($original as $row) {
            if (($map[$row['id']] ?? null) !== $row) {
                $unchanged = false;
            }
        }
        $result['tables'][$table] = ['rows' => count($rows), 'original_rows' => count($original), 'original_rows_unchanged' => $unchanged];
    }
} else {
    if ($database !== 'penguin_academy_test' || config('database.connections.mysql.username') !== 'penguin_test') {
        throw new RuntimeException('Synthetic fixture inspection requires the dedicated test database.');
    }
    $prefix = $input['prefix'];
    if (! preg_match('/^m6-[a-f0-9]{12}$/', $prefix)) {
        throw new RuntimeException('Invalid unique QA fixture namespace.');
    }
    $users = DB::table('users')->where('email', 'like', $prefix.'-%@example.test')->get(['id', 'name', 'email', 'role', 'is_active', 'must_change_password']);
    $courses = DB::table('courses')->where('slug', 'like', $prefix.'-%')->get();
    $courseIds = $courses->pluck('id');
    $enrollments = DB::table('enrollments')->whereIn('course_id', $courseIds)->get();
    $result = [
        'database' => $database,
        'users' => $users,
        'courses' => $courses,
        'lessons' => DB::table('lessons')->whereIn('course_id', $courseIds)->orderBy('position')->get(),
        'enrollments' => $enrollments,
        'completions' => DB::table('lesson_completions')->whereIn('course_id', $courseIds)->get(),
    ];
    if ($operation === 'cleanup') {
        DB::transaction(function () use ($courseIds, $users): void {
            DB::table('lesson_completions')->whereIn('course_id', $courseIds)->delete();
            DB::table('enrollments')->whereIn('course_id', $courseIds)->delete();
            DB::table('lessons')->whereIn('course_id', $courseIds)->delete();
            DB::table('courses')->whereIn('id', $courseIds)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $users->pluck('id'))->delete();
            DB::table('sessions')->whereIn('user_id', $users->pluck('id'))->delete();
            DB::table('users')->whereIn('id', $users->pluck('id'))->delete();
        });
        $result = ['database' => $database, 'cleanup' => 'Only this run’s synthetic users and course records removed.'];
    } elseif ($operation !== 'inspect') {
        throw new RuntimeException('Unsupported QA operation.');
    }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
