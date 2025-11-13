<?php

namespace App\Commands\Users;

use App\Commands\BaseCommand;
use App\Services\ServiceContext;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Exception;

class ResetPasswordAction extends BaseCommand
{
    public function handle(): array
    {
        return $this->executeInTransaction(function () {
            $adminId = $this->context->getUserId();
            $userId = $this->getValue('id');
            
            if (!$adminId || !$userId) {
                throw new Exception('Invalid service context: missing admin or user ID');
            }

            $admin = User::findOrFail($adminId);
            $user = User::findOrFail($userId);

            // Validate permissions
            $this->validatePermissions($admin, $user);

            // Store old password hash for audit
            $oldPasswordHash = $user->password;
            $newPassword = $this->getValue('password');

            // Update password
            $user->update([
                'password' => Hash::make($newPassword),
                'password_changed_at' => now(),
                'changed_password_by' => $adminId,
                'password_changed_reason' => $this->getValue('reason', 'Password reset by administrator'),
                'force_password_change' => $this->boolean('force_change_on_login', false),
            ]);

            // Log out all active sessions for this user (except admin's current session)
            $this->revokeUserSessions($user);

            // Send email notification if requested
            if ($this->boolean('send_email', true)) {
                $this->sendPasswordResetNotification($user, $newPassword);
            }

            $this->audit('user.password_reset', [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'reset_by_admin_id' => $adminId,
                'reset_reason' => $this->getValue('reason', 'Password reset by administrator'),
                'force_password_change' => $this->boolean('force_change_on_login', false),
                'email_sent' => $this->boolean('send_email', true),
            ]);

            // Log to security log separately
            Log::security('ADMIN_PASSWORD_RESET', [
                'admin_id' => $adminId,
                'admin_name' => $admin->name,
                'target_user_id' => $user->id,
                'target_user_name' => $user->name,
                'target_user_email' => $user->email,
                'ip' => $this->context->getIpAddress(),
                'user_agent' => $this->context->getUserAgent(),
                'force_change' => $this->boolean('force_change_on_login', false),
            ]);

            return [
                'success' => true,
                'message' => 'Password reset successfully',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'force_password_change' => $this->boolean('force_change_on_login', false),
                ],
            ];
        });
    }

    private function validatePermissions(User $admin, User $user): void
    {
        // Cannot reset your own password through admin interface
        if ($user->id === $admin->id) {
            throw new Exception('You cannot reset your own password through the admin interface');
        }

        // Super admin can reset anyone's password
        if ($admin->hasRole('super_admin')) {
            return;
        }

        // Admin can reset passwords for users and guests
        if ($admin->hasRole('admin')) {
            if (!in_array($user->system_role, ['user', 'guest'])) {
                throw new Exception('Administrators can only reset passwords for users and guests');
            }
            return;
        }

        throw new Exception('You do not have permission to reset this user\'s password');
    }

    private function revokeUserSessions(User $user): void
    {
        try {
            // Get all active sessions for this user
            $sessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->get();

            // Delete all sessions except possibly the current admin's session
            foreach ($sessions as $session) {
                // We could add logic here to preserve the admin's session if needed
                DB::table('sessions')
                    ->where('id', $session->id)
                    ->delete();
            }

            Log::info('User sessions revoked', [
                'user_id' => $user->id,
                'sessions_revoked' => $sessions->count(),
                'reason' => 'Password reset by administrator',
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to revoke user sessions', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendPasswordResetNotification(User $user, string $newPassword): void
    {
        try {
            // This would typically send an email notification
            // Implementation depends on your notification system
            // For now, just log the attempt
            
            Log::info('Password reset notification prepared', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'notification_type' => 'password_reset',
                'temporary_password_provided' => true,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to send password reset notification', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}