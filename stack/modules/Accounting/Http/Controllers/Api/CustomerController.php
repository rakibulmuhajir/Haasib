<?php

namespace Modules\Accounting\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Accounting\Domain\Customers\Actions\AdjustCustomerCreditLimitAction;
use Modules\Accounting\Domain\Customers\Actions\AssignCustomerToGroupAction;
use Modules\Accounting\Domain\Customers\Actions\ChangeCustomerStatusAction;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerAction;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerAddressAction;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerContactAction;
use Modules\Accounting\Domain\Customers\Actions\CreateCustomerGroupAction;
use Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAction;
use Modules\Accounting\Domain\Customers\Actions\DeleteCustomerAddressAction;
use Modules\Accounting\Domain\Customers\Actions\DeleteCustomerCommunicationAction;
use Modules\Accounting\Domain\Customers\Actions\DeleteCustomerContactAction;
use Modules\Accounting\Domain\Customers\Actions\LogCustomerCommunicationAction;
use Modules\Accounting\Domain\Customers\Actions\RemoveCustomerFromGroupAction;
use Modules\Accounting\Domain\Customers\Actions\UpdateCustomerAction;
use Modules\Accounting\Domain\Customers\Actions\UpdateCustomerContactAction;
use Modules\Accounting\Domain\Customers\DTOs\CustomerAddressData;
use Modules\Accounting\Domain\Customers\DTOs\CustomerCommunicationData;
use Modules\Accounting\Domain\Customers\DTOs\CustomerContactData;
use Modules\Accounting\Domain\Customers\Models\CustomerAddress;
use Modules\Accounting\Domain\Customers\Models\CustomerCommunication;
use Modules\Accounting\Domain\Customers\Models\CustomerContact;
use Modules\Accounting\Domain\Customers\Models\CustomerGroup;
use Modules\Accounting\Domain\Customers\Services\CustomerCreditService;
use Modules\Accounting\Domain\Customers\Services\CustomerQueryService;
use Modules\Accounting\Http\Requests\ExportCustomersRequest;
use Modules\Accounting\Http\Requests\ImportCustomersRequest;

class CustomerController extends Controller
{
    public function __construct(
        private CustomerQueryService $customerQueryService,
        private CustomerCreditService $creditService,
        private CreateCustomerAction $createCustomerAction,
        private UpdateCustomerAction $updateCustomerAction,
        private DeleteCustomerAction $deleteCustomerAction,
        private ChangeCustomerStatusAction $changeCustomerStatusAction
    ) {}

    /**
     * Display a listing of customers.
     */
    public function index(Request $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $filters = [
            'status' => $request->get('status'),
            'search' => $request->get('search'),
            'currency' => $request->get('currency'),
        ];

        $customers = $this->customerQueryService->getCustomers(
            $currentCompany,
            array_filter($filters),
            $request->get('per_page', 15)
        );

        return response()->json([
            'data' => $customers->items(),
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created customer.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'customer_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'default_currency' => 'required|string|size:3',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked',
        ]);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        try {
            $customer = $this->createCustomerAction->execute(
                $currentCompany,
                $validated,
                $user
            );

            return response()->json([
                'message' => 'Customer created successfully',
                'data' => $customer,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified customer.
     */
    public function show(Request $request, string $customerId): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $customer = $this->customerQueryService->getCustomerDetails($currentCompany, $customerId);

        if (! $customer) {
            return response()->json([
                'message' => 'Customer not found',
            ], 404);
        }

        // Include related data if user has appropriate permissions
        $includeContacts = $request->user()->can('accounting.customers.manage_contacts');
        $includeCommunications = $request->user()->can('accounting.customers.manage_comms');

        $responseData = [
            'data' => $customer,
        ];

        if ($includeContacts) {
            $responseData['contacts'] = CustomerContact::where('customer_id', $customerId)
                ->orderBy('is_primary', 'desc')
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get();

            $responseData['addresses'] = CustomerAddress::where('customer_id', $customerId)
                ->orderBy('is_default', 'desc')
                ->orderBy('address_type')
                ->orderBy('created_at')
                ->get();
        }

        if ($includeCommunications) {
            $responseData['communications'] = CustomerCommunication::where('customer_id', $customerId)
                ->orderBy('communication_date', 'desc')
                ->paginate(20);
        }

        return response()->json($responseData);
    }

    /**
     * Update the specified customer.
     */
    public function update(Request $request, string $customerId): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'customer_number' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'default_currency' => 'sometimes|required|string|size:3',
            'payment_terms' => 'nullable|string|max:100',
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_id' => 'nullable|string|max:50',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,blocked',
        ]);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        try {
            $customer = $this->updateCustomerAction->execute(
                $currentCompany,
                $customerId,
                $validated,
                $user
            );

            return response()->json([
                'message' => 'Customer updated successfully',
                'data' => $customer,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified customer.
     */
    public function destroy(Request $request, string $customerId): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        try {
            $this->deleteCustomerAction->execute(
                $currentCompany,
                $customerId,
                $user
            );

            return response()->json([
                'message' => 'Customer deleted successfully',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Cannot delete customer',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Change customer status.
     */
    public function changeStatus(Request $request, string $customerId): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,blocked',
            'reason' => 'nullable|string|required_if:status,blocked',
            'approval_reference' => 'nullable|string',
        ]);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        try {
            $customer = $this->changeCustomerStatusAction->execute(
                $currentCompany,
                $customerId,
                $validated['status'],
                [
                    'reason' => $validated['reason'] ?? null,
                    'approval_reference' => $validated['approval_reference'] ?? null,
                ],
                $user
            );

            return response()->json([
                'message' => 'Customer status changed successfully',
                'data' => $customer,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Cannot change customer status',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to change customer status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Search customers.
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => 'required|string|min:2',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $currentCompany = $request->attributes->get('company');

        $customers = $this->customerQueryService->searchCustomers(
            $currentCompany,
            $validated['q'],
            $validated['limit'] ?? 20
        );

        return response()->json([
            'data' => $customers,
        ]);
    }

    /**
     * Get customer statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $statistics = $this->customerQueryService->getCustomerStatistics($currentCompany);

        return response()->json([
            'data' => $statistics,
        ]);
    }

    /**
     * Export customers.
     */
    public function export(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'format' => 'nullable|in:csv,xlsx',
            'status' => 'nullable|in:active,inactive,blocked',
            'search' => 'nullable|string',
        ]);

        $currentCompany = $request->attributes->get('company');

        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'search' => $validated['search'] ?? null,
        ]);

        $customers = $this->customerQueryService->exportCustomers($currentCompany, $filters);

        // Format for CSV export
        $csv = "Customer Number,Name,Legal Name,Email,Phone,Status,Currency,Credit Limit,Tax ID,Website,Created At\n";

        foreach ($customers as $customer) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"%s',
                $customer->customer_number,
                str_replace('"', '""', $customer->name),
                str_replace('"', '""', $customer->legal_name ?? ''),
                $customer->email ?? '',
                $customer->phone ?? '',
                $customer->status,
                $customer->default_currency,
                number_format($customer->credit_limit ?? 0, 2),
                $customer->tax_id ?? '',
                $customer->website ?? '',
                $customer->created_at->format('Y-m-d H:i:s'),
                "\n"
            );
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="customers_export.csv"');
    }

    /**
     * Bulk operations on customers.
     */
    public function bulk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:delete,status_change',
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'required|uuid',
            'status' => 'required_if:action,status_change|in:active,inactive,blocked',
            'reason' => 'nullable|string|required_if:action,status_change,status,blocked',
            'approval_reference' => 'nullable|string',
        ]);

        $currentCompany = $request->attributes->get('company');
        $user = $request->user();

        try {
            if ($validated['action'] === 'status_change') {
                $results = $this->changeCustomerStatusAction->bulkChangeStatus(
                    $currentCompany,
                    $validated['customer_ids'],
                    $validated['status'],
                    [
                        'reason' => $validated['reason'] ?? null,
                        'approval_reference' => $validated['approval_reference'] ?? null,
                    ],
                    $user
                );

                return response()->json([
                    'message' => 'Bulk status change completed',
                    'data' => $results,
                ]);
            }

            // Handle other bulk actions here
            return response()->json([
                'message' => 'Bulk operation not implemented',
            ], 501);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Bulk operation failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ===== CONTACT METHODS =====

    /**
     * Display a listing of customer contacts.
     */
    public function contactsIndex(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $contacts = CustomerContact::where('customer_id', $customerId)
            ->with(['creator'])
            ->get();

        return response()->json([
            'data' => $contacts,
        ]);
    }

    /**
     * Store a newly created customer contact.
     */
    public function contactsStore(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role' => 'required|string|max:100',
            'is_primary' => 'boolean',
            'preferred_channel' => 'required|in:email,phone,sms,portal',
        ]);

        try {
            $contactData = CustomerContactData::fromArray($validated);
            $contact = app(CreateCustomerContactAction::class)->execute($customer, $contactData);

            return response()->json([
                'message' => 'Contact created successfully',
                'data' => $contact,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified customer contact.
     */
    public function contactsShow(Request $request, string $customerId, string $contactId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);
        $contact = CustomerContact::where('id', $contactId)
            ->where('customer_id', $customerId)
            ->with(['creator'])
            ->firstOrFail();

        return response()->json([
            'data' => $contact,
        ]);
    }

    /**
     * Update the specified customer contact.
     */
    public function contactsUpdate(Request $request, string $customerId, string $contactId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'role' => 'required|string|max:100',
            'is_primary' => 'boolean',
            'preferred_channel' => 'required|in:email,phone,sms,portal',
        ]);

        try {
            $contact = CustomerContact::where('id', $contactId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $contactData = CustomerContactData::fromArray($validated);
            $updatedContact = app(UpdateCustomerContactAction::class)->execute($contact, $contactData);

            return response()->json([
                'message' => 'Contact updated successfully',
                'data' => $updatedContact,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified customer contact.
     */
    public function contactsDestroy(Request $request, string $customerId, string $contactId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        try {
            $contact = CustomerContact::where('id', $contactId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            app(DeleteCustomerContactAction::class)->execute($contact);

            return response()->json([
                'message' => 'Contact deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set contact as primary for its role.
     */
    public function contactsSetPrimary(Request $request, string $customerId, string $contactId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        try {
            $contact = CustomerContact::where('id', $contactId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $contact->setAsPrimary();

            return response()->json([
                'message' => 'Contact set as primary successfully',
                'data' => $contact->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to set contact as primary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ===== ADDRESS METHODS =====

    /**
     * Display a listing of customer addresses.
     */
    public function addressesIndex(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $addresses = CustomerAddress::where('customer_id', $customerId)
            ->get();

        return response()->json([
            'data' => $addresses,
        ]);
    }

    /**
     * Store a newly created customer address.
     */
    public function addressesStore(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'label' => 'required|string|max:100',
            'type' => 'required|in:billing,shipping,statement,other',
            'line1' => 'required|string|max:255',
            'line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:30',
            'country' => 'required|string|size:2',
            'is_default' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $addressData = CustomerAddressData::fromArray($validated);
            $address = app(CreateCustomerAddressAction::class)->execute($customer, $addressData);

            return response()->json([
                'message' => 'Address created successfully',
                'data' => $address,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified customer address.
     */
    public function addressesDestroy(Request $request, string $customerId, string $addressId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        try {
            $address = CustomerAddress::where('id', $addressId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            app(DeleteCustomerAddressAction::class)->execute($address);

            return response()->json([
                'message' => 'Address deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set address as default for its type.
     */
    public function addressesSetDefault(Request $request, string $customerId, string $addressId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        try {
            $address = CustomerAddress::where('id', $addressId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $address->setAsDefault();

            return response()->json([
                'message' => 'Address set as default successfully',
                'data' => $address->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to set address as default',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ===== GROUP METHODS =====

    /**
     * Display groups for a customer.
     */
    public function groupsIndex(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $groups = $customer->groups()->with(['members'])->get();

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Assign customer to a group.
     */
    public function groupsAssign(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'group_id' => 'required|uuid|exists:pgsql.acct.customer_groups,id',
        ]);

        try {
            $group = CustomerGroup::where('company_id', $customer->company_id)
                ->findOrFail($validated['group_id']);

            $membership = app(AssignCustomerToGroupAction::class)->execute($customer, $group);

            return response()->json([
                'message' => 'Customer assigned to group successfully',
                'data' => $membership,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to assign customer to group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove customer from a group.
     */
    public function groupsRemove(Request $request, string $customerId, string $groupId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        try {
            $group = CustomerGroup::where('company_id', $customer->company_id)
                ->findOrFail($groupId);

            app(RemoveCustomerFromGroupAction::class)->execute($customer, $group);

            return response()->json([
                'message' => 'Customer removed from group successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove customer from group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ===== COMMUNICATION METHODS =====

    /**
     * Display a listing of customer communications.
     */
    public function communicationsIndex(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $communications = CustomerCommunication::where('customer_id', $customerId)
            ->with(['contact', 'loggedBy'])
            ->orderBy('occurred_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $communications->items(),
            'pagination' => [
                'current_page' => $communications->currentPage(),
                'last_page' => $communications->lastPage(),
                'per_page' => $communications->perPage(),
                'total' => $communications->total(),
                'from' => $communications->firstItem(),
                'to' => $communications->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created customer communication.
     */
    public function communicationsStore(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'contact_id' => 'nullable|uuid|exists:pgsql.acct.customer_contacts,id',
            'channel' => 'required|in:email,phone,meeting,note',
            'direction' => 'required|in:inbound,outbound,internal',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'occurred_at' => 'nullable|date',
            'attachments' => 'nullable|array',
        ]);

        try {
            $communicationData = CustomerCommunicationData::fromArray($validated);
            $communication = app(LogCustomerCommunicationAction::class)->execute($customer, $communicationData);

            return response()->json([
                'message' => 'Communication logged successfully',
                'data' => $communication,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to log communication',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified customer communication.
     */
    public function communicationsShow(Request $request, string $customerId, string $commId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);
        $communication = CustomerCommunication::where('id', $commId)
            ->where('customer_id', $customerId)
            ->with(['contact', 'loggedBy'])
            ->firstOrFail();

        return response()->json([
            'data' => $communication,
        ]);
    }

    /**
     * Remove the specified customer communication.
     */
    public function communicationsDestroy(Request $request, string $customerId, string $commId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        try {
            $communication = CustomerCommunication::where('id', $commId)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            app(DeleteCustomerCommunicationAction::class)->execute($communication);

            return response()->json([
                'message' => 'Communication deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete communication',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get communication timeline.
     */
    public function communicationsTimeline(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $limit = $request->get('limit', 50);
        $offset = $request->get('offset', 0);

        try {
            $timeline = CustomerCommunication::getTimeline($customer, $limit, $offset);

            return response()->json([
                'data' => $timeline,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get communication timeline',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ===== GLOBAL GROUP METHODS =====

    /**
     * Display all groups for the company.
     */
    public function groupsGlobalIndex(Request $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $groups = CustomerGroup::where('company_id', $currentCompany->id)
            ->withCount('members')
            ->get();

        return response()->json([
            'data' => $groups,
        ]);
    }

    /**
     * Store a newly created customer group.
     */
    public function groupsGlobalStore(Request $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_default' => 'boolean',
        ]);

        try {
            $group = app(CreateCustomerGroupAction::class)->execute($validated, $currentCompany->id);

            return response()->json([
                'message' => 'Group created successfully',
                'data' => $group,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create group',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get group members.
     */
    public function groupsMembers(Request $request, string $groupId): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $group = CustomerGroup::where('id', $groupId)
            ->where('company_id', $currentCompany->id)
            ->with(['members' => function ($query) {
                $query->with(['customer', 'addedBy']);
            }])
            ->firstOrFail();

        return response()->json([
            'data' => $group->members,
        ]);
    }

    /**
     * Get customer credit limit information
     */
    public function creditLimit(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $creditLimit = $this->creditService->getCurrentCreditLimit($customer);
        $exposure = $this->creditService->calculateExposure($customer);
        $utilization = $this->creditService->getCreditUtilizationStatus($customer);
        $history = $this->creditService->getCreditLimitHistory($customer);

        return response()->json([
            'data' => [
                'customer_id' => $customer->id,
                'credit_limit' => $creditLimit,
                'current_exposure' => $exposure,
                'available_credit' => $creditLimit ? max(0, $creditLimit - $exposure) : null,
                'utilization' => $utilization,
                'risk_assessment' => $this->creditService->getCreditRiskAssessment($customer),
                'history' => $history,
            ],
        ]);
    }

    /**
     * Adjust customer credit limit
     */
    public function creditLimitAdjust(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'new_limit' => 'required|numeric|min:0',
            'effective_at' => 'required|date',
            'expires_at' => 'nullable|date|after:effective_at',
            'reason' => 'nullable|string|max:500',
            'approval_reference' => 'nullable|string|max:100',
            'status' => 'nullable|in:pending,approved',
            'auto_expire_conflicts' => 'boolean',
        ]);

        try {
            $action = app(AdjustCustomerCreditLimitAction::class);

            $creditLimit = $action->execute(
                $customer,
                $validated['new_limit'],
                new \DateTime($validated['effective_at']),
                [
                    'expires_at' => $validated['expires_at'] ? new \DateTime($validated['expires_at']) : null,
                    'reason' => $validated['reason'],
                    'approval_reference' => $validated['approval_reference'],
                    'status' => $validated['status'] ?? 'approved',
                    'auto_expire_conflicts' => $validated['auto_expire_conflicts'] ?? false,
                    'changed_by_user_id' => $request->user()->id,
                ]
            );

            return response()->json([
                'message' => 'Credit limit adjusted successfully',
                'data' => [
                    'id' => $creditLimit->id,
                    'limit_amount' => $creditLimit->limit_amount,
                    'effective_at' => $creditLimit->effective_at,
                    'expires_at' => $creditLimit->expires_at,
                    'status' => $creditLimit->status,
                    'customer_updated' => $customer->fresh(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to adjust credit limit',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Approve pending credit limit adjustment
     */
    public function creditLimitApprove(Request $request, string $customerId, string $creditLimitId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'approval_reference' => 'nullable|string|max:100',
            'approval_reason' => 'nullable|string|max:500',
        ]);

        try {
            $creditLimit = CustomerCreditLimit::where('id', $creditLimitId)
                ->where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->firstOrFail();

            $action = app(AdjustCustomerCreditLimitAction::class);

            $approvedLimit = $action->approveRequest(
                $creditLimit,
                $validated['approval_reference'],
                $validated['approval_reason']
            );

            return response()->json([
                'message' => 'Credit limit approved successfully',
                'data' => [
                    'id' => $approvedLimit->id,
                    'status' => $approvedLimit->status,
                    'approval_reference' => $approvedLimit->approval_reference,
                    'customer_updated' => $customer->fresh(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to approve credit limit',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Reject pending credit limit adjustment
     */
    public function creditLimitReject(Request $request, string $customerId, string $creditLimitId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $creditLimit = CustomerCreditLimit::where('id', $creditLimitId)
                ->where('customer_id', $customer->id)
                ->where('status', 'pending')
                ->firstOrFail();

            $action = app(AdjustCustomerCreditLimitAction::class);

            $rejectedLimit = $action->rejectRequest($creditLimit, $validated['rejection_reason']);

            return response()->json([
                'message' => 'Credit limit rejected successfully',
                'data' => [
                    'id' => $rejectedLimit->id,
                    'status' => $rejectedLimit->status,
                    'reason' => $rejectedLimit->reason,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to reject credit limit',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get credit limit history for a customer
     */
    public function creditLimitHistory(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $limit = $request->get('limit', 20);
        $history = $this->creditService->getCreditLimitHistory($customer, $limit);

        return response()->json([
            'data' => $history,
        ]);
    }

    /**
     * Check if invoice can be created within credit limit
     */
    public function creditLimitCheck(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $creditCheck = $this->creditService->canCreateInvoice($customer, $validated['amount']);

        return response()->json([
            'data' => $creditCheck,
        ]);
    }

    /**
     * Get customer aging information
     */
    public function aging(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'as_of_date' => 'nullable|date',
            'history_days' => 'nullable|integer|min:1|max:365',
            'trend_days' => 'nullable|integer|min:1|max:365',
            'include_trend' => 'boolean',
            'include_health_score' => 'boolean',
        ]);

        $asOfDate = $validated['as_of_date'] ? now()->parse($validated['as_of_date']) : now();
        $historyDays = $validated['history_days'] ?? 30;
        $trendDays = $validated['trend_days'] ?? 90;

        $agingService = app(\Modules\Accounting\Domain\Customers\Services\CustomerAgingService::class);
        $refreshAction = app(\Modules\Accounting\Domain\Customers\Actions\RefreshCustomerAgingSnapshotAction::class);

        // Calculate current aging buckets
        $agingBuckets = $agingService->calculateAgingBuckets($customer, $asOfDate);

        // Get aging history
        $agingHistory = $agingService->getAgingHistory($customer, $historyDays);

        $response = [
            'customer_id' => $customer->id,
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'aging_buckets' => $agingBuckets,
            'history' => $agingHistory,
            'latest_snapshot' => $agingHistory->first(),
        ];

        // Include trend data if requested
        if ($validated['include_trend'] ?? false) {
            $response['trend'] = $agingService->getAgingTrend($customer, $trendDays);
        }

        // Include health score if requested
        if ($validated['include_health_score'] ?? false) {
            $response['health_score'] = $refreshAction->getAgingHealthScore($customer, $asOfDate);
        }

        return response()->json([
            'data' => $response,
        ]);
    }

    /**
     * Refresh customer aging snapshot
     */
    public function agingRefresh(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'snapshot_date' => 'nullable|date',
            'generated_via' => 'required|in:scheduled,on_demand',
        ]);

        $snapshotDate = $validated['snapshot_date'] ? now()->parse($validated['snapshot_date']) : now();

        $refreshAction = app(\Modules\Accounting\Domain\Customers\Actions\RefreshCustomerAgingSnapshotAction::class);

        $snapshot = $refreshAction->execute(
            $customer,
            $snapshotDate,
            $validated['generated_via'],
            $request->user()->id
        );

        return response()->json([
            'message' => 'Aging snapshot refreshed successfully',
            'data' => [
                'snapshot_id' => $snapshot->id,
                'customer_id' => $customer->id,
                'snapshot_date' => $snapshot->snapshot_date->format('Y-m-d'),
                'generated_via' => $snapshot->generated_via,
                'generated_at' => $snapshot->created_at->toISOString(),
                'buckets' => [
                    'current' => $snapshot->bucket_current,
                    '1_30' => $snapshot->bucket_1_30,
                    '31_60' => $snapshot->bucket_31_60,
                    '61_90' => $snapshot->bucket_61_90,
                    '90_plus' => $snapshot->bucket_90_plus,
                ],
                'total_invoices' => $snapshot->total_invoices,
                'total_outstanding' => $snapshot->bucket_current + $snapshot->bucket_1_30 +
                                      $snapshot->bucket_31_60 + $snapshot->bucket_61_90 + $snapshot->bucket_90_plus,
            ],
        ]);
    }

    /**
     * Get customer statements
     */
    public function statements(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
        ]);

        $statementService = app(\Modules\Accounting\Domain\Customers\Services\CustomerStatementService::class);

        $query = \Modules\Accounting\Domain\Customers\Models\CustomerStatement::where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->orderBy('period_end', 'desc');

        if ($validated['limit']) {
            $query->limit($validated['limit']);
        }

        if ($validated['period_start']) {
            $query->where('period_start', '>=', $validated['period_start']);
        }

        if ($validated['period_end']) {
            $query->where('period_end', '<=', $validated['period_end']);
        }

        $statements = $query->get();

        return response()->json([
            'data' => $statements,
            'pagination' => [
                'limit' => $validated['limit'],
                'returned' => $statements->count(),
            ],
        ]);
    }

    /**
     * Generate a customer statement
     */
    public function statementGenerate(Request $request, string $customerId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $validated = $request->validate([
            'period_start' => 'required|date|before_or_equal:period_end',
            'period_end' => 'required|date',
            'format' => 'required|in:pdf,csv',
            'approval_reference' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        $periodStart = now()->parse($validated['period_start'])->startOfDay();
        $periodEnd = now()->parse($validated['period_end'])->endOfDay();

        $statementAction = app(\Modules\Accounting\Domain\Customers\Actions\GenerateCustomerStatementAction::class);

        $statement = $statementAction->execute(
            $customer,
            $periodStart,
            $periodEnd,
            [
                'format' => $validated['format'],
                'generated_by_user_id' => $request->user()->id,
                'approval_reference' => $validated['approval_reference'],
                'reason' => $validated['reason'],
            ]
        );

        return response()->json([
            'message' => 'Statement generated successfully',
            'data' => [
                'statement_id' => $statement->id,
                'customer_id' => $customer->id,
                'period_start' => $statement->period_start->format('Y-m-d'),
                'period_end' => $statement->period_end->format('Y-m-d'),
                'generated_at' => $statement->generated_at->toISOString(),
                'generated_by' => $statement->generated_by_user_id,
                'format' => $validated['format'],
                'opening_balance' => $statement->opening_balance,
                'total_invoiced' => $statement->total_invoiced,
                'total_paid' => $statement->total_paid,
                'total_credit_notes' => $statement->total_credit_notes,
                'closing_balance' => $statement->closing_balance,
                'document_path' => $statement->document_path,
                'checksum' => $statement->checksum,
                'aging_summary' => $statement->aging_bucket_summary,
            ],
        ]);
    }

    /**
     * Download a customer statement document
     */
    public function statementDownload(Request $request, string $customerId, string $statementId): JsonResponse
    {
        $customer = $this->getCustomerWithPermission($request, $customerId);

        $statement = \Modules\Accounting\Domain\Customers\Models\CustomerStatement::where('id', $statementId)
            ->where('customer_id', $customer->id)
            ->where('company_id', $customer->company_id)
            ->firstOrFail();

        if (! $statement->document_path || ! \Storage::exists($statement->document_path)) {
            return response()->json([
                'message' => 'Statement document not found',
                'error' => 'The statement document file does not exist',
            ], 404);
        }

        $fileContents = \Storage::get($statement->document_path);
        $mimeType = \Storage::mimeType($statement->document_path);

        return response($fileContents)
            ->header('Content-Type', $mimeType)
            ->header('Content-Disposition', 'attachment; filename="'.basename($statement->document_path).'"');
    }

    /**
     * Get company-wide aging summary
     */
    public function companyAgingSummary(Request $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $validated = $request->validate([
            'as_of_date' => 'nullable|date',
        ]);

        $asOfDate = $validated['as_of_date'] ? now()->parse($validated['as_of_date']) : now()->subDay();

        $agingService = app(\Modules\Accounting\Domain\Customers\Services\CustomerAgingService::class);
        $summary = $agingService->getCompanyAgingSummary($currentCompany, $asOfDate);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get high-risk customers for collections
     */
    public function highRiskCustomers(Request $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        $validated = $request->validate([
            'threshold' => 'nullable|numeric|min:0',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $threshold = $validated['threshold'] ?? 5000.00;
        $limit = $validated['limit'] ?? 50;

        $agingService = app(\Modules\Accounting\Domain\Customers\Services\CustomerAgingService::class);
        $highRiskCustomers = $agingService->getHighRiskCustomers($currentCompany->id, $threshold, $limit);

        return response()->json([
            'data' => $highRiskCustomers,
            'threshold_used' => $threshold,
            'limit_used' => $limit,
        ]);
    }

    /**
     * Import customers from file or data.
     */
    public function import(ImportCustomersRequest $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');
        $user = $request->user();
        $validated = $request->validated();

        try {
            $importAction = app(\Modules\Accounting\Domain\Customers\Actions\ImportCustomersAction::class);

            $result = $importAction->execute([
                'company_id' => $currentCompany->id,
                'created_by_user_id' => $user->id,
                'source_type' => $validated['source_type'],
                'file' => $validated['file'] ?? null,
                'entries' => $validated['entries'] ?? [],
                'options' => $validated['options'] ?? [],
                'notes' => $validated['notes'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed: '.$e->getMessage(),
                'error_code' => 'import_failed',
            ], 422);
        }
    }

    /**
     * Get import status and results.
     */
    public function importStatus(Request $request, string $batchId): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        // For now, return basic status. In a real implementation,
        // you'd track import status in a database table
        return response()->json([
            'success' => true,
            'data' => [
                'import_batch_id' => $batchId,
                'company_id' => $currentCompany->id,
                'status' => 'completed', // queued, processing, completed, failed
                'progress' => 100,
                'total_count' => 0,
                'imported_count' => 0,
                'skipped_count' => 0,
                'error_count' => 0,
                'errors' => [],
                'created_at' => now()->toISOString(),
                'completed_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Export customers to file.
     */
    public function exportCustomers(ExportCustomersRequest $request): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');
        $user = $request->user();
        $config = $request->getExportConfig();

        try {
            $exportAction = app(\Modules\Accounting\Domain\Customers\Actions\ExportCustomersAction::class);

            $result = $exportAction->execute([
                'company_id' => $currentCompany->id,
                'created_by_user_id' => $user->id,
                'format' => $config['format'],
                'filters' => $config['filters'],
                'columns' => $config['columns'],
                'sort_by' => $config['sort_by'],
                'sort_direction' => $config['sort_direction'],
                'limit' => $config['limit'],
                'include_invoices' => $config['include_invoices'],
                'include_payments' => $config['include_payments'],
                'include_aging' => $config['include_aging'],
                'options' => $config['options'],
                'compress' => $config['compress'],
                'notes' => $config['notes'],
                'metadata' => $config['metadata'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $result,
                'is_large_export' => $request->isLargeExport(),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: '.$e->getMessage(),
                'error_code' => 'export_failed',
            ], 422);
        }
    }

    /**
     * Download exported file.
     */
    public function exportDownload(Request $request, string $batchId): JsonResponse
    {
        $currentCompany = $request->attributes->get('company');

        // For now, return basic download info. In a real implementation,
        // you'd retrieve the export record from the database
        return response()->json([
            'success' => true,
            'data' => [
                'export_batch_id' => $batchId,
                'company_id' => $currentCompany->id,
                'download_url' => '#', // Would be actual download URL
                'file_name' => "customers-export-{$batchId}.csv",
                'file_size' => 0,
                'expires_at' => now()->addHours(24)->toISOString(),
            ],
        ]);
    }

    // ===== HELPER METHODS =====

    /**
     * Get customer with permission check.
     */
    private function getCustomerWithPermission(Request $request, string $customerId): Customer
    {
        $currentCompany = $request->attributes->get('company');

        $customer = Customer::where('id', $customerId)
            ->where('company_id', $currentCompany->id)
            ->firstOrFail();

        return $customer;
    }
}
