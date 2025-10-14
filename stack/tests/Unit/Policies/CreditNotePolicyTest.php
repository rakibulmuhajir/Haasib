<?php

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Policies\CreditNotePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('CreditNotePolicy Tests', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $this->invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'status' => 'posted',
        ]);

        $this->policy = new CreditNotePolicy;
    });

    describe('viewAny', function () {
        it('allows users with credit_notes.view permission', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewAny($user))->toBeTrue();
        });

        it('denies users without credit_notes.view permission', function () {
            $user = User::factory()->create();

            expect($this->policy->viewAny($user))->toBeFalse();
        });
    });

    describe('view', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->view($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->view($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->view($user, $this->creditNote))->toBeFalse();
        });

        it('allows super admin access', function () {
            $user = User::factory()->create();
            $user->assignRole('super_admin');
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->view($user, $this->creditNote))->toBeTrue();
        });
    });

    describe('create', function () {
        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.create');

            expect($this->policy->create($user, $this->company))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->create($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.create');

            expect($this->policy->create($user, $this->company))->toBeFalse();
        });
    });

    describe('createForInvoice', function () {
        it('allows users with correct permission and invoice company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.create');

            expect($this->policy->createForInvoice($user, $this->invoice))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->createForInvoice($user, $this->invoice))->toBeFalse();
        });

        it('denies users without invoice company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.create');

            expect($this->policy->createForInvoice($user, $this->invoice))->toBeFalse();
        });
    });

    describe('update', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.update');

            expect($this->policy->update($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->update($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.update');

            expect($this->policy->update($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('delete', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.delete');

            expect($this->policy->delete($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->delete($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.delete');

            expect($this->policy->delete($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('post', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.post');

            expect($this->policy->post($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->post($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.post');

            expect($this->policy->post($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('cancel', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.cancel');

            expect($this->policy->cancel($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->cancel($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.cancel');

            expect($this->policy->cancel($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('apply', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.apply');

            expect($this->policy->apply($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->apply($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.apply');

            expect($this->policy->apply($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('generatePdf', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with view permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->generatePdf($user, $this->creditNote))->toBeTrue();
        });

        it('allows users with pdf permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.pdf');

            expect($this->policy->generatePdf($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without proper permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->generatePdf($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->generatePdf($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('email', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->email($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->email($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->email($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('scheduleEmail', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->scheduleEmail($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->scheduleEmail($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->scheduleEmail($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('processScheduledEmails', function () {
        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->processScheduledEmails($user, $this->company))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->processScheduledEmails($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->processScheduledEmails($user, $this->company))->toBeFalse();
        });
    });

    describe('sendReminders', function () {
        it('allows users with correct permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->sendReminders($user, $this->company))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->sendReminders($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.email');

            expect($this->policy->sendReminders($user, $this->company))->toBeFalse();
        });
    });

    describe('bulkOperate', function () {
        it('allows users with bulk permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.bulk');

            expect($this->policy->bulkOperate($user, $this->company))->toBeTrue();
        });

        it('allows users with update permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.update');

            expect($this->policy->bulkOperate($user, $this->company))->toBeTrue();
        });

        it('denies users without any bulk-related permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'viewer']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->bulkOperate($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.bulk');

            expect($this->policy->bulkOperate($user, $this->company))->toBeFalse();
        });
    });

    describe('viewStatistics', function () {
        it('allows users with view permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewStatistics($user, $this->company))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->viewStatistics($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewStatistics($user, $this->company))->toBeFalse();
        });
    });

    describe('export', function () {
        it('allows users with view permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->export($user, $this->company))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->export($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->export($user, $this->company))->toBeFalse();
        });
    });

    describe('viewAuditTrail', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with view permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewAuditTrail($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->viewAuditTrail($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewAuditTrail($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('syncWithLedger', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with post permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.post');

            expect($this->policy->syncWithLedger($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->syncWithLedger($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.post');

            expect($this->policy->syncWithLedger($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('viewApplications', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with view permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewApplications($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->viewApplications($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewApplications($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('modifyItems', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);
        });

        it('allows users with update permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.update');

            expect($this->policy->modifyItems($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->modifyItems($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.update');

            expect($this->policy->modifyItems($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('modifyReason', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);
        });

        it('allows users with update permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.update');

            expect($this->policy->modifyReason($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->modifyReason($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.update');

            expect($this->policy->modifyReason($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('viewFinancialImpact', function () {
        beforeEach(function () {
            $this->creditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
            ]);
        });

        it('allows users with view permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewFinancialImpact($user, $this->creditNote))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->viewFinancialImpact($user, $this->creditNote))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->viewFinancialImpact($user, $this->creditNote))->toBeFalse();
        });
    });

    describe('accessCustomerCreditNotes', function () {
        it('allows users with view permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->accessCustomerCreditNotes($user, $this->company))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->accessCustomerCreditNotes($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->accessCustomerCreditNotes($user, $this->company))->toBeFalse();
        });
    });

    describe('processAutomaticAdjustments', function () {
        it('allows users with apply permission and company access', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.apply');

            expect($this->policy->processAutomaticAdjustments($user, $this->company))->toBeTrue();
        });

        it('denies users without permission', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->processAutomaticAdjustments($user, $this->company))->toBeFalse();
        });

        it('denies users without company access', function () {
            $user = User::factory()->create();
            $user->givePermissionTo('credit_notes.apply');

            expect($this->policy->processAutomaticAdjustments($user, $this->company))->toBeFalse();
        });
    });

    describe('Business Rule Validations', function () {
        beforeEach(function () {
            $this->draftCreditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'draft',
            ]);

            $this->postedCreditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'posted',
                'posted_at' => now(),
            ]);

            $this->cancelledCreditNote = CreditNote::factory()->create([
                'company_id' => $this->company->id,
                'invoice_id' => $this->invoice->id,
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            $this->user = User::factory()->create();
            $this->company->users()->attach($this->user->id, ['role' => 'owner']);
            $this->user->givePermissionTo('credit_notes.update');
        });

        it('allows modification of draft credit notes', function () {
            expect($this->policy->canModifyBasedOnStatus($this->user, $this->draftCreditNote))->toBeTrue();
        });

        it('allows limited modification of posted credit notes', function () {
            expect($this->policy->canModifyBasedOnStatus($this->user, $this->postedCreditNote))->toBeTrue();
        });

        it('denies modification of cancelled credit notes', function () {
            expect($this->policy->canModifyBasedOnStatus($this->user, $this->cancelledCreditNote))->toBeFalse();
        });

        it('allows financial operations for users with proper permissions', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'owner']);
            $user->givePermissionTo('credit_notes.post');

            expect($this->policy->canPerformFinancialOperations($user, $this->draftCreditNote))->toBeTrue();
        });

        it('denies financial operations for users without proper permissions', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'viewer']);
            $user->givePermissionTo('credit_notes.view');

            expect($this->policy->canPerformFinancialOperations($user, $this->draftCreditNote))->toBeFalse();
        });

        it('allows sensitive operations for admin users during business hours', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'admin']);
            $user->givePermissionTo('credit_notes.post');

            // Mock business hours (8am - 6pm)
            $businessHour = now()->hour(10);
            $this->mock('Carbon\Carbon')->shouldReceive('now')->andReturn($businessHour);

            expect($this->policy->canPerformSensitiveOperations($user, $this->draftCreditNote))->toBeTrue();
        });

        it('denies sensitive operations for non-admin users outside business hours', function () {
            $user = User::factory()->create();
            $this->company->users()->attach($user->id, ['role' => 'accountant']);
            $user->givePermissionTo('credit_notes.post');

            // Mock non-business hours (10pm)
            $nonBusinessHour = now()->hour(22);
            $this->mock('Carbon\Carbon')->shouldReceive('now')->andReturn($nonBusinessHour);

            expect($this->policy->canPerformSensitiveOperations($user, $this->draftCreditNote))->toBeFalse();
        });
    });

    describe('Role-Based Access', function () {
        it('allows owner full access', function () {
            $user = User::factory()->create();
            $user->assignRole('owner');
            $this->company->users()->attach($user->id, ['role' => 'owner']);

            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.create'))->toBeTrue();
            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.delete'))->toBeTrue();
        });

        it('allows admin most operations', function () {
            $user = User::factory()->create();
            $user->assignRole('admin');
            $this->company->users()->attach($user->id, ['role' => 'admin']);

            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.create'))->toBeTrue();
            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.update'))->toBeTrue();
            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.delete'))->toBeTrue();
        });

        it('allows accountant create, update, and apply operations', function () {
            $user = User::factory()->create();
            $user->assignRole('accountant');
            $this->company->users()->attach($user->id, ['role' => 'accountant']);

            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.create'))->toBeTrue();
            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.update'))->toBeTrue();
            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.apply'))->toBeTrue();
        });

        it('only allows viewer to view credit notes', function () {
            $user = User::factory()->create();
            $user->assignRole('viewer');
            $this->company->users()->attach($user->id, ['role' => 'viewer']);

            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.view'))->toBeTrue();
            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.create'))->toBeFalse();
            expect($this->policy->hasRoleBasedAccess($user, 'credit_notes.update'))->toBeFalse();
        });
    });
});
