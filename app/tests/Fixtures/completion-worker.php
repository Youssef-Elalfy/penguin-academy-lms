<?php

use App\Actions\Academy\CompletionActions;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

foreach (['APP_ENV' => 'testing', 'DB_DATABASE' => 'penguin_academy_test', 'DB_USERNAME' => 'penguin_test', 'DB_URL' => ''] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if ($app->configurationIsCached() || ! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'penguin_academy_test' || config('database.connections.mysql.username') !== 'penguin_test') {
    throw new RuntimeException('Completion workers require the isolated MySQL test database.');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$actor = User::findOrFail($input['actor']);
$course = Course::findOrFail($input['course']);
$lesson = Lesson::findOrFail($input['lesson']);
echo "READY\n";
flush();
try {
    $completion = app(CompletionActions::class)->setCompleted($actor, $course, $lesson, $input['completed']);
    $result = ['status' => 200, 'id' => $completion?->id];
} catch (AuthorizationException $exception) {
    $result = ['status' => 403];
} catch (ModelNotFoundException $exception) {
    $result = ['status' => 404];
} catch (ValidationException $exception) {
    $result = ['status' => 422];
} catch (HttpExceptionInterface $exception) {
    $result = ['status' => $exception->getStatusCode()];
}
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
