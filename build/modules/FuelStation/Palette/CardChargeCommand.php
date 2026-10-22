<?php

namespace App\Modules\FuelStation\Palette;

use App\Modules\FuelStation\Services\CardChargeService;
use App\Support\Palette\Commands\Money\MoneyCommand;
use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/**
 * The charge a card supplier kept, as an amount or as a % of the card's sales for the month.
 * The cards offered are the ones the card-charge page offers: enabled and set to manual charges.
 */
final class CardChargeCommand extends MoneyCommand
{
    public function name(): string
    {
        return 'card charge';
    }

    public function label(): string
    {
        return 'Card charge';
    }

    public function sentence(): string
    {
        return 'Card charge {channel} {amount} on {date?}';
    }

    public function slots(): array
    {
        // The cards are the company's own, so resolve() lists them; there is nothing fixed to declare.
        return ['channel' => Slot::choice([]), 'amount' => Slot::amount(), 'date' => Slot::date()];
    }

    public function target(): string
    {
        return 'fuel.card_charge.record';
    }

    public function undo(): ?string
    {
        return 'fuel.card_charge.undo';
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $channels = app(CardChargeService::class)->channels($ctx->company, true);
        if ($channels === []) {
            return Resolution::error('Add a card channel first');
        }

        $states = [];
        $channel = $this->channel($in, $channels, $states);
        [$amount, $percent] = $this->charge($in, $states);
        $day = $this->day($in, $ctx, $states);

        if ($pending = $this->unsettled($states, null)) {
            return $pending;
        }

        $params = ['channel_code' => $channel['code'], 'charge_date' => $day];
        if ($percent !== null) {
            $params['percent'] = $percent;
            $card = ['what' => 'Card charge', 'from' => $channel['label'], 'note' => $this->fmt($percent).'% of sales', 'currency' => $ctx->defaults['currency'], 'date' => $day];
            $summary = "Card charge {$this->fmt($percent)}% on {$channel['label']}";
        } else {
            $params['amount'] = $amount;
            $card = $this->card($ctx, $amount, $day, ['what' => 'Card charge', 'from' => $channel['label']]);
            $summary = "Card charge {$this->money($ctx, $amount)} on {$channel['label']}";
        }

        return Resolution::ready('fuel.card_charge.record', $params, $card, $summary);
    }

    /** @return array{code: string, label: string}|null */
    private function channel(SlotInput $in, array $channels, array &$states): ?array
    {
        $text = $in->text('channel');
        $picked = $in->value('channel');

        $match = null;
        if ($picked !== null) {
            $match = collect($channels)->firstWhere('code', $picked);
        }
        if ($match === null && $text !== null) {
            $q = mb_strtolower($text);
            $exact = array_values(array_filter($channels, fn ($c) => mb_strtolower($c['label']) === $q || mb_strtolower($c['code']) === $q));
            $loose = array_values(array_filter($channels, fn ($c) => str_contains(mb_strtolower($c['label'].' '.$c['code']), $q)));
            $hits = count($exact) === 1 ? $exact : $loose;
            if (count($hits) === 1) {
                $match = $hits[0];
            } elseif ($hits === []) {
                $states['channel'] = ['state' => 'not_found', 'message' => 'Not found'];

                return null;
            } else {
                $channels = $hits;
            }
        }
        if ($match !== null) {
            $states['channel'] = ['state' => 'resolved', 'value' => $match['code'], 'label' => $match['label']];

            return ['code' => $match['code'], 'label' => $match['label']];
        }

        $states['channel'] = $channels === []
            ? ['state' => 'missing']
            : [
                'state' => 'choices',
                'prompt' => 'Which card?',
                'choices' => array_map(fn ($c) => ['value' => $c['code'], 'label' => $c['label']], $channels),
            ];

        return null;
    }

    /**
     * `2%` is a share of the card's sales (percent); anything else is an amount.
     *
     * @return array{0: ?float, 1: ?float} amount, percent
     */
    private function charge(SlotInput $in, array &$states): array
    {
        $text = $in->text('amount');
        if ($text !== null && preg_match('/^(\d+(?:\.\d+)?)\s*%$/D', $text, $m)) {
            $percent = (float) $m[1];
            if ($percent <= 0 || $percent > 10) {
                $states['amount'] = ['state' => 'not_found', 'message' => 'Amount needed'];

                return [null, null];
            }
            $states['amount'] = ['state' => 'resolved', 'value' => $percent, 'label' => $text];

            return [null, $percent];
        }

        return [$this->amount($in, $states), null];
    }
}
