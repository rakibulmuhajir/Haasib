<?php

namespace App\Support\Palette\Commands\Core;

use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\Resolution;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\Slot;
use App\Support\Palette\Commands\SlotInput;

/**
 * Opens a report page. A query command: the browser opens params.url instead of posting.
 * The pages are the named routes under /{company}/reports/* in routes/web.php.
 */
final class ReportCommand extends PaletteCommand
{
    /** @var array<string, string> label => route name */
    public const REPORTS = [
        'Profit and loss' => 'reports.profit-loss',
        'Trial balance' => 'reports.trial-balance',
        'Balance sheet' => 'reports.balance-sheet',
        'Receivables aging' => 'reports.receivables-aging',
        'Payables aging' => 'reports.payables-aging',
        'Statements' => 'reports.statements',
        'Withholding tax' => 'reports.withholding-tax',
    ];

    public function name(): string
    {
        return 'report';
    }

    public function label(): string
    {
        return 'Open a report';
    }

    public function sentence(): string
    {
        return 'Report {report}';
    }

    public function slots(): array
    {
        return ['report' => Slot::choice(array_keys(self::REPORTS))];
    }

    public function target(): string
    {
        return 'report.view';
    }

    public function query(): bool
    {
        return true;
    }

    public function resolve(SlotInput $in, ResolveContext $ctx): Resolution
    {
        $text = $in->value('report') ?? $in->text('report');
        if ($text === null) {
            return Resolution::pending(['report' => ['state' => 'missing']]);
        }

        $wanted = mb_strtolower(trim($text));
        $matches = array_values(array_filter(
            array_keys(self::REPORTS),
            fn ($label) => mb_strtolower($label) === $wanted,
        ));
        if ($matches === []) {
            $words = preg_split('/\s+/', $wanted);
            $matches = array_values(array_filter(
                array_keys(self::REPORTS),
                function ($label) use ($words) {
                    $hay = mb_strtolower($label);
                    foreach ($words as $w) {
                        if (! str_contains($hay, $w)) {
                            return false;
                        }
                    }

                    return true;
                },
            ));
        }

        if ($matches === []) {
            return Resolution::pending(['report' => ['state' => 'not_found', 'message' => 'Not found']]);
        }
        if (count($matches) > 1) {
            return Resolution::pending(['report' => [
                'state' => 'choices',
                'prompt' => 'Which report?',
                'choices' => array_map(fn ($label) => ['value' => $label, 'label' => $label], $matches),
            ]]);
        }

        $label = $matches[0];
        $url = route(self::REPORTS[$label], ['company' => $ctx->company->slug], false);

        return Resolution::ready(
            'report.open',
            ['url' => $url],
            ['what' => $label, 'currency' => $ctx->defaults['currency']],
            $label,
        );
    }
}
