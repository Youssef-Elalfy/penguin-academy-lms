<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../app/vendor/autoload.php';
$app = require __DIR__.'/../app/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection()->getDatabaseName();
if (! in_array($database, ['penguin_academy', 'penguin_academy_test'], true)) {
    throw new RuntimeException('Inspection is limited to the two local academy databases.');
}
$tables = ['users', 'courses', 'lessons', 'enrollments', 'lesson_completions'];
$checks = [
    'lessons_without_course' => 'SELECT COUNT(*) n FROM lessons l LEFT JOIN courses c ON c.id=l.course_id WHERE c.id IS NULL',
    'courses_with_wrong_teacher_role' => "SELECT COUNT(*) n FROM courses c LEFT JOIN users u ON u.id=c.teacher_id WHERE c.teacher_id IS NOT NULL AND (u.id IS NULL OR u.role <> 'teacher')",
    'enrollments_without_course_or_student_role' => "SELECT COUNT(*) n FROM enrollments e LEFT JOIN courses c ON c.id=e.course_id LEFT JOIN users u ON u.id=e.student_id WHERE c.id IS NULL OR u.id IS NULL OR u.role <> 'student'",
    'completions_with_missing_or_cross_course_parents' => 'SELECT COUNT(*) n FROM lesson_completions lc LEFT JOIN lessons l ON l.id=lc.lesson_id LEFT JOIN enrollments e ON e.id=lc.enrollment_id WHERE l.id IS NULL OR e.id IS NULL OR lc.course_id<>l.course_id OR lc.course_id<>e.course_id',
    'duplicate_enrollment_pairs' => 'SELECT COUNT(*) n FROM (SELECT student_id,course_id FROM enrollments GROUP BY student_id,course_id HAVING COUNT(*)>1) x',
    'duplicate_completion_pairs' => 'SELECT COUNT(*) n FROM (SELECT enrollment_id,lesson_id FROM lesson_completions GROUP BY enrollment_id,lesson_id HAVING COUNT(*)>1) x',
    'duplicate_lesson_positions' => 'SELECT COUNT(*) n FROM (SELECT course_id,position FROM lessons GROUP BY course_id,position HAVING COUNT(*)>1) x',
];
$result = ['database' => $database, 'server' => DB::selectOne('SELECT VERSION() version, @@session.time_zone time_zone'), 'rows' => [], 'violations' => []];
foreach ($tables as $table) {
    $result['rows'][$table] = DB::table($table)->count();
}
foreach ($checks as $label => $sql) {
    $result['violations'][$label] = (int) DB::selectOne($sql)->n;
}
$result['tables'] = DB::select("SELECT TABLE_NAME table_name, ENGINE engine, TABLE_COLLATION collation FROM information_schema.TABLES WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('users','courses','lessons','enrollments','lesson_completions') ORDER BY TABLE_NAME", [$database]);
$result['new_columns'] = DB::select("SELECT TABLE_NAME table_name, COLUMN_NAME column_name, COLUMN_TYPE column_type, IS_NULLABLE nullable, COLUMN_DEFAULT default_value FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND ((TABLE_NAME='lessons' AND COLUMN_NAME='deleted_at') OR (TABLE_NAME='courses' AND COLUMN_NAME='lesson_order_version')) ORDER BY TABLE_NAME", [$database]);
$result['indexes'] = DB::select("SELECT TABLE_NAME table_name, INDEX_NAME index_name, NON_UNIQUE non_unique, SEQ_IN_INDEX sequence, COLUMN_NAME column_name FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=? AND TABLE_NAME IN ('courses','lessons','enrollments','lesson_completions') ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX", [$database]);
$result['foreign_keys'] = DB::select("SELECT TABLE_NAME table_name, CONSTRAINT_NAME constraint_name, COLUMN_NAME column_name, REFERENCED_TABLE_NAME parent_table, REFERENCED_COLUMN_NAME parent_column FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_NAME IN ('courses','lessons','enrollments','lesson_completions') ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION", [$database]);
$result['migrations'] = DB::table('migrations')->orderBy('id')->get(['migration', 'batch']);
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
exit(array_sum($result['violations']) === 0 ? 0 : 1);
