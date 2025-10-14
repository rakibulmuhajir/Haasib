<?php

namespace App\Console\Concerns;

use App\Models\Company;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ContextService;
use App\Support\CliContext;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

trait InteractsWithCliContext
{
    protected ?CliContext $cliContextInstance = null;

    protected function cliContext(): CliContext
    {
        return $this->cliContextInstance ??= new CliContext;
    }

    protected function resolveActingUser(Command $command, AuthService $authService, ?string $identifier = null): User
    {
        if ($identifier) {
            $user = $this->findUser($identifier);
            if (! $user) {
                throw new \RuntimeException("User '{$identifier}' not found.");
            }

            if (! $user->is_active) {
                throw new \RuntimeException("User '{$user->email}' is inactive.");
            }

            $this->cliContext()->rememberUser($user);

            return $user;
        }

        $stored = $this->cliContext()->getUser();

        if (! $stored) {
            throw new \RuntimeException('No acting user in context. Provide --user or run user:switch.');
        }

        if (! $stored->is_active) {
            throw new \RuntimeException("Stored acting user '{$stored->email}' is inactive.");
        }

        return $stored;
    }

    protected function resolveCompany(Command $command, AuthService $authService, ContextService $contextService, User $user, ?string $identifier = null, bool $required = false): ?Company
    {
        $company = null;

        if ($identifier) {
            $company = $this->findCompany($identifier);
            if (! $company) {
                throw new \RuntimeException("Company '{$identifier}' not found.");
            }

            if (! $authService->canAccessCompany($user, $company)) {
                throw new \RuntimeException("User '{$user->email}' does not have access to company '{$company->name}'.");
            }

            $this->cliContext()->rememberCompany($company);
        } else {
            $storedCompany = $this->cliContext()->getCompany();
            if ($storedCompany && $authService->canAccessCompany($user, $storedCompany)) {
                $company = $storedCompany;
            } elseif ($required) {
                throw new \RuntimeException('No company context available. Provide --company or run company:switch.');
            }
        }

        if ($company) {
            $contextService->setCurrentCompany($user, $company);
        }

        return $company;
    }

    protected function findUser(string $identifier): ?User
    {
        return Str::isUuid($identifier)
            ? User::find($identifier)
            : User::where('email', $identifier)->first();
    }

    protected function findCompany(string $identifier): ?Company
    {
        return Str::isUuid($identifier)
            ? Company::find($identifier)
            : Company::where('slug', $identifier)->orWhere('name', $identifier)->first();
    }
}
