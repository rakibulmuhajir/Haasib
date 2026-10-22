<?php

namespace App\Support\Palette\Commands;

/**
 * The answer to "what does this typed line mean?": ready to run, still missing
 * something (one state per slot), or refused with a message.
 *
 * Slot states:
 *   ['state' => 'resolved', 'value' => id, 'label' => name]
 *   ['state' => 'choices', 'prompt' => ..., 'choices' => [['value' => ..., 'label' => ..., 'meta' => ...?]]]
 *   ['state' => 'not_found', 'message' => ...]
 *   ['state' => 'missing']
 */
final class Resolution
{
    private function __construct(private readonly array $data) {}

    /**
     * @param  array<string, mixed>  $params  the CommandBus params
     * @param  array<string, mixed>  $card  what the user is shown
     * @param  list<string>  $notes
     */
    public static function ready(string $key, array $params, array $card, string $summary, array $notes = []): self
    {
        return new self([
            'status' => 'ready', 'key' => $key, 'params' => $params,
            'card' => $card, 'summary' => $summary, 'notes' => $notes,
        ]);
    }

    /** @param array<string, array<string, mixed>> $slots slot name => slot state */
    public static function pending(array $slots): self
    {
        return new self(['status' => 'pending', 'slots' => $slots]);
    }

    public static function error(string $message): self
    {
        return new self(['status' => 'error', 'message' => $message]);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
