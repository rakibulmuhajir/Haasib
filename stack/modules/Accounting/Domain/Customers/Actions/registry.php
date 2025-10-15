<?php

return [
    // Customer Core Actions
    'customer.create' => \Modules\Accounting\Domain\Customers\Actions\CreateCustomerAction::class,
    'customer.update' => \Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAction::class,
    'customer.delete' => \Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAction::class,
    'customer.status' => \Modules\Accounting\Domain\Customers\Actions\ChangeCustomerStatusAction::class,

    // Contact Actions
    'customer.contact.create' => \Modules\Accounting\Domain\Customers\Actions\CreateCustomerContactAction::class,
    'customer.contact.update' => \Modules\Accounting\Domain\Customers\Actions\UpdateCustomerContactAction::class,
    'customer.contact.delete' => \Modules\Accounting\Domain\Customers\Actions\DeleteCustomerContactAction::class,

    // Address Actions
    'customer.address.create' => \Modules\Accounting\Domain\Customers\Actions\CreateCustomerAddressAction::class,
    'customer.address.update' => \Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAddressAction::class,
    'customer.address.delete' => \Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAddressAction::class,

    // Group Actions
    'customer.group.create' => \Modules\Accounting\Domain\Customers\Actions\CreateCustomerGroupAction::class,
    'customer.group.assign' => \Modules\Accounting\Domain\Customers\Actions\AssignCustomerToGroupAction::class,
    'customer.group.remove' => \Modules\Accounting\Domain\Customers\Actions\RemoveCustomerFromGroupAction::class,

    // Communication Actions
    'customer.communication.log' => \Modules\Accounting\Domain\Customers\Actions\LogCustomerCommunicationAction::class,
    'customer.communication.delete' => \Modules\Accounting\Domain\Customers\Actions\DeleteCustomerCommunicationAction::class,

    // Credit Actions
    'customer.credit.adjust' => \Modules\Accounting\Domain\Customers\Actions\AdjustCustomerCreditLimitAction::class,

    // Statement Actions
    'customer.statement.generate' => \Modules\Accounting\Domain\Customers\Actions\GenerateCustomerStatementAction::class,

    // Aging Actions
    'customer.aging.refresh' => \Modules\Accounting\Domain\Customers\Actions\RefreshCustomerAgingSnapshotAction::class,

    // Import/Export Actions
    'customer.import' => \Modules\Accounting\Domain\Customers\Actions\ImportCustomersAction::class,
    'customer.export' => \Modules\Accounting\Domain\Customers\Actions\ExportCustomersAction::class,
];
