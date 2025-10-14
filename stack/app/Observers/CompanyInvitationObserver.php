<?php

namespace App\Observers;

use App\Models\AuditEntry;
use App\Models\CompanyInvitation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyInvitationObserver
{
    public function created(CompanyInvitation $invitation): void
    {
        $this->createAuditEntry($invitation, 'create', [
            'old_values' => null,
            'new_values' => $this->getInvitationValues($invitation),
        ]);
    }

    public function updated(CompanyInvitation $invitation): void
    {
        $changes = $invitation->getChanges();
        $original = $invitation->getOriginal();
        
        // Special handling for status changes (accept/reject)
        if (isset($changes['status'])) {
            $action = match ($changes['status']) {
                'accepted' => 'accept',
                'rejected' => 'reject',
                'expired' => 'expire',
                default => 'update',
            };
        } else {
            $action = 'update';
        }
        
        $this->createAuditEntry($invitation, $action, [
            'old_values' => $this->filterChangedValues($original, $changes),
            'new_values' => $this->filterChangedValues($changes, $changes),
        ]);
    }

    public function deleted(CompanyInvitation $invitation): void
    {
        $this->createAuditEntry($invitation, 'delete', [
            'old_values' => $this->getInvitationValues($invitation),
            'new_values' => null,
        ]);
    }

    private function createAuditEntry(CompanyInvitation $invitation, string $action, array $data): void
    {
        try {
            $user = Auth::user();
            
            // Generate idempotency key for this audit entry
            $idempotencyKey = DB::selectOne("SELECT gen_random_uuid() as uuid")->uuid;
            
            AuditEntry::create([
                'company_id' => $invitation->company_id,
                'entity_type' => 'CompanyInvitation',
                'entity_id' => $invitation->id,
                'action' => $action,
                'old_values' => json_encode($data['old_values']),
                'new_values' => json_encode($data['new_values']),
                'idempotency_key' => $idempotencyKey,
                'user_id' => $user?->id,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the application
            logger()->error('Company invitation audit entry creation failed', [
                'error' => $e->getMessage(),
                'invitation_id' => $invitation->id,
                'action' => $action,
            ]);
        }
    }

    private function getInvitationValues(CompanyInvitation $invitation): array
    {
        return [
            'company_id' => $invitation->company_id,
            'email' => $invitation->email,
            'role' => $invitation->role,
            'status' => $invitation->status,
            'invited_by_user_id' => $invitation->invited_by_user_id,
            'accepted_by_user_id' => $invitation->accepted_by_user_id,
            'expires_at' => $invitation->expires_at?->toISOString(),
            'accepted_at' => $invitation->accepted_at?->toISOString(),
        ];
    }

    private function filterChangedValues(array $values, array $changes): array
    {
        return array_intersect_key($values, $changes);
    }
}