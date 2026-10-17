<?php

namespace App\Support\Palette;

use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Transaction;
use App\Services\CommandBus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Dry-runs a posting command and reports the journal lines it would write.
 *
 * The command runs for real, through CommandBus (so validation, permissions, period and
 * daily-close locks are the action's own), inside a database transaction that is ALWAYS rolled
 * back, success or not. Nothing is therefore stored: rows, document numbers and their counters
 * all live in the database and go with the rollback.
 *
 * Only commands on the allow-list below are previewed. Each one was checked for work outside the
 * database (mail, queues, broadcasts, HTTP, cache counters, files) and has none. Anything that
 * later gains such work must either check PalettePreview::active() and skip it, or leave the list.
 */
final class PalettePreview
{
    /**
     * Previewable commands -> what the user is told when they lack the permission.
     * Keep in step with the grep in docs/superpowers/plans/2026-10-16-palette-daily-transactions.md.
     *
     * @var array<string, string>
     */
    public const COMMANDS = [
        'bank_transaction.create' => "You can't record bank transactions",
        'payment.create' => "You can't record customer payments",
        'bill_payment.create' => "You can't record vendor payments",
        'expense.create' => "You can't record expenses",
        'sale.create' => "You can't record sales",
    ];

    /** Permission key -> refusal text, for the permission the action reports. */
    private const PERMISSION_MESSAGES = [
        'bank_account.update' => "You can't record bank transactions",
        'payment.create' => "You can't record customer payments",
        'bill.pay' => "You can't record vendor payments",
        'expense.create' => "You can't record expenses",
        'invoice.create' => "You can't record sales",
    ];

    private static bool $active = false;

    /** @var list<string> */
    private static array $captured = [];

    /** The event dispatcher our listener was registered on (a new one means register again). */
    private static ?int $listeningOn = null;

    /** True while a preview is running. Actions and services skip outside-the-database work when it is. */
    public static function active(): bool
    {
        return self::$active;
    }

    /**
     * @return array{ok: bool, lines?: list<array>, balances?: list<array>, summary?: array, message?: string, errors?: array<string, list<string>>, code?: string}
     */
    public static function run(string $key, array $params, $user): array
    {
        if (! array_key_exists($key, self::COMMANDS)) {
            return self::refuse('NOT_PREVIEWABLE', 'No preview for this command');
        }

        $bus = app(CommandBus::class);
        if (! $bus->has($key)) {
            return self::refuse('NOT_FOUND', "Unknown command: {$key}");
        }
        if (! $user) {
            return self::refuse('UNAUTHORIZED', 'Sign in to continue');
        }
        $company = CompanyContext::getCompany();
        if (! $company) {
            return self::refuse('BAD_REQUEST', 'Choose a company first');
        }

        // Permission first, so a missing one is never hidden behind "amount is required".
        $needed = $bus->permissionFor($key);
        if ($needed && method_exists($user, 'hasCompanyPermission') && ! $user->hasCompanyPermission($needed)) {
            return self::refuse('FORBIDDEN', self::permissionMessage($key, "Permission denied: {$needed}"));
        }

        self::listen();
        $level = DB::transactionLevel();
        DB::beginTransaction();
        self::$active = true;
        self::$captured = [];

        try {
            $result = $bus->dispatch($key, $params, $user);

            return self::report($company->id, $result);
        } catch (ValidationException $e) {
            return self::refuse('VALIDATION', self::firstMessage($e->errors()) ?? 'Check the entry', $e->errors());
        } catch (AuthorizationException $e) {
            return self::refuse('FORBIDDEN', self::permissionMessage($key, $e->getMessage()));
        } catch (ModelNotFoundException) {
            return self::refuse('NOT_FOUND', 'Record not found');
        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning("Palette preview query failed: {$key}", ['error' => $e->getMessage()]);

            return self::refuse('SERVER_ERROR', "Couldn't preview this");
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            // Actions throw these with a message written for the person at the keyboard.
            return self::refuse('REFUSED', $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning("Palette preview failed: {$key}", ['error' => $e->getMessage()]);

            return self::refuse('SERVER_ERROR', app()->environment('production') ? "Couldn't preview this" : $e->getMessage());
        } finally {
            self::$active = false;
            self::$captured = [];
            // Always: a successful dry run is rolled back exactly like a failed one.
            DB::rollBack($level);
        }
    }

    private static function listen(): void
    {
        $dispatcher = spl_object_id(Event::getFacadeRoot());
        if (self::$listeningOn === $dispatcher) {
            return;
        }
        self::$listeningOn = $dispatcher;
        Event::listen('eloquent.created: '.Transaction::class, function ($transaction) {
            if (self::$active) {
                self::$captured[] = $transaction->id;
            }
        });
    }

    private static function report(string $companyId, array $result): array
    {
        $ids = array_values(array_unique(self::$captured));

        $entries = $ids === [] ? collect() : DB::table('acct.journal_entries as je')
            ->join('acct.accounts as a', 'a.id', '=', 'je.account_id')
            ->whereIn('je.transaction_id', $ids)
            ->orderBy('je.line_number')
            ->get(['je.transaction_id', 'je.account_id', 'je.debit_amount', 'je.credit_amount', 'a.code', 'a.name', 'a.normal_balance']);

        $lines = [];
        $delta = [];
        $debitTotal = 0.0;
        $creditTotal = 0.0;
        foreach ($ids as $id) {
            foreach ($entries->where('transaction_id', $id) as $e) {
                $debit = round((float) $e->debit_amount, 2);
                $credit = round((float) $e->credit_amount, 2);
                $isDebit = $debit > 0;
                $lines[] = [
                    'side' => $isDebit ? 'debit' : 'credit',
                    'account_code' => $e->code,
                    'account_name' => $e->name,
                    'amount' => $isDebit ? $debit : $credit,
                ];
                $debitTotal += $debit;
                $creditTotal += $credit;
                $signed = $e->normal_balance === 'credit' ? $credit - $debit : $debit - $credit;
                $delta[$e->account_id] = [
                    'name' => $e->name,
                    'code' => $e->code,
                    'delta' => round(($delta[$e->account_id]['delta'] ?? 0) + $signed, 2),
                    'normal' => $e->normal_balance,
                ];
            }
        }

        $balances = [];
        foreach ($delta as $accountId => $d) {
            if (abs($d['delta']) < 0.005) {
                continue; // e.g. receivables on a cash sale: in and out in the same entry
            }
            $after = self::balance($companyId, $accountId, $d['normal']);
            $balances[] = [
                'account_name' => $d['name'],
                'before' => round($after - $d['delta'], 2),
                'after' => $after,
            ];
        }

        return [
            'ok' => true,
            'lines' => $lines,
            'balances' => $balances,
            'summary' => [
                'transactions' => count($ids),
                'debit_total' => round($debitTotal, 2),
                'credit_total' => round($creditTotal, 2),
                'message' => $result['message'] ?? null,
            ],
        ];
    }

    /** Ledger balance in the account's own direction, from every posted or locked line (inside the dry-run transaction). */
    private static function balance(string $companyId, string $accountId, ?string $normal): float
    {
        $row = DB::table('acct.journal_entries as je')
            ->join('acct.transactions as t', 't.id', '=', 'je.transaction_id')
            ->where('t.company_id', $companyId)
            ->whereIn('t.status', ['posted', 'locked'])
            ->where('je.account_id', $accountId)
            ->selectRaw('COALESCE(SUM(je.debit_amount),0) as d, COALESCE(SUM(je.credit_amount),0) as c')
            ->first();

        $d = (float) ($row->d ?? 0);
        $c = (float) ($row->c ?? 0);

        return round($normal === 'credit' ? $c - $d : $d - $c, 2);
    }

    private static function permissionMessage(string $key, string $raw): string
    {
        if (preg_match('/Permission denied: (\S+)/', $raw, $m) && isset(self::PERMISSION_MESSAGES[$m[1]])) {
            return self::PERMISSION_MESSAGES[$m[1]];
        }

        return self::COMMANDS[$key] ?? "You can't do this";
    }

    /** @param array<string, list<string>> $errors */
    private static function firstMessage(array $errors): ?string
    {
        foreach ($errors as $messages) {
            foreach ((array) $messages as $message) {
                return $message;
            }
        }

        return null;
    }

    /** @param array<string, list<string>>|null $errors */
    private static function refuse(string $code, string $message, ?array $errors = null): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message, 'errors' => $errors ?? ['_' => [$message]]];
    }
}
