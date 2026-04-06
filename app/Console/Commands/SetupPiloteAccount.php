<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class SetupPiloteAccount extends Command
{
    protected $signature = 'app:setup-pilote
                            {--password= : Plain-text password (omit to use PILOTE_PASSWORD env or default pilote)}';

    protected $description = 'Create or update the pilote admin user (legacy role admin + Spatie super_admin)';

    public function handle(): int
    {
        $password = $this->option('password');
        if ($password === null || $password === '') {
            $password = env('PILOTE_PASSWORD', 'pilote');
            $this->warn('No --password given; using PILOTE_PASSWORD env or default "pilote".');
        }

        $user = User::query()->updateOrCreate(
            ['name' => 'pilote'],
            [
                'email' => 'pilote@example.com',
                'password' => $password,
                'role' => 'admin',
            ],
        );

        $guard = config('auth.defaults.guard', 'web');
        $role = Role::findByName('super_admin', $guard);
        $user->syncRoles([$role]);

        $this->components->info("Pilote user ready: {$user->email} (Spatie: super_admin).");

        return self::SUCCESS;
    }
}
