<?php

namespace App\Support\Palette\Commands\Money;

/**
 * Picking cash, bank and expense accounts from the quick-entry defaults, as the typed
 * quick entry always did. An account is ['id' => ..., 'code' => ..., 'name' => ...].
 */
final class Accounts
{
    /** Accounts whose code or name contains every word typed. An exact name or code wins outright. */
    public static function matchAccounts(array $list, string $query): array
    {
        $q = self::norm($query);
        if ($q === '') {
            return [];
        }

        $exact = array_values(array_filter($list, fn ($a) => self::norm($a['name']) === $q || self::norm($a['code']) === $q));
        if (count($exact) === 1) {
            return $exact;
        }

        $words = preg_split('/\s+/', $q);

        return array_values(array_filter($list, function ($a) use ($words) {
            $hay = self::norm($a['code'].' '.$a['name']);
            foreach ($words as $w) {
                if (! str_contains($hay, $w)) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Resolves one typed name (or a picked id) against a list.
     *
     * @param  bool  $offerAllWhenNone  when nothing matches, offer the whole list instead of "not found"
     *                                  (a free-text description is not a name)
     * @return array{0: array<string, mixed>, 1: ?array} the slot state and the account when resolved
     */
    public static function pickAccount(string $noun, array $list, ?string $text, ?string $value, bool $offerAllWhenNone = false): array
    {
        if ($value !== null) {
            foreach ($list as $a) {
                if ($a['id'] === $value) {
                    return [self::resolved($a), $a];
                }
            }
        }
        if ($text === null) {
            return [['state' => 'not_found', 'message' => 'Not found'], null];
        }

        $matches = self::matchAccounts($list, $text);
        if (count($matches) === 1) {
            return [self::resolved($matches[0]), $matches[0]];
        }
        if ($matches === []) {
            if ($offerAllWhenNone && $list !== []) {
                return [[
                    'state' => 'choices',
                    'prompt' => "No {$noun} named \"{$text}\". Choose one",
                    'choices' => array_map([self::class, 'option'], $list),
                ], null];
            }

            return [['state' => 'not_found', 'message' => "No {$noun} matches \"{$text}\""], null];
        }

        return [[
            'state' => 'choices',
            'prompt' => ucfirst($noun).'s matching "'.$text.'"',
            'choices' => array_map([self::class, 'option'], $matches),
        ], null];
    }

    public static function option(array $a): array
    {
        return ['value' => $a['id'], 'label' => $a['name'], 'meta' => $a['code']];
    }

    public static function defaultCash(array $d): ?array
    {
        return self::find($d['cash'], $d['default_cash_id'] ?? null) ?? ($d['cash'][0] ?? null);
    }

    public static function defaultBank(array $d): ?array
    {
        return self::find($d['banks'], $d['default_bank_id'] ?? null) ?? ($d['banks'][0] ?? null);
    }

    /** Cash or bank account the money moves through when nothing is set: the cash drawer first. */
    public static function defaultSettlement(array $d): ?array
    {
        return self::defaultCash($d) ?? self::defaultBank($d);
    }

    /** The account chosen in settings for money in (receive, sale) or out (pay, spend), if still usable. */
    public static function configured(array $d, string $direction): ?array
    {
        $id = $direction === 'in' ? ($d['receive_account_id'] ?? null) : ($d['pay_account_id'] ?? null);

        return $id ? self::find(array_merge($d['cash'], $d['banks']), $id) : null;
    }

    /** Where money goes, or comes from, when the line names no account: the setting, else the cash drawer. */
    public static function settlementFallback(array $d, string $direction): ?array
    {
        return self::configured($d, $direction) ?? self::defaultSettlement($d);
    }

    /** Bank for deposit / withdraw / charge when none is named: the setting if it is a bank, else the company default. */
    public static function bankFallback(array $d, string $direction): ?array
    {
        $chosen = self::configured($d, $direction);

        return ($chosen ? self::find($d['banks'], $chosen['id']) : null) ?? self::defaultBank($d);
    }

    /**
     * Names the bank at the front of a charge line; whatever follows is the note.
     *
     * @return array{bank: string, note: ?string}|null
     */
    public static function splitChargeTail(string $tail, array $banks): ?array
    {
        $words = array_values(array_filter(explode(' ', $tail), fn ($w) => $w !== ''));
        for ($n = count($words); $n >= 1; $n--) {
            $candidate = implode(' ', array_slice($words, 0, $n));
            if (self::matchAccounts($banks, $candidate) !== []) {
                $note = trim(implode(' ', array_slice($words, $n)));

                return ['bank' => $candidate, 'note' => $note === '' ? null : $note];
            }
        }

        return null;
    }

    private static function resolved(array $a): array
    {
        return ['state' => 'resolved', 'value' => $a['id'], 'label' => $a['name']];
    }

    private static function find(array $list, ?string $id): ?array
    {
        if ($id === null) {
            return null;
        }
        foreach ($list as $a) {
            if ($a['id'] === $id) {
                return $a;
            }
        }

        return null;
    }

    private static function norm(string $s): string
    {
        return mb_strtolower(trim($s));
    }
}
