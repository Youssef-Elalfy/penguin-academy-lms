<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../app/vendor/autoload.php';
$app = require __DIR__.'/../app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'penguin_academy') {
    throw new RuntimeException('Development database inspection only.');
}
$tables = ['users', 'courses', 'lessons', 'enrollments', 'lesson_completions'];
$private = __DIR__.'/../.runtime/m3';
if (! is_dir($private)) {
    mkdir($private, 0700, true);
}
$before = $argv[1] === 'before';
$summary = ['phase' => $argv[1], 'database' => DB::connection()->getDatabaseName(), 'tables' => []];
foreach ($tables as $table) {
    $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    $path = "$private/$table-before.json";
    if ($before) {
        if (file_exists($path)) {
            throw new RuntimeException('Baseline already exists; refusing to overwrite.');
        }
        file_put_contents($path, json_encode($rows, JSON_THROW_ON_ERROR));
        chmod($path, 0600);
    }
    $original = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    $map = array_column($rows, null, 'id');
    $unchanged = true;
    foreach ($original as $row) {
        $current = $map[$row['id']] ?? [];
        if (array_intersect_key($current, $row) !== $row) {
            $unchanged = false;
        }
    }
    $summary['tables'][$table] = ['rows' => count($rows), 'original_rows' => count($original), 'original_columns_and_rows_unchanged' => $unchanged];
}
$summary['mysql'] = DB::selectOne('SELECT VERSION() AS version, @@session.time_zone AS time_zone');
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
