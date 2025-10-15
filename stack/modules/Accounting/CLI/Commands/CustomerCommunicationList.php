<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerCommunication;

class CustomerCommunicationList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:communication:list {customer_id : The ID of the customer}
                           {--type= : Filter by communication type}
                           {--direction= : Filter by direction (inbound/outbound)}
                           {--from= : Filter communications from date (Y-m-d)}
                           {--to= : Filter communications to date (Y-m-d)}
                           {--search= : Search in subject, content, or notes}
                           {--limit= : Limit number of results (default: 50)}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List communications for a customer';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $customerId = $this->argument('customer_id');
        $isJson = $this->option('json');
        $limit = $this->option('limit') ?? 50;

        // Validate customer exists
        $customer = Customer::find($customerId);
        if (! $customer) {
            $this->error("Customer with ID {$customerId} not found.");

            return 1;
        }

        // Build query
        $query = CustomerCommunication::where('customer_id', $customerId);

        // Apply filters
        if ($this->option('type')) {
            $query->where('type', $this->option('type'));
        }

        if ($this->option('direction')) {
            $query->where('direction', $this->option('direction'));
        }

        if ($this->option('from')) {
            $query->whereDate('communication_date', '>=', $this->option('from'));
        }

        if ($this->option('to')) {
            $query->whereDate('communication_date', '<=', $this->option('to'));
        }

        if ($this->option('search')) {
            $search = $this->option('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Get communications with pagination
        $communications = $query->orderBy('communication_date', 'desc')
            ->limit($limit)
            ->get();

        if ($isJson) {
            $this->line(json_encode([
                'success' => true,
                'data' => [
                    'customer_id' => $customerId,
                    'customer_name' => $customer->name,
                    'communications' => $communications->map(function ($comm) {
                        return [
                            'id' => $comm->id,
                            'type' => $comm->type,
                            'direction' => $comm->direction,
                            'subject' => $comm->subject,
                            'content' => $comm->content,
                            'communication_date' => $comm->communication_date,
                            'notes' => $comm->notes,
                            'created_at' => $comm->created_at,
                        ];
                    })->toArray(),
                    'pagination' => [
                        'limit' => $limit,
                        'count' => $communications->count(),
                        'total' => CustomerCommunication::where('customer_id', $customerId)->count(),
                    ],
                ],
            ], JSON_PRETTY_PRINT));
        } else {
            if ($communications->isEmpty()) {
                $this->info("No communications found for customer {$customer->name}.");

                return 0;
            }

            $this->info("Communications for {$customer->name} (Customer ID: {$customerId}):");
            $this->info(str_repeat('-', 80));

            foreach ($communications as $comm) {
                $type = strtoupper($comm->type);
                $direction = strtoupper($comm->direction);
                $date = $comm->communication_date->format('M j, Y g:i A');

                $this->info("{$type} - {$direction} (ID: {$comm->id})");
                $this->info("Date: {$date}");

                if ($comm->subject) {
                    $this->info("Subject: {$comm->subject}");
                }

                $this->info('Content: '.substr($comm->content, 0, 100).
                            (strlen($comm->content) > 100 ? '...' : ''));

                if ($comm->notes) {
                    $this->info("Notes: {$comm->notes}");
                }

                $this->info('');
            }

            $total = CustomerCommunication::where('customer_id', $customerId)->count();
            $this->info("Showing {$communications->count()} of {$total} communications");
        }

        return 0;
    }
}
