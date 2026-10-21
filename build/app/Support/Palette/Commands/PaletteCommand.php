<?php

namespace App\Support\Palette\Commands;

use App\Models\Company;
use App\Support\Palette\PalettePreview;

/**
 * A command typed as a sentence: `Pay {vendor} {amount} from {account?} on {date?}`.
 * It points at one CommandBus action (target) and turns the typed slots into that
 * action's params in resolve(). A module adds commands by returning these from its
 * manifest; no module JavaScript.
 */
abstract class PaletteCommand
{
    /** The first word(s) typed, e.g. 'pay', 'bank charge'. */
    abstract public function name(): string;

    /** @return list<string> other first words that mean the same, e.g. ['paid'] */
    public function aliases(): array
    {
        return [];
    }

    abstract public function label(): string;

    abstract public function sentence(): string;

    /** @return array<string, Slot> keyed by the slot names used in sentence() */
    abstract public function slots(): array;

    /** The CommandBus key; its permission gates the command. */
    abstract public function target(): string;

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        return Resolution::error('Not supported');
    }

    /** Where Open goes after posting: '/{company}/bill-payments/{id}', 'redirect' or null. */
    public function opens(): ?string
    {
        return null;
    }

    /** The CommandBus key that reverses this command, e.g. 'bill_payment.void'. */
    public function undo(): ?string
    {
        return null;
    }

    /** When set, Enter opens this page instead of resolving. */
    public function formUrl(): ?string
    {
        return null;
    }

    /** True for commands that show a card or a table and never execute. */
    public function query(): bool
    {
        return false;
    }

    public function toCatalog(Company $company): array
    {
        $optional = $this->optionalSlots();
        $slots = [];
        foreach ($this->slots() as $name => $slot) {
            $entry = ['name' => $name, 'kind' => $slot->kind, 'optional' => in_array($name, $optional, true)];
            if ($slot->entity !== null) {
                $entry['entity'] = $slot->entity;
            }
            if ($slot->options !== []) {
                $entry['options'] = $slot->options;
            }
            $slots[] = $entry;
        }

        $formUrl = $this->formUrl();

        return [
            'name' => $this->name(),
            'aliases' => $this->aliases(),
            'label' => $this->label(),
            'sentence' => $this->sentence(),
            'key' => $this->target(),
            'slots' => $slots,
            'generic' => false,
            'form_url' => $formUrl !== null ? str_replace('{company}', $company->slug, $formUrl) : null,
            'query' => $this->query(),
            'opens' => $this->opens(),
            'undo' => $this->undo(),
            'previewable' => array_key_exists($this->target(), PalettePreview::COMMANDS),
        ];
    }

    /** @return list<string> slot names written as {name?} in the sentence */
    private function optionalSlots(): array
    {
        preg_match_all('/\{(\w+)\?\}/', $this->sentence(), $m);

        return $m[1];
    }
}
