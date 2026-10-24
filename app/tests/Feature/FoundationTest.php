<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_foundation_page_renders_built_assets(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSeeText('Penguin Academy')
            ->assertSee('/build/assets/', false);
    }

    public function test_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_database_uses_mysql_utf8mb4_innodb_and_utc(): void
    {
        $settings = DB::selectOne('SELECT VERSION() AS version, DATABASE() AS database_name, @@session.time_zone AS timezone, @@character_set_connection AS charset');

        $this->assertStringStartsWith('8.4.', $settings->version);
        $this->assertSame('penguin_academy_test', $settings->database_name);
        $this->assertSame('+00:00', $settings->timezone);
        $this->assertSame('utf8mb4', $settings->charset);
        $this->assertSame('UTC', config('app.timezone'));
        $engine = DB::selectOne("SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users'");
        $this->assertSame('InnoDB', $engine->engine);
    }

    public function test_record_survives_a_database_reconnection(): void
    {
        $user = User::factory()->create(['name' => 'Foundation persistence probe']);

        try {
            DB::purge('mysql');
            $reloaded = User::findOrFail($user->id);
            $this->assertSame($user->email, $reloaded->email);
            $this->assertSame('Foundation persistence probe', $reloaded->name);
        } finally {
            User::whereKey($user->id)->delete();
        }
    }

    public function test_failed_transaction_rolls_back_a_write(): void
    {
        $user = User::factory()->make();

        try {
            DB::transaction(function () use ($user): void {
                $user->save();

                throw new RuntimeException('Intentional rollback probe');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Intentional rollback probe', $exception->getMessage());
        }

        $this->assertDatabaseMissing('users', ['email' => $user->email]);
    }

    public function test_test_account_cannot_read_the_development_database(): void
    {
        try {
            DB::select('SELECT id FROM penguin_academy.users LIMIT 1');
            $this->fail('The test account must not be able to access development data.');
        } catch (QueryException $exception) {
            $this->assertContains((int) $exception->errorInfo[1], [1044, 1142]);
        }
    }

    public function test_database_rejects_duplicate_email_addresses(): void
    {
        $user = User::factory()->create();

        try {
            User::factory()->create(['email' => $user->email]);
            $this->fail('The users.email unique constraint must reject duplicates.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, (int) $exception->errorInfo[1]);
        } finally {
            User::where('email', $user->email)->delete();
        }
    }
}
