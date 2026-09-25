<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import MoneyText from '@/components/MoneyText.vue';
import PageShell from '@/components/PageShell.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { formatDateTime as formatSharedDateTime } from '@/lib/datetime';
import { formatMoneyText } from '@/lib/money';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    AlertCircle,
    ArrowDownRight,
    Calculator,
    CheckCircle,
    Droplets,
    FileWarning,
    Fuel,
    Loader2,
    Plus,
    RotateCcw,
    Save,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DailyCloseNav from '../../../components/DailyCloseNav.vue';
import CreditSalesEntry from '../../../components/CreditSalesEntry.vue';
import PaymentsReceivedEntry from '../../../components/PaymentsReceivedEntry.vue';
import { useLexicon } from '@/composables/useLexicon';
import TankLevelGauge from '../../../components/TankLevelGauge.vue';

interface FuelItem {
    id: string;
    name: string;
    fuel_category: string;
    avg_cost: number;
    sale_price: number;
}

const { t } = useLexicon();

interface Tank {
    id: string;
    code: string;
    name: string;
    capacity: number;
    linked_item_id: string;
    dip_stick_id: string | null;
    linked_item?: { id: string; name: string; fuel_category: string };
    dip_stick?: { id: string; code: string; name: string; unit: string };
    current_stock_liters?: number | null;
    current_stock_source_label?: string | null;
    current_stock_as_of?: string | null;
    current_stock_after_close_date?: boolean;
    stock_movements_since_baseline_liters?: number | null;
    pending_delivery_liters?: number | null;
    pending_deliveries?: Array<{
        bill_id: string;
        bill_number: string;
        bill_date: string;
        litres: number;
    }>;
}

interface Pump {
    id: string;
    name: string;
    tank_id: string;
    current_meter_reading: number;
    nozzle_count: number;
    tank?: { id: string; name: string; linked_item_id: string };
}

interface Nozzle {
    id: string;
    code: string;
    label: string | null;
    pump_id: string;
    pump_name: string | null;
    tank_id: string;
    tank_name: string | null;
    item_id: string;
    fuel_name: string | null;
    fuel_category: string | null;
    has_electronic_meter: boolean;
    opening_reading: number;
    opening_manual: number | null;
    sale_rate: number;
}

interface Partner {
    id: string;
    name: string;
    drawing_limit_period: string;
    drawing_limit_amount: number | null;
    current_period_withdrawn: number;
    remaining_drawing_limit: number | null;
    total_invested: number;
    total_withdrawn: number;
    net_capital: number;
}

interface Employee {
    id: string;
    first_name: string;
    last_name: string;
    full_name: string;
    position: string;
    base_salary: number;
    outstanding_advances: number;
}

interface PayrollPayout {
    payslip_id: string;
    payslip_number: string;
    employee_id: string;
    employee_name: string;
    employee_number: string | null;
    amount: number;
    approved_at: string | null;
}

interface AmanatHolder {
    id: string;
    name: string;
    phone: string | null;
    amanat_balance: number;
}

interface Investor {
    id: string;
    name: string;
    total_invested: number;
    outstanding_commission: number;
    units_remaining: number;
}

interface BankAccount {
    id: string;
    code: string;
    name: string;
}

interface ExpenseAccount {
    id: string;
    code: string;
    name: string;
}

interface OtherDepositAccount {
    id: string;
    code: string;
    name: string;
    type: string;
}

interface LubricantItem {
    id: string;
    name: string;
    sku: string;
    brand: string | null;
    unit: string;
    sale_price: number;
}

interface PaymentChannel {
    code: string;
    label: string;
    type: 'cash' | 'bank_transfer' | 'card_pos' | 'fuel_card' | 'mobile_wallet';
    enabled: boolean;
    bank_account_id: string | null;
    clearing_account_id: string | null;
}

interface RateChangeSnapshot {
    id: string;
    item_id: string;
    item_name: string | null;
    effective_date: string;
    old_sale_rate: number;
    new_sale_rate: number;
    snapshot_dip_liters: number | null;
    snapshot_nozzle_count: number;
    snapshot_nozzle_readings: Array<{
        nozzle_id: string;
        electronic_reading: number | null;
        manual_reading: number | null;
    }>;
}

interface PendingBillPayment {
    payment_id: string;
    payment_number: string;
    payment_group_number: string | null;
    vendor_id: string;
    vendor_name: string;
    payment_account_id: string;
    payment_account_name: string;
    payment_account_subtype: string | null;
    payment_method: string;
    amount: number;
    currency: string;
    reference_number: string | null;
    bill_numbers: string[];
    affects_cash_drawer: boolean;
}

interface PendingFuelInvoice {
    invoice_id: string;
    invoice_number: string;
    customer_id: string;
    customer_name: string;
    litres: number;
    amount: number;
    reference: string;
}

/** A plain Accounting -> Invoices invoice on a fuel revenue account, pre-loaded the same way
 * as PendingFuelInvoice -- see DailyCloseCreditSaleService::pendingAccountingInvoiceDetails. */
interface PendingAccountingInvoice {
    invoice_id: string;
    invoice_number: string;
    customer_id: string;
    customer_name: string;
    amount: number;
    reference: string;
}

interface OpenInvoice {
    id: string;
    invoice_number: string;
    customer_id: string;
    customer_name: string;
    balance: number;
    currency: string;
}

interface PurchaseSupplier {
    id: string;
    name: string;
}

interface PurchaseItem {
    id: string;
    name: string;
    is_fuel: boolean;
    unit: string;
}

interface Features {
    has_partners: boolean;
    has_amanat: boolean;
    has_lubricant_sales: boolean;
    has_investors: boolean;
    dual_meter_readings: boolean;
}

const props = defineProps<{
    company: { id: string; name: string; slug: string; base_currency: string };
    parkedDraft?: Record<string, any> | null;
    canonicalActivity?: Array<{
        id: string;
        type: string;
        reference: string;
        business_date: string;
        amount: number;
        money_in: number;
        money_out: number;
        cash_effect: number;
    }>;
    date: string;
    fuelItems: FuelItem[];
    rates: Record<string, { purchase_rate: number; sale_rate: number }>;
    rateChangeSnapshots: RateChangeSnapshot[];
    tanks: Tank[];
    pumps: Pump[];
    nozzles: Nozzle[];
    partners: Partner[];
    employees: Employee[];
    approvedPayrollPayouts: PayrollPayout[];
    pendingBillPayments: PendingBillPayment[];
    pendingFuelInvoices?: PendingFuelInvoice[];
    pendingAccountingInvoices?: PendingAccountingInvoice[];
    unpaidDirectDeliveries?: Array<{ id: string; invoice_number: string; customer_name: string | null; balance: number }>;
    openInvoices?: OpenInvoice[];
    cashAccountIds?: string[];
    purchaseSuppliers?: PurchaseSupplier[];
    purchaseItems?: PurchaseItem[];
    canEnterPurchases?: boolean;
    amanatHolders: AmanatHolder[];
    investors: Investor[];
    bankAccounts: BankAccount[];
    paymentAccounts: BankAccount[];
    expenseAccounts: ExpenseAccount[];
    otherDepositAccounts: OtherDepositAccount[];
    lubricantItems: LubricantItem[];
    existingTankReadings: any[];
    previousTankReadings: Array<{
        tank_id: string;
        liters: number;
        stick_reading: number;
        source?: string | null;
        source_label?: string | null;
        as_of?: string | null;
    }>;
    previousClose: {
        date: string | null;
        closing_cash: number;
        exists: boolean;
        source?: string;
    };
    paymentChannels: PaymentChannel[];
    features: Features;
    fuelVendor: string;
    fuelCardLabel: string;
    canFillTestData?: boolean;
    isAmendment?: boolean;
    originalTransaction?: {
        id: string;
        transaction_number: string;
        metadata: Record<string, unknown>;
    } | null;
    originalFormData?: {
        nozzle_readings?: Array<{
            nozzle_id: string;
            item_id: string;
            opening_electronic: number;
            closing_electronic: number;
            opening_manual?: number;
            closing_manual?: number;
            liters_sold: number;
            sale_rate: number;
        }>;
        other_sales?: Array<{
            item_id: string;
            item_name: string;
            quantity: number;
            unit_price: number;
            amount: number;
        }>;
        tank_readings?: Array<{
            tank_id: string;
            stick_reading: number;
            liters: number;
        }>;
        opening_cash?: number;
        closing_cash?: number;
        partner_deposits?: Array<{ partner_id: string; amount: number }>;
        amanat_deposits?: Array<{
            customer_id?: string;
            customer_name?: string;
            amount: number;
            payment_account_id?: string;
            reference?: string;
        }>;
        other_deposits?: Array<{
            deposit_type: string;
            account_id?: string;
            description?: string;
            amount: number;
        }>;
        payment_receipts?: Record<
            string,
            {
                entries: Array<{
                    reference?: string;
                    last_four?: string;
                    amount: number;
                }>;
            }
        >;
        bank_withdrawals?: Array<{ bank_account_id: string; amount: number; reference?: string; purpose?: string }>;
        bank_deposits?: Array<{
            bank_account_id: string;
            amount: number;
            reference?: string;
            purpose?: string;
        }>;
        partner_withdrawals?: Array<{ partner_id: string; amount: number }>;
        employee_advances?: Array<{
            employee_id: string;
            amount: number;
            reason?: string;
        }>;
        payroll_payouts?: PayrollPayout[];
        bill_payments?: PendingBillPayment[];
        amanat_disbursements?: Array<{
            customer_id?: string;
            customer_name?: string;
            amount: number;
            payment_account_id?: string;
        }>;
        expenses?: Array<{
            account_id: string;
            description: string;
            amount: number;
        }>;
        notes?: string;
    };
}>();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: `/${props.company.slug}` },
    { title: 'Fuel', href: `/${props.company.slug}/fuel/dashboard` },
    { title: 'Daily Close', href: `/${props.company.slug}/fuel/daily-close` },
]);

const activeTab = ref('sales');
const currencyCode = computed(() => props.company.base_currency || 'PKR');
const partnerSearch = ref('');
const investorSearch = ref('');

// Numeric fields start at zero for the accounting calculations. Selecting a
// zero on focus lets the first typed digit replace it, so operators can enter
// readings directly without a manual delete/backspace first.
const selectZeroValue = (event: FocusEvent) => {
    const input = event.target as HTMLInputElement | null;
    if (input?.value === '0') input.select();
};

const accountingHints = {
    fuelSales:
        'Posting: Dr Cash/Bank/Clearing · Cr Fuel Sales. Cost also posts Dr Fuel COGS · Cr Fuel Inventory.',
    partnerDeposit: 'Posting: Dr Cash on Hand · Cr Partner Deposits.',
    amanatDeposit:
        'Posting: Dr Cash on Hand · Cr Amanat Deposits, and the depositor balance is increased.',
    otherDeposit:
        'Posting: Dr Cash on Hand · Cr selected income/liability account.',
    nonCashReceipt: 'Posting: Dr destination bank/clearing · Cr Fuel Sales.',
    bankDeposit: 'Posting: Dr Bank · Cr Cash on Hand.',
    partnerWithdrawal: 'Posting: Dr Partner Drawings · Cr Cash on Hand.',
    employeeAdvance: 'Posting: Dr Employee Advances · Cr Cash on Hand.',
    payrollPayout: 'Posting: Dr Payroll Payable · Cr Cash on Hand.',
    billPayment:
        'Posting: Dr Accounts Payable · Cr selected payment account. Cash account payments reduce drawer cash; bank/fuel-card payments do not.',
    amanatDisbursement:
        'Posting: Dr Amanat Deposits · Cr Cash on Hand, and the depositor balance is reduced.',
    expense: 'Posting: Dr selected expense · Cr Cash on Hand.',
    variance: 'Cash difference posts to Cash Over/Short.',
};

// Amendment mode
const isAmendmentMode = computed(
    () => props.isAmendment && props.originalTransaction !== null,
);
const amendmentReason = ref('');

// localStorage draft management
const currentDraftDate = ref(props.date);
const DRAFT_KEY = computed(
    () => `daily-close-draft-${props.company.id}-${currentDraftDate.value}`,
);
const showDraftRestoreDialog = ref(false);
const hasDraft = ref(false);
const draftTimestamp = ref<string | null>(null);
const suppressDateChange = ref(false);

// Check if form has meaningful data worth saving
const hasFormData = (formData: Record<string, unknown>): boolean => {
    if ((formData.bank_withdrawals as unknown[] | undefined)?.length) return true;
    // Check nozzle readings - any closing reading entered
    const nozzleReadings = formData.nozzle_readings as
        | Array<{ closing_electronic: number }>
        | undefined;
    if (nozzleReadings?.some((r) => r.closing_electronic > 0)) return true;

    // Check other sales (lubricants)
    const otherSales = formData.other_sales as
        | Array<{ amount: number }>
        | undefined;
    if (otherSales && otherSales.length > 0) return true;

    // Check tank readings
    const tankReadings = formData.tank_readings as
        | Array<{ stick_reading: number; liters: number }>
        | undefined;
    if (tankReadings?.some((t) => t.stick_reading > 0 || t.liters > 0))
        return true;

    // Check partner deposits
    const partnerDeposits = formData.partner_deposits as
        | Array<{ amount: number }>
        | undefined;
    if (partnerDeposits && partnerDeposits.length > 0) return true;

    const amanatDeposits = formData.amanat_deposits as
        | Array<{ amount: number }>
        | undefined;
    if (amanatDeposits && amanatDeposits.length > 0) return true;

    const otherDeposits = formData.other_deposits as
        | Array<{ amount: number }>
        | undefined;
    if (otherDeposits && otherDeposits.length > 0) return true;

    // Check payment receipts
    const paymentReceipts = formData.payment_receipts as
        | Record<string, { entries: Array<{ amount: number }> }>
        | undefined;
    if (paymentReceipts) {
        for (const channelCode of Object.keys(paymentReceipts)) {
            if (paymentReceipts[channelCode]?.entries?.length > 0) return true;
        }
    }

    // Check bank deposits
    if ((formData.credit_sales as unknown[] | undefined)?.length) return true;
    const bankDeposits = formData.bank_deposits as
        | Array<{ amount: number }>
        | undefined;
    if (bankDeposits && bankDeposits.length > 0) return true;

    // Check partner withdrawals
    const partnerWithdrawals = formData.partner_withdrawals as
        | Array<{ amount: number }>
        | undefined;
    if (partnerWithdrawals && partnerWithdrawals.length > 0) return true;

    // Check employee advances
    const employeeAdvances = formData.employee_advances as
        | Array<{ amount: number }>
        | undefined;
    if (employeeAdvances && employeeAdvances.length > 0) return true;

    const payrollPayouts = formData.payroll_payouts as
        | Array<{ amount: number }>
        | undefined;
    if (payrollPayouts && payrollPayouts.length > 0) return true;

    // Check amanat disbursements
    const amanatDisbursements = formData.amanat_disbursements as
        | Array<{ amount: number }>
        | undefined;
    if (amanatDisbursements && amanatDisbursements.length > 0) return true;

    // Check expenses
    const expenses = formData.expenses as Array<{ amount: number }> | undefined;
    if (expenses && expenses.length > 0) return true;

    // Check closing cash
    const closingCash = formData.closing_cash as number | undefined;
    if (closingCash && closingCash > 0) return true;

    // Check notes
    const notes = formData.notes as string | undefined;
    if (notes && notes.trim().length > 0) return true;

    return false;
};

// Check for existing draft for a specific date
const checkForDraft = (date: string) => {
    if (isAmendmentMode.value) return;

    const draftKey = `daily-close-draft-${props.company.id}-${date}`;
    const savedDraft = localStorage.getItem(draftKey);

    if (savedDraft) {
        try {
            const parsed = JSON.parse(savedDraft);
            // Only show restore dialog if draft has meaningful data
            if (parsed.formData && hasFormData(parsed.formData)) {
                hasDraft.value = true;
                draftTimestamp.value = parsed.savedAt;
                showDraftRestoreDialog.value = true;
            } else {
                // Draft exists but has no meaningful data - remove it
                localStorage.removeItem(draftKey);
                hasDraft.value = false;
                draftTimestamp.value = null;
            }
        } catch {
            localStorage.removeItem(draftKey);
            hasDraft.value = false;
            draftTimestamp.value = null;
        }
    } else {
        hasDraft.value = false;
        draftTimestamp.value = null;
    }
};

// Check for existing draft on mount
onMounted(() => {
    if (!props.parkedDraft) checkForDraft(props.date);
});

// Auto-save draft every 30 seconds
let draftSaveInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    if (!isAmendmentMode.value) {
        draftSaveInterval = setInterval(saveDraft, 30000);
    }
});

onUnmounted(() => {
    if (draftSaveInterval) {
        clearInterval(draftSaveInterval);
    }
});

const saveDraft = () => {
    if (isAmendmentMode.value) return;

    const formData = form.data();

    // Only save if there's meaningful data
    if (!hasFormData(formData)) {
        // Remove any existing empty draft
        localStorage.removeItem(DRAFT_KEY.value);
        return;
    }

    const draftData = {
        savedAt: new Date().toISOString(),
        formData: formData,
    };
    localStorage.setItem(DRAFT_KEY.value, JSON.stringify(draftData));
};

/**
 * A tank row built from what the server says now: opening dip, deliveries since it, pending
 * deliveries. Only stick_reading and liters are typed by hand.
 */
const tankRowFromProps = (tank: (typeof props.tanks)[number]) => {
    const prevReading = props.previousTankReadings?.find(
        (r) => r.tank_id === tank.id,
    );
    return {
        tank_id: tank.id,
        item_id: tank.linked_item_id,
        tank_name: tank.name,
        tank_code: tank.code,
        capacity: tank.capacity,
        fuel_name: tank.linked_item?.name || '',
        fuel_category: tank.linked_item?.fuel_category || '',
        dip_stick_code: tank.dip_stick?.code || '',
        previous_liters: prevReading?.liters ?? 0,
        previous_stick: prevReading?.stick_reading ?? 0,
        previous_source: prevReading?.source ?? '',
        previous_source_label: prevReading?.source_label ?? '',
        previous_as_of: prevReading?.as_of ?? '',
        current_stock_liters: tank.current_stock_liters ?? null,
        current_stock_source_label: tank.current_stock_source_label ?? '',
        current_stock_as_of: tank.current_stock_as_of ?? '',
        current_stock_after_close_date:
            tank.current_stock_after_close_date ?? false,
        stock_movements_since_baseline_liters:
            tank.stock_movements_since_baseline_liters ?? 0,
        pending_delivery_liters: tank.pending_delivery_liters ?? 0,
        pending_deliveries: tank.pending_deliveries ?? [],
        stick_reading: 0,
        liters: 0,
    };
};

/**
 * After restoring a draft, bring every tank's server figures up to date and keep only what
 * was typed. A draft saved before a delivery was received still said 0 L delivered, and the
 * dip then showed that delivery as a gain.
 */
const refreshTankFacts = () => {
    const typed = new Map(
        (form.tank_readings || []).map((row: any) => [row.tank_id, row]),
    );
    form.tank_readings = props.tanks.map((tank) => {
        const row = tankRowFromProps(tank);
        const saved: any = typed.get(tank.id);
        return saved
            ? { ...row, stick_reading: saved.stick_reading, liters: saved.liters }
            : row;
    });
};

/**
 * "Received in cash" on a direct delivery: records the customer payment into the cash drawer for
 * this business day, so the close counts it as money in instead of showing an overage.
 */
const receivingDirectCash = ref<string | null>(null);
const receiveDirectDeliveryCash = (invoiceId: string) => {
    receivingDirectCash.value = invoiceId;
    router.post(
        `/${props.company.slug}/fuel/daily-close/direct-deliveries/${invoiceId}/cash`,
        { date: form.date },
        {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                receivingDirectCash.value = null;
            },
        },
    );
};

const restoreDraft = () => {
    const savedDraft = localStorage.getItem(DRAFT_KEY.value);
    if (savedDraft) {
        try {
            const parsed = JSON.parse(savedDraft);
            const savedData = parsed.formData;

            // Restore form data
            Object.keys(savedData).forEach((key) => {
                if (key in form) {
                    (form as any)[key] = savedData[key];
                }
            });
            refreshTankFacts();

            toast.success('Draft restored', {
                description: 'Your previous work has been loaded',
            });
        } catch {
            toast.error('Failed to restore draft');
        }
    }
    showDraftRestoreDialog.value = false;
};

const discardDraft = () => {
    localStorage.removeItem(DRAFT_KEY.value);
    showDraftRestoreDialog.value = false;
    hasDraft.value = false;
};

const clearDraftOnSuccess = () => {
    localStorage.removeItem(DRAFT_KEY.value);
};

// Get enabled payment channels
const enabledChannels = computed(() => {
    return (props.paymentChannels || []).filter((ch) => ch.enabled);
});

const searchMatches = (
    value: string | null | undefined,
    query: string,
): boolean => {
    return (value || '').toLowerCase().includes(query.trim().toLowerCase());
};

const filteredPartners = computed(() =>
    props.partners.filter((partner) =>
        searchMatches(partner.name, partnerSearch.value),
    ),
);
const filteredInvestors = computed(() =>
    props.investors.filter((investor) =>
        searchMatches(investor.name, investorSearch.value),
    ),
);

// A deleted pump can leave legacy nozzle rows behind. Never surface those rows
// as an unnamed pump in a new daily close.
const configuredNozzles = computed(() =>
    props.nozzles.filter((nozzle) =>
        Boolean(nozzle.pump_id && nozzle.pump_name?.trim()),
    ),
);

// Channels grouped by type for UI sections
const bankTransferChannels = computed(() =>
    enabledChannels.value.filter((ch) => ch.type === 'bank_transfer'),
);
const cardPosChannels = computed(() =>
    enabledChannels.value.filter((ch) => ch.type === 'card_pos'),
);
const fuelCardChannels = computed(() =>
    enabledChannels.value.filter((ch) => ch.type === 'fuel_card'),
);
const mobileWalletChannels = computed(() =>
    enabledChannels.value.filter((ch) => ch.type === 'mobile_wallet'),
);

// Group nozzles by pump for display
const nozzlesByPump = computed(() => {
    const grouped: Record<
        string,
        {
            pump_id: string;
            pump_name: string;
            fuel_name: string;
            fuel_category: string;
            nozzle_indices: number[];
        }
    > = {};
    form.nozzle_readings.forEach((reading, index) => {
        const pumpId = reading.pump_id;
        if (!grouped[pumpId]) {
            grouped[pumpId] = {
                pump_id: pumpId,
                pump_name: reading.pump_name as string,
                fuel_name: reading.fuel_name || '',
                fuel_category: reading.fuel_category || '',
                nozzle_indices: [],
            };
        }
        grouped[pumpId].nozzle_indices.push(index);
    });
    return Object.values(grouped);
});

// Get pump total liters and amount
const getPumpTotalLiters = (nozzleIndices: number[]) => {
    return nozzleIndices.reduce(
        (sum, idx) => sum + form.nozzle_readings[idx].liters_sold,
        0,
    );
};

const getPumpTotalAmount = (nozzleIndices: number[]) => {
    return nozzleIndices.reduce((sum, idx) => {
        const r = form.nozzle_readings[idx];
        return sum + rateAdjustedNozzleRevenue(r);
    }, 0);
};

// Form data
const form = useForm({
    date: props.date,
    // Empty means the usual - 08:00 the morning after - and the server fills that in.

    // Tab 1: Nozzle readings (each nozzle has electronic + optional manual readings)
    nozzle_readings: configuredNozzles.value.map((nozzle) => ({
        nozzle_id: nozzle.id,
        nozzle_code: nozzle.code,
        nozzle_label: nozzle.label,
        item_id: nozzle.item_id,
        fuel_name: nozzle.fuel_name,
        fuel_category: nozzle.fuel_category,
        pump_id: nozzle.pump_id,
        pump_name: nozzle.pump_name,
        has_electronic_meter: nozzle.has_electronic_meter,
        opening_electronic: nozzle.opening_reading,
        closing_electronic: 0,
        meter_rolled_over: false,
        opening_manual: nozzle.opening_manual ?? null,
        closing_manual: null as number | null,
        liters_sold: 0,
        sale_rate: nozzle.sale_rate,
    })),

    other_sales: [] as {
        item_id: string;
        item_name: string;
        quantity: number;
        unit_price: number;
        amount: number;
    }[],

    // Tab 2: Tank readings - include previous day's closing for variance calculation
    tank_readings: props.tanks.map((tank) => {
        const prevReading = props.previousTankReadings?.find(
            (r) => r.tank_id === tank.id,
        );
        return {
            tank_id: tank.id,
            item_id: tank.linked_item_id,
            tank_name: tank.name,
            tank_code: tank.code,
            capacity: tank.capacity,
            fuel_name: tank.linked_item?.name || '',
            fuel_category: tank.linked_item?.fuel_category || '',
            dip_stick_code: tank.dip_stick?.code || '',
            previous_liters: prevReading?.liters ?? 0,
            previous_stick: prevReading?.stick_reading ?? 0,
            previous_source: prevReading?.source ?? '',
            previous_source_label: prevReading?.source_label ?? '',
            previous_as_of: prevReading?.as_of ?? '',
            current_stock_liters: tank.current_stock_liters ?? null,
            current_stock_source_label: tank.current_stock_source_label ?? '',
            current_stock_as_of: tank.current_stock_as_of ?? '',
            current_stock_after_close_date:
                tank.current_stock_after_close_date ?? false,
            stock_movements_since_baseline_liters:
                tank.stock_movements_since_baseline_liters ?? 0,
            pending_delivery_liters: tank.pending_delivery_liters ?? 0,
            pending_deliveries: tank.pending_deliveries ?? [],
            stick_reading: 0,
            liters: 0,
        };
    }),

    // Tab 3: Money In - Dynamic payment receipts
    opening_cash: props.previousClose.closing_cash || 0,
    payments_received: [] as {
        customer_id: string;
        customer_name: string;
        invoice_ids: string[];
        amount: number;
        payment_account_id: string;
        reference: string;
    }[],
    partner_deposits: [] as {
        partner_id: string;
        partner_name: string;
        amount: number;
    }[],
    amanat_deposits: [] as {
        customer_id: string;
        customer_name: string;
        available_balance: number;
        amount: number;
        reference: string;
        payment_account_id?: string;
    }[],
    other_deposits: [] as {
        deposit_type: string;
        account_id: string;
        description: string;
        amount: number;
    }[],

    // Dynamic payment channel receipts (money coming in via non-cash methods)
    payment_receipts: {} as Record<
        string,
        {
            entries: Array<{
                reference: string;
                amount: number;
                customer_name?: string;
                last_four?: string;
            }>;
        }
    >,

    // Tab 4: Money Out. Pending fuel-sale invoices (credit sales already made through a nozzle)
    // are pre-checked here exactly like pendingBillPayments below: they are a channel of the
    // close, never additional sales, and reduce expected cash by their amount. Pending plain
    // Accounting invoices on a fuel revenue account are pre-checked the same way.
    credit_sales: [
        ...(props.pendingFuelInvoices ?? []).map((invoice) => ({
            customer_id: invoice.customer_id,
            customer_name: invoice.customer_name,
            amount: invoice.amount,
            reference: invoice.reference,
            invoice_id: invoice.invoice_id,
            invoice_number: invoice.invoice_number,
            pending_fuel_invoice: true,
        })),
        ...(props.pendingAccountingInvoices ?? []).map((invoice) => ({
            customer_id: invoice.customer_id,
            customer_name: invoice.customer_name,
            amount: invoice.amount,
            reference: invoice.reference,
            invoice_id: invoice.invoice_id,
            invoice_number: invoice.invoice_number,
            pending_accounting_invoice: true,
        })),
    ] as { customer_id: string; customer_name: string; amount: number; reference: string; invoice_id?: string; invoice_number?: string; pending_fuel_invoice?: boolean; pending_accounting_invoice?: boolean }[],
    bank_withdrawals: [] as { bank_account_id: string; amount: number; reference: string; purpose: string }[],
    bank_deposits: [] as {
        bank_account_id: string;
        amount: number;
        reference: string;
        purpose: string;
    }[],
    partner_withdrawals: [] as {
        partner_id: string;
        partner_name: string;
        amount: number;
    }[],
    employee_advances: [] as {
        employee_id: string;
        employee_name: string;
        amount: number;
        reason: string;
    }[],
    payroll_payouts: props.approvedPayrollPayouts.map((payout) => ({
        ...payout,
    })),
    bill_payments: props.pendingBillPayments.map((payment) => ({ ...payment })),
    amanat_disbursements: [] as {
        customer_id: string;
        customer_name: string;
        available_balance: number;
        amount: number;
        payment_account_id?: string;
    }[],
    expenses: [] as {
        account_id: string;
        account_name: string;
        description: string;
        amount: number;
    }[],

    // Supplier bills / fuel purchases entered inline instead of via the Bills module.
    purchases: [] as {
        supplier_id: string;
        item_id: string;
        description: string;
        quantity: number | null;
        unit_cost: number | null;
        tank_id: string;
        supplier_invoice_number: string;
        notes: string;
        paid_now: boolean;
    }[],

    // Tab 5: Summary
    closing_cash: 0,
    cash_variance: 0,
    notes: '',

    // Explicit zero-sales-day confirmation. Required to post with no nozzle
    // readings at all, so an accidental empty submission cannot silently
    // claim the date; a genuine zero-sales day can still be posted.
    zero_sales_confirmed: false,
    zero_sales_reason: '',
});

const nozzleErrorMessage = computed(
    () => (form.errors as Record<string, string>).nozzle_readings,
);

// Row-array error lookups - server keys are `<array>.<index>.<field>`, one helper per array
// since readings/tanks/deposits/etc. are separate arrays with independently-indexed rows.
const nozzleError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[
        `nozzle_readings.${index}.${field}`
    ];

/**
 * A pump totaliser only counts up, so a closing reading below its opening one is a mistake -
 * a skipped nozzle, a transposed digit, a reading typed into the wrong row.
 *
 * The litres calculation clamps at zero, which meant an impossible reading quietly became a
 * sale of zero litres and the only symptom was a cash surplus. Day 13 of the fourteen-day
 * scenario lost 375 litres of diesel that way. The server refuses it now; this says so while
 * the number is still under the cursor.
 */
const nozzleMeterWentBackwards = (index: number): boolean => {
    const reading = form.nozzle_readings[index];
    if (!reading) return false;

    const opening = Number(reading.opening_electronic);
    const closing = Number(reading.closing_electronic);

    // A closing of 0 is the untouched default, not a reading: a real totaliser never shows
    // all zeros. Warning on it flagged every pump before anything was typed. The server still
    // refuses a closing below the opening when the close is posted.
    if (!(closing > 0)) return false;

    return Number.isFinite(opening) && Number.isFinite(closing) && closing < opening;
};

/**
 * Litres from a pair of meter readings, the same rule the server applies in
 * DailyCloseService::litresFromMeters - the server works its own figure out and posts that, so
 * this is only the preview. A totaliser that passed its last digit restarted from zero: the
 * litres are the distance to the rollover point plus the new reading. It has to be declared
 * with the tick box; guessing would let a mistyped reading through as a rollover.
 */
const meterRolloverPoint = (opening: number): number =>
    10 ** String(Math.floor(Math.max(opening, 1))).length;

const litresFromMeters = (opening: number, closing: number, rolledOver: boolean): number =>
    rolledOver
        ? Math.round((meterRolloverPoint(opening) - opening + closing) * 1000) / 1000
        : Math.max(0, closing - opening);

const otherSaleError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[`other_sales.${index}.${field}`];

const tankReadingError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[`tank_readings.${index}.${field}`];

const partnerDepositError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[
        `partner_deposits.${index}.${field}`
    ];

const amanatDepositError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[
        `amanat_deposits.${index}.${field}`
    ];

const otherDepositError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[`other_deposits.${index}.${field}`];

// Payment receipts are keyed by channel code, not a plain array, so this indexes both.
const paymentReceiptError = (
    channelCode: string,
    index: number,
    field: string,
) =>
    (form.errors as Record<string, string>)[
        `payment_receipts.${channelCode}.entries.${index}.${field}`
    ];

const bankDepositError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[`bank_deposits.${index}.${field}`];

const partnerWithdrawalError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[
        `partner_withdrawals.${index}.${field}`
    ];

const employeeAdvanceError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[
        `employee_advances.${index}.${field}`
    ];

const amanatDisbursementError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[
        `amanat_disbursements.${index}.${field}`
    ];

const expenseError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[`expenses.${index}.${field}`];

const purchaseError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[`purchases.${index}.${field}`];

const purchaseLineTotal = (row: { quantity: number | null; unit_cost: number | null }) =>
    Number(row.quantity || 0) * Number(row.unit_cost || 0);

const addPurchaseRow = () => {
    form.purchases.push({
        supplier_id: '',
        item_id: '',
        description: '',
        quantity: null,
        unit_cost: null,
        tank_id: '',
        supplier_invoice_number: '',
        notes: '',
        paid_now: false,
    });
};

const removePurchaseRow = (index: number) => {
    form.purchases.splice(index, 1);
};

const isFuelPurchaseItem = (itemId: string) =>
    (props.purchaseItems ?? []).find((item) => item.id === itemId)?.is_fuel ?? false;

// Reset form to initial empty state (preserving structure from props)
const resetFormToInitial = () => {
    // Reset nozzle readings - keep structure but clear entered values
    form.nozzle_readings = configuredNozzles.value.map((nozzle) => ({
        nozzle_id: nozzle.id,
        nozzle_code: nozzle.code,
        nozzle_label: nozzle.label,
        item_id: nozzle.item_id,
        fuel_name: nozzle.fuel_name,
        fuel_category: nozzle.fuel_category,
        pump_id: nozzle.pump_id,
        pump_name: nozzle.pump_name,
        has_electronic_meter: nozzle.has_electronic_meter,
        opening_electronic: nozzle.opening_reading,
        closing_electronic: 0,
        meter_rolled_over: false,
        opening_manual: nozzle.opening_manual ?? null,
        closing_manual: null,
        liters_sold: 0,
        sale_rate: nozzle.sale_rate,
    }));

    // Reset other sales
    form.other_sales = [];

    // Reset tank readings - keep structure but clear entered values
    form.tank_readings = props.tanks.map((tank) => tankRowFromProps(tank));

    form.bank_withdrawals = [];
    // Reset money in
    form.opening_cash = props.previousClose.closing_cash || 0;
    form.payments_received = [];
    form.partner_deposits = [];
    form.amanat_deposits = [];
    form.other_deposits = [];

    // Reset payment receipts - reinitialize empty structure for each channel
    form.payment_receipts = {};
    enabledChannels.value.forEach((channel) => {
        if (channel.type !== 'cash') {
            form.payment_receipts[channel.code] = { entries: [] };
        }
    });

    // Reset money out
    form.credit_sales = [
        ...(props.pendingFuelInvoices ?? []).map((invoice) => ({
            customer_id: invoice.customer_id,
            customer_name: invoice.customer_name,
            amount: invoice.amount,
            reference: invoice.reference,
            invoice_id: invoice.invoice_id,
            invoice_number: invoice.invoice_number,
            pending_fuel_invoice: true,
        })),
        ...(props.pendingAccountingInvoices ?? []).map((invoice) => ({
            customer_id: invoice.customer_id,
            customer_name: invoice.customer_name,
            amount: invoice.amount,
            reference: invoice.reference,
            invoice_id: invoice.invoice_id,
            invoice_number: invoice.invoice_number,
            pending_accounting_invoice: true,
        })),
    ];
    form.bank_deposits = [];
    form.partner_withdrawals = [];
    form.employee_advances = [];
    form.payroll_payouts = props.approvedPayrollPayouts.map((payout) => ({
        ...payout,
    }));
    form.bill_payments = props.pendingBillPayments.map((payment) => ({
        ...payment,
    }));
    form.amanat_disbursements = [];
    form.expenses = [];
    form.purchases = [];

    // Reset summary
    form.closing_cash = 0;
    form.cash_variance = 0;
    form.notes = '';

    // Reset tab saved states
    tabsSaved.value = {
        sales: false,
        tanks: false,
        moneyIn: false,
        moneyOut: false,
    };
};

// Watch for date changes and check for draft (must be after form is defined)
watch(
    () => form.date,
    (newDate, oldDate) => {
        if (suppressDateChange.value) {
            return;
        }
        if (newDate !== oldDate) {
            // Save current draft before switching dates (only if there's meaningful data)
            if (oldDate && !isAmendmentMode.value) {
                const formData = form.data();
                if (hasFormData(formData)) {
                    const oldDraftKey = `daily-close-draft-${props.company.id}-${oldDate}`;
                    const draftData = {
                        savedAt: new Date().toISOString(),
                        formData: formData,
                    };
                    localStorage.setItem(
                        oldDraftKey,
                        JSON.stringify(draftData),
                    );
                }
            }

            if (isAmendmentMode.value) {
                suppressDateChange.value = true;
                form.date = oldDate ?? props.date;
                nextTick(() => {
                    suppressDateChange.value = false;
                });
                return;
            }

            router.get(
                `/${props.company.slug}/fuel/daily-close`,
                { date: newDate },
                { preserveScroll: true, preserveState: false, replace: true },
            );
        }
    },
);

// Initialize payment_receipts for each enabled non-cash channel
enabledChannels.value.forEach((channel) => {
    if (channel.type !== 'cash') {
        form.payment_receipts[channel.code] = { entries: [] };
    }
});

// Hydrate form with original data when in amendment mode
const hydrateFormForAmendment = () => {
    if (!props.isAmendment || !props.originalFormData) return;

    const orig = props.originalFormData;

    // Hydrate nozzle readings - match by nozzle_id
    if (orig.nozzle_readings) {
        const origReadingsMap = new Map(
            orig.nozzle_readings.map((r) => [r.nozzle_id, r]),
        );
        form.nozzle_readings.forEach((nozzle, idx) => {
            const origReading = origReadingsMap.get(nozzle.nozzle_id);
            if (origReading) {
                form.nozzle_readings[idx].opening_electronic =
                    origReading.opening_electronic ?? nozzle.opening_electronic;
                form.nozzle_readings[idx].closing_electronic =
                    origReading.closing_electronic ?? 0;
                form.nozzle_readings[idx].opening_manual =
                    origReading.opening_manual ?? null;
                form.nozzle_readings[idx].closing_manual =
                    origReading.closing_manual ?? null;
                form.nozzle_readings[idx].liters_sold =
                    origReading.liters_sold ?? 0;
                form.nozzle_readings[idx].sale_rate =
                    origReading.sale_rate ?? nozzle.sale_rate;
            }
        });
    }

    // Hydrate other sales
    if (orig.other_sales && orig.other_sales.length > 0) {
        form.other_sales = orig.other_sales.map((sale) => ({
            item_id: sale.item_id,
            item_name: sale.item_name,
            quantity: sale.quantity,
            unit_price: sale.unit_price,
            amount: sale.amount,
        }));
    }

    // Hydrate tank readings - match by tank_id
    if (orig.tank_readings) {
        const origTankMap = new Map(
            orig.tank_readings.map((t) => [t.tank_id, t]),
        );
        form.tank_readings.forEach((tank, idx) => {
            const origTank = origTankMap.get(tank.tank_id);
            if (origTank) {
                form.tank_readings[idx].stick_reading =
                    origTank.stick_reading ?? 0;
                form.tank_readings[idx].liters = origTank.liters ?? 0;
            }
        });
    }

    // Hydrate money values
    if (orig.opening_cash !== undefined) form.opening_cash = orig.opening_cash;
    if (orig.closing_cash !== undefined) form.closing_cash = orig.closing_cash;

    // Hydrate partner deposits
    if (orig.partner_deposits && orig.partner_deposits.length > 0) {
        form.partner_deposits = orig.partner_deposits.map((pd) => {
            const partner = props.partners.find((p) => p.id === pd.partner_id);
            return {
                partner_id: pd.partner_id,
                partner_name: partner?.name ?? '',
                amount: pd.amount,
            };
        });
    }

    if (orig.amanat_deposits && orig.amanat_deposits.length > 0) {
        form.amanat_deposits = orig.amanat_deposits.map((deposit) => {
            const holder = props.amanatHolders.find(
                (h) => h.id === deposit.customer_id,
            );
            return {
                customer_id: deposit.customer_id ?? '',
                customer_name: holder?.name ?? deposit.customer_name ?? '',
                available_balance: holder?.amanat_balance ?? 0,
                amount: deposit.amount,
                reference: deposit.reference ?? '',
            };
        });
    }

    if (orig.other_deposits && orig.other_deposits.length > 0) {
        form.other_deposits = orig.other_deposits.map((deposit) => ({
            deposit_type: deposit.deposit_type,
            account_id: deposit.account_id ?? '',
            description: deposit.description ?? '',
            amount: deposit.amount,
        }));
    }

    // Hydrate payment receipts
    if (orig.payment_receipts) {
        Object.keys(orig.payment_receipts).forEach((channelCode) => {
            const origChannel = orig.payment_receipts![channelCode];
            if (origChannel?.entries) {
                form.payment_receipts[channelCode] = {
                    entries: origChannel.entries.map((e) => ({
                        reference: e.reference ?? '',
                        last_four: e.last_four ?? '',
                        amount: e.amount,
                    })),
                };
            }
        });
    }

    form.bank_withdrawals = (orig.bank_withdrawals || []).map((row) => ({ ...row, reference: row.reference ?? '', purpose: row.purpose ?? '' }));
    // Hydrate bank deposits
    if (orig.bank_deposits && orig.bank_deposits.length > 0) {
        form.bank_deposits = orig.bank_deposits.map((bd) => ({
            bank_account_id: bd.bank_account_id,
            amount: bd.amount,
            reference: bd.reference ?? '',
            purpose: bd.purpose ?? '',
        }));
    }

    // Hydrate partner withdrawals
    if (orig.partner_withdrawals && orig.partner_withdrawals.length > 0) {
        form.partner_withdrawals = orig.partner_withdrawals.map((pw) => {
            const partner = props.partners.find((p) => p.id === pw.partner_id);
            return {
                partner_id: pw.partner_id,
                partner_name: partner?.name ?? '',
                amount: pw.amount,
            };
        });
    }

    // Hydrate employee advances
    if (orig.employee_advances && orig.employee_advances.length > 0) {
        form.employee_advances = orig.employee_advances.map((ea) => {
            const employee = props.employees.find(
                (e) => e.id === ea.employee_id,
            );
            return {
                employee_id: ea.employee_id,
                employee_name: employee
                    ? `${employee.first_name} ${employee.last_name}`
                    : '',
                amount: ea.amount,
                reason: ea.reason ?? '',
            };
        });
    }

    if (orig.payroll_payouts && orig.payroll_payouts.length > 0) {
        form.payroll_payouts = orig.payroll_payouts.map((payout) => ({
            ...payout,
        }));
    }

    if (orig.bill_payments && orig.bill_payments.length > 0) {
        form.bill_payments = orig.bill_payments.map((payment) => ({
            ...payment,
        }));
    }

    // Hydrate amanat disbursements
    if (orig.amanat_disbursements && orig.amanat_disbursements.length > 0) {
        form.amanat_disbursements = orig.amanat_disbursements.map((ad) => {
            const holder = props.amanatHolders.find(
                (h) => h.id === ad.customer_id,
            );
            return {
                customer_id: ad.customer_id ?? '',
                customer_name: holder?.name ?? ad.customer_name ?? '',
                available_balance: holder?.amanat_balance ?? 0,
                amount: ad.amount,
            };
        });
    }

    // Hydrate expenses
    if (orig.expenses && orig.expenses.length > 0) {
        form.expenses = orig.expenses.map((exp) => {
            const account = props.expenseAccounts.find(
                (a) => a.id === exp.account_id,
            );
            return {
                account_id: exp.account_id,
                account_name: account?.name ?? '',
                description: exp.description,
                amount: exp.amount,
            };
        });
    }

    // Hydrate notes
    if (orig.notes) form.notes = orig.notes;
};

// Run hydration on mount if in amendment mode
onMounted(() => {
    if (props.isAmendment && props.originalFormData) {
        hydrateFormForAmendment();
    }
});

// Helper to add entry to a payment channel
const addPaymentEntry = (channelCode: string) => {
    if (!form.payment_receipts[channelCode]) {
        form.payment_receipts[channelCode] = { entries: [] };
    }
    form.payment_receipts[channelCode].entries.push({
        reference: '',
        amount: 0,
    });
};

// Helper to remove entry from a payment channel
const removePaymentEntry = (channelCode: string, index: number) => {
    if (form.payment_receipts[channelCode]) {
        form.payment_receipts[channelCode].entries.splice(index, 1);
    }
};

// Get total for a specific payment channel
const getChannelTotal = (channelCode: string): number => {
    const entries = form.payment_receipts[channelCode]?.entries || [];
    return entries.reduce((sum, e) => sum + (e.amount || 0), 0);
};

// Computed calculations

// Calculate quantity sold per tank from both meter readings and open/bulk product sales.
const litersSoldByTank = computed(() => {
    const byTank: Record<string, number> = {};
    form.nozzle_readings.forEach((r) => {
        // Find the nozzle's tank from props
        const nozzle = configuredNozzles.value.find(
            (n) => n.id === r.nozzle_id,
        );
        if (nozzle?.tank_id) {
            byTank[nozzle.tank_id] =
                (byTank[nozzle.tank_id] || 0) + r.liters_sold;
        }
    });
    form.other_sales.forEach((sale) => {
        const tank = form.tank_readings.find((t) => t.item_id === sale.item_id);
        if (tank?.tank_id) {
            byTank[tank.tank_id] =
                (byTank[tank.tank_id] || 0) + Number(sale.quantity || 0);
        }
    });
    return byTank;
});

const tankSalesLabel = (tank: { item_id?: string; tank_id: string }) => {
    const hasNozzle = configuredNozzles.value.some(
        (nozzle) => nozzle.tank_id === tank.tank_id,
    );
    if (hasNozzle) return 'Meter Sales';

    const hasBulkSaleRow = form.other_sales.some(
        (sale) => sale.item_id === tank.item_id,
    );
    return hasBulkSaleRow ? 'Recorded Sales' : 'Recorded Sales';
};

const stockMovementLabel = (liters: number) => {
    if (Math.abs(liters) < 0.001) return 'No stock movement since opening';
    return liters > 0
        ? 'Stock added since opening'
        : 'Stock removed since opening';
};

const expectedTankClosingLiters = (tank: {
    previous_liters: number;
    stock_movements_since_baseline_liters?: number | null;
    pending_delivery_liters?: number | null;
    tank_id: string;
}) => {
    const soldFromTank = litersSoldByTank.value[tank.tank_id] || 0;
    return (
        Number(tank.previous_liters || 0) +
        Number(tank.stock_movements_since_baseline_liters || 0) +
        Number(tank.pending_delivery_liters || 0) -
        soldFromTank
    );
};

// Calculate tank variance (shrinkage/gain)
// Formula: Opening baseline + received stock + pending (unreceived) deliveries
// - sales = expected closing. Pending deliveries are bills whose litres already
// belong to this tank but haven't been received yet — the close receives them
// on posting, so they must count here or an unreceived delivery reads as a
// physical "gain" in the dip. Difference between physical dip and expected
// closing, after deliveries, is the real variance.
const tankVariances = computed(() => {
    return form.tank_readings.map((tank) => {
        const soldFromTank = litersSoldByTank.value[tank.tank_id] || 0;
        const expectedClosing = expectedTankClosingLiters(tank);
        const variance = expectedClosing - tank.liters; // Positive = loss, Negative = gain
        const usageFromDip =
            tank.previous_liters +
            Number(tank.stock_movements_since_baseline_liters || 0) +
            Number(tank.pending_delivery_liters || 0) -
            tank.liters;

        return {
            tank_id: tank.tank_id,
            tank_name: tank.tank_name,
            fuel_category: tank.fuel_category,
            previous_liters: tank.previous_liters,
            current_liters: tank.liters,
            expected_closing_liters: expectedClosing,
            usage_from_dip: usageFromDip,
            liters_sold: soldFromTank,
            variance: variance,
            variance_percent:
                soldFromTank > 0 ? (variance / soldFromTank) * 100 : 0,
        };
    });
});

// Total variance across all tanks
const totalTankVariance = computed(() => {
    return tankVariances.value.reduce((sum, v) => sum + v.variance, 0);
});

const getTankFillPercent = (tank: {
    capacity?: number;
    liters?: number;
    previous_liters?: number;
}): number => {
    const capacity = Number(tank.capacity ?? 0);
    if (capacity <= 0) return 0;
    const liters = Number(
        tank.liters > 0 ? tank.liters : (tank.previous_liters ?? 0),
    );
    return Math.min(100, Math.max(0, Math.round((liters / capacity) * 100)));
};

const totalFuelSales = computed(() => {
    return form.nozzle_readings.reduce((sum, r) => {
        return sum + rateAdjustedNozzleRevenue(r);
    }, 0);
});

const rateSnapshotByItem = computed(() => {
    const map = new Map<string, RateChangeSnapshot>();
    (props.rateChangeSnapshots || []).forEach((snapshot) => {
        if (snapshot.snapshot_nozzle_count > 0) {
            map.set(snapshot.item_id, snapshot);
        }
    });
    return map;
});

const rateAdjustedNozzleRevenue = (reading: {
    nozzle_id: string;
    item_id: string;
    opening_electronic: number;
    closing_electronic: number;
    liters_sold: number;
    sale_rate: number;
}) => {
    const snapshot = rateSnapshotByItem.value.get(reading.item_id);
    if (!snapshot || snapshot.old_sale_rate <= 0) {
        return (
            Number(reading.liters_sold || 0) * Number(reading.sale_rate || 0)
        );
    }

    const snapshotRow = snapshot.snapshot_nozzle_readings.find(
        (row) => row.nozzle_id === reading.nozzle_id,
    );
    const snapshotMeter = Number(snapshotRow?.electronic_reading ?? 0);
    const opening = Number(reading.opening_electronic || 0);
    const closing = Number(reading.closing_electronic || 0);

    if (snapshotMeter <= opening || snapshotMeter >= closing) {
        return (
            Number(reading.liters_sold || 0) * Number(reading.sale_rate || 0)
        );
    }

    const oldLiters = Math.max(0, snapshotMeter - opening);
    const newLiters = Math.max(0, closing - snapshotMeter);
    const segmentedLiters = oldLiters + newLiters;
    const fallbackLiters = Math.max(
        0,
        Number(reading.liters_sold || 0) - segmentedLiters,
    );

    return (
        oldLiters * snapshot.old_sale_rate +
        newLiters * snapshot.new_sale_rate +
        fallbackLiters * Number(reading.sale_rate || 0)
    );
};

const rateChangeSplitForReading = (reading: {
    nozzle_id: string;
    item_id: string;
    opening_electronic: number;
    closing_electronic: number;
}) => {
    const snapshot = rateSnapshotByItem.value.get(reading.item_id);
    const snapshotRow = snapshot?.snapshot_nozzle_readings.find(
        (row) => row.nozzle_id === reading.nozzle_id,
    );
    const snapshotMeter = Number(snapshotRow?.electronic_reading ?? 0);
    const opening = Number(reading.opening_electronic || 0);
    const closing = Number(reading.closing_electronic || 0);

    if (
        !snapshot ||
        snapshot.old_sale_rate <= 0 ||
        snapshotMeter <= opening ||
        snapshotMeter >= closing
    ) {
        return null;
    }

    return {
        oldLiters: Math.max(0, snapshotMeter - opening),
        newLiters: Math.max(0, closing - snapshotMeter),
        oldRate: snapshot.old_sale_rate,
        newRate: snapshot.new_sale_rate,
    };
};

const totalOtherSales = computed(() => {
    return form.other_sales.reduce((sum, s) => sum + s.amount, 0);
});

const totalSales = computed(() => totalFuelSales.value + totalOtherSales.value);

// Sales breakdown by fuel type for summary
const salesByFuelType = computed(() => {
    const byFuel: Record<
        string,
        {
            fuel_name: string;
            fuel_category: string;
            liters: number;
            amount: number;
        }
    > = {};
    form.nozzle_readings.forEach((r) => {
        const key = r.item_id;
        if (!byFuel[key]) {
            byFuel[key] = {
                fuel_name: r.fuel_name || 'Unknown',
                fuel_category: r.fuel_category || '',
                liters: 0,
                amount: 0,
            };
        }
        byFuel[key].liters += r.liters_sold;
        byFuel[key].amount += rateAdjustedNozzleRevenue(r);
    });
    return Object.values(byFuel).filter((f) => f.liters > 0);
});

// Partner deposits total
const totalPartnerDeposits = computed(() => {
    return form.partner_deposits.reduce((sum, d) => sum + d.amount, 0);
});

const totalAmanatDeposits = computed(() => {
    return form.amanat_deposits.reduce((sum, d) => sum + d.amount, 0);
});

const totalOtherDeposits = computed(() => {
    return form.other_deposits.reduce((sum, d) => sum + d.amount, 0);
});

// Money out breakdown totals
const totalBankDeposits = computed(() => {
    return form.bank_deposits.reduce((sum, d) => sum + d.amount, 0);
});

const totalPartnerWithdrawals = computed(() => {
    return form.partner_withdrawals.reduce((sum, w) => sum + w.amount, 0);
});

const totalEmployeeAdvances = computed(() => {
    return form.employee_advances.reduce((sum, a) => sum + a.amount, 0);
});

const totalPayrollPayouts = computed(() => {
    return form.payroll_payouts.reduce((sum, payout) => sum + payout.amount, 0);
});

const totalBillPayments = computed(() => {
    return form.bill_payments.reduce((sum, payment) => sum + payment.amount, 0);
});

const totalCashBillPayments = computed(() => {
    return form.bill_payments
        .filter((payment) => payment.affects_cash_drawer)
        .reduce((sum, payment) => sum + payment.amount, 0);
});

const totalNonCashBillPayments = computed(() => {
    return totalBillPayments.value - totalCashBillPayments.value;
});

const totalAmanatDisbursements = computed(() => {
    return form.amanat_disbursements.reduce((sum, a) => sum + a.amount, 0);
});

const totalExpenses = computed(() => {
    return form.expenses.reduce((sum, e) => sum + e.amount, 0);
});

// Total of all non-cash payment receipts (cards, transfers, fuel cards, wallets).
// These are sales that left the drawer for a bank/clearing account, so they are Money Out.
const totalNonCashReceipts = computed(() => {
    let total = 0;
    for (const channelCode of Object.keys(form.payment_receipts)) {
        total += getChannelTotal(channelCode);
    }
    return total;
});

// Money In = opening cash + every cash deposit + TOTAL sales (cash, card, transfer — all of it)
const totalBankWithdrawals = computed(() => form.bank_withdrawals.reduce((sum, row) => sum + Number(row.amount || 0), 0));
// Only a payment received into a cash account raises expected drawer cash, matching
// DailyClosePaymentsReceivedService's affects_cash_drawer flag on the backend; a bank
// account never touches it.
const totalPaymentsReceivedCash = computed(() => {
    const cashIds = new Set(props.cashAccountIds ?? []);
    return form.payments_received.reduce((sum, row) => (cashIds.has(row.payment_account_id) ? sum + Number(row.amount || 0) : sum), 0);
});

const totalMoneyIn = computed(() => {
    return (
        form.opening_cash +
        totalPaymentsReceivedCash.value +
        totalBankWithdrawals.value +
        totalPartnerDeposits.value +
        totalAmanatDeposits.value +
        totalOtherDeposits.value +
        totalSales.value
    );
});

// One row per enabled non-cash channel with a value, labelled "channel → destination account"
const channelOutRows = computed(() => {
    return enabledChannels.value
        .filter((ch) => ch.type !== 'cash' && getChannelTotal(ch.code) > 0)
        .map((ch) => {
            const accountId = ch.clearing_account_id || ch.bank_account_id;
            const account = props.bankAccounts.find((a) => a.id === accountId);
            return {
                code: ch.code,
                label: account ? `${ch.label} → ${account.name}` : ch.label,
                amount: getChannelTotal(ch.code),
            };
        });
});

// Money Out = everything that left the drawer, including sales that went straight to bank/card accounts
const totalCreditSales = computed(() => form.credit_sales.reduce((sum, row) => sum + Number(row.amount || 0), 0));
const totalMoneyOut = computed(() => {
    const bankDeposits = form.bank_deposits.reduce(
        (sum, d) => sum + d.amount,
        0,
    );
    const partnerWithdrawals = form.partner_withdrawals.reduce(
        (sum, w) => sum + w.amount,
        0,
    );
    const employeeAdvances = form.employee_advances.reduce(
        (sum, a) => sum + a.amount,
        0,
    );
    const payrollPayouts = form.payroll_payouts.reduce(
        (sum, payout) => sum + payout.amount,
        0,
    );
    const cashBillPayments = totalCashBillPayments.value;
    const amanat = form.amanat_disbursements.reduce(
        (sum, a) => sum + a.amount,
        0,
    );
    const expenses = form.expenses.reduce((sum, e) => sum + e.amount, 0);

    return (
        totalNonCashReceipts.value +
        totalCreditSales.value +
        bankDeposits +
        partnerWithdrawals +
        employeeAdvances +
        payrollPayouts +
        cashBillPayments +
        amanat +
        expenses
    );
});

const expectedClosingCash = computed(
    () => totalMoneyIn.value - totalMoneyOut.value + externalCashEffect.value,
);

// In whole rupees. Sales are litres x rate, so the expected figure carries paisa that nobody
// counts; comparing to the paisa turned a few of them into "Cash over by 1". The server
// posts any leftover paisa to Cash Over/Short so the books still balance exactly.
const cashVariance = computed(
    () => Math.round(Number(form.closing_cash || 0)) - Math.round(expectedClosingCash.value),
);

// Watch for closing reading changes to auto-calculate liters (from electronic readings)
watch(
    () =>
        form.nozzle_readings.map((r) => ({
            o: r.opening_electronic,
            c: r.closing_electronic,
            rolled: Boolean(r.meter_rolled_over),
        })),
    (readings) => {
        readings.forEach((r, i) => {
            form.nozzle_readings[i].liters_sold = litresFromMeters(Number(r.o), Number(r.c), r.rolled);
        });
    },
    { deep: true },
);

/**
 * A stable identity per repeat row, so `v-for` keys track the row rather than its position.
 *
 * These lists were keyed by index. Index keys tell Vue that the third row is the third row,
 * whatever it now contains, so when a row is added or removed the child components stay put
 * and are handed different data. That matters here because Input is not a plain element: it
 * holds the value in a local ref (useVModel, passive), so a reused instance can keep showing
 * what it held before the list changed - a figure from another row, or another section
 * entirely, sitting in a field the user never typed it into.
 *
 * The uid rides along on the row object, so it survives a draft save and restore. The server
 * reads named keys and ignores it.
 */
let rowUidCounter = 0
const nextRowUid = () => `row-${++rowUidCounter}`

/**
 * A stable key for any repeat row, without adding a field to the row.
 *
 * The expense and deposit rows carry a uid for this; the other ten lists in this form were
 * still keyed by index, which binds each row's input components to its position rather than to
 * the row. Add or remove a row and a component can keep showing a value that belonged to
 * whatever used to sit there - which is how an expense row came to display the bank-deposit
 * amount.
 *
 * A WeakMap keyed on the row object gives each row an identity on first render and never
 * touches the data posted to the server. Vue hands back the same reactive proxy for the same
 * row every time, so the key is stable; a row rebuilt from a restored draft is a new object and
 * correctly gets a new key.
 */
const rowKeys = new WeakMap<object, string>()
const rowKey = (row: object): string => {
    let key = rowKeys.get(row)
    if (!key) {
        key = nextRowUid()
        rowKeys.set(row, key)
    }
    return key
}

// Add/remove helpers
const addOtherSale = () => {
    form.other_sales.push({
        item_id: '',
        item_name: '',
        quantity: 1,
        unit_price: 0,
        amount: 0,
    });
};

const removeOtherSale = (index: number) => {
    form.other_sales.splice(index, 1);
};

// When lubricant item is selected, populate name and price
const setOtherSaleItem = (index: number) => {
    const item = props.lubricantItems.find(
        (i) => i.id === form.other_sales[index].item_id,
    );
    if (item) {
        form.other_sales[index].item_name = item.name;
        form.other_sales[index].unit_price = item.sale_price;
        recalculateOtherSaleAmount(index);
    }
};

/**
 * Call this from `@update:model-value`, not `@input`.
 *
 * Input is a component, not a native element. `@input` only reaches its inner element as a
 * fallthrough listener, so whether it sees the new quantity depends on which of the two
 * handlers on that element Vue happens to run first. These two fields were the only two in
 * this form wired that way - every other recalculation here uses the declared emit - and
 * they were the two that silently kept the amount from the previous value. A lubricant line
 * entered as 4 x 2,400 posted 2,400.
 */
const recalculateOtherSaleAmount = (index: number) => {
    const sale = form.other_sales[index];
    // Rounded the way the server rounds it, so the figure on screen is the figure posted.
    sale.amount = Math.round(sale.quantity * sale.unit_price * 100) / 100;
};

const testSeedForDate = computed(() => {
    const digits = form.date.replace(/\D/g, '');
    return Number(digits.slice(-4) || 1);
});

const fillDummyDailyCloseData = () => {
    const seed = testSeedForDate.value;

    form.nozzle_readings.forEach((reading, index) => {
        const liters = 80 + ((seed + index * 37) % 240);
        const opening = Number(reading.opening_electronic || 0);
        reading.closing_electronic = opening + liters;
        reading.liters_sold = liters;

        if (reading.opening_manual !== null) {
            reading.closing_manual =
                Number(reading.opening_manual || 0) +
                liters +
                ((seed + index) % 3 === 0 ? 1 : 0);
        }
    });

    form.tank_readings.forEach((tank, index) => {
        const sold = litersSoldByTank.value[tank.tank_id] || 0;
        const expected = expectedTankClosingLiters(tank);
        const variance = ((seed + index) % 5) - 2;
        tank.liters = Math.max(0, Math.round(expected + variance));
        tank.stick_reading = Math.max(
            0,
            Number(tank.previous_stick || 0) + ((seed + index) % 4),
        );
    });

    form.other_sales = [];
    if (props.lubricantItems.length > 0) {
        const item = props.lubricantItems[seed % props.lubricantItems.length];
        const quantity = 1 + (seed % 4);
        form.other_sales.push({
            item_id: item.id,
            item_name: item.name,
            quantity,
            unit_price: Number(item.sale_price || 0),
            amount: quantity * Number(item.sale_price || 0),
        });
    }

    Object.keys(form.payment_receipts).forEach((channelCode, index) => {
        const amount = Math.round(
            totalSales.value * (index === 0 ? 0.18 : 0.08),
        );
        form.payment_receipts[channelCode].entries =
            amount > 0
                ? [
                      {
                          reference: `TEST-${form.date}-${channelCode}`,
                          amount,
                          customer_name: 'Test customer',
                          last_four: '1234',
                      },
                  ]
                : [];
    });

    form.bank_deposits = [];
    if (props.bankAccounts.length > 0 && expectedClosingCash.value > 50000) {
        form.bank_deposits.push({
            uid: nextRowUid(),
            bank_account_id: props.bankAccounts[0].id,
            amount: Math.round(expectedClosingCash.value * 0.35),
            reference: `TEST-DEP-${form.date}`,
            purpose: 'Test cash deposit',
        });
    }

    form.partner_withdrawals = [];
    if (props.partners.length > 0 && seed % 2 === 0) {
        form.partner_withdrawals.push({
            partner_id: props.partners[0].id,
            partner_name: props.partners[0].name,
            amount: 5000 + (seed % 5) * 1000,
        });
    }

    form.employee_advances = [];
    if (props.employees.length > 0 && seed % 3 === 0) {
        form.employee_advances.push({
            employee_id: props.employees[0].id,
            employee_name: props.employees[0].full_name,
            amount: 2000 + (seed % 4) * 500,
            reason: 'Test advance',
        });
    }

    form.amanat_deposits = [];
    if (props.amanatHolders.length > 0 && seed % 4 === 0) {
        const holder = props.amanatHolders[0];
        form.amanat_deposits.push({
            customer_id: holder.id,
            customer_name: holder.name,
            available_balance: holder.amanat_balance,
            amount: 3000 + (seed % 4) * 1000,
            reference: `TEST-AMANAT-${form.date}`,
        });
    }

    form.expenses = [];
    if (props.expenseAccounts.length > 0) {
        form.expenses.push({
            account_id: props.expenseAccounts[0].id,
            account_name: props.expenseAccounts[0].name,
            description: 'Test station expense',
            amount: 1000 + (seed % 6) * 250,
        });
    }

    form.closing_cash = Math.max(0, Math.round(expectedClosingCash.value));
    form.cash_variance = cashVariance.value;
    form.notes = `Auto-filled test daily close data for ${form.date}.`;
    tabsSaved.value = {
        sales: false,
        tanks: false,
        moneyIn: false,
        moneyOut: false,
    };
    activeTab.value = 'sales';
    saveDraft();
    toast.success('Test data filled', {
        description: 'Review the tabs, then save/post when ready.',
    });
};

const addPartnerDeposit = () => {
    form.partner_deposits.push({ partner_id: '', partner_name: '', amount: 0 });
};

const removePartnerDeposit = (index: number) => {
    form.partner_deposits.splice(index, 1);
};

const addAmanatDeposit = () => {
    form.amanat_deposits.push({
        customer_id: '',
        customer_name: '',
        available_balance: 0,
        amount: 0,
        reference: '',
        payment_account_id: '',
    });
};

const removeAmanatDeposit = (index: number) => {
    form.amanat_deposits.splice(index, 1);
};

const setAmanatDepositCustomer = (index: number) => {
    const holder = props.amanatHolders.find(
        (h) => h.id === form.amanat_deposits[index].customer_id,
    );
    if (holder) {
        form.amanat_deposits[index].customer_name = holder.name;
        form.amanat_deposits[index].available_balance = holder.amanat_balance;
    }
};

const otherDepositTypes = [
    { value: 'loss_compensation', label: 'Loss compensation' },
    { value: 'fuel_disbursement', label: 'Fuel disbursement recovery' },
    { value: 'misc_income', label: 'Other cash income' },
];

const addOtherDeposit = () => {
    form.other_deposits.push({
        deposit_type: 'loss_compensation',
        account_id: '',
        description: '',
        amount: 0,
    });
};

const removeOtherDeposit = (index: number) => {
    form.other_deposits.splice(index, 1);
};

const getOtherDepositTypeLabel = (type: string) => {
    return (
        otherDepositTypes.find((option) => option.value === type)?.label ??
        'Other deposit'
    );
};

const bankWithdrawalError = (index: number, field: string) =>
    (form.errors as Record<string, string>)[`bank_withdrawals.${index}.${field}`];
const addBankWithdrawal = () => {
    form.bank_withdrawals.push({ bank_account_id: '', amount: 0, reference: '', purpose: '' });
};
const removeBankWithdrawal = (index: number) => form.bank_withdrawals.splice(index, 1);

const addBankDeposit = () => {
    form.bank_deposits.push({
        uid: nextRowUid(),
        bank_account_id: '',
        amount: 0,
        reference: '',
        purpose: '',
    });
};

const removeBankDeposit = (index: number) => {
    form.bank_deposits.splice(index, 1);
};

const addPartnerWithdrawal = () => {
    form.partner_withdrawals.push({
        partner_id: '',
        partner_name: '',
        amount: 0,
    });
};

const removePartnerWithdrawal = (index: number) => {
    form.partner_withdrawals.splice(index, 1);
};

const addEmployeeAdvance = () => {
    form.employee_advances.push({
        employee_id: '',
        employee_name: '',
        amount: 0,
        reason: '',
    });
};

const removeEmployeeAdvance = (index: number) => {
    form.employee_advances.splice(index, 1);
};

const addAmanat = () => {
    form.amanat_disbursements.push({
        customer_id: '',
        customer_name: '',
        available_balance: 0,
        amount: 0,
        payment_account_id: '',
    });
};

const removeAmanat = (index: number) => {
    form.amanat_disbursements.splice(index, 1);
};

const addExpense = () => {
    form.expenses.push({
        uid: nextRowUid(),
        account_id: '',
        account_name: '',
        description: '',
        amount: 0,
    });
};

const removeExpense = (index: number) => {
    form.expenses.splice(index, 1);
};

const formatDraftTime = (value: string | null) => {
    return formatSharedDateTime(value, {
        mode: 'datetime',
        fallback: 'earlier',
    });
};

const formatBaselineDate = (value?: string | null) => {
    return value ? formatSharedDateTime(value, { mode: 'date' }) : '';
};

const baselineLabel = (tank: {
    previous_source_label?: string | null;
    previous_as_of?: string | null;
}) => {
    if (!tank.previous_source_label) return '';

    const date = formatBaselineDate(tank.previous_as_of);

    if (tank.previous_source_label === 'Tank dip') {
        return date
            ? `Yesterday morning's dip · ${date}`
            : `Yesterday morning's dip`;
    }

    return date
        ? `${tank.previous_source_label} · ${date}`
        : tank.previous_source_label;
};

// Partner/Employee name helpers
const setPartnerName = (index: number, field: 'deposits' | 'withdrawals') => {
    const list =
        field === 'deposits' ? form.partner_deposits : form.partner_withdrawals;
    const partner = props.partners.find((p) => p.id === list[index].partner_id);
    if (partner) list[index].partner_name = partner.name;
};

const setEmployeeName = (index: number) => {
    const employee = props.employees.find(
        (e) => e.id === form.employee_advances[index].employee_id,
    );
    if (employee)
        form.employee_advances[index].employee_name =
            employee.full_name ||
            `${employee.first_name} ${employee.last_name}`;
};

const setAmanatCustomer = (index: number) => {
    const holder = props.amanatHolders.find(
        (h) => h.id === form.amanat_disbursements[index].customer_id,
    );
    if (holder) {
        form.amanat_disbursements[index].customer_name = holder.name;
        form.amanat_disbursements[index].available_balance =
            holder.amanat_balance;
    }
};

const getPartner = (id: string) => props.partners.find((p) => p.id === id);
const getEmployee = (id: string) => props.employees.find((e) => e.id === id);
const getAmanatHolder = (id: string) =>
    props.amanatHolders.find((h) => h.id === id);

const setExpenseAccountName = (index: number) => {
    const account = props.expenseAccounts.find(
        (a) => a.id === form.expenses[index].account_id,
    );
    if (account) form.expenses[index].account_name = account.name;
};

// Track which tabs have been saved/validated
const tabsSaved = ref({
    sales: false,
    tanks: false,
    moneyIn: false,
    moneyOut: false,
});

const tabSequence = ['sales', 'tanks', 'money-in', 'money-out', 'summary'];

const goToNextTab = (current: string) => {
    const index = tabSequence.indexOf(current);
    if (index >= 0 && index < tabSequence.length - 1) {
        activeTab.value = tabSequence[index + 1];
    }
};

// Per-tab validation functions (local save with toast feedback)
const saveSales = () => {
    // Validate: check if all nozzles have closing readings
    const missingReadings = form.nozzle_readings.filter(
        (r) => r.closing_electronic <= r.opening_electronic,
    );
    if (missingReadings.length > 0 && totalFuelSales.value === 0) {
        toast.error('Please enter closing readings for all nozzles');
        return;
    }

    tabsSaved.value.sales = true;
    toast.success('Sales data saved', {
        description: `Total: ${formatMoneyText(totalSales.value, currencyCode.value)}`,
    });
    goToNextTab('sales');
};

const saveTanks = () => {
    // Validate: check if tank readings are entered
    const emptyTanks = form.tank_readings.filter(
        (t) => t.stick_reading === 0 && t.liters === 0,
    );
    if (emptyTanks.length === form.tank_readings.length) {
        toast.warning('No tank readings entered', {
            description: 'You can continue without tank readings',
        });
    } else {
        toast.success('Tank readings saved');
    }
    tabsSaved.value.tanks = true;
    goToNextTab('tanks');
};

const saveMoneyIn = () => {
    tabsSaved.value.moneyIn = true;
    toast.success('Money In saved', {
        description: `Opening cash: ${formatMoneyText(form.opening_cash, currencyCode.value)}`,
    });
    goToNextTab('money-in');
};

const saveMoneyOut = () => {
    tabsSaved.value.moneyOut = true;
    const total = totalMoneyOut.value;
    toast.success('Money Out saved', {
        description: `Total outflows: ${formatMoneyText(total, currencyCode.value)}`,
    });
    goToNextTab('money-out');
};

// Final submit - posts everything to server
const submitting = ref(false);

// Clean form data before submission - filter out incomplete entries
const getCleanedFormData = () => {
    const data = form.data();

    // Filter out incomplete expenses (missing account_id or amount)
    data.expenses = (data.expenses || []).filter(
        (e: { account_id: string; amount: number }) =>
            e.account_id && e.amount > 0,
    );

    // Filter out incomplete partner deposits
    data.partner_deposits = (data.partner_deposits || []).filter(
        (d: { partner_id: string; amount: number }) =>
            d.partner_id && d.amount > 0,
    );

    data.amanat_deposits = (data.amanat_deposits || []).filter(
        (d: { customer_id: string; amount: number }) =>
            d.customer_id && d.amount > 0,
    );

    data.other_deposits = (data.other_deposits || []).filter(
        (d: { deposit_type: string; amount: number }) =>
            d.deposit_type && d.amount > 0,
    );

    // Filter out incomplete bank deposits
    data.bank_deposits = (data.bank_deposits || []).filter(
        (d: { bank_account_id: string; amount: number }) =>
            d.bank_account_id && d.amount > 0,
    );

    // Filter out incomplete partner withdrawals
    data.partner_withdrawals = (data.partner_withdrawals || []).filter(
        (w: { partner_id: string; amount: number }) =>
            w.partner_id && w.amount > 0,
    );

    // Filter out incomplete employee advances
    data.employee_advances = (data.employee_advances || []).filter(
        (a: { employee_id: string; amount: number }) =>
            a.employee_id && a.amount > 0,
    );

    data.payroll_payouts = (data.payroll_payouts || []).filter(
        (p: { payslip_id: string; amount: number }) =>
            p.payslip_id && p.amount > 0,
    );

    // Filter out incomplete amanat disbursements
    data.amanat_disbursements = (data.amanat_disbursements || []).filter(
        (a: { customer_id: string; amount: number }) =>
            a.customer_id && a.amount > 0,
    );

    // Filter out incomplete other sales
    data.other_sales = (data.other_sales || []).filter(
        (s: { item_id: string; amount: number }) => s.item_id && s.amount > 0,
    );

    // Clean payment receipts - filter out empty entries from each channel
    if (data.payment_receipts) {
        for (const channelCode of Object.keys(data.payment_receipts)) {
            if (data.payment_receipts[channelCode]?.entries) {
                data.payment_receipts[channelCode].entries =
                    data.payment_receipts[channelCode].entries.filter(
                        (e: { amount: number }) => e.amount > 0,
                    );
            }
        }
    }

    return data;
};

const parkDailyClose = () => {
    form.clearErrors();
    submitting.value = true;
    router.post(
        `/${props.company.slug}/fuel/daily-close`,
        { ...getCleanedFormData(), intent: 'park' },
        {
            preserveScroll: true,
            onError: (errors) => {
                form.setError(errors as Record<string, string>);
                toast.error(String(Object.values(errors)[0]));
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
};

onMounted(() => {
    if (props.parkedDraft && !props.isAmendment) {
        for (const [key, value] of Object.entries(props.parkedDraft)) {
            if (key in form.data()) (form as any)[key] = value;
        }
        refreshTankFacts();
        showDraftRestoreDialog.value = false;
    }
});

const externalCashEffect = computed(() =>
    (props.canonicalActivity || []).reduce(
        (sum, row) => sum + Number(row.cash_effect || 0),
        0,
    ),
);

/**
 * The "Already recorded" rows as money in and money out, one row per document. A document
 * edited after posting shows up with its reversal (BILL-00015 and BILL-00015-REV); those are
 * netted together, and anything that nets to nothing - like a bill re-posted off the cash
 * account - drops out, leaving only real movements of cash through the drawer.
 */
const recordedTypeLabels: Record<string, string> = {
    bill: 'Bill',
    bill_payment: 'Supplier payment',
    payment: 'Customer payment',
    invoice: 'Invoice',
    expense: 'Expense',
};
const recordedElsewhere = computed(() => {
    const groups = new Map<string, { key: string; label: string; amount: number }>();
    for (const row of props.canonicalActivity || []) {
        const reference = String(row.reference ?? '').replace(/-REV(-\d+)?$/, '');
        const key = `${row.type}|${reference}`;
        const label = `${recordedTypeLabels[row.type] ?? String(row.type).replace(/[_:]/g, ' ')} · ${reference}`;
        const group = groups.get(key) ?? { key, label, amount: 0 };
        group.amount += Number(row.cash_effect || 0);
        groups.set(key, group);
    }
    return [...groups.values()].filter((g) => Math.abs(g.amount) >= 0.005);
});
const recordedMoneyIn = computed(() => recordedElsewhere.value.filter((g) => g.amount > 0));
const recordedMoneyOut = computed(() => recordedElsewhere.value.filter((g) => g.amount < 0));
const recordedMoneyInTotal = computed(() => recordedMoneyIn.value.reduce((sum, g) => sum + g.amount, 0));
const recordedMoneyOutTotal = computed(() => -recordedMoneyOut.value.reduce((sum, g) => sum + g.amount, 0));
// What the summaries show: this form's own figures plus what was recorded on other screens.
// Expected closing is unchanged: in - out + (recorded in - recorded out) is the same sum.
const shownMoneyIn = computed(() => totalMoneyIn.value + recordedMoneyInTotal.value);
const shownMoneyOut = computed(() => totalMoneyOut.value + recordedMoneyOutTotal.value);

const submitDailyClose = () => {
    // Validate closing cash is entered
    if (form.closing_cash < 0) {
        toast.error('Please enter the actual closing cash amount');
        return;
    }

    // For amendments, require a reason
    if (isAmendmentMode.value && amendmentReason.value.length < 10) {
        toast.error(
            'Please provide a reason for the amendment (at least 10 characters)',
        );
        return;
    }

    submitting.value = true;
    form.clearErrors();
    form.cash_variance = cashVariance.value;

    const cleanedData = getCleanedFormData();

    if (isAmendmentMode.value && props.originalTransaction) {
        // Amendment submission
        router.post(
            `/${props.company.slug}/fuel/daily-close/${props.originalTransaction.id}/amend`,
            {
                ...cleanedData,
                amendment_reason: amendmentReason.value,
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    // router.post bypasses form.post, so form.errors needs wiring up manually for InputError to see it
                    form.setError(errors as Record<string, string>);
                    const firstError = Object.values(errors)[0];
                    toast.error('Failed to post amendment', {
                        description: firstError as string,
                    });
                },
                onFinish: () => {
                    submitting.value = false;
                },
            },
        );
    } else {
        // Normal submission
        router.post(`/${props.company.slug}/fuel/daily-close`, cleanedData, {
            preserveScroll: true,
            onSuccess: (page) => {
                const flash = (page.props as any).flash;
                if (flash?.success) {
                    clearDraftOnSuccess();
                }
            },
            onError: (errors) => {
                // router.post bypasses form.post, so form.errors needs wiring up manually for InputError to see it
                form.setError(errors as Record<string, string>);
                if (Object.keys(errors).some((key) => key === 'credit_sales' || key.startsWith('credit_sales.'))) {
                    activeTab.value = 'money-out';
                }
                const firstError = Object.values(errors)[0];
                toast.error('Failed to post daily close', {
                    description: firstError as string,
                });
            },
            onFinish: () => {
                submitting.value = false;
            },
        });
    }
};

// Helper to get nozzle position label (Front/Back)
const getNozzlePosition = (index: number, totalInPump: number): string => {
    if (totalInPump === 1) return '';
    return index === 0 ? 'Front' : 'Back';
};

// Format currency
/*
 * Litres, not money. This was called formatCurrency and was doing both jobs --
 * grouping tank volumes and grouping rupees -- which is how a litre count ends
 * up looking like a price. Money goes through MoneyText; this only ever
 * groups a quantity, and the " L" that follows it in the template is the unit.
 */
const formatLiters = (liters: number, digits = 0) => {
    return new Intl.NumberFormat('en-PK', {
        minimumFractionDigits: digits,
        maximumFractionDigits: digits,
    }).format(liters);
};

const tabs = [
    { id: 'sales', label: 'Meter Sales', icon: Fuel },
    { id: 'tanks', label: 'Tank Dip', icon: Droplets },
    { id: 'money-in', label: 'Cash In', icon: Wallet },
    { id: 'money-out', label: 'Cash Out', icon: ArrowDownRight },
    { id: 'summary', label: 'Review & Post', icon: Calculator },
];

const completedWorkflowSteps = computed(() => {
    return Object.values(tabsSaved.value).filter(Boolean).length;
});
</script>

<template>
    <Head
        :title="
            isAmendmentMode
                ? `Amend Daily Close - ${originalTransaction?.transaction_number}`
                : 'Daily Close'
        "
    />

    <PageShell
        :title="isAmendmentMode ? 'Amend Daily Close' : 'Daily Close'"
        :description="
            isAmendmentMode
                ? `Amending ${originalTransaction?.transaction_number} for ${form.date}`
                : `Close the day in five steps: meters, tanks, cash in, cash out, and review.`
        "
        :icon="isAmendmentMode ? RotateCcw : Calculator"
        :breadcrumbs="breadcrumbs"
    >
        <DailyCloseNav :company="company" :history="isAmendmentMode" />
        <template v-if="canFillTestData" #actions>
            <Button
                type="button"
                variant="outline"
                @click="fillDummyDailyCloseData"
            >
                <FileWarning class="mr-2 h-4 w-4" />
                Fill test data
            </Button>
        </template>

        <div
            class="mb-4 flex items-center justify-between gap-4 rounded-lg border p-4"
        >
            <p class="text-sm">
                {{
                    parkedDraft
                        ? 'PARKED — changes remain editable.'
                        : 'Business date is the register day. Entry time is recorded separately.'
                }}
            </p>
            <Button
                v-if="!isAmendmentMode"
                variant="outline"
                :disabled="submitting"
                @click="parkDailyClose"
                >Park / Save draft</Button
            >
        </div>
        <InputError v-if="nozzleErrorMessage" class="mb-4" :message="nozzleErrorMessage" />
        <Card v-if="canonicalActivity?.length" class="mb-4">
            <CardHeader
                ><CardTitle>Already recorded for this business date</CardTitle
                ><CardDescription
                    >These canonical records are included in cash
                    reconciliation.</CardDescription
                ></CardHeader
            >
            <CardContent>
                <div
                    v-for="row in canonicalActivity"
                    :key="row.id"
                    class="flex justify-between border-b py-2 text-sm"
                >
                    <span
                        >{{ row.type }} · {{ row.reference }} ·
                        {{ row.business_date }}</span
                    >
                    <MoneyText
                        :amount="row.cash_effect"
                        :currency="company.base_currency"
                    />
                </div>
            </CardContent>
        </Card>
        <!-- Draft Restore Dialog -->
        <Dialog
            :open="showDraftRestoreDialog"
            @update:open="showDraftRestoreDialog = $event"
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle class="flex items-center gap-2">
                        <FileWarning class="h-5 w-5 text-status-attention" />
                        Restore Draft?
                    </DialogTitle>
                    <DialogDescription>
                        You have an unsaved draft for this date from
                        {{ formatDraftTime(draftTimestamp) }}. Would you like to
                        restore it?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="discardDraft"
                        >Discard</Button
                    >
                    <Button @click="restoreDraft">Restore Draft</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Amendment Banner -->
        <div
            v-if="isAmendmentMode"
            class="mb-6 rounded-lg border border-status-attention/30 bg-status-attention/10 p-4"
        >
            <div class="flex items-start gap-3">
                <RotateCcw class="mt-0.5 h-5 w-5 text-status-attention" />
                <div class="flex-1">
                    <h3 class="font-medium text-status-attention">
                        Amending Entry:
                        {{ originalTransaction?.transaction_number }}
                    </h3>
                    <p class="mt-1 text-sm text-status-attention">
                        This will create a reversal of the original entry and
                        post a new corrected entry. Both entries will remain in
                        history for audit purposes.
                    </p>
                    <div class="mt-3">
                        <Label
                            for="amendment-reason"
                            class="text-status-attention"
                            >Reason for Amendment *</Label
                        >
                        <Textarea
                            id="amendment-reason"
                            v-model="amendmentReason"
                            placeholder="Explain why this entry needs to be amended (minimum 10 characters)..."
                            class="mt-1"
                            :class="{
                                'border-status-critical':
                                    amendmentReason.length > 0 &&
                                    amendmentReason.length < 10,
                            }"
                        />
                        <InputError :message="form.errors.amendment_reason" />
                        <p
                            v-if="
                                amendmentReason.length > 0 &&
                                amendmentReason.length < 10
                            "
                            class="mt-1 text-sm text-status-critical"
                        >
                            Please provide at least 10 characters
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <Card class="mb-6 border-border/80">
            <CardContent class="space-y-4 p-4 lg:p-5">
                <div
                    class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div
                        class="grid gap-4 sm:grid-cols-[12rem_1fr] sm:items-end"
                    >
                        <div class="space-y-1.5">
                            <Label>Date</Label>
                            <Input v-model="form.date" data-testid="business-date" type="date" />
                            <p class="text-xs text-muted-foreground">
                                Close each day the next morning, after the tank
                                dip.
                            </p>
                            <InputError :message="form.errors.date" />
                        </div>
                        <div
                            class="rounded-lg border border-border/70 bg-muted/30 px-4 py-3 text-sm text-muted-foreground"
                        >
                            <template v-if="previousClose.exists">
                                Previous close was {{ previousClose.date }} with
                                <MoneyText
                                    :amount="previousClose.closing_cash"
                                    :currency="currencyCode"
                                    :fraction-digits="0"
                                />
                                cash.
                            </template>
                            <template
                                v-else-if="
                                    previousClose.source === 'ledger' &&
                                    previousClose.closing_cash > 0
                                "
                            >
                                No previous close. Opening cash is the ledger
                                cash balance as of the day before:
                                <MoneyText
                                    :amount="previousClose.closing_cash"
                                    :currency="currencyCode"
                                    :fraction-digits="0"
                                />.
                            </template>
                            <template v-else>
                                No previous close found. Opening cash starts
                                from zero unless you enter it under Cash In.
                            </template>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <Badge variant="secondary" class="justify-center">
                            {{ completedWorkflowSteps }}/4 sections saved
                        </Badge>
                        <Badge
                            v-if="cashVariance !== 0"
                            variant="outline"
                            class="border-l-status-attention"
                        >
                            {{ cashVariance > 0 ? 'Cash over' : 'Cash short' }}:
                            <MoneyText
                                :amount="Math.abs(cashVariance)"
                                :currency="currencyCode"
                                :fraction-digits="0"
                            />
                        </Badge>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Tabbed Content -->
        <Tabs v-model="activeTab" class="space-y-6">
            <TabsList
                class="grid h-auto w-full grid-cols-2 gap-1 md:grid-cols-5"
            >
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.id"
                    :value="tab.id"
                    class="min-h-10 gap-2 px-2 text-xs sm:text-sm"
                >
                    <component :is="tab.icon" class="h-4 w-4" />
                    <span class="truncate">{{ tab.label }}</span>
                </TabsTrigger>
            </TabsList>

            <!-- Tab 1: Sales -->
            <TabsContent value="sales">
                <Card>
                    <CardHeader>
                        <CardTitle>Meter Sales</CardTitle>
                        <CardDescription
                            >Enter opening and closing readings for each active
                            nozzle.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Empty State: No nozzles configured -->
                        <div
                            v-if="form.nozzle_readings.length === 0"
                            class="py-12 text-center"
                        >
                            <Fuel
                                class="mx-auto mb-4 h-12 w-12 text-muted-foreground opacity-50"
                            />
                            <h3 class="mb-2 text-lg font-semibold">
                                No Fuel Points Configured
                            </h3>
                            <p
                                class="mx-auto mb-6 max-w-md text-muted-foreground"
                            >
                                To record daily sales, you need to add fuel
                                pumps with nozzles first. Each pump tracks meter
                                readings to calculate your daily sales.
                            </p>
                            <Button as-child>
                                <Link :href="`/${company.slug}/fuel/pumps`">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Fuel Pumps
                                </Link>
                            </Button>
                        </div>

                        <!-- Nozzle Readings grouped by Pump -->
                        <div v-else class="space-y-5">
                            <div
                                v-for="pump in nozzlesByPump"
                                :key="pump.pump_id"
                                class="rounded-lg border"
                            >
                                <!-- Pump Header -->
                                <div
                                    class="flex items-center justify-between border-b bg-muted/40 px-5 py-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <div class="text-base font-semibold">
                                            {{ pump.pump_name }}
                                        </div>
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >{{ pump.fuel_name }}</span
                                        >
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm text-muted-foreground"
                                            >Total:</span
                                        >
                                        <span class="text-lg font-bold"
                                            ><MoneyText
                                                :amount="
                                                    getPumpTotalAmount(
                                                        pump.nozzle_indices,
                                                    )
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                </div>

                                <!-- Automatic Readings Row - Both nozzles in one row -->
                                <div class="px-5 py-4">
                                    <!-- Header Row -->
                                    <div
                                        class="mb-3 grid grid-cols-12 gap-4 text-xs font-medium text-muted-foreground"
                                    >
                                        <div class="col-span-1">Side</div>
                                        <div class="col-span-2 text-right">
                                            Opening
                                        </div>
                                        <div class="col-span-2 text-right">
                                            Closing
                                        </div>
                                        <div class="col-span-2 text-right">
                                            Liters
                                        </div>
                                        <div class="col-span-2 text-right">
                                            Rate/L
                                        </div>
                                        <div class="col-span-3 text-right">
                                            Amount
                                        </div>
                                    </div>

                                    <!-- Automatic Readings - One row per nozzle but visually grouped -->
                                    <div class="space-y-3">
                                        <div
                                            v-for="(
                                                idx, localIdx
                                            ) in pump.nozzle_indices"
                                            :key="
                                                form.nozzle_readings[idx]
                                                    .nozzle_id
                                            "
                                            class="grid grid-cols-12 items-center gap-4"
                                        >
                                            <!-- Nozzle Label (Front/Back) -->
                                            <div class="col-span-1">
                                                <span
                                                    class="text-sm font-medium"
                                                    >{{
                                                        getNozzlePosition(
                                                            localIdx,
                                                            pump.nozzle_indices
                                                                .length,
                                                        ) ||
                                                        form.nozzle_readings[
                                                            idx
                                                        ].nozzle_code
                                                    }}</span
                                                >
                                            </div>
                                            <!-- Opening -->
                                            <div class="col-span-2">
                                                <Input
                                                    v-model.number="
                                                        form.nozzle_readings[
                                                            idx
                                                        ].opening_electronic
                                                    " :data-testid="'nozzle-' + idx + '-opening-electronic'"
                                                    type="number"
                                                    @focus="selectZeroValue"
                                                    step="1"
                                                    class="h-9 bg-muted/30 text-right"
                                                />
                                                <InputError
                                                    :message="
                                                        nozzleError(
                                                            idx,
                                                            'opening_electronic',
                                                        )
                                                    "
                                                />
                                            </div>
                                            <!-- Closing -->
                                            <div class="col-span-2">
                                                <Input
                                                    v-model.number="
                                                        form.nozzle_readings[
                                                            idx
                                                        ].closing_electronic
                                                    " :data-testid="'nozzle-' + idx + '-closing-electronic'"
                                                    type="number"
                                                    @focus="selectZeroValue"
                                                    step="1"
                                                    class="h-9 text-right"
                                                    :aria-invalid="(nozzleMeterWentBackwards(idx) && !form.nozzle_readings[idx].meter_rolled_over) || undefined"
                                                />
                                                <p
                                                    v-if="nozzleMeterWentBackwards(idx) && !form.nozzle_readings[idx].meter_rolled_over"
                                                    class="mt-1 text-xs text-destructive"
                                                >
                                                    Below the opening reading — a pump meter cannot go backwards.
                                                </p>
                                                <!-- Only offered when the reading went down, which is the one
                                                     case a rollover can explain. -->
                                                <div
                                                    v-if="nozzleMeterWentBackwards(idx) || form.nozzle_readings[idx].meter_rolled_over"
                                                    class="mt-1 flex items-center gap-2"
                                                >
                                                    <Checkbox
                                                        :id="'nozzle-' + idx + '-rolled-over'"
                                                        v-model="form.nozzle_readings[idx].meter_rolled_over"
                                                    />
                                                    <Label :for="'nozzle-' + idx + '-rolled-over'" class="text-xs">
                                                        Meter rolled over — it passed its last digit and restarted from zero
                                                    </Label>
                                                </div>
                                                <InputError
                                                    :message="
                                                        nozzleError(
                                                            idx,
                                                            'closing_electronic',
                                                        )
                                                    "
                                                />
                                            </div>
                                            <!-- Liters -->
                                            <div class="col-span-2 text-right">
                                                <span
                                                    class="text-base font-semibold"
                                                    >{{
                                                        form.nozzle_readings[
                                                            idx
                                                        ].liters_sold.toFixed(0)
                                                    }}</span
                                                >
                                                <span
                                                    class="ml-1 text-xs text-muted-foreground"
                                                    >L</span
                                                >
                                            </div>
                                            <!-- Rate -->
                                            <div class="col-span-2">
                                                <Input
                                                    v-model.number="
                                                        form.nozzle_readings[
                                                            idx
                                                        ].sale_rate
                                                    " :data-testid="'nozzle-' + idx + '-sale-rate'"
                                                    type="number"
                                                    @focus="selectZeroValue"
                                                    step="0.01"
                                                    class="h-9 text-right"
                                                />
                                                <InputError
                                                    :message="
                                                        nozzleError(
                                                            idx,
                                                            'sale_rate',
                                                        )
                                                    "
                                                />
                                            </div>
                                            <!-- Amount -->
                                            <div class="col-span-3 text-right">
                                                <span
                                                    class="text-base font-semibold"
                                                    ><MoneyText
                                                        :amount="
                                                            rateAdjustedNozzleRevenue(
                                                                form
                                                                    .nozzle_readings[
                                                                    idx
                                                                ],
                                                            )
                                                        "
                                                        :currency="currencyCode"
                                                        :fraction-digits="0"
                                                /></span>
                                                <div
                                                    v-if="
                                                        rateChangeSplitForReading(
                                                            form
                                                                .nozzle_readings[
                                                                idx
                                                            ],
                                                        )
                                                    "
                                                    class="text-xs text-status-info"
                                                >
                                                    Split by rate-change meter
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Manual Readings (optional) -->
                                    <div
                                        class="mt-4 border-t border-dashed pt-4"
                                    >
                                        <div
                                            class="mb-1 text-xs font-medium text-muted-foreground"
                                        >
                                            Manual Readings (daily, optional)
                                        </div>
                                        <p
                                            class="mb-3 text-xs text-muted-foreground"
                                        >
                                            Use these to verify the electronic
                                            readings for today. Leaving them
                                            blank won’t block submission, and
                                            you can enter or backdate manual
                                            readings later.
                                        </p>
                                        <div class="grid grid-cols-12 gap-4">
                                            <div
                                                v-for="(
                                                    idx, localIdx
                                                ) in pump.nozzle_indices"
                                                :key="
                                                    'manual-' +
                                                    form.nozzle_readings[idx]
                                                        .nozzle_id
                                                "
                                                :class="
                                                    pump.nozzle_indices
                                                        .length === 2
                                                        ? 'col-span-6'
                                                        : 'col-span-12'
                                                "
                                            >
                                                <div
                                                    class="flex items-center gap-3 rounded-md bg-muted/30 p-3"
                                                >
                                                    <span
                                                        class="w-12 text-sm font-medium"
                                                        >{{
                                                            getNozzlePosition(
                                                                localIdx,
                                                                pump
                                                                    .nozzle_indices
                                                                    .length,
                                                            ) || 'Manual'
                                                        }}</span
                                                    >
                                                    <div
                                                        class="flex flex-1 items-center gap-2"
                                                    >
                                                        <div class="flex-1">
                                                            <Input
                                                                v-model.number="
                                                                    form
                                                                        .nozzle_readings[
                                                                        idx
                                                                    ]
                                                                        .opening_manual
                                                                " :data-testid="'nozzle-' + idx + '-opening-manual'"
                                                                type="number"
                                                                @focus="
                                                                    selectZeroValue
                                                                "
                                                                step="1"
                                                                placeholder="Opening"
                                                                class="h-8 text-right text-sm"
                                                            />
                                                            <InputError
                                                                :message="
                                                                    nozzleError(
                                                                        idx,
                                                                        'opening_manual',
                                                                    )
                                                                "
                                                            />
                                                        </div>
                                                        <span
                                                            class="text-muted-foreground"
                                                            >→</span
                                                        >
                                                        <div class="flex-1">
                                                            <Input
                                                                v-model.number="
                                                                    form
                                                                        .nozzle_readings[
                                                                        idx
                                                                    ]
                                                                        .closing_manual
                                                                " :data-testid="'nozzle-' + idx + '-closing-manual'"
                                                                type="number"
                                                                @focus="
                                                                    selectZeroValue
                                                                "
                                                                step="1"
                                                                placeholder="Closing"
                                                                class="h-8 text-right text-sm"
                                                            />
                                                            <InputError
                                                                :message="
                                                                    nozzleError(
                                                                        idx,
                                                                        'closing_manual',
                                                                    )
                                                                "
                                                            />
                                                        </div>
                                                    </div>
                                                    <!-- Variance indicator -->
                                                    <div
                                                        class="w-20 text-right"
                                                    >
                                                        <template
                                                            v-if="
                                                                form
                                                                    .nozzle_readings[
                                                                    idx
                                                                ]
                                                                    .closing_manual &&
                                                                form
                                                                    .nozzle_readings[
                                                                    idx
                                                                ].opening_manual
                                                            "
                                                        >
                                                            <span
                                                                v-if="
                                                                    Math.abs(
                                                                        form
                                                                            .nozzle_readings[
                                                                            idx
                                                                        ]
                                                                            .closing_manual -
                                                                            form
                                                                                .nozzle_readings[
                                                                                idx
                                                                            ]
                                                                                .opening_manual -
                                                                            form
                                                                                .nozzle_readings[
                                                                                idx
                                                                            ]
                                                                                .liters_sold,
                                                                    ) <= 0.5
                                                                "
                                                                class="text-sm font-medium text-status-success"
                                                            >
                                                                <CheckCircle
                                                                    class="inline h-4 w-4"
                                                                />
                                                            </span>
                                                            <span
                                                                v-else
                                                                class="text-sm text-status-attention"
                                                            >
                                                                {{
                                                                    (
                                                                        form
                                                                            .nozzle_readings[
                                                                            idx
                                                                        ]
                                                                            .closing_manual -
                                                                        form
                                                                            .nozzle_readings[
                                                                            idx
                                                                        ]
                                                                            .opening_manual -
                                                                        form
                                                                            .nozzle_readings[
                                                                            idx
                                                                        ]
                                                                            .liters_sold
                                                                    ).toFixed(
                                                                        0,
                                                                    )
                                                                }}L
                                                            </span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pump Total Row -->
                                    <div
                                        class="mt-4 flex items-center justify-between border-t pt-3"
                                    >
                                        <span
                                            class="text-sm font-medium text-muted-foreground"
                                            >Pump Total</span
                                        >
                                        <div class="flex items-center gap-6">
                                            <span class="text-sm"
                                                >{{
                                                    getPumpTotalLiters(
                                                        pump.nozzle_indices,
                                                    ).toFixed(0)
                                                }}
                                                L</span
                                            >
                                            <span class="text-lg font-bold"
                                                ><MoneyText
                                                    :amount="
                                                        getPumpTotalAmount(
                                                            pump.nozzle_indices,
                                                        )
                                                    "
                                                    :currency="currencyCode"
                                                    :fraction-digits="0"
                                            /></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Other Sales (Lubricants, etc.) -->
                        <template
                            v-if="
                                features.has_lubricant_sales &&
                                lubricantItems.length > 0
                            "
                        >
                            <Separator />
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <h4 class="font-medium">
                                        Other Sales (Lubricants, etc.)
                                    </h4>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="addOtherSale"
                                    >
                                        <Plus class="mr-1 h-4 w-4" /> Add Item
                                    </Button>
                                </div>

                                <!-- Header Row -->
                                <div
                                    v-if="form.other_sales.length > 0"
                                    class="grid grid-cols-12 gap-3 px-1 text-xs font-medium text-muted-foreground"
                                >
                                    <div class="col-span-5">Product</div>
                                    <div class="col-span-2 text-right">Qty</div>
                                    <div class="col-span-2 text-right">
                                        Price
                                    </div>
                                    <div class="col-span-2 text-right">
                                        Amount
                                    </div>
                                    <div class="col-span-1"></div>
                                </div>

                                <div
                                    v-for="(sale, index) in form.other_sales"
                                    :key="rowKey(sale)"
                                    class="grid grid-cols-12 items-center gap-3"
                                >
                                    <!-- Product Select -->
                                    <div class="col-span-5">
                                        <Select
                                            v-model="sale.item_id"
                                            @update:model-value="
                                                setOtherSaleItem(index)
                                            "
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Select product"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="item in lubricantItems"
                                                    :key="item.id"
                                                    :value="item.id"
                                                >
                                                    {{ item.name }}
                                                    <span
                                                        v-if="item.brand"
                                                        class="ml-1 text-muted-foreground"
                                                        >({{
                                                            item.brand
                                                        }})</span
                                                    >
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="
                                                otherSaleError(index, 'item_id')
                                            "
                                        />
                                    </div>
                                    <!-- Quantity -->
                                    <div class="col-span-2">
                                        <Input
                                            v-model.number="sale.quantity"
                                            type="number"
                                            @focus="selectZeroValue"
                                            min="1"
                                            step="1"
                                            class="text-right"
                                            @update:model-value="
                                                recalculateOtherSaleAmount(
                                                    index,
                                                )
                                            "
                                        />
                                        <InputError
                                            :message="
                                                otherSaleError(
                                                    index,
                                                    'quantity',
                                                )
                                            "
                                        />
                                    </div>
                                    <!-- Unit Price -->
                                    <div class="col-span-2">
                                        <Input
                                            v-model.number="sale.unit_price"
                                            type="number"
                                            @focus="selectZeroValue"
                                            step="0.01"
                                            class="text-right"
                                            @update:model-value="
                                                recalculateOtherSaleAmount(
                                                    index,
                                                )
                                            "
                                        />
                                        <InputError
                                            :message="
                                                otherSaleError(
                                                    index,
                                                    'unit_price',
                                                )
                                            "
                                        />
                                    </div>
                                    <!-- Amount (calculated) -->
                                    <div
                                        class="col-span-2 text-right font-semibold"
                                    >
                                        <MoneyText
                                            :amount="sale.amount"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                        />
                                    </div>
                                    <!-- Delete -->
                                    <div class="col-span-1 text-right">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="removeOtherSale(index)"
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-destructive"
                                            />
                                        </Button>
                                    </div>
                                </div>

                                <!-- Subtotal -->
                                <div
                                    v-if="form.other_sales.length > 0"
                                    class="flex justify-end border-t pt-2"
                                >
                                    <div class="text-sm">
                                        <span class="mr-2 text-muted-foreground"
                                            >Lubricant Sales:</span
                                        >
                                        <span class="font-semibold"
                                            ><MoneyText
                                                :amount="totalOtherSales"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <Separator />

                        <!-- Sales Summary by Product -->
                        <div class="space-y-3 rounded-lg bg-muted/30 p-4">
                            <h4 class="text-sm font-semibold">Sales Summary</h4>
                            <div class="space-y-2">
                                <!-- Fuel Sales by Type -->
                                <div
                                    v-for="fuel in salesByFuelType"
                                    :key="fuel.fuel_name"
                                    class="flex justify-between text-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <span>{{ fuel.fuel_name }}</span>
                                        <span class="text-muted-foreground"
                                            >({{
                                                fuel.liters.toFixed(0)
                                            }}
                                            L)</span
                                        >
                                    </div>
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="fuel.amount"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <!-- Lubricants/Other -->
                                <div
                                    v-if="totalOtherSales > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Lubricants & Other</span>
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="totalOtherSales"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <Separator />
                                <!-- Grand Total -->
                                <div
                                    class="flex justify-between text-base font-semibold"
                                >
                                    <span>Total Sales</span>
                                    <span
                                        ><MoneyText
                                            :amount="totalSales"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer with Submit -->
                        <div class="flex justify-end">
                            <Button
                                @click="saveSales"
                                :variant="
                                    tabsSaved.sales ? 'outline' : 'default'
                                "
                                class="min-w-32"
                            >
                                <CheckCircle
                                    v-if="tabsSaved.sales"
                                    class="mr-2 h-4 w-4 text-status-success"
                                />
                                <Save v-else class="mr-2 h-4 w-4" />
                                {{
                                    tabsSaved.sales
                                        ? 'Saved'
                                        : 'Save Meter Sales'
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Tab 2: Tank Readings -->
            <TabsContent value="tanks">
                <Card>
                    <CardHeader>
                        <CardTitle>Tank Dip</CardTitle>
                        <CardDescription
                            >Enter physical tank measurements and review stock
                            variance.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Empty State: No tanks configured -->
                        <div
                            v-if="form.tank_readings.length === 0"
                            class="py-12 text-center"
                        >
                            <Droplets
                                class="mx-auto mb-4 h-12 w-12 text-muted-foreground opacity-50"
                            />
                            <h3 class="mb-2 text-lg font-semibold">
                                No Storage Tanks Configured
                            </h3>
                            <p
                                class="mx-auto mb-6 max-w-md text-muted-foreground"
                            >
                                Tank readings help track fuel inventory and
                                detect shrinkage. Add storage tanks to enable
                                this feature.
                            </p>
                            <Button as-child>
                                <Link :href="`/${company.slug}/fuel/tanks`">
                                    <Plus class="mr-2 h-4 w-4" />
                                    Add Storage Tanks
                                </Link>
                            </Button>
                        </div>

                        <!-- Tank Reading Cards -->
                        <template v-else>
                            <div
                                v-for="(tank, index) in form.tank_readings"
                                :key="tank.tank_id"
                                class="rounded-lg border"
                            >
                                <!-- Tank Header -->
                                <div
                                    class="flex items-center justify-between border-b bg-muted/40 px-4 py-3"
                                >
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex flex-col items-center gap-1"
                                        >
                                            <TankLevelGauge
                                                :percent="
                                                    getTankFillPercent(tank)
                                                "
                                                :size="36"
                                            />
                                            <span
                                                class="text-[11px] text-muted-foreground"
                                                >{{
                                                    getTankFillPercent(tank)
                                                }}%</span
                                            >
                                        </div>
                                        <div>
                                            <div
                                                class="text-base font-semibold"
                                            >
                                                {{ tank.tank_name }}
                                            </div>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="text-sm text-muted-foreground"
                                                    >{{ tank.fuel_name }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="tank.dip_stick_code"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Dip Stick: {{ tank.dip_stick_code }}
                                    </div>
                                </div>

                                <div class="space-y-4 p-4">
                                    <!-- Readings Row -->
                                    <div class="grid grid-cols-12 gap-4">
                                        <!-- Opening baseline -->
                                        <div class="col-span-3">
                                            <Label
                                                class="text-xs text-muted-foreground"
                                                >Opening stock on
                                                {{
                                                    formatBaselineDate(
                                                        form.date,
                                                    )
                                                }}
                                                (L)</Label
                                            >
                                            <div
                                                class="mt-1 text-lg font-semibold"
                                            >
                                                {{
                                                    tank.previous_liters > 0
                                                        ? formatLiters(
                                                              tank.previous_liters,
                                                          )
                                                        : '—'
                                                }}
                                            </div>
                                            <div
                                                v-if="baselineLabel(tank)"
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ baselineLabel(tank) }}
                                            </div>
                                            <div
                                                v-else
                                                class="text-xs text-status-attention"
                                            >
                                                No opening stock yet — record
                                                opening stock in Fuel setup, or
                                                post the previous day's close.
                                            </div>
                                            <div
                                                v-if="tank.previous_stick > 0"
                                                class="text-xs text-muted-foreground"
                                            >
                                                Stick:
                                                {{ tank.previous_stick }} cm
                                            </div>
                                            <div
                                                class="mt-2 rounded-md bg-muted/60 px-2 py-1.5 text-xs"
                                            >
                                                <div
                                                    class="font-medium text-foreground"
                                                >
                                                    Expected this morning:
                                                    {{
                                                        formatLiters(
                                                            expectedTankClosingLiters(
                                                                tank,
                                                            ),
                                                        )
                                                    }}
                                                    L
                                                </div>
                                                <div
                                                    class="text-muted-foreground"
                                                >
                                                    {{
                                                        formatLiters(
                                                            tank.previous_liters,
                                                        )
                                                    }}
                                                    L opening
                                                    <span
                                                        v-if="
                                                            Math.abs(
                                                                tank.stock_movements_since_baseline_liters ||
                                                                    0,
                                                            ) >= 0.001
                                                        "
                                                    >
                                                        {{
                                                            (tank.stock_movements_since_baseline_liters ||
                                                                0) > 0
                                                                ? '+'
                                                                : '-'
                                                        }}
                                                        {{
                                                            formatLiters(
                                                                Math.abs(
                                                                    tank.stock_movements_since_baseline_liters ||
                                                                        0,
                                                                ),
                                                            )
                                                        }}
                                                        L stock
                                                    </span>
                                                    <span
                                                        v-if="
                                                            (litersSoldByTank[
                                                                tank.tank_id
                                                            ] || 0) > 0
                                                        "
                                                    >
                                                        -
                                                        {{
                                                            formatLiters(
                                                                litersSoldByTank[
                                                                    tank.tank_id
                                                                ] || 0,
                                                            )
                                                        }}
                                                        L sales
                                                    </span>
                                                </div>
                                                <div
                                                    class="text-muted-foreground"
                                                >
                                                    {{
                                                        stockMovementLabel(
                                                            tank.stock_movements_since_baseline_liters ||
                                                                0,
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    v-if="
                                                        tank.current_stock_after_close_date
                                                    "
                                                    class="text-status-attention"
                                                >
                                                    This stock entry is after
                                                    the selected close date, so
                                                    it is not used as the
                                                    opening baseline.
                                                </div>
                                                <div
                                                    v-if="
                                                        (tank.pending_deliveries ||
                                                            []
                                                        ).length > 0
                                                    "
                                                    class="mt-1.5 border-t border-border/60 pt-1.5 text-status-attention"
                                                >
                                                    <div class="font-medium">
                                                        +
                                                        {{
                                                            formatLiters(
                                                                tank.pending_delivery_liters ||
                                                                    0,
                                                            )
                                                        }}
                                                        L delivered, not yet
                                                        received
                                                    </div>
                                                    <div
                                                        v-for="delivery in tank.pending_deliveries"
                                                        :key="delivery.bill_id"
                                                    >
                                                        <a
                                                            :href="`/${props.company.slug}/bills/${delivery.bill_id}`"
                                                            target="_blank"
                                                            class="underline hover:no-underline"
                                                        >
                                                            {{
                                                                delivery.bill_number
                                                            }}
                                                        </a>
                                                        ·
                                                        {{
                                                            formatBaselineDate(
                                                                delivery.bill_date,
                                                            )
                                                        }}
                                                        ·
                                                        {{
                                                            formatLiters(
                                                                delivery.litres,
                                                            )
                                                        }}
                                                        L
                                                    </div>
                                                    <div
                                                        class="mt-1 text-muted-foreground"
                                                    >
                                                        Received into the tank
                                                        when this close is
                                                        posted.
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Today's Reading Inputs -->
                                        <div class="col-span-2">
                                            <Label class="text-xs"
                                                >Stick Reading (cm)</Label
                                            >
                                            <Input
                                                v-model.number="
                                                    tank.stick_reading
                                                " :data-testid="'tank-' + index + '-stick'"
                                                type="number"
                                                step="0.1"
                                                placeholder="cm"
                                                @focus="selectZeroValue"
                                                class="mt-1"
                                            />
                                            <InputError
                                                :message="
                                                    tankReadingError(
                                                        index,
                                                        'stick_reading',
                                                    )
                                                "
                                            />
                                        </div>
                                        <div class="col-span-2">
                                            <Label class="text-xs"
                                                >Dip this morning (L)</Label
                                            >
                                            <Input
                                                v-model.number="tank.liters" :data-testid="'tank-' + index + '-liters'"
                                                type="number"
                                                step="1"
                                                @focus="selectZeroValue"
                                                class="mt-1"
                                            />
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                Taken the morning after
                                                {{
                                                    formatBaselineDate(
                                                        form.date,
                                                    )
                                                }}. It closes that day and opens
                                                the next.
                                            </p>
                                            <InputError
                                                :message="
                                                    tankReadingError(
                                                        index,
                                                        'liters',
                                                    )
                                                "
                                            />
                                        </div>

                                        <!-- Calculated Values -->
                                        <div class="col-span-2">
                                            <Label
                                                class="text-xs text-muted-foreground"
                                                >Used since yesterday's
                                                dip</Label
                                            >
                                            <div
                                                class="mt-1 text-base font-medium"
                                            >
                                                {{
                                                    tank.previous_liters > 0 &&
                                                    tank.liters > 0
                                                        ? formatLiters(
                                                              tank.previous_liters -
                                                                  tank.liters,
                                                          )
                                                        : '—'
                                                }}
                                                L
                                            </div>
                                        </div>
                                        <div class="col-span-2">
                                            <Label
                                                class="text-xs text-muted-foreground"
                                                >{{
                                                    tankSalesLabel(tank)
                                                }}</Label
                                            >
                                            <div
                                                class="mt-1 text-base font-medium"
                                            >
                                                {{
                                                    formatLiters(
                                                        litersSoldByTank[
                                                            tank.tank_id
                                                        ] || 0,
                                                    )
                                                }}
                                                L
                                            </div>
                                        </div>

                                        <!-- Variance -->
                                        <div class="col-span-1">
                                            <Label
                                                class="text-xs text-muted-foreground"
                                                >Variance</Label
                                            >
                                            <div
                                                v-if="
                                                    tank.previous_liters > 0 &&
                                                    tank.liters > 0
                                                "
                                                class="mt-1"
                                            >
                                                <span
                                                    :class="[
                                                        'text-base font-semibold',
                                                        tankVariances[index]
                                                            ?.variance > 0
                                                            ? 'text-status-critical'
                                                            : tankVariances[
                                                                    index
                                                                ]?.variance < 0
                                                              ? 'text-status-attention'
                                                              : 'text-status-success',
                                                    ]"
                                                >
                                                    {{
                                                        tankVariances[index]
                                                            ?.variance > 0
                                                            ? '-'
                                                            : tankVariances[
                                                                    index
                                                                ]?.variance < 0
                                                              ? '+'
                                                              : ''
                                                    }}{{
                                                        Math.abs(
                                                            tankVariances[index]
                                                                ?.variance || 0,
                                                        ).toFixed(0)
                                                    }}
                                                    L
                                                </span>
                                            </div>
                                            <div
                                                v-else
                                                class="mt-1 text-base text-muted-foreground"
                                            >
                                                —
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Variance Explanation (if significant) -->
                                    <div
                                        v-if="
                                            tank.previous_liters > 0 &&
                                            tank.liters > 0 &&
                                            Math.abs(
                                                tankVariances[index]
                                                    ?.variance || 0,
                                            ) > 5
                                        "
                                        class="rounded-md px-3 py-2 text-xs"
                                        :class="
                                            tankVariances[index]?.variance > 0
                                                ? 'bg-status-critical/10 text-status-critical'
                                                : 'bg-status-attention/10 text-status-attention'
                                        "
                                    >
                                        <span
                                            v-if="
                                                tankVariances[index]?.variance >
                                                0
                                            "
                                        >
                                            Loss of
                                            {{
                                                Math.abs(
                                                    tankVariances[index]
                                                        ?.variance,
                                                ).toFixed(0)
                                            }}L detected ({{
                                                Math.abs(
                                                    tankVariances[index]
                                                        ?.variance_percent,
                                                ).toFixed(1)
                                            }}% of sales) — may indicate
                                            evaporation, leakage, or measurement
                                            error
                                        </span>
                                        <span v-else>
                                            Gain of
                                            {{
                                                Math.abs(
                                                    tankVariances[index]
                                                        ?.variance,
                                                ).toFixed(0)
                                            }}L detected — may indicate
                                            measurement error or unrecorded
                                            receipt
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Summary -->
                            <div
                                v-if="
                                    form.tank_readings.some(
                                        (t) =>
                                            t.previous_liters > 0 &&
                                            t.liters > 0,
                                    )
                                "
                                class="rounded-lg bg-muted/50 p-4"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="text-sm font-medium">
                                        Total Variance
                                    </div>
                                    <div
                                        :class="[
                                            'text-lg font-bold',
                                            totalTankVariance > 0
                                                ? 'text-status-critical'
                                                : totalTankVariance < 0
                                                  ? 'text-status-attention'
                                                  : 'text-status-success',
                                        ]"
                                    >
                                        {{
                                            totalTankVariance > 0
                                                ? 'Loss: '
                                                : totalTankVariance < 0
                                                  ? 'Gain: '
                                                  : ''
                                        }}{{
                                            Math.abs(totalTankVariance).toFixed(
                                                0,
                                            )
                                        }}
                                        L
                                    </div>
                                </div>
                            </div>

                            <Separator />

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <Button
                                    @click="saveTanks"
                                    :variant="
                                        tabsSaved.tanks ? 'outline' : 'default'
                                    "
                                    class="min-w-32"
                                >
                                    <CheckCircle
                                        v-if="tabsSaved.tanks"
                                        class="mr-2 h-4 w-4 text-status-success"
                                    />
                                    <Save v-else class="mr-2 h-4 w-4" />
                                    {{
                                        tabsSaved.tanks
                                            ? 'Saved'
                                            : 'Save Tank Dip'
                                    }}
                                </Button>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Tab 3: Money In -->
            <TabsContent value="money-in">
                <Card>
                    <CardHeader>
                        <CardTitle>Cash In</CardTitle>
                        <CardDescription
                            >Opening cash, cash sales, deposits, and non-cash
                            receipts.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Opening Cash -->
                        <div class="rounded-lg bg-muted/50 p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <Label>Opening Cash Balance</Label>
                                    <p class="text-xs text-muted-foreground">
                                        Carried forward from previous day
                                    </p>
                                </div>
                                <div class="w-48">
                                    <Input
                                        v-model.number="form.opening_cash" data-testid="opening-cash"
                                        type="number"
                                        @focus="selectZeroValue"
                                        class="text-right text-lg font-semibold"
                                    />
                                    <InputError
                                        :message="form.errors.opening_cash"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <Label>Total Sales</Label>
                                    <p class="text-xs text-muted-foreground">
                                        Fuel + other sales, including card /
                                        bank sales (moved to Money Out)
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.fuelSales }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-semibold">
                                        <MoneyText
                                            :amount="totalSales"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Partner Deposits (only if partners feature enabled) -->
                        <template
                            v-if="features.has_partners && partners.length > 0"
                        >
                            <Separator />

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">
                                            Partner Deposits
                                        </h4>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ accountingHints.partnerDeposit }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="addPartnerDeposit"
                                    >
                                        <Plus class="mr-1 h-4 w-4" /> Add
                                    </Button>
                                </div>

                                <Input
                                    v-model="partnerSearch"
                                    placeholder="Search partners by name"
                                    class="max-w-sm"
                                />

                                <div
                                    v-for="(
                                        deposit, index
                                    ) in form.partner_deposits"
                                    :key="rowKey(deposit)"
                                    class="flex items-end gap-4"
                                >
                                    <div class="flex-1">
                                        <Label class="text-xs">Partner</Label>
                                        <Select
                                            v-model="deposit.partner_id"
                                            @update:model-value="
                                                setPartnerName(
                                                    index,
                                                    'deposits',
                                                )
                                            "
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Select partner"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="p in filteredPartners"
                                                    :key="p.id"
                                                    :value="p.id"
                                                >
                                                    {{ p.name }} · Capital
                                                    <MoneyText
                                                        :amount="p.net_capital"
                                                        :currency="currencyCode"
                                                        :fraction-digits="0"
                                                    />
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p
                                            v-if="
                                                getPartner(deposit.partner_id)
                                            "
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Current capital:
                                            <MoneyText
                                                :amount="
                                                    getPartner(
                                                        deposit.partner_id,
                                                    )?.net_capital || 0
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                            />
                                        </p>
                                        <InputError
                                            :message="
                                                partnerDepositError(
                                                    index,
                                                    'partner_id',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="w-32">
                                        <Label class="text-xs">Amount</Label>
                                        <Input
                                            v-model.number="deposit.amount"
                                            type="number"
                                            @focus="selectZeroValue"
                                        />
                                        <InputError
                                            :message="
                                                partnerDepositError(
                                                    index,
                                                    'amount',
                                                )
                                            "
                                        />
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="removePartnerDeposit(index)"
                                    >
                                        <Trash2
                                            class="h-4 w-4 text-destructive"
                                        />
                                    </Button>
                                </div>
                            </div>
                        </template>

                        <Separator />

                        <template v-if="features.has_amanat">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">
                                            Amanat Deposits
                                        </h4>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ accountingHints.amanatDeposit }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            props.amanatHolders.length === 0
                                        "
                                        @click="addAmanatDeposit"
                                    >
                                        <Plus class="mr-1 h-4 w-4" /> Add
                                    </Button>
                                </div>

                                <div
                                    v-if="props.amanatHolders.length === 0"
                                    class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <span
                                            >No Amanat depositors are available
                                            for deposits yet.</span
                                        >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="`/${company.slug}/fuel/amanat`"
                                                >Add Amanat depositor</Link
                                            >
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    v-for="(
                                        deposit, index
                                    ) in form.amanat_deposits"
                                    :key="rowKey(deposit)"
                                    class="grid grid-cols-12 items-end gap-3"
                                >
                                    <div class="col-span-5">
                                        <Label class="text-xs">Depositor</Label>
                                        <Select
                                            v-model="deposit.customer_id"
                                            :disabled="
                                                props.amanatHolders.length === 0
                                            "
                                            @update:model-value="
                                                setAmanatDepositCustomer(index)
                                            "
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Select depositor"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="holder in props.amanatHolders"
                                                    :key="holder.id"
                                                    :value="holder.id"
                                                >
                                                    {{ holder.name }} · Balance
                                                    <MoneyText
                                                        :amount="
                                                            holder.amanat_balance
                                                        "
                                                        :currency="currencyCode"
                                                        :fraction-digits="0"
                                                    />
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="
                                                amanatDepositError(
                                                    index,
                                                    'customer_id',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="col-span-3">
                                        <Label class="text-xs">Receive into</Label>
                                        <Select v-model="deposit.payment_account_id">
                                            <SelectTrigger><SelectValue placeholder="Cash on Hand" /></SelectTrigger>
                                            <SelectContent>
                                        <SelectItem v-for="account in props.paymentAccounts" :key="account.id" :value="account.id">
                                                    {{ account.code }} - {{ account.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="amanatDepositError(index, 'payment_account_id')" />
                                    </div>
                                    <div class="col-span-3">
                                        <Label class="text-xs">Reference</Label>
                                        <Input
                                            v-model="deposit.reference"
                                            placeholder="Optional"
                                        />
                                        <InputError
                                            :message="
                                                amanatDepositError(
                                                    index,
                                                    'reference',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="col-span-3">
                                        <Label class="text-xs">Amount</Label>
                                        <Input
                                            v-model.number="deposit.amount"
                                            type="number"
                                            @focus="selectZeroValue"
                                        />
                                        <InputError
                                            :message="
                                                amanatDepositError(
                                                    index,
                                                    'amount',
                                                )
                                            "
                                        />
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="removeAmanatDeposit(index)"
                                    >
                                        <Trash2
                                            class="h-4 w-4 text-destructive"
                                        />
                                    </Button>
                                </div>
                            </div>

                            <Separator />
                        </template>

                        <div
                            v-if="props.unpaidDirectDeliveries?.length"
                            class="space-y-3"
                        >
                            <div>
                                <h4 class="font-medium">Direct deliveries not yet paid</h4>
                                <p class="text-xs text-muted-foreground">
                                    Fuel sold straight to a customer on this day. If they paid in
                                    cash, record it here and it is counted in today's money in.
                                </p>
                            </div>
                            <div
                                v-for="invoice in props.unpaidDirectDeliveries"
                                :key="invoice.id"
                                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
                            >
                                <div class="text-sm">
                                    <Link
                                        :href="`/${props.company.slug}/invoices/${invoice.id}`"
                                        class="font-medium underline-offset-2 hover:underline"
                                        >{{ invoice.invoice_number }}</Link
                                    >
                                    <span class="text-muted-foreground">
                                        · {{ invoice.customer_name || 'Customer' }} ·
                                    </span>
                                    <MoneyText
                                        :amount="invoice.balance"
                                        :currency="currencyCode"
                                        :fraction-digits="0"
                                    />
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="receivingDirectCash !== null"
                                    @click="receiveDirectDeliveryCash(invoice.id)"
                                    >Received in cash</Button
                                >
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Other Cash In</h4>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.otherDeposit }}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="addOtherDeposit"
                                >
                                    <Plus class="mr-1 h-4 w-4" /> Add
                                </Button>
                            </div>

                            <div
                                v-for="(deposit, index) in form.other_deposits"
                                :key="rowKey(deposit)"
                                class="grid grid-cols-12 items-end gap-3"
                            >
                                <div class="col-span-3">
                                    <Label class="text-xs">Type</Label>
                                    <Select v-model="deposit.deposit_type">
                                        <SelectTrigger>
                                            <SelectValue
                                                placeholder="Select type"
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="type in otherDepositTypes"
                                                :key="type.value"
                                                :value="type.value"
                                            >
                                                {{ type.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            otherDepositError(
                                                index,
                                                'deposit_type',
                                            )
                                        "
                                    />
                                </div>
                                <div class="col-span-4">
                                    <Label class="text-xs">Account</Label>
                                    <Select
                                        v-model="deposit.account_id"
                                        :disabled="
                                            deposit.deposit_type ===
                                            'loss_compensation'
                                        "
                                    >
                                        <SelectTrigger>
                                            <SelectValue
                                                :placeholder="
                                                    deposit.deposit_type ===
                                                    'loss_compensation'
                                                        ? 'Cash Over/Short'
                                                        : 'Select account'
                                                "
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="account in props.otherDepositAccounts"
                                                :key="account.id"
                                                :value="account.id"
                                            >
                                                {{ account.code }} —
                                                {{ account.name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            otherDepositError(
                                                index,
                                                'account_id',
                                            )
                                        "
                                    />
                                </div>
                                <div class="col-span-3">
                                    <Label class="text-xs">Description</Label>
                                    <Input
                                        v-model="deposit.description"
                                        :placeholder="
                                            getOtherDepositTypeLabel(
                                                deposit.deposit_type,
                                            )
                                        "
                                    />
                                    <InputError
                                        :message="
                                            otherDepositError(
                                                index,
                                                'description',
                                            )
                                        "
                                    />
                                </div>
                                <div class="col-span-1">
                                    <Label class="text-xs">Amount</Label>
                                    <Input
                                        v-model.number="deposit.amount"
                                        type="number"
                                        @focus="selectZeroValue"
                                    />
                                    <InputError
                                        :message="
                                            otherDepositError(index, 'amount')
                                        "
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="removeOtherDeposit(index)"
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <template
                            v-if="
                                features.has_investors && investors.length > 0
                            "
                        >
                            <Separator />

                            <div class="space-y-3 rounded-lg border p-4">
                                <div>
                                    <h4 class="font-medium">
                                        Investor Balances
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        Live lookup only. Investor lot posting
                                        stays in investor tools.
                                    </p>
                                </div>
                                <Input
                                    v-model="investorSearch"
                                    placeholder="Search investors by name"
                                    class="max-w-sm"
                                />
                                <div class="grid gap-2 md:grid-cols-2">
                                    <div
                                        v-for="investor in filteredInvestors.slice(
                                            0,
                                            6,
                                        )"
                                        :key="investor.id"
                                        class="rounded-md border bg-muted/20 p-3 text-sm"
                                    >
                                        <div class="font-medium">
                                            {{ investor.name }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            Invested
                                            <MoneyText
                                                :amount="
                                                    investor.total_invested
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                            />
                                            · Remaining
                                            {{
                                                formatLiters(
                                                    investor.units_remaining,
                                                    2,
                                                )
                                            }}L · Commission due
                                            <MoneyText
                                                :amount="
                                                    investor.outstanding_commission
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Cash Withdrawn from Bank</h4>
                                    <p class="text-xs text-muted-foreground">
                                        Cash collected from your bank and added to the station drawer.
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="addBankWithdrawal"
                                >
                                    <Plus class="mr-1 h-4 w-4" /> Add
                                </Button>
                            </div>

                            <div
                                v-for="(deposit, index) in form.bank_withdrawals"
                                :key="rowKey(deposit)"
                                class="grid grid-cols-5 items-end gap-4"
                            >
                                <div>
                                    <Label class="text-xs">Bank Account</Label>
                                    <Select v-model="deposit.bank_account_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="b in bankAccounts"
                                                :key="b.id"
                                                :value="b.id"
                                                >{{ b.name }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            bankWithdrawalError(
                                                index,
                                                'bank_account_id',
                                            )
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Amount</Label>
                                    <Input
                                        v-model.number="deposit.amount"
                                        type="number"
                                        @focus="selectZeroValue"
                                    />
                                    <InputError
                                        :message="
                                            bankWithdrawalError(index, 'amount')
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Reference</Label>
                                    <Input
                                        v-model="deposit.reference"
                                        placeholder="Slip #"
                                    />
                                    <InputError
                                        :message="
                                            bankWithdrawalError(index, 'reference')
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Purpose</Label>
                                    <Input
                                        v-model="deposit.purpose"
                                        placeholder="e.g., cash for station expenses"
                                    />
                                    <InputError
                                        :message="
                                            bankWithdrawalError(index, 'purpose')
                                        "
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="removeBankWithdrawal(index)"
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>

                        <div v-if="totalBankWithdrawals" class="flex justify-between text-sm"><span>Cash Withdrawn from Bank</span><MoneyText :amount="totalBankWithdrawals" :currency="currencyCode" /></div>

                        <PaymentsReceivedEntry
                            v-model="form.payments_received"
                            :errors="form.errors as Record<string, string>"
                            :disabled="submitting || form.processing"
                            :open-invoices="props.openInvoices ?? []"
                            :payment-accounts="(props as any).paymentAccounts ?? []"
                            :currency="currencyCode"
                        />
                        <Separator />

                        <!-- Money In Summary -->
                        <div class="space-y-3 rounded-lg bg-muted/30 p-4">
                            <h4 class="text-sm font-semibold">
                                Money In Summary
                            </h4>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Opening Cash</span>
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="form.opening_cash"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <div
                                    v-if="totalPartnerDeposits > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Partner Deposits</span>
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="totalPartnerDeposits"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <div
                                    v-if="totalAmanatDeposits > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Amanat Deposits</span>
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="totalAmanatDeposits"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <div
                                    v-if="totalOtherDeposits > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Other Cash In</span>
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="totalOtherDeposits"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Total Sales</span>
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="totalSales"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <template v-if="recordedMoneyIn.length">
                                    <p class="pt-1 text-xs font-medium text-muted-foreground">
                                        Recorded on other screens this day
                                    </p>
                                    <div
                                        v-for="row in recordedMoneyIn"
                                        :key="row.key"
                                        class="flex justify-between text-sm"
                                    >
                                        <span>{{ row.label }}</span>
                                        <span class="font-medium"
                                            ><MoneyText
                                                :amount="Math.abs(row.amount)"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                </template>
                                <Separator />
                                <div
                                    class="flex justify-between text-base font-semibold"
                                >
                                    <span>Total Money In</span>
                                    <span
                                        ><MoneyText
                                            :amount="shownMoneyIn"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end pt-2">
                            <Button
                                @click="saveMoneyIn"
                                :variant="
                                    tabsSaved.moneyIn ? 'outline' : 'default'
                                "
                                class="min-w-32"
                            >
                                <CheckCircle
                                    v-if="tabsSaved.moneyIn"
                                    class="mr-2 h-4 w-4 text-status-success"
                                />
                                <Save v-else class="mr-2 h-4 w-4" />
                                {{
                                    tabsSaved.moneyIn ? 'Saved' : 'Save Cash In'
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Tab 4: Money Out -->
            <TabsContent value="money-out">
                <Card>
                    <CardHeader>
                        <CardTitle>Cash Out</CardTitle>
                        <CardDescription
                            >Bank deposits, withdrawals, advances, amanat, and
                            expenses.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Sales that went to bank / card accounts (Money Out: they never reached the drawer) -->
                        <CreditSalesEntry v-model="form.credit_sales" :errors="form.errors as Record<string, string>" :disabled="submitting || form.processing" :company-slug="props.company.slug" :currency="currencyCode" />
                        <Separator />
                        <div class="space-y-4">
                            <div>
                                <h4 class="text-sm font-semibold">
                                    Sales that went to bank / card accounts
                                </h4>
                                <p class="text-xs text-muted-foreground">
                                    Card swipes, transfers and fuel-card sales
                                    are already inside Total Sales. Enter them
                                    here so they are taken out of expected cash.
                                </p>
                            </div>
                            <template
                                v-for="channel in enabledChannels"
                                :key="channel.code"
                            >
                                <div
                                    v-if="channel.type !== 'cash'"
                                    class="space-y-4"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <h4 class="font-medium">
                                                {{ channel.label }}
                                            </h4>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    channel.type ===
                                                    'bank_transfer'
                                                        ? 'Bank transfer payments received'
                                                        : ''
                                                }}
                                                {{
                                                    channel.type === 'card_pos'
                                                        ? 'Credit/debit card swipes'
                                                        : ''
                                                }}
                                                {{
                                                    channel.type === 'fuel_card'
                                                        ? `${fuelCardLabel} sales (goes to clearing)`
                                                        : ''
                                                }}
                                                {{
                                                    channel.type ===
                                                    'mobile_wallet'
                                                        ? 'Mobile wallet payments'
                                                        : ''
                                                }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    accountingHints.nonCashReceipt
                                                }}
                                            </p>
                                        </div>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            @click="
                                                addPaymentEntry(channel.code)
                                            "
                                        >
                                            <Plus class="mr-1 h-4 w-4" /> Add
                                        </Button>
                                    </div>

                                    <div
                                        v-for="(entry, index) in form
                                            .payment_receipts[channel.code]
                                            ?.entries || []"
                                        :key="rowKey(entry)"
                                        class="flex items-end gap-4"
                                    >
                                        <!-- Reference field varies by type -->
                                        <div
                                            v-if="channel.type === 'card_pos'"
                                            class="w-32"
                                        >
                                            <Label class="text-xs"
                                                >Last 4 Digits</Label
                                            >
                                            <Input
                                                v-model="entry.last_four"
                                                maxlength="4"
                                                placeholder="1234"
                                            />
                                            <InputError
                                                :message="
                                                    paymentReceiptError(
                                                        channel.code,
                                                        index,
                                                        'last_four',
                                                    )
                                                "
                                            />
                                        </div>
                                        <div v-else class="flex-1">
                                            <Label class="text-xs">{{
                                                channel.type === 'bank_transfer'
                                                    ? 'Customer / Reference'
                                                    : 'Card / Reference'
                                            }}</Label>
                                            <Input
                                                v-model="entry.reference"
                                                :placeholder="
                                                    channel.type ===
                                                    'bank_transfer'
                                                        ? 'Customer name or slip #'
                                                        : 'Card #'
                                                "
                                            />
                                            <InputError
                                                :message="
                                                    paymentReceiptError(
                                                        channel.code,
                                                        index,
                                                        'reference',
                                                    )
                                                "
                                            />
                                        </div>
                                        <div class="w-32">
                                            <Label class="text-xs"
                                                >Amount</Label
                                            >
                                            <Input
                                                v-model.number="entry.amount"
                                                type="number"
                                                @focus="selectZeroValue"
                                            />
                                            <InputError
                                                :message="
                                                    paymentReceiptError(
                                                        channel.code,
                                                        index,
                                                        'amount',
                                                    )
                                                "
                                            />
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            @click="
                                                removePaymentEntry(
                                                    channel.code,
                                                    index,
                                                )
                                            "
                                        >
                                            <Trash2
                                                class="h-4 w-4 text-destructive"
                                            />
                                        </Button>
                                    </div>

                                    <!-- Channel subtotal -->
                                    <div
                                        v-if="
                                            (form.payment_receipts[channel.code]
                                                ?.entries?.length || 0) > 0
                                        "
                                        class="text-right text-sm text-muted-foreground"
                                    >
                                        Subtotal:
                                        <MoneyText
                                            :amount="
                                                getChannelTotal(channel.code)
                                            "
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                        />
                                    </div>
                                </div>
                            </template>
                        </div>

                        <Separator />

                        <!-- Bank Deposits -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Bank Deposits</h4>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.bankDeposit }}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="addBankDeposit"
                                >
                                    <Plus class="mr-1 h-4 w-4" /> Add
                                </Button>
                            </div>

                            <div
                                v-for="(deposit, index) in form.bank_deposits"
                                :key="deposit.uid ?? `deposit-${index}`"
                                class="grid grid-cols-5 items-end gap-4"
                            >
                                <div>
                                    <Label class="text-xs">Bank Account</Label>
                                    <Select v-model="deposit.bank_account_id">
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="b in bankAccounts"
                                                :key="b.id"
                                                :value="b.id"
                                                >{{ b.name }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            bankDepositError(
                                                index,
                                                'bank_account_id',
                                            )
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Amount</Label>
                                    <Input
                                        v-model.number="deposit.amount"
                                        type="number"
                                        @focus="selectZeroValue"
                                    />
                                    <InputError
                                        :message="
                                            bankDepositError(index, 'amount')
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Reference</Label>
                                    <Input
                                        v-model="deposit.reference"
                                        placeholder="Slip #"
                                    />
                                    <InputError
                                        :message="
                                            bankDepositError(index, 'reference')
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Purpose</Label>
                                    <Input
                                        v-model="deposit.purpose"
                                        placeholder="e.g., vendor card payment"
                                    />
                                    <InputError
                                        :message="
                                            bankDepositError(index, 'purpose')
                                        "
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="removeBankDeposit(index)"
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>

                        <!-- Partner Withdrawals (only if partners feature enabled) -->
                        <template
                            v-if="features.has_partners && partners.length > 0"
                        >
                            <Separator />

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">
                                            Partner Withdrawals
                                        </h4>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                accountingHints.partnerWithdrawal
                                            }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="addPartnerWithdrawal"
                                    >
                                        <Plus class="mr-1 h-4 w-4" /> Add
                                    </Button>
                                </div>

                                <Input
                                    v-model="partnerSearch"
                                    placeholder="Search partners by name"
                                    class="max-w-sm"
                                />

                                <div
                                    v-for="(
                                        withdrawal, index
                                    ) in form.partner_withdrawals"
                                    :key="rowKey(withdrawal)"
                                    class="flex items-end gap-4"
                                >
                                    <div class="flex-1">
                                        <Label class="text-xs">Partner</Label>
                                        <Select
                                            v-model="withdrawal.partner_id"
                                            @update:model-value="
                                                setPartnerName(
                                                    index,
                                                    'withdrawals',
                                                )
                                            "
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Select partner"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="p in filteredPartners"
                                                    :key="p.id"
                                                    :value="p.id"
                                                >
                                                    {{ p.name }}
                                                    ·
                                                    <template
                                                        v-if="
                                                            p.remaining_drawing_limit ===
                                                            null
                                                        "
                                                        >No drawing
                                                        limit</template
                                                    ><template v-else
                                                        ><MoneyText
                                                            :amount="
                                                                p.remaining_drawing_limit
                                                            "
                                                            :currency="
                                                                currencyCode
                                                            "
                                                            :fraction-digits="0"
                                                        />
                                                        left</template
                                                    >
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p
                                            v-if="
                                                getPartner(
                                                    withdrawal.partner_id,
                                                )
                                            "
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Capital
                                            <MoneyText
                                                :amount="
                                                    getPartner(
                                                        withdrawal.partner_id,
                                                    )?.net_capital || 0
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                            />
                                            · Withdrawn this period
                                            <MoneyText
                                                :amount="
                                                    getPartner(
                                                        withdrawal.partner_id,
                                                    )
                                                        ?.current_period_withdrawn ||
                                                    0
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                            />
                                        </p>
                                        <InputError
                                            :message="
                                                partnerWithdrawalError(
                                                    index,
                                                    'partner_id',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="w-48">
                                        <Label class="text-xs">Pay from</Label>
                                        <Select v-model="amanat.payment_account_id">
                                            <SelectTrigger><SelectValue placeholder="Cash on Hand" /></SelectTrigger>
                                            <SelectContent>
                                        <SelectItem v-for="account in props.paymentAccounts" :key="account.id" :value="account.id">
                                                    {{ account.code }} - {{ account.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="amanatDisbursementError(index, 'payment_account_id')" />
                                    </div>
                                    <div class="w-32">
                                        <Label class="text-xs">Amount</Label>
                                        <Input
                                            v-model.number="withdrawal.amount"
                                            type="number"
                                            @focus="selectZeroValue"
                                        />
                                        <InputError
                                            :message="
                                                partnerWithdrawalError(
                                                    index,
                                                    'amount',
                                                )
                                            "
                                        />
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="removePartnerWithdrawal(index)"
                                    >
                                        <Trash2
                                            class="h-4 w-4 text-destructive"
                                        />
                                    </Button>
                                </div>
                            </div>
                        </template>

                        <Separator />

                        <!-- Employee Advances -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">
                                        Employee Salary Advances
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.employeeAdvance }}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="props.employees.length === 0"
                                    @click="addEmployeeAdvance"
                                >
                                    <Plus class="mr-1 h-4 w-4" /> Add
                                </Button>
                            </div>

                            <div
                                v-if="props.employees.length === 0"
                                class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                            >
                                <div
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <span
                                        >No active payroll employees are
                                        available for salary advances.</span
                                    >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/${company.slug}/employees/create`"
                                            >Add employee</Link
                                        >
                                    </Button>
                                </div>
                            </div>

                            <div
                                v-for="(
                                    advance, index
                                ) in form.employee_advances"
                                :key="rowKey(advance)"
                                class="grid grid-cols-4 items-end gap-4"
                            >
                                <div>
                                    <Label class="text-xs">Employee</Label>
                                    <Select
                                        v-model="advance.employee_id"
                                        :disabled="props.employees.length === 0"
                                        @update:model-value="
                                            setEmployeeName(index)
                                        "
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="e in props.employees"
                                                :key="e.id"
                                                :value="e.id"
                                            >
                                                {{
                                                    e.full_name ||
                                                    `${e.first_name} ${e.last_name}`
                                                }}
                                                · Due
                                                <MoneyText
                                                    :amount="
                                                        e.outstanding_advances
                                                    "
                                                    :currency="currencyCode"
                                                    :fraction-digits="0"
                                                />
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        v-if="getEmployee(advance.employee_id)"
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        Current advance balance:
                                        <MoneyText
                                            :amount="
                                                getEmployee(advance.employee_id)
                                                    ?.outstanding_advances || 0
                                            "
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                        />
                                    </p>
                                    <InputError
                                        :message="
                                            employeeAdvanceError(
                                                index,
                                                'employee_id',
                                            )
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Amount</Label>
                                    <Input
                                        v-model.number="advance.amount"
                                        type="number"
                                        @focus="selectZeroValue"
                                    />
                                    <InputError
                                        :message="
                                            employeeAdvanceError(
                                                index,
                                                'amount',
                                            )
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Reason</Label>
                                    <Input
                                        v-model="advance.reason"
                                        placeholder="Optional"
                                    />
                                    <InputError
                                        :message="
                                            employeeAdvanceError(
                                                index,
                                                'reason',
                                            )
                                        "
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="removeEmployeeAdvance(index)"
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>

                        <template v-if="form.payroll_payouts.length > 0">
                            <Separator />

                            <div class="space-y-4">
                                <div>
                                    <h4 class="font-medium">
                                        Approved Salaries
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.payrollPayout }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="payout in form.payroll_payouts"
                                        :key="payout.payslip_id"
                                        class="flex items-center justify-between rounded-md border p-3 text-sm"
                                    >
                                        <div>
                                            <p class="font-medium">
                                                {{ payout.employee_name }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ payout.payslip_number }}
                                                <span
                                                    v-if="
                                                        payout.employee_number
                                                    "
                                                >
                                                    ·
                                                    {{
                                                        payout.employee_number
                                                    }}</span
                                                >
                                                <span v-if="payout.approved_at">
                                                    · Approved
                                                    {{
                                                        formatSharedDateTime(
                                                            payout.approved_at,
                                                            {
                                                                mode: 'datetime',
                                                            },
                                                        )
                                                    }}</span
                                                >
                                            </p>
                                        </div>
                                        <span
                                            class="font-semibold text-destructive"
                                            ><MoneyText
                                                :amount="payout.amount"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template v-if="form.bill_payments.length > 0">
                            <Separator />

                            <div class="space-y-4">
                                <div>
                                    <h4 class="font-medium">
                                        Supplier Bill Payments
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.billPayment }}
                                    </p>
                                </div>

                                <div class="space-y-2">
                                    <div
                                        v-for="payment in form.bill_payments"
                                        :key="payment.payment_id"
                                        class="rounded-md border p-3 text-sm"
                                    >
                                        <div
                                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                        >
                                            <div class="space-y-1">
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <p class="font-medium">
                                                        {{
                                                            payment.vendor_name
                                                        }}
                                                    </p>
                                                    <Badge
                                                        :variant="
                                                            payment.affects_cash_drawer
                                                                ? 'destructive'
                                                                : 'secondary'
                                                        "
                                                        class="text-xs"
                                                    >
                                                        {{
                                                            payment.affects_cash_drawer
                                                                ? 'Drawer cash'
                                                                : 'No drawer cash effect'
                                                        }}
                                                    </Badge>
                                                </div>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ payment.payment_number }}
                                                    <span
                                                        v-if="
                                                            payment.payment_group_number
                                                        "
                                                    >
                                                        ·
                                                        {{
                                                            payment.payment_group_number
                                                        }}</span
                                                    >
                                                    <span
                                                        v-if="
                                                            payment.bill_numbers
                                                                .length
                                                        "
                                                    >
                                                        · Bills
                                                        {{
                                                            payment.bill_numbers.join(
                                                                ', ',
                                                            )
                                                        }}</span
                                                    >
                                                </p>
                                                <p
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        payment.payment_account_name
                                                    }}
                                                    ·
                                                    {{
                                                        payment.payment_method.replace(
                                                            /_/g,
                                                            ' ',
                                                        )
                                                    }}
                                                    <span
                                                        v-if="
                                                            payment.reference_number
                                                        "
                                                    >
                                                        · Ref
                                                        {{
                                                            payment.reference_number
                                                        }}</span
                                                    >
                                                </p>
                                            </div>
                                            <span
                                                :class="
                                                    payment.affects_cash_drawer
                                                        ? 'text-destructive'
                                                        : 'text-foreground'
                                                "
                                                class="font-semibold"
                                            >
                                                <MoneyText
                                                    :amount="payment.amount"
                                                    :currency="currencyCode"
                                                    :fraction-digits="0"
                                                />
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Amanat Disbursements (only if amanat feature enabled) -->
                        <template v-if="features.has_amanat">
                            <Separator />

                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="font-medium">
                                            Amanat Disbursements
                                        </h4>
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                accountingHints.amanatDisbursement
                                            }}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            props.amanatHolders.length === 0
                                        "
                                        @click="addAmanat"
                                    >
                                        <Plus class="mr-1 h-4 w-4" /> Add
                                    </Button>
                                </div>

                                <div
                                    v-if="props.amanatHolders.length === 0"
                                    class="rounded-md border border-dashed p-3 text-sm text-muted-foreground"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <span
                                            >No Amanat depositors are available
                                            for disbursement yet.</span
                                        >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            as-child
                                        >
                                            <Link
                                                :href="`/${company.slug}/fuel/amanat`"
                                                >Add Amanat depositor</Link
                                            >
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    v-for="(
                                        amanat, index
                                    ) in form.amanat_disbursements"
                                    :key="rowKey(amanat)"
                                    class="flex items-end gap-4"
                                >
                                    <div class="flex-1">
                                        <Label class="text-xs">Depositor</Label>
                                        <Select
                                            v-model="amanat.customer_id"
                                            :disabled="
                                                props.amanatHolders.length === 0
                                            "
                                            @update:model-value="
                                                setAmanatCustomer(index)
                                            "
                                        >
                                            <SelectTrigger>
                                                <SelectValue
                                                    placeholder="Select depositor"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="holder in props.amanatHolders"
                                                    :key="holder.id"
                                                    :value="holder.id"
                                                >
                                                    {{ holder.name }} · Balance
                                                    <MoneyText
                                                        :amount="
                                                            holder.amanat_balance
                                                        "
                                                        :currency="currencyCode"
                                                        :fraction-digits="0"
                                                    />
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <p
                                            v-if="
                                                getAmanatHolder(
                                                    amanat.customer_id,
                                                )
                                            "
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            Available Amanat:
                                            <MoneyText
                                                :amount="
                                                    getAmanatHolder(
                                                        amanat.customer_id,
                                                    )?.amanat_balance || 0
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                            />
                                        </p>
                                        <InputError
                                            :message="
                                                amanatDisbursementError(
                                                    index,
                                                    'customer_id',
                                                )
                                            "
                                        />
                                    </div>
                                    <div class="w-32">
                                        <Label class="text-xs">Amount</Label>
                                        <Input
                                            v-model.number="amanat.amount"
                                            type="number"
                                            @focus="selectZeroValue"
                                        />
                                        <InputError
                                            :message="
                                                amanatDisbursementError(
                                                    index,
                                                    'amount',
                                                )
                                            "
                                        />
                                    </div>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        @click="removeAmanat(index)"
                                    >
                                        <Trash2
                                            class="h-4 w-4 text-destructive"
                                        />
                                    </Button>
                                </div>
                            </div>
                        </template>

                        <Separator />

                        <!-- Operating Expenses -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">
                                        Operating Expenses
                                    </h4>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.expense }}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="addExpense"
                                >
                                    <Plus class="mr-1 h-4 w-4" /> Add
                                </Button>
                            </div>

                            <div
                                v-for="(expense, index) in form.expenses"
                                :key="expense.uid ?? `expense-${index}`"
                                class="grid grid-cols-4 items-end gap-4"
                            >
                                <div>
                                    <Label class="text-xs">Category</Label>
                                    <Select
                                        v-model="expense.account_id"
                                        @update:model-value="
                                            setExpenseAccountName(index)
                                        "
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="a in expenseAccounts"
                                                :key="a.id"
                                                :value="a.id"
                                                >{{ a.name }}</SelectItem
                                            >
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="
                                            expenseError(index, 'account_id')
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Description</Label>
                                    <Input
                                        v-model="expense.description"
                                        placeholder="Details"
                                    />
                                    <InputError
                                        :message="
                                            expenseError(index, 'description')
                                        "
                                    />
                                </div>
                                <div>
                                    <Label class="text-xs">Amount</Label>
                                    <Input
                                        v-model.number="expense.amount"
                                        type="number"
                                        @focus="selectZeroValue"
                                    />
                                    <InputError
                                        :message="expenseError(index, 'amount')"
                                    />
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    @click="removeExpense(index)"
                                >
                                    <Trash2 class="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <!-- Supplier bills / fuel purchases entered inline (requires bill.create) -->
                        <div v-if="canEnterPurchases" class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h4 class="font-medium">Purchases</h4>
                                    <p class="text-xs text-muted-foreground">
                                        A supplier bill (and, for fuel, a stock receipt into a tank) posted the moment this close is posted.
                                    </p>
                                </div>
                                <Button variant="outline" size="sm" @click="addPurchaseRow">
                                    <Plus class="mr-1 h-4 w-4" /> Add
                                </Button>
                            </div>

                            <div
                                v-for="(purchase, index) in form.purchases"
                                :key="rowKey(purchase)"
                                class="space-y-2 rounded-lg border p-3"
                            >
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                    <div>
                                        <Label class="text-xs">Supplier</Label>
                                        <Select v-model="purchase.supplier_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="s in purchaseSuppliers ?? []"
                                                    :key="s.id"
                                                    :value="s.id"
                                                    >{{ s.name }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="purchaseError(index, 'supplier_id')" />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Item</Label>
                                        <Select v-model="purchase.item_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="it in purchaseItems ?? []"
                                                    :key="it.id"
                                                    :value="it.id"
                                                    >{{ it.name }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="purchaseError(index, 'item_id')" />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Quantity</Label>
                                        <Input v-model.number="purchase.quantity" type="number" @focus="selectZeroValue" />
                                        <InputError :message="purchaseError(index, 'quantity')" />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Unit cost</Label>
                                        <Input v-model.number="purchase.unit_cost" type="number" @focus="selectZeroValue" />
                                        <InputError :message="purchaseError(index, 'unit_cost')" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                                    <div v-if="isFuelPurchaseItem(purchase.item_id)">
                                        <Label class="text-xs">Tank</Label>
                                        <Select v-model="purchase.tank_id">
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="t in tanks"
                                                    :key="t.id"
                                                    :value="t.id"
                                                    >{{ t.name }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                        <InputError :message="purchaseError(index, 'tank_id')" />
                                    </div>
                                    <div>
                                        <Label class="text-xs">Supplier invoice #</Label>
                                        <Input v-model="purchase.supplier_invoice_number" placeholder="Optional" />
                                    </div>
                                    <div class="flex items-end gap-2">
                                        <input
                                            :id="'purchase-paid-now-' + index"
                                            v-model="purchase.paid_now"
                                            type="checkbox"
                                            class="h-4 w-4"
                                        />
                                        <Label :for="'purchase-paid-now-' + index" class="text-xs"
                                            >Paid now from cash</Label
                                        >
                                    </div>
                                    <div class="flex items-end justify-between gap-2">
                                        <div class="text-sm font-medium">
                                            <MoneyText
                                                :amount="purchaseLineTotal(purchase)"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                            />
                                        </div>
                                        <Button variant="ghost" size="icon" @click="removePurchaseRow(index)">
                                            <Trash2 class="h-4 w-4 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator v-if="canEnterPurchases" />

                        <!-- Money Out Summary -->
                        <div class="space-y-3 rounded-lg bg-muted/30 p-4">
                            <h4 class="text-sm font-semibold">
                                Money Out Summary
                            </h4>
                            <div class="space-y-2">
                                <!-- Card / bank sales -->
                                <div
                                    v-for="row in channelOutRows"
                                    :key="'out-' + row.code"
                                    class="flex justify-between text-sm"
                                >
                                    <span>{{ row.label }}</span>
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="row.amount"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <!-- Bank Deposits -->
                                <div
                                    v-if="totalCreditSales > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>{{ t('meterCreditSales') }}</span>
                                    <MoneyText :amount="totalCreditSales" :currency="currencyCode" :fraction-digits="2" />
                                </div>
                                <div
                                    v-if="totalBankDeposits > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Bank Deposits (Vendor Payments)</span>
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="totalBankDeposits"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <!-- Partner Withdrawals -->
                                <div
                                    v-if="totalPartnerWithdrawals > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Partner Withdrawals</span>
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="totalPartnerWithdrawals"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <!-- Employee Advances -->
                                <div
                                    v-if="totalEmployeeAdvances > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Employee Salary Advances</span>
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="totalEmployeeAdvances"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <div
                                    v-if="totalPayrollPayouts > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Approved Salaries</span>
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="totalPayrollPayouts"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <div
                                    v-if="totalCashBillPayments > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span
                                        >Supplier Bill Payments (station
                                        cash)</span
                                    >
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="totalCashBillPayments"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <div
                                    v-if="totalNonCashBillPayments > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span
                                        >Supplier Bill Payments (bank/fuel
                                        card)</span
                                    >
                                    <span class="font-medium"
                                        ><MoneyText
                                            :amount="totalNonCashBillPayments"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <!-- Amanat Disbursements -->
                                <div
                                    v-if="totalAmanatDisbursements > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Amanat Disbursements</span>
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="totalAmanatDisbursements"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <!-- Expenses -->
                                <div
                                    v-if="totalExpenses > 0"
                                    class="flex justify-between text-sm"
                                >
                                    <span>Operating Expenses</span>
                                    <span class="font-medium text-destructive"
                                        ><MoneyText
                                            :amount="totalExpenses"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                                <!-- Empty state -->
                                <div
                                    v-if="shownMoneyOut === 0"
                                    class="py-2 text-center text-sm text-muted-foreground"
                                >
                                    No outflows recorded
                                </div>
                                <template v-if="recordedMoneyOut.length">
                                    <p class="pt-1 text-xs font-medium text-muted-foreground">
                                        Recorded on other screens this day
                                    </p>
                                    <div
                                        v-for="row in recordedMoneyOut"
                                        :key="row.key"
                                        class="flex justify-between text-sm"
                                    >
                                        <span>{{ row.label }}</span>
                                        <span class="font-medium text-destructive"
                                            ><MoneyText
                                                :amount="Math.abs(row.amount)"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                </template>
                                <Separator />
                                <!-- Total -->
                                <div
                                    class="flex justify-between text-base font-semibold"
                                >
                                    <span>Total Money Out</span>
                                    <span class="text-destructive"
                                        ><MoneyText
                                            :amount="shownMoneyOut"
                                            :currency="currencyCode"
                                            :fraction-digits="0"
                                    /></span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <Button
                                @click="saveMoneyOut"
                                :variant="
                                    tabsSaved.moneyOut ? 'outline' : 'default'
                                "
                                class="min-w-32"
                            >
                                <CheckCircle
                                    v-if="tabsSaved.moneyOut"
                                    class="mr-2 h-4 w-4 text-status-success"
                                />
                                <Save v-else class="mr-2 h-4 w-4" />
                                {{
                                    tabsSaved.moneyOut
                                        ? 'Saved'
                                        : 'Save Cash Out'
                                }}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>

            <!-- Tab 5: Summary -->
            <TabsContent value="summary">
                <Card>
                    <CardHeader>
                        <CardTitle>Review &amp; Post</CardTitle>
                        <CardDescription
                            >Review totals, enter counted cash, and post the
                            daily close.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Summary Grid -->
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Left: Cash Flow -->
                            <div class="space-y-4">
                                <h4 class="font-semibold">Cash Flow</h4>
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span>Opening Cash</span>
                                        <span
                                            ><MoneyText
                                                :amount="form.opening_cash"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div
                                        v-if="totalPartnerDeposits > 0"
                                        class="flex justify-between"
                                    >
                                        <span>+ Partner Deposits</span>
                                        <span
                                            ><MoneyText
                                                :amount="totalPartnerDeposits"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div
                                        v-if="totalAmanatDeposits > 0"
                                        class="flex justify-between"
                                    >
                                        <span>+ Amanat Deposits</span>
                                        <span
                                            ><MoneyText
                                                :amount="totalAmanatDeposits"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div
                                        v-if="totalOtherDeposits > 0"
                                        class="flex justify-between"
                                    >
                                        <span>+ Other Cash In</span>
                                        <span
                                            ><MoneyText
                                                :amount="totalOtherDeposits"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>+ Total Sales</span>
                                        <span
                                            ><MoneyText
                                                :amount="totalSales"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div
                                        v-for="row in recordedMoneyIn"
                                        :key="'cf-in-' + row.key"
                                        class="flex justify-between"
                                    >
                                        <span>+ {{ row.label }}</span>
                                        <span
                                            ><MoneyText
                                                :amount="row.amount"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <Separator />
                                    <div
                                        class="flex justify-between font-medium"
                                    >
                                        <span>Total Money In</span>
                                        <span
                                            ><MoneyText
                                                :amount="shownMoneyIn"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div
                                        class="flex justify-between text-destructive"
                                    >
                                        <span
                                            >− Money Out (incl. card / bank
                                            sales)</span
                                        >
                                        <span
                                            ><MoneyText
                                                :amount="totalMoneyOut"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div
                                        v-for="row in recordedMoneyOut"
                                        :key="'cf-out-' + row.key"
                                        class="flex justify-between text-destructive"
                                    >
                                        <span>− {{ row.label }}</span>
                                        <span
                                            ><MoneyText
                                                :amount="Math.abs(row.amount)"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <Separator />
                                    <div
                                        class="flex justify-between text-lg font-semibold"
                                    >
                                        <span>Expected Closing</span>
                                        <span
                                            ><MoneyText
                                                :amount="expectedClosingCash"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Right: Sales Summary (by Pump) -->
                            <div class="space-y-4">
                                <h4 class="font-semibold">Sales Summary</h4>
                                <div class="space-y-2 text-sm">
                                    <div
                                        v-for="pump in nozzlesByPump"
                                        :key="pump.pump_id"
                                        class="flex justify-between"
                                    >
                                        <span
                                            >{{ pump.pump_name }} -
                                            {{ pump.fuel_name }} ({{
                                                getPumpTotalLiters(
                                                    pump.nozzle_indices,
                                                ).toFixed(0)
                                            }}L)</span
                                        >
                                        <span
                                            ><MoneyText
                                                :amount="
                                                    getPumpTotalAmount(
                                                        pump.nozzle_indices,
                                                    )
                                                "
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <div
                                        v-if="totalOtherSales > 0"
                                        class="flex justify-between"
                                    >
                                        <span>Other Sales</span>
                                        <span
                                            ><MoneyText
                                                :amount="totalOtherSales"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                    <Separator />
                                    <div
                                        class="flex justify-between font-semibold"
                                    >
                                        <span>Total Sales</span>
                                        <span
                                            ><MoneyText
                                                :amount="totalSales"
                                                :currency="currencyCode"
                                                :fraction-digits="0"
                                        /></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <Separator />

                        <!-- Actual Closing Cash -->
                        <div class="rounded-lg bg-muted/50 p-6">
                            <div class="flex items-center justify-between">
                                <div>
                                    <Label class="text-lg font-semibold"
                                        >Actual Closing Cash</Label
                                    >
                                    <p class="text-sm text-muted-foreground">
                                        Count the cash and enter the actual
                                        amount
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ accountingHints.variance }}
                                    </p>
                                </div>
                                <div class="w-64">
                                    <Input
                                        v-model.number="form.closing_cash" data-testid="closing-cash"
                                        type="number"
                                        @focus="selectZeroValue"
                                        class="h-14 text-right text-2xl font-bold"
                                    />
                                    <InputError
                                        :message="form.errors.closing_cash"
                                    />
                                </div>
                            </div>

                            <!-- Variance -->
                            <div
                                v-if="form.closing_cash > 0"
                                class="mt-4 flex items-center gap-2"
                            >
                                <component
                                    :is="
                                        cashVariance === 0
                                            ? CheckCircle
                                            : AlertCircle
                                    "
                                    :class="[
                                        'h-5 w-5',
                                        cashVariance === 0
                                            ? 'text-status-success'
                                            : 'text-status-attention',
                                    ]"
                                />
                                <span
                                    v-if="cashVariance === 0"
                                    class="font-medium text-status-success"
                                    >Cash matches expected amount</span
                                >
                                <span
                                    v-else-if="cashVariance > 0"
                                    class="font-medium text-status-info"
                                >
                                    Cash over by
                                    <MoneyText
                                        :amount="cashVariance"
                                        :currency="currencyCode"
                                        :fraction-digits="0"
                                    />
                                </span>
                                <span
                                    v-else
                                    class="font-medium text-status-critical"
                                >
                                    Cash short by
                                    <MoneyText
                                        :amount="Math.abs(cashVariance)"
                                        :currency="currencyCode"
                                        :fraction-digits="0"
                                    />
                                </span>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-2">
                            <Label>Notes</Label>
                            <Textarea
                                v-model="form.notes"
                                placeholder="Any additional notes for this day..."
                                rows="3"
                            />
                            <InputError :message="form.errors.notes" />
                        </div>

                        <!-- Submit -->
                        <div class="flex justify-end gap-4">
                            <Button
                                variant="outline"
                                @click="
                                    router.visit(
                                        `/${company.slug}/fuel/dashboard`,
                                    )
                                "
                            >
                                Cancel
                            </Button>
                            <Button
                                @click="submitDailyClose"
                                :disabled="submitting"
                                class="min-w-40 bg-status-success hover:bg-status-success"
                            >
                                <Loader2
                                    v-if="submitting"
                                    class="mr-2 h-4 w-4 animate-spin"
                                />
                                <CheckCircle v-else class="mr-2 h-4 w-4" />
                                Post Daily Close
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </PageShell>
</template>
