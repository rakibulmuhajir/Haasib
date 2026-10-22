<?php

namespace App\Support\Palette\Commands;

use App\Support\Palette\Commands\Core\AddAccountCommand;
use App\Support\Palette\Commands\Core\AddCustomerCommand;
use App\Support\Palette\Commands\Core\AddItemCommand;
use App\Support\Palette\Commands\Core\AddVendorCommand;
use App\Support\Palette\Commands\Core\BalanceCommand;
use App\Support\Palette\Commands\Core\BillCommand;
use App\Support\Palette\Commands\Core\CreditCommand;
use App\Support\Palette\Commands\Core\InvoiceCommand;
use App\Support\Palette\Commands\Core\ReportCommand;
use App\Support\Palette\Commands\Core\VoidBillCommand;
use App\Support\Palette\Commands\Core\VoidInvoiceCommand;
use App\Support\Palette\Commands\Money\BankChargeCommand;
use App\Support\Palette\Commands\Money\DepositCommand;
use App\Support\Palette\Commands\Money\PayCommand;
use App\Support\Palette\Commands\Money\ReceiveCommand;
use App\Support\Palette\Commands\Money\SaleCommand;
use App\Support\Palette\Commands\Money\SpendCommand;
use App\Support\Palette\Commands\Money\TransferCommand;
use App\Support\Palette\Commands\Money\WithdrawCommand;

/** The sentence commands core ships. Modules add theirs through ModuleManifest::paletteCommands(). */
final class CorePaletteCommands
{
    /** @return list<PaletteCommand> */
    public static function all(): array
    {
        return [
            new DepositCommand, new WithdrawCommand, new TransferCommand, new BankChargeCommand,
            new ReceiveCommand, new PayCommand, new SpendCommand, new SaleCommand,
            new AddCustomerCommand, new AddVendorCommand, new AddItemCommand, new AddAccountCommand,
            new InvoiceCommand, new BillCommand, new CreditCommand,
            new BalanceCommand, new ReportCommand, new VoidInvoiceCommand, new VoidBillCommand,
        ];
    }
}
