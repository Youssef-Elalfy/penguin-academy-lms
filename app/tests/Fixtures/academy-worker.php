<?php

use App\Actions\Academy\EnrollmentActions;
use App\Actions\Academy\LessonActions;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

foreach (['APP_ENV' => 'testing', 'DB_DATABASE' => 'penguin_academy_test', 'DB_USERNAME' => 'penguin_test', 'DB_URL' => ''] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || config('database.connections.mysql.database') !== 'penguin_academy_test' || config('database.connections.mysql.username') !== 'penguin_test') {
    throw new RuntimeException('Concurrency worker is restricted to the test database.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$actor = User::findOrFail($input['actor']);
$course = Course::findOrFail($input['course']);
echo "READY\n";
flush();
try {
    $data = match ($input['operation']) {
        'enroll' => ['id' => app(EnrollmentActions::class)->enroll($actor, $course)->id],
        'lesson' => ['id' => app(LessonActions::class)->save($actor, $course, null, ['title' => $input['title'], 'content' => 'Concurrent lesson content'])->id],
        'reorder' => (function () use ($actor, $course, $input): array {
            app(LessonActions::class)->reorder($actor, $course, $input['ids'], 0);

            return ['ids' => $input['ids']];
        })(),
    };
    echo json_encode(['status' => 200, ...$data], JSON_THROW_ON_ERROR)."\n";
} catch (HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode(), 'message' => $exception->getMessage()], JSON_THROW_ON_ERROR)."\n";
}
