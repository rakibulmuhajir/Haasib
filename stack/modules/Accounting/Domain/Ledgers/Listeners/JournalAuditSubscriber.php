<?php

namespace Modules\Accounting\Domain\Ledgers\Listeners;

use App\Models\JournalAudit;
use Illuminate\Support\Facades\Event;

class JournalAuditSubscriber
{
    /**
     * Register the listeners for the subscriber.
     *
     * @param  \Illuminate\Events\Dispatcher  $events
     * @return void
     */
    public function subscribe($events)
    {
        $eventList = [
            'Modules\Accounting\Domain\Ledgers\Events\JournalEntryCreated',
            'Modules\Accounting\Domain\Ledgers\Events\JournalEntryUpdated',
            'Modules\Accounting\Domain\Ledgers\Events\JournalEntrySubmitted',
            'Modules\Accounting\Domain\Ledgers\Events\JournalEntryApproved',
            'JournalEntryPosted',
            'JournalEntryVoided',
            'JournalEntryReversed',
            'JournalEntryAttachmentAdded',
            'Modules\Accounting\Domain\Ledgers\Events\AccountBalanceUpdated',
            'Modules\Accounting\Domain\Ledgers\Events\ReversalCreated',
            'Modules\Accounting\Domain\Ledgers\Events\JournalBatchCreated',
            'Modules\Accounting\Domain\Ledgers\Events\JournalBatchApproved',
            'Modules\Accounting\Domain\Ledgers\Events\JournalBatchPosted',
        ];

        foreach ($eventList as $event) {
            $events->listen($event, [$this, 'handleJournalEvent']);
        }
    }

    /**
     * Handle journal entry events and create audit records.
     *
     * @param  object  $event
     * @return void
     */
    public function handleJournalEvent($event)
    {
        try {
            $journalEntryId = $this->extractJournalEntryId($event);
            $eventType = $this->getEventType($event);
            $actorId = $this->extractActorId($event);
            $payload = $this->buildPayload($event);

            if ($journalEntryId && $eventType) {
                JournalAudit::createEvent(
                    $journalEntryId,
                    $eventType,
                    $payload,
                    $actorId
                );
            }
        } catch (\Exception $e) {
            // Log error but don't break the application
            logger()->error('Failed to create journal audit record', [
                'event' => get_class($event),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Extract journal entry ID from event.
     *
     * @param  object  $event
     */
    private function extractJournalEntryId($event): ?string
    {
        // Try different methods to get the journal entry ID
        if (method_exists($event, 'getJournalEntry')) {
            return $event->getJournalEntry()?->id;
        }

        if (property_exists($event, 'journalEntry')) {
            return $event->journalEntry?->id;
        }

        if (method_exists($event, 'getId')) {
            return $event->getId();
        }

        return null;
    }

    /**
     * Get event type based on event class.
     *
     * @param  object  $event
     */
    private function getEventType($event): string
    {
        $className = class_basename($event);

        // Map event classes to audit event types
        $eventMap = [
            'JournalEntryCreated' => 'created',
            'JournalEntryUpdated' => 'updated',
            'JournalEntrySubmitted' => 'updated',
            'JournalEntryApproved' => 'approved',
            'JournalEntryPosted' => 'posted',
            'JournalEntryVoided' => 'voided',
            'JournalEntryReversed' => 'reversed',
            'JournalEntryAttachmentAdded' => 'attachment_added',
            'AccountBalanceUpdated' => 'updated',
            'ReversalCreated' => 'created',
            'JournalBatchCreated' => 'created',
            'JournalBatchApproved' => 'approved',
            'JournalBatchPosted' => 'posted',
        ];

        return $eventMap[$className] ?? 'updated';
    }

    /**
     * Extract actor ID from event.
     *
     * @param  object  $event
     */
    private function extractActorId($event): ?string
    {
        // Try to get actor from event
        if (method_exists($event, 'getActor')) {
            return $event->getActor()?->id;
        }

        if (property_exists($event, 'actor')) {
            return $event->actor?->id;
        }

        // Try to get from authenticated user
        $user = auth()->user();

        return $user ? $user->id : null;
    }

    /**
     * Build audit payload from event data.
     *
     * @param  object  $event
     */
    private function buildPayload($event): array
    {
        $payload = [];

        // Extract data from event
        if (method_exists($event, 'getData')) {
            $payload = array_merge($payload, $event->getData());
        }

        if (property_exists($event, 'data')) {
            $payload = array_merge($payload, $event->data);
        }

        // Add common metadata
        $payload['event_class'] = get_class($event);
        $payload['timestamp'] = now()->toISOString();

        // Try to extract previous state if available
        if (method_exists($event, 'getPreviousState')) {
            $payload['previous_state'] = $event->getPreviousState();
        }

        if (property_exists($event, 'previousState')) {
            $payload['previous_state'] = $event->previousState;
        }

        // Try to extract new state if available
        if (method_exists($event, 'getNewState')) {
            $payload['new_state'] = $event->getNewState();
        }

        if (property_exists($event, 'newState')) {
            $payload['new_state'] = $event->newState;
        }

        return $payload;
    }
}
