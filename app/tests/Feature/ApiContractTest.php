<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\ApiTestCase;

class ApiContractTest extends ApiTestCase
{
    public static function protectedEndpoints(): array
    {
        return [
            ['GET', '/tokens'], ['DELETE', '/tokens/current'], ['DELETE', '/tokens/99999999'],
            ['GET', '/courses'], ['POST', '/courses'], ['GET', '/courses/99999999'],
            ['PATCH', '/courses/99999999'], ['DELETE', '/courses/99999999'], ['PUT', '/courses/99999999/publication'],
            ['GET', '/courses/99999999/lessons'], ['POST', '/courses/99999999/lessons'],
            ['GET', '/courses/99999999/lessons/99999999'], ['PATCH', '/courses/99999999/lessons/99999999'],
            ['DELETE', '/courses/99999999/lessons/99999999'], ['PUT', '/courses/99999999/lessons/99999999/publication'],
            ['PUT', '/courses/99999999/lessons/order'], ['GET', '/enrollments'], ['POST', '/enrollments'],
            ['DELETE', '/enrollments/99999999'], ['GET', '/enrollments/99999999/progress'],
            ['PUT', '/enrollments/99999999/lessons/99999999/completion'],
            ['DELETE', '/enrollments/99999999/lessons/99999999/completion'], ['GET', '/reports/overview'],
        ];
    }

    #[DataProvider('protectedEndpoints')]
    public function test_every_protected_endpoint_requires_a_bearer_token(string $method, string $uri): void
    {
        $this->api($method, $uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated')
            ->assertJsonMissingPath('exception')->assertJsonMissingPath('trace');
    }

    #[DataProvider('protectedEndpoints')]
    public function test_every_protected_endpoint_requires_its_explicit_ability(string $method, string $uri): void
    {
        $user = User::factory()->admin()->create();

        $this->api($method, $uri, [], $this->token($user, []))->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    #[TestWith(['per_page=101', 'per_page'])]
    #[TestWith(['page=0', 'page'])]
    #[TestWith(['sort=title%20desc', 'sort'])]
    #[TestWith(['q[]=array', 'q'])]
    #[TestWith(['token=DO_NOT_USE_QUERY_TOKENS', 'token'])]
    public function test_invalid_filters_return_safe_consistent_validation_errors(string $query, string $field): void
    {
        $user = User::factory()->create();

        $this->api('GET', '/courses?'.$query, [], $this->token($user, ['courses:read']))->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors($field)->assertJsonMissingPath('trace');
    }

    public function test_unknown_routes_methods_and_server_errors_are_safe_json_even_without_accept_header(): void
    {
        $this->get('/api/v1/does-not-exist')->assertNotFound()->assertJsonPath('code', 'not_found')->assertHeader('Cache-Control', 'no-store, private');
        $this->json('PUT', '/api/v1/tokens')->assertMethodNotAllowed()->assertJsonPath('code', 'method_not_allowed')->assertHeader('Allow');
        Route::get('/api/v1/test-error', fn () => throw new \RuntimeException('Synthetic private exception details'));
        config(['app.debug' => true]);

        $this->get('/api/v1/test-error')->assertInternalServerError()->assertExactJson(['message' => 'An unexpected error occurred.', 'code' => 'server_error']);
    }

    public function test_read_only_ability_cannot_write_and_write_throttle_is_enforced(): void
    {
        $teacher = User::factory()->teacher()->create();
        $read = $this->token($teacher, ['courses:read']);
        $this->api('POST', '/courses', ['title' => 'Blocked', 'slug' => 'blocked'], $read)->assertForbidden();
        $write = $this->token($teacher, ['courses:write']);
        for ($i = 0; $i < 59; $i++) {
            $this->api('POST', '/courses', [], $write)->assertUnprocessable();
        }

        $this->api('POST', '/courses', [], $write)->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_openapi_operation_inventory_matches_implemented_routes(): void
    {
        $spec = json_decode(file_get_contents(base_path('docs/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/v1/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $actual[] = $method.' /'.$route->uri();
                $operation = $spec['paths']['/'.$route->uri()][strtolower($method)] ?? null;
                $this->assertNotNull($operation, $method.' '.$route->uri().' must be documented.');
                $this->assertNotEmpty($operation['responses']);
                $middleware = $route->gatherMiddleware();
                $abilities = array_values(array_filter($middleware, fn (string $m): bool => str_starts_with($m, 'abilities:')));
                $this->assertSame($abilities ? substr($abilities[0], 10) : null, $operation['x-token-ability'] ?? null);
            }
        }
        $documented = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true)) {
                    $documented[] = strtoupper($method).' '.$path;
                }
            }
        }
        sort($actual);
        sort($documented);
        $this->assertSame($actual, $documented);
    }
}
