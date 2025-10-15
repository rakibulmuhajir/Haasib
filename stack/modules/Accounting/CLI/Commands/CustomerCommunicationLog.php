<?php

namespace Modules\Accounting\CLI\Commands;

use Illuminate\Console\Command;
use Modules\Accounting\Domain\Customers\Actions\LogCustomerCommunicationAction;
use Modules\Accounting\Domain\Customers\Models\Customer;

class CustomerCommunicationLog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'customer:communication:log {customer_id : The ID of the customer}
                           {--type= : Communication type (email/phone/meeting/letter/sms/note/other)}
                           {--direction= : Direction (inbound/outbound)}
                           {--subject= : Subject or topic}
                           {--content= : Content of the communication}
                           {--date= : Communication date (Y-m-d H:i:s, defaults to now)}
                           {--notes= : Internal notes}
                           {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Log a communication with a customer';

    /**
     * Execute the console command.
     */
    public function handle(LogCustomerCommunicationAction $action): int
    {
        $customerId = $this->argument('customer_id');
        $isJson = $this->option('json');

        // Validate customer exists
        $customer = Customer::find($customerId);
        if (! $customer) {
            $this->error("Customer with ID {$customerId} not found.");

            return 1;
        }

        // Collect communication data
        $data = [
            'type' => $this->option('type') ?? $this->choice(
                'Communication type',
                ['email', 'phone', 'meeting', 'letter', 'sms', 'note', 'other'],
                0
            ),
            'direction' => $this->option('direction') ?? $this->choice(
                'Direction',
                ['inbound', 'outbound'],
                1
            ),
            'subject' => $this->option('subject') ?? $this->ask('Subject or topic (optional)'),
            'content' => $this->option('content') ?? $this->ask('Content of the communication'),
            'communication_date' => $this->option('date')
                ? new \DateTime($this->option('date'))
                : now(),
            'notes' => $this->option('notes') ?? $this->ask('Internal notes (optional)'),
        ];

        try {
            $communication = $action->execute($customer, $data);

            if ($isJson) {
                $this->line(json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $communication->id,
                        'customer_id' => $communication->customer_id,
                        'type' => $communication->type,
                        'direction' => $communication->direction,
                        'subject' => $communication->subject,
                        'content' => $communication->content,
                        'communication_date' => $communication->communication_date,
                        'notes' => $communication->notes,
                        'created_at' => $communication->created_at,
                    ],
                ], JSON_PRETTY_PRINT));
            } else {
                $this->info('Communication logged successfully!');
                $this->info("ID: {$communication->id}");
                $this->info("Type: {$communication->type}");
                $this->info("Direction: {$communication->direction}");

                if ($communication->subject) {
                    $this->info("Subject: {$communication->subject}");
                }

                $this->info('Date: '.$communication->communication_date->format('Y-m-d H:i:s'));

                if ($communication->notes) {
                    $this->info("Notes: {$communication->notes}");
                }
            }

            return 0;
        } catch (\Exception $e) {
            $error = $e->getMessage();

            if ($isJson) {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $error,
                ], JSON_PRETTY_PRINT));
            } else {
                $this->error("Failed to log communication: {$error}");
            }

            return 1;
        }
    }
}
