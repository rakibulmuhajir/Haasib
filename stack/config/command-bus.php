<?php

return [
    'user.create' => App\Actions\DevOps\UserCreate::class,
    'user.update' => App\Actions\DevOps\UserUpdate::class,
    'user.delete' => App\Actions\DevOps\UserDelete::class,
    'user.activate' => App\Actions\User\ActivateUser::class,
    'user.deactivate' => App\Actions\User\DeactivateUser::class,

    // Customer actions - mapped to Accounting module registry
    'customer.create' => Modules\Accounting\Domain\Customers\Actions\CreateCustomerAction::class,
    'customer.update' => Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAction::class,
    'customer.delete' => Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAction::class,
    'customer.status' => Modules\Accounting\Domain\Customers\Actions\ChangeCustomerStatusAction::class,
    'customer.contact.create' => Modules\Accounting\Domain\Customers\Actions\CreateCustomerContactAction::class,
    'customer.contact.update' => Modules\Accounting\Domain\Customers\Actions\UpdateCustomerContactAction::class,
    'customer.contact.delete' => Modules\Accounting\Domain\Customers\Actions\DeleteCustomerContactAction::class,
    'customer.address.create' => Modules\Accounting\Domain\Customers\Actions\CreateCustomerAddressAction::class,
    'customer.address.update' => Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAddressAction::class,
    'customer.address.delete' => Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAddressAction::class,
    'customer.group.create' => Modules\Accounting\Domain\Customers\Actions\CreateCustomerGroupAction::class,
    'customer.group.assign' => Modules\Accounting\Domain\Customers\Actions\AssignCustomerToGroupAction::class,
    'customer.group.remove' => Modules\Accounting\Domain\Customers\Actions\RemoveCustomerFromGroupAction::class,
    'customer.communication.log' => Modules\Accounting\Domain\Customers\Actions\LogCustomerCommunicationAction::class,
    'customer.communication.delete' => Modules\Accounting\Domain\Customers\Actions\DeleteCustomerCommunicationAction::class,
    'customer.credit.adjust' => Modules\Accounting\Domain\Customers\Actions\AdjustCustomerCreditLimitAction::class,
    'customer.statement.generate' => Modules\Accounting\Domain\Customers\Actions\GenerateCustomerStatementAction::class,
    'customer.aging.refresh' => Modules\Accounting\Domain\Customers\Actions\RefreshCustomerAgingSnapshotAction::class,
    'customer.import' => Modules\Accounting\Domain\Customers\Actions\ImportCustomersAction::class,
    'customer.export' => Modules\Accounting\Domain\Customers\Actions\ExportCustomersAction::class,

    // Invoice actions
    'invoice.create' => App\Actions\DevOps\InvoiceCreate::class,
    'invoice.update' => App\Actions\DevOps\InvoiceUpdate::class,
    'invoice.delete' => App\Actions\DevOps\InvoiceDelete::class,
    'invoice.post' => App\Actions\DevOps\InvoicePost::class,
    'invoice.cancel' => App\Actions\DevOps\InvoiceCancel::class,

    // Journal entry actions - mapped to Accounting module actions
    'journal.create' => Modules\Accounting\Domain\Ledgers\Actions\CreateManualJournalEntryAction::class,
    'journal.submit' => Modules\Accounting\Domain\Ledgers\Actions\SubmitJournalEntryAction::class,
    'journal.approve' => Modules\Accounting\Domain\Ledgers\Actions\ApproveJournalEntryAction::class,
    'journal.post' => Modules\Accounting\Domain\Ledgers\Actions\PostJournalEntryAction::class,
    'journal.reverse' => Modules\Accounting\Domain\Ledgers\Actions\ReverseJournalEntryAction::class,
    'journal.void' => Modules\Accounting\Domain\Ledgers\Actions\VoidJournalEntryAction::class,
    'journal.auto' => Modules\Accounting\Domain\Ledgers\Actions\AutoJournalEntryAction::class,
    'company.create' => App\Actions\DevOps\CompanyCreate::class,
    'company.activate' => App\Actions\Company\ActivateCompany::class,
    'company.deactivate' => App\Actions\Company\DeactivateCompany::class,
    'company.delete' => App\Actions\DevOps\CompanyDelete::class,
    'company.assign' => App\Actions\DevOps\CompanyAssign::class,
    'company.update_role' => App\Actions\DevOps\CompanyUpdateRole::class,
    'company.unassign' => App\Actions\DevOps\CompanyUnassign::class,
    'company.invite' => App\Actions\Company\CompanyInvite::class,
    'invitation.revoke' => App\Actions\Invitation\InvitationRevoke::class,

    // Period close actions
    'period-close.start' => Modules\Ledger\Domain\PeriodClose\Actions\StartPeriodCloseAction::class,
    'period-close.snapshot' => Modules\Ledger\Domain\PeriodClose\Actions\GetPeriodCloseSnapshotAction::class,
    'period-close.validate' => Modules\Ledger\Domain\PeriodClose\Actions\ValidatePeriodCloseAction::class,
    'period-close.adjustment' => Modules\Ledger\Domain\PeriodClose\Actions\CreatePeriodCloseAdjustmentAction::class,
    'period-close.lock' => Modules\Ledger\Domain\PeriodClose\Actions\LockPeriodCloseAction::class,
    'period-close.complete' => Modules\Ledger\Domain\PeriodClose\Actions\CompletePeriodCloseAction::class,
    'period-close.reopen' => Modules\Ledger\Domain\PeriodClose\Actions\ReopenPeriodCloseAction::class,
    'period-close.reports.generate' => Modules\Ledger\Domain\PeriodClose\Actions\GeneratePeriodCloseReportsAction::class,

    // Period close template actions
    'period-close.template.sync' => Modules\Ledger\Domain\PeriodClose\Actions\SyncPeriodCloseTemplateAction::class,
    'period-close.template.update' => Modules\Ledger\Domain\PeriodClose\Actions\UpdatePeriodCloseTemplateAction::class,
    'period-close.template.archive' => Modules\Ledger\Domain\PeriodClose\Actions\ArchivePeriodCloseTemplateAction::class,
];
