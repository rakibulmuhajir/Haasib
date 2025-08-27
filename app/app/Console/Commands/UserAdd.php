<?php
// app/Console/Commands/UserAdd.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\DevOpsService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class UserAdd extends Command
{
    protected $signature = 'user:add
        {--name= : Full name}
        {--email= : Email address}
        {--password= : Plain password (hashed)}
        {--superadmin : Assign global superadmin role}
        {--system-role= : Explicit system role (e.g. superadmin)}
        {--send-reset : Queue password reset email}';

    protected $description = 'Create or update a user; optionally set password and system role';

    public function handle(DevOpsService $ops): int
    {
        $name  = (string) ($this->option('name')  ?? '');
        $email = (string) ($this->option('email') ?? '');
        if ($email === '') { $this->error('Email is required'); return self::INVALID; }

        // Password strategy
        $password = $this->option('password');
        if ($this->option('send-reset')) {
            $password = null;
        } elseif (! $password) {
            $password = $this->secret('Password (leave blank to autogenerate)');
            if (! $password) {
                $password = Str::password(16);
                $this->warn('No password provided; generated one-time password shown below.');
            }
        }
        if ($password) {
            validator(['password' => $password], ['password' => ['required', Password::min(8)]])->validate();
        }

        // Create/update user via your service to keep logic centralized
        $out = $ops->createUser($name, $email, $password);
        $role = $this->option('system-role') ?: ($this->option('superadmin') ? 'superadmin' : null);
        if ($role) {
            \App\Models\User::where('email', strtolower($email))->update(['system_role' => $role]);
            $out['user']['system_role'] = $role;
        }

        if ($this->option('send-reset')) {
            // Hook mail when you configure it:
            // \Illuminate\Support\Facades\Password::sendResetLink(['email' => $email]);
            $out['reset'] = 'Password reset requested (configure mail to send).';
        }

        if ($password && ! $this->option('send-reset')) {
            $out['one_time_password'] = $password;
        }

        $this->line(json_encode(['ok' => true, 'output' => $out], JSON_PRETTY_PRINT));
        return self::SUCCESS;
    }
}

