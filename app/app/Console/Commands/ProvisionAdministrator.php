<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ProvisionAdministrator extends Command
{
    protected $signature = 'academy:admin {--name= : Administrator display name} {--email= : Administrator email} {--password-stdin : Read one password line from standard input}';

    protected $description = 'Create an administrator securely; never promotes or overwrites an existing account';

    public function handle(): int
    {
        if (! $this->input->isInteractive() && (! $this->option('name') || ! $this->option('email') || ! $this->option('password-stdin'))) {
            $this->error('Non-interactive provisioning requires --name, --email and --password-stdin. Passwords are never command arguments.');

            return self::FAILURE;
        }
        $name = $this->option('name') ?: $this->ask('Administrator name');
        $email = mb_strtolower(trim((string) ($this->option('email') ?: $this->ask('Administrator email'))));
        if ($this->option('password-stdin')) {
            $password = rtrim((string) fgets(STDIN, 1024), "\r\n");
            $confirmation = $password;
        } else {
            $password = $this->secret('Password (12+ characters, mixed case and numbers)', false);
            $confirmation = $this->secret('Confirm password', false);
        }
        $validator = Validator::make([
            'name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation,
        ], [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', 'password_bytes', 'confirmed', Password::defaults()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        try {
            DB::transaction(function () use ($name, $email, $password): void {
                $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
                $user->role = UserRole::Admin;
                $user->is_active = true;
                $user->must_change_password = false;
                $user->save();
            });
        } catch (UniqueConstraintViolationException) {
            $this->error('That email already exists. No account was modified.');

            return self::FAILURE;
        }
        $this->info('Administrator created. No credentials were printed.');

        return self::SUCCESS;
    }
}
