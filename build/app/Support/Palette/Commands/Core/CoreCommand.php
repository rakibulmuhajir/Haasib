<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\SlotInput;
use App\Support\Palette\PaletteSearchSources;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** What the record, document and lookup commands share: naming one record through its search source. */
abstract class CoreCommand extends PaletteCommand
{
    private const TABLES = [
        'customer' => ['acct.customers', 'name'],
        'vendor' => ['acct.vendors', 'name'],
        'invoice' => ['acct.invoices', 'invoice_number'],
        'bill' => ['acct.bills', 'bill_number'],
        'account' => ['acct.accounts', 'name'],
    ];

    /**
     * Reads one record from a search slot: the picked id, else the typed name (one match, or an exact one).
     * Fills $states for the slot; null leaves the command pending.
     *
     * @return array{id: string, label: string}|null
     */
    protected function record(string $slot, string $entity, SlotInput $in, ResolveContext $ctx, array &$states): ?array
    {
        $text = $in->text($slot);
        $picked = $in->value($slot);

        if ($picked !== null) {
            $label = $this->labelOf($entity, $picked, $ctx);
            if ($label !== null) {
                $states[$slot] = ['state' => 'resolved', 'value' => $picked, 'label' => $label];

                return ['id' => $picked, 'label' => $label];
            }
        }
        if ($text === null) {
            $states[$slot] = $picked === null ? ['state' => 'missing'] : ['state' => 'not_found', 'message' => 'Not found'];

            return null;
        }

        $found = PaletteSearchSources::search($entity, $ctx->company->id, $text);
        $exact = array_values(array_filter($found, fn ($o) => mb_strtolower(trim($o['label'])) === mb_strtolower(trim($text))));
        $hit = count($exact) === 1 ? $exact[0] : (count($found) === 1 ? $found[0] : null);
        if ($hit !== null) {
            $states[$slot] = ['state' => 'resolved', 'value' => $hit['value'], 'label' => $hit['label']];

            return ['id' => $hit['value'], 'label' => $hit['label']];
        }

        $states[$slot] = $found === []
            ? ['state' => 'not_found', 'message' => 'Not found']
            : [
                'state' => 'choices',
                'prompt' => 'Which one?',
                'choices' => array_map(fn ($o) => array_filter(
                    ['value' => $o['value'], 'label' => $o['label'], 'meta' => $o['meta'] ?? null],
                    fn ($v) => $v !== null,
                ), $found),
            ];

        return null;
    }

    private function labelOf(string $entity, string $id, ResolveContext $ctx): ?string
    {
        return self::ownedLabel($entity, $id, $ctx);
    }

    /** True when ownedLabel() can check this entity's ids. */
    public static function checks(string $entity): bool
    {
        return isset(self::TABLES[$entity]);
    }

    /** The record's name when the id is one of the current company's live records, else null. */
    public static function ownedLabel(string $entity, string $id, ResolveContext $ctx): ?string
    {
        if (! isset(self::TABLES[$entity]) || ! Str::isUuid($id)) {
            return null;
        }
        [$table, $column] = self::TABLES[$entity];

        $label = DB::table($table)->where('company_id', $ctx->company->id)->whereNull('deleted_at')->where('id', $id)->value($column);

        return $label === null ? null : (string) $label;
    }
}
