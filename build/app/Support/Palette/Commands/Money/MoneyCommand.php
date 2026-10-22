<?php

namespace App\Support\Palette\Commands\Money;

use App\Support\Palette\Commands\AmountParser;
use App\Support\Palette\Commands\DateParser;
use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\SlotInput;
use App\Support\Palette\PaletteSearchSources;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * What the money commands share: reading the amount and date, naming a bank or a
 * cash/bank account (or falling back to the company's), and naming a customer or vendor.
 *
 * resolve() fills $states with one entry per slot it read. A slot that is not resolved
 * leaves the command pending; a problem that is not any one slot's ("Add a bank account
 * first") comes back as an error once every slot is settled.
 */
abstract class MoneyCommand extends PaletteCommand
{
    protected function amount(SlotInput $in, array &$states): ?float
    {
        $text = $in->text('amount');
        if ($text === null) {
            $states['amount'] = ['state' => 'missing'];

            return null;
        }

        $amount = AmountParser::parse($text);
        if ($amount === null) {
            $states['amount'] = ['state' => 'not_found', 'message' => 'Amount needed'];

            return null;
        }
        $states['amount'] = ['state' => 'resolved', 'value' => $amount, 'label' => $text];

        return $amount;
    }

    /** The day it will be booked on: the typed date, else the company's today. */
    protected function day(SlotInput $in, ResolveContext $ctx, array &$states): ?string
    {
        $text = $in->text('date');
        if ($text === null) {
            return $ctx->today;
        }

        $date = DateParser::resolve($text, $ctx->today);
        if (! $date['ok']) {
            $states['date'] = ['state' => 'not_found', 'message' => $date['message']];

            return null;
        }
        $states['date'] = ['state' => 'resolved', 'value' => $date['date'], 'label' => $text];

        return $date['date'];
    }

    /** A named bank, or the company default when none was typed. */
    protected function bank(string $slot, ?string $text, ?string $value, string $direction, ResolveContext $ctx, array &$states, ?string &$error): ?array
    {
        if ($text !== null || $value !== null) {
            return $this->named($slot, 'bank', $ctx->defaults['banks'], $text, $value, $states);
        }

        $fallback = Accounts::bankFallback($ctx->defaults, $direction);
        if ($fallback === null) {
            $error ??= 'Add a bank account first';
        }

        return $fallback;
    }

    /** A named cash or bank account, or where money goes (comes from) when none was typed. */
    protected function settlement(string $slot, ?string $text, ?string $value, string $direction, ResolveContext $ctx, array &$states, ?string &$error): ?array
    {
        if ($text !== null || $value !== null) {
            return $this->named($slot, 'account', array_merge($ctx->defaults['cash'], $ctx->defaults['banks']), $text, $value, $states);
        }

        $fallback = Accounts::settlementFallback($ctx->defaults, $direction);
        if ($fallback === null) {
            $error ??= 'Add a cash or bank account first';
        }

        return $fallback;
    }

    protected function cashDrawer(ResolveContext $ctx, ?string &$error): ?array
    {
        $cash = Accounts::defaultCash($ctx->defaults);
        if ($cash === null) {
            $error ??= 'Add a cash account first';
        }

        return $cash;
    }

    /** @return array{id: string, label: string}|null */
    protected function party(string $slot, string $entity, SlotInput $in, ResolveContext $ctx, array &$states): ?array
    {
        $text = $in->text($slot);
        $picked = $in->value($slot);

        if ($picked !== null) {
            $label = $this->partyName($entity, $picked, $ctx);
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
            ? ['state' => 'not_found', 'message' => "No {$entity} matches \"{$text}\""]
            : [
                'state' => 'choices',
                'prompt' => ucfirst($entity).'s matching "'.$text.'"',
                'choices' => array_map(fn ($o) => array_filter(
                    ['value' => $o['value'], 'label' => $o['label'], 'meta' => $o['meta'] ?? null],
                    fn ($v) => $v !== null,
                ), $found),
            ];

        return null;
    }

    /** Pending while any slot is unsettled, else the command-level error, else null (go ahead). */
    protected function unsettled(array $states, ?string $error): ?Resolution
    {
        foreach ($states as $state) {
            if ($state['state'] !== 'resolved') {
                return Resolution::pending($states);
            }
        }

        return $error !== null ? Resolution::error($error) : null;
    }

    /** `PKR 5,000` or `PKR 250.5`: at most two decimals, none when whole. */
    protected function money(ResolveContext $ctx, float $amount): string
    {
        return $ctx->defaults['currency'].' '.$this->fmt($amount);
    }

    protected function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ','), '0'), '.');
    }

    /** The card every money command shows: its own lines, then the amount, currency and day. */
    protected function card(ResolveContext $ctx, float $amount, string $day, array $lines): array
    {
        return array_merge($lines, ['amount' => $amount, 'currency' => $ctx->defaults['currency'], 'date' => $day]);
    }

    private function named(string $slot, string $noun, array $list, ?string $text, ?string $value, array &$states): ?array
    {
        [$state, $account] = Accounts::pickAccount($noun, $list, $text, $value);
        $states[$slot] = $state;

        return $account;
    }

    private function partyName(string $entity, string $id, ResolveContext $ctx): ?string
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $name = DB::table($entity === 'customer' ? 'acct.customers' : 'acct.vendors')
            ->where('company_id', $ctx->company->id)->whereNull('deleted_at')->where('id', $id)->value('name');

        return $name === null ? null : (string) $name;
    }
}
