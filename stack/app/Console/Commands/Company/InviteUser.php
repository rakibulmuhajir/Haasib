<?php

namespace App\Console\Commands\Company;

use App\Actions\Company\CompanyInvite;
use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class InviteUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'company:invite {company} --email= --role= --expires-in-days=7 --message= --invited-by=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Invite a user to join a company';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $arguments = $this->validateArguments();
            
            $company = $this->resolveCompany($arguments['company']);
            $inviter = $this->resolveInviter($arguments['invited_by']);
            
            $this->info("Inviting user to company: {$company->name}");
            
            $invite = new CompanyInvite(
                inviter: $inviter,
                company: $company,
                email: $arguments['email'],
                role: CompanyRole::from($arguments['role']),
                message: $arguments['message'],
                expiresInDays: (int) $arguments['expires_in_days']
            );
            
            $invitation = $invite->execute();
            
            $this->info('✓ Invitation sent successfully');
            $this->info("Email: {$invitation->email}");
            $this->info("Company: {$company->name}");
            $this->info("Role: {$invitation->role}");
            $this->info("Expires: {$invitation->expires_at->diffForHumans()}");
            $this->info("Token: {$invitation->token}");
            
            return 0;
            
        } catch (ValidationException $e) {
            $this->error('Validation failed:');
            foreach ($e->errors() as $field => $errors) {
                foreach ($errors as $error) {
                    $this->error("  - {$error}");
                }
            }
            return 1;
            
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
    
    private function validateArguments(): array
    {
        $validator = Validator::make([
            'email' => $this->option('email'),
            'role' => $this->option('role'),
            'expires_in_days' => $this->option('expires-in-days'),
            'message' => $this->option('message'),
            'invited_by' => $this->option('invited-by'),
        ], [
            'email' => 'required|email',
            'role' => 'required|in:owner,admin,accountant,viewer',
            'expires_in_days' => 'required|integer|min:1|max:30',
            'message' => 'nullable|string|max:500',
            'invited_by' => 'nullable|uuid',
        ]);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        return $validator->validated();
    }
    
    private function resolveCompany(string $identifier): Company
    {
        // Try to find by UUID first
        $company = Company::find($identifier);
        
        // If not found, try by slug
        if (!$company) {
            $company = Company::where('slug', $identifier)->first();
        }
        
        if (!$company) {
            throw new \InvalidArgumentException('Company not found.');
        }
        
        return $company;
    }
    
    private function resolveInviter(?string $inviterId): User
    {
        if ($inviterId) {
            $inviter = User::find($inviterId);
            if (!$inviter) {
                throw new \InvalidArgumentException('Inviter user not found.');
            }
            return $inviter;
        }
        
        // For CLI usage, we might need to prompt for user selection
        // For now, we'll use the first user (this should be improved)
        $user = User::first();
        if (!$user) {
            throw new \InvalidArgumentException('No users found in the system.');
        }
        
        return $user;
    }
}
