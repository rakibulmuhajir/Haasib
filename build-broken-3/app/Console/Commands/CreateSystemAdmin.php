<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateSystemAdmin extends Command
{
    protected $signature = 'admin:create-system {name} {email} {--password=}';

    protected $description = 'Create a new system administrator with god-mode access';

    public function handle(): int
    {
        $authenticatedUser = auth()->user();
        
        if (!$authenticatedUser || !$authenticatedUser->isSuperAdmin()) {
            $this->error('Only the super admin can create system administrators.');
            return self::FAILURE;
        }

        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->option('password') ?: Str::random(16);

        if (User::where('email', $email)->exists()) {
            $this->error("A user with email {$email} already exists.");
            return self::FAILURE;
        }

        try {
            $systemAdmin = User::createSystemAdmin(
                $name,
                $email,
                Hash::make($password)
            );

            $this->info('System Administrator created successfully!');
            $this->info('');
            $this->info("Name:       {$systemAdmin->name}");
            $this->info("Email:      {$systemAdmin->email}");
            $this->info("Username:   {$systemAdmin->username}");
            $this->info("User ID:    {$systemAdmin->id}");
            $this->info("Admin #:    {$systemAdmin->getSystemAdminNumber()}");
            $this->info("Password:   {$password}");
            $this->info('');
            $this->warn('Please save the password securely. It will not be shown again.');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create system administrator: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
