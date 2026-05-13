<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends Command
{
    protected $signature = 'users:create
                            {email : Email address}
                            {name : Display name}
                            {--password= : Password (>= 8 chars); prompted if absent}
                            {--timezone=UTC : IANA timezone}
                            {--color= : avatar hex color, e.g. #FF5A1F}
                            {--admin : Grant admin role (AI chat access, can write meals for any user)}';

    protected $description = 'Create a user account. Public registration is disabled; this is the only way to add a user.';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name  = (string) $this->argument('name');

        $password = (string) ($this->option('password') ?? $this->secret('Password (>= 8 chars)'));

        $payload = [
            'email'    => $email,
            'name'     => $name,
            'password' => $password,
            'timezone' => (string) $this->option('timezone'),
            'color'    => $this->option('color'),
        ];

        $validator = Validator::make($payload, [
            'email'    => ['required', 'email', 'max:255', 'unique:users,email'],
            'name'     => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8'],
            'timezone' => ['required', 'string', 'timezone'],
            'color'    => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $msg) {
                $this->error($msg);
            }
            return self::FAILURE;
        }

        $role  = $this->option('admin') ? User::ROLE_ADMIN : User::ROLE_USER;
        $color = $payload['color'] ?: self::pickDefaultColor($email);

        $user = User::create([
            'name'         => $name,
            'email'        => $email,
            'password'     => Hash::make($password),
            'timezone'     => $payload['timezone'],
            'avatar_color' => $color,
            'role'         => $role,
        ]);

        $this->info("Created user {$user->name} <{$user->email}> uuid={$user->uuid} role={$user->role} color={$user->avatar_color}");
        $this->line('They can log in via the regular login screen now.');
        return self::SUCCESS;
    }

    /**
     * Deterministic pick from a curated palette so two users on the same
     * device get visually distinct avatars without having to pass --color.
     */
    private static function pickDefaultColor(string $email): string
    {
        $palette = [
            '#FF5A1F', // brand orange
            '#8B5CF6', // violet
            '#10B981', // emerald
            '#F59E0B', // amber
            '#3B82F6', // blue
            '#EC4899', // pink
        ];
        $idx = abs(crc32($email)) % count($palette);
        return $palette[$idx];
    }
}
