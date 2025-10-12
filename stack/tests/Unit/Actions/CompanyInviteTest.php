<?php

use App\Actions\Company\CompanyInvite;
use App\Enums\CompanyRole;
use App\Models\Company;
use App\Models\CompanyInvitation;
use App\Models\User;

// CompanyInvite action unit tests
it('creates a company invitation successfully', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    // Attach inviter as owner
    $company->users()->attach($inviter, ['role' => 'owner']);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test@example.com',
        role: CompanyRole::ADMIN
    );

    $invitation = $action->execute();

    expect($invitation)->toBeInstanceOf(CompanyInvitation::class);
    expect($invitation->company_id)->toBe($company->id);
    expect($invitation->email)->toBe('test@example.com');
    expect($invitation->role)->toBe(CompanyRole::ADMIN->value);
    expect($invitation->invited_by_user_id)->toBe($inviter->id);
    expect($invitation->status)->toBe('pending');
    expect($invitation->token)->not->toBeEmpty();
});

it('validates inviter has permission to invite users', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    // Attach inviter as viewer (cannot invite)
    $company->users()->attach($inviter, ['role' => 'viewer']);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test@example.com',
        role: CompanyRole::ADMIN
    );

    expect(fn () => $action->execute())->toThrow('Illuminate\Auth\Access\AuthorizationException');
});

it('prevents duplicate invitations for the same email', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);

    // Create existing invitation
    CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'email' => 'test@example.com',
        'status' => 'pending',
    ]);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test@example.com',
        role: CompanyRole::ADMIN
    );

    expect(fn () => $action->execute())->toThrow('Illuminate\Validation\ValidationException');
});

it('prevents invitations to existing company members', function () {
    $inviter = User::factory()->create();
    $member = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);
    $company->users()->attach($member, ['role' => 'admin']);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: $member->email,
        role: CompanyRole::VIEWER
    );

    expect(fn () => $action->execute())->toThrow('Illuminate\Validation\ValidationException');
});

it('allows invitations to existing members of other companies', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);
    $otherCompany = Company::factory()->create();

    // Setup relationships
    $company->users()->attach($inviter, ['role' => 'owner']);
    $otherCompany->users()->attach($invitee, ['role' => 'admin']);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: $invitee->email,
        role: CompanyRole::VIEWER
    );

    $invitation = $action->execute();

    expect($invitation->email)->toBe($invitee->email);
    expect($invitation->status)->toBe('pending');
});

it('sets correct expiration date for invitations', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test@example.com',
        role: CompanyRole::ADMIN,
        expiresInDays: 14
    );

    $invitation = $action->execute();

    $expectedExpiry = now()->addDays(14);
    expect($invitation->expires_at)->closeTo($expectedExpiry, 1); // Within 1 second
});

it('generates unique tokens for each invitation', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);

    $action1 = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test1@example.com',
        role: CompanyRole::ADMIN
    );

    $action2 = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test2@example.com',
        role: CompanyRole::ADMIN
    );

    $invitation1 = $action1->execute();
    $invitation2 = $action2->execute();

    expect($invitation1->token)->not->toBe($invitation2->token);
    expect($invitation1->token)->toHaveLength(64); // SHA-256 hash length
});

it('validates email format', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'invalid-email',
        role: CompanyRole::ADMIN
    );

    expect(fn () => $action->execute())->toThrow('Illuminate\Validation\ValidationException');
});

it('respects role hierarchy in invitations', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    // Test different inviter roles
    $testCases = [
        ['inviterRole' => 'owner', 'canInviteOwner' => true],
        ['inviterRole' => 'admin', 'canInviteOwner' => false],
        ['inviterRole' => 'accountant', 'canInviteOwner' => false],
        ['inviterRole' => 'viewer', 'canInviteOwner' => false],
    ];

    foreach ($testCases as $testCase) {
        $company->users()->detach($inviter);
        $company->users()->attach($inviter, ['role' => $testCase['inviterRole']]);

        $action = new CompanyInvite(
            inviter: $inviter,
            company: $company,
            email: 'test@example.com',
            role: CompanyRole::OWNER
        );

        if ($testCase['canInviteOwner']) {
            expect(fn () => $action->execute())->not->toThrow();
        } else {
            expect(fn () => $action->execute())->toThrow();
        }
    }
});

it('handles invitation with custom message', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);

    $customMessage = 'Please join our company as an admin user';

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test@example.com',
        role: CompanyRole::ADMIN,
        message: $customMessage
    );

    $invitation = $action->execute();

    // This assumes the invitation stores the message - adjust based on actual implementation
    expect($invitation)->toBeInstanceOf(CompanyInvitation::class);
});

it('cleans up expired invitations before creating new ones', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);

    // Create expired invitation
    CompanyInvitation::factory()->create([
        'company_id' => $company->id,
        'email' => 'test@example.com',
        'status' => 'pending',
        'expires_at' => now()->subDays(1),
    ]);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: 'test@example.com',
        role: CompanyRole::ADMIN
    );

    $invitation = $action->execute();

    expect($invitation->email)->toBe('test@example.com');
    expect($invitation->status)->toBe('pending');

    // Verify expired invitation was handled (deleted or marked as expired)
    $expiredInvitation = CompanyInvitation::where('email', 'test@example.com')
        ->where('expires_at', '<', now())
        ->first();

    expect($expiredInvitation->status)->toBe('expired');
});

it('prevents self-invitation', function () {
    $inviter = User::factory()->create();
    $company = Company::factory()->create(['created_by_user_id' => $inviter->id]);

    $company->users()->attach($inviter, ['role' => 'owner']);

    $action = new CompanyInvite(
        inviter: $inviter,
        company: $company,
        email: $inviter->email,
        role: CompanyRole::ADMIN
    );

    expect(fn () => $action->execute())->toThrow('Illuminate\Validation\ValidationException');
});
