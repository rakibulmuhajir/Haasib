<script setup lang="ts">
import CompanyCurrencies from '@/components/company/CompanyCurrencies.vue';
import LedgerRegister from '@/components/LedgerRegister.vue';
import MetaChip from '@/components/MetaChip.vue';
import PageShell from '@/components/PageShell.vue';
import StatusBadge from '@/components/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatDateTime } from '@/lib/datetime';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Building2,
    CalendarRange,
    Check,
    ChevronDown,
    Coins,
    ExternalLink,
    KeyRound,
    PackageCheck,
    Settings2,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

interface Company {
    id: string;
    name: string;
    slug: string;
    industry?: string;
    industry_code?: string;
    industry_name?: string | null;
    country?: string;
    base_currency: string;
    logo_url?: string | null;
    stamp_url?: string | null;
    signature_url?: string | null;
    signer_name?: string | null;
    signer_title?: string | null;
    stamp_documents?: Record<string, boolean>;
    address?: CompanyAddress | null;
    language?: string | null;
    locale?: string | null;
    is_active: boolean;
    created_at: string;
    current_user_role: string | null;
    can_manage_company: boolean;
    can_manage_users: boolean;
    settings?: {
        fiscal_year_start_month?: number;
        auto_create_fiscal_year?: boolean;
        default_period_type?: string;
        modules?: Record<string, boolean>;
        contact_email?: string | null;
        contact_phone?: string | null;
        billed_by_name?: string | null;
        billed_by_designation?: string | null;
        billed_by_phone?: string | null;
        website?: string | null;
    };
}

/**
 * The postal address printed on every document the company issues. Stored as
 * jsonb in the same shape as a customer's billing address, so one renderer
 * serves both sides of a document.
 */
interface CompanyAddress {
    line1?: string | null;
    line2?: string | null;
    city?: string | null;
    state?: string | null;
    postal_code?: string | null;
    country?: string | null;
}

interface CompanyUser {
    id: string;
    name: string;
    email: string;
    role: string;
    joined_at: string | null;
    permissions: string[];
    capabilities: { label: string; allowed: boolean; detail: string | null }[];
}

const page = usePage();
const props = page.props as any;
const company = ref<Company>(props.company);
const companyCurrencies = props.companyCurrencies || [];
const availableCurrencies = props.availableCurrencies || [];
const users = (props.users || []) as CompanyUser[];
const logoPreview = ref(company.value.logo_url || '');
const stampPreview = ref(company.value.stamp_url || '');
const signaturePreview = ref(company.value.signature_url || '');

// Documents the stamp can appear on (final ones only; the server decides which are final).
const stampDocumentOptions = [
    { key: 'invoice', label: 'Invoices' },
    { key: 'consolidated_invoice', label: 'Consolidated invoices' },
    { key: 'statement', label: 'Customer statements' },
    { key: 'payment_receipt', label: 'Payment receipts' },
    { key: 'credit_note', label: 'Credit notes' },
    { key: 'bill_payment', label: 'Bill payment slips' },
];
const expandedUserId = ref<string | null>(null);

/**
 * A member list is a register. It is not a table of settings that happens to
 * have rows -- whether someone is a manager is the same kind of fact as whether
 * an invoice is paid, and both get read down a column the same way.
 */
const userColumns = [
    { key: 'name', label: 'User', kind: 'text' as const },
    { key: 'role', label: 'Role', kind: 'text' as const },
    {
        key: 'access',
        label: 'Access',
        kind: 'text' as const,
        class: 'hidden max-w-md md:table-cell',
        headerClass: 'hidden md:table-cell',
    },
    {
        key: 'joined_at',
        label: 'Joined',
        kind: 'date' as const,
        class: 'hidden sm:table-cell',
        headerClass: 'hidden sm:table-cell',
    },
];

const formatDate = (value: string | null) =>
    value ? formatDateTime(value, { mode: 'date' }) : '—';
const roleLabel = (role: string | null) =>
    ({
        owner: 'Owner',
        manager: 'Manager',
        accountant: 'Accountant',
        operations: 'Operations Clerk',
        agent: 'Agent',
    })[String(role)] ||
    role ||
    'System administrator';

const roleDescription = (role: string) =>
    ({
        owner: 'Full company access, including ownership and team controls.',
        manager:
            'Runs the business and manages team members, but cannot remove the Owner.',
        accountant:
            'Handles accounts, vouchers, payments, expenses, reports, and payroll.',
        operations:
            'Creates groups and vouchers without seeing prices, costs, or accounting.',
        agent: 'Works only with their own groups, vouchers, payments, and reports.',
    })[role] || 'Company access';

const permissionGroup = (permission: string) => {
    const prefix = permission.split('.')[0];

    if (['company'].includes(prefix)) return 'Company & team';
    if (['customer', 'vendor'].includes(prefix)) return 'Contacts';
    if (['invoice', 'bill', 'credit_note', 'vendor_credit'].includes(prefix))
        return 'Sales & purchases';
    if (['account', 'journal', 'posting_template', 'tax'].includes(prefix))
        return 'Accounting';
    if (
        [
            'payment',
            'bank_account',
            'bank_transaction',
            'bank_feed',
            'bank_reconciliation',
            'bank_rule',
        ].includes(prefix)
    )
        return 'Banking & payments';
    if (['item', 'item_category', 'warehouse', 'stock'].includes(prefix))
        return 'Inventory';
    if (
        [
            'employee',
            'payroll',
            'payroll_run',
            'leave_request',
            'payslip',
        ].includes(prefix)
    )
        return 'Payroll';
    if (prefix === 'umrah') return 'Umrah operations';
    if (
        [
            'fuel',
            'fuel_rate',
            'fuel_product',
            'fuel_sale',
            'pump',
            'pump_reading',
            'tank_reading',
            'investor',
            'handover',
            'amanat',
            'daily_close',
        ].includes(prefix)
    )
        return 'Fuel station';

    return 'Other';
};

const permissionLabel = (permission: string) =>
    permission
        .split('.')
        .map((part) => part.replaceAll('_', ' ').replaceAll('-', ' '))
        .join(' · ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

const permissionGroups = (permissions: string[]) =>
    permissions.reduce<Record<string, string[]>>((groups, permission) => {
        const group = permissionGroup(permission);
        groups[group] ||= [];
        groups[group].push(permission);
        return groups;
    }, {});

const toggleUserPermissions = (userId: string) => {
    expandedUserId.value = expandedUserId.value === userId ? null : userId;
};

// Opened from the Settings page on a particular tab (?tab=users, ?tab=currencies ...).
const initialTab = (() => {
    const tab = typeof window === 'undefined' ? null : new URLSearchParams(window.location.search).get('tab');
    return tab && ['general', 'users', 'currencies', 'modules', 'accounting'].includes(tab) ? tab : 'general';
})();

const generalForm = useForm({
    name: company.value.name,
    logo: null as File | null,
    stamp: null as File | null,
    signature: null as File | null,
    remove_stamp: false,
    remove_signature: false,
    signer_name: company.value.signer_name || '',
    signer_title: company.value.signer_title || '',
    stamp_documents: Object.fromEntries(
        ['invoice', 'consolidated_invoice', 'statement', 'payment_receipt', 'credit_note', 'bill_payment'].map((key) => [
            key,
            company.value.stamp_documents?.[key] ?? key !== 'bill_payment',
        ]),
    ) as Record<string, boolean>,
    contact_email: company.value.settings?.contact_email || '',
    contact_phone: company.value.settings?.contact_phone || '',
    website: company.value.settings?.website || '',
    billed_by_name: company.value.settings?.billed_by_name || '',
    billed_by_designation: company.value.settings?.billed_by_designation || '',
    billed_by_phone: company.value.settings?.billed_by_phone || '',
    address: {
        line1: company.value.address?.line1 || '',
        line2: company.value.address?.line2 || '',
        city: company.value.address?.city || '',
        state: company.value.address?.state || '',
        postal_code: company.value.address?.postal_code || '',
        country: company.value.address?.country || '',
    },
    language: company.value.language || 'en',
    locale: company.value.locale || 'en_US',
});

const fiscalYearForm = useForm({
    fiscal_year_start_month:
        company.value.settings?.fiscal_year_start_month ?? 1,
    auto_create_fiscal_year:
        company.value.settings?.auto_create_fiscal_year ?? true,
    default_period_type:
        company.value.settings?.default_period_type ?? 'monthly',
});

const moduleSettingsForm = useForm({
    inventory: company.value.settings?.modules?.inventory !== false,
    payroll: company.value.settings?.modules?.payroll !== false,
});

const createUserForm = useForm({
    name: '',
    email: '',
    role: 'operations',
    password: '',
    password_confirmation: '',
});

const invitationRoles = [
    { value: 'manager', label: 'Manager' },
    { value: 'accountant', label: 'Accountant' },
    { value: 'operations', label: 'Operations Clerk' },
];

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
].map((label, index) => ({ value: index + 1, label }));

const periodTypes = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
];

const selectLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] || null;
    generalForm.logo = file;
    if (file) logoPreview.value = URL.createObjectURL(file);
};

const selectStamp = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] || null;
    generalForm.stamp = file;
    generalForm.remove_stamp = false;
    if (file) stampPreview.value = URL.createObjectURL(file);
};

const removeStamp = () => {
    generalForm.stamp = null;
    generalForm.remove_stamp = true;
    stampPreview.value = '';
};

const selectSignature = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] || null;
    generalForm.signature = file;
    generalForm.remove_signature = false;
    if (file) signaturePreview.value = URL.createObjectURL(file);
};

const removeSignature = () => {
    generalForm.signature = null;
    generalForm.remove_signature = true;
    signaturePreview.value = '';
};

const saveGeneralSettings = () =>
    generalForm
        .transform((data) => ({ ...data, _method: 'patch' }))
        .post(`/${company.value.slug}/settings`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                generalForm.logo = null;
                generalForm.stamp = null;
                generalForm.signature = null;
                generalForm.remove_stamp = false;
                generalForm.remove_signature = false;
            },
        });

const saveModuleSettings = () =>
    moduleSettingsForm.patch(`/${company.value.slug}/settings/modules`, {
        preserveScroll: true,
    });

const saveFiscalYearSettings = () =>
    fiscalYearForm.patch(`/${company.value.slug}/settings`, {
        preserveScroll: true,
    });

const createUser = () =>
    createUserForm.post(`/${company.value.slug}/users`, {
        preserveScroll: true,
        onSuccess: () => {
            createUserForm.reset();
            createUserForm.role = 'operations';
            toast.success('User created successfully');
        },
        onError: () => toast.error('Could not create the user'),
    });
</script>

<template>
    <Head title="Settings" />

    <PageShell title="Settings">
        <div class="mx-auto w-full max-w-6xl space-y-6 pb-12">
            <section
                class="flex flex-col gap-5 border-b border-border pb-6 md:flex-row md:items-end md:justify-between"
            >
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <h1
                            class="text-2xl font-semibold tracking-tight text-foreground"
                        >
                            {{ company.name }}
                        </h1>
                        <StatusBadge
                            :status="company.is_active ? 'active' : 'inactive'"
                        />
                        <MetaChip tone="neutral" bare>{{
                            roleLabel(company.current_user_role)
                        }}</MetaChip>
                    </div>
                    <p
                        class="max-w-2xl text-sm leading-6 text-muted-foreground"
                    >
                        Company details, team access, currencies, modules, and
                        accounting defaults.
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            company.industry_name ||
                            company.industry ||
                            company.industry_code ||
                            'General business'
                        }}
                        · {{ company.country || 'Country not set' }} ·
                        {{ company.base_currency }} base currency · Created
                        {{ formatDate(company.created_at) }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        @click="router.get(`/${company.slug}/onboarding`)"
                    >
                        Setup guide
                        <ExternalLink class="ml-2 h-3.5 w-3.5" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="router.get(`/${company.slug}/tax/settings`)"
                    >
                        Tax settings
                        <ExternalLink class="ml-2 h-3.5 w-3.5" />
                    </Button>
                </div>
            </section>

            <Tabs :default-value="initialTab" class="space-y-6">
                <TabsList
                    class="grid h-auto w-full grid-cols-2 gap-1 bg-muted/60 p-1 md:grid-cols-5"
                >
                    <TabsTrigger value="general" class="gap-2 py-2.5">
                        <Building2 class="h-4 w-4" /> General
                    </TabsTrigger>
                    <TabsTrigger value="users" class="gap-2 py-2.5">
                        <Users class="h-4 w-4" /> Users
                    </TabsTrigger>
                    <TabsTrigger value="currencies" class="gap-2 py-2.5">
                        <Coins class="h-4 w-4" /> Currencies
                    </TabsTrigger>
                    <TabsTrigger value="modules" class="gap-2 py-2.5">
                        <PackageCheck class="h-4 w-4" /> Modules
                    </TabsTrigger>
                    <TabsTrigger
                        value="accounting"
                        class="col-span-2 gap-2 py-2.5 md:col-span-1"
                    >
                        <CalendarRange class="h-4 w-4" /> Accounting
                    </TabsTrigger>
                </TabsList>

                <TabsContent value="general">
                    <Card variant="form">
                        <CardHeader>
                            <CardTitle>Company details</CardTitle>
                            <CardDescription
                                >Identity and contact information shown across
                                Haasib.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                novalidate
                                class="space-y-6"
                                @submit.prevent="saveGeneralSettings"
                            >
                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="company-name"
                                            >Company name</Label
                                        >
                                        <Input
                                            id="company-name"
                                            v-model="generalForm.name"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                        <p
                                            v-if="generalForm.errors.name"
                                            class="text-xs text-destructive"
                                        >
                                            {{ generalForm.errors.name }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="base-currency"
                                            >Base currency</Label
                                        >
                                        <Input
                                            id="base-currency"
                                            :model-value="company.base_currency"
                                            disabled
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Fixed after the company is created.
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="contact-email"
                                            >Contact email</Label
                                        >
                                        <Input
                                            id="contact-email"
                                            v-model="generalForm.contact_email"
                                            type="email"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                        <p
                                            v-if="
                                                generalForm.errors.contact_email
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{
                                                generalForm.errors.contact_email
                                            }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="contact-phone"
                                            >Contact phone</Label
                                        >
                                        <Input
                                            id="contact-phone"
                                            v-model="generalForm.contact_phone"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                        <p
                                            v-if="
                                                generalForm.errors.contact_phone
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{
                                                generalForm.errors.contact_phone
                                            }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="billed-by-name">Billed by — name</Label>
                                        <Input
                                            id="billed-by-name"
                                            v-model="generalForm.billed_by_name"
                                            :disabled="!company.can_manage_company"
                                        />
                                        <p v-if="generalForm.errors.billed_by_name" class="text-xs text-destructive">{{ generalForm.errors.billed_by_name }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="billed-by-designation">Billed by — designation</Label>
                                        <Input
                                            id="billed-by-designation"
                                            v-model="generalForm.billed_by_designation"
                                            :disabled="!company.can_manage_company"
                                        />
                                        <p v-if="generalForm.errors.billed_by_designation" class="text-xs text-destructive">{{ generalForm.errors.billed_by_designation }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="billed-by-phone">Billed by — phone</Label>
                                        <Input
                                            id="billed-by-phone"
                                            v-model="generalForm.billed_by_phone"
                                            :disabled="!company.can_manage_company"
                                        />
                                        <p v-if="generalForm.errors.billed_by_phone" class="text-xs text-destructive">{{ generalForm.errors.billed_by_phone }}</p>
                                    </div>
                                    <div class="space-y-2 md:col-span-2">
                                        <Label for="website">Website</Label>
                                        <Input
                                            id="website"
                                            v-model="generalForm.website"
                                            type="url"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                        <p
                                            v-if="generalForm.errors.website"
                                            class="text-xs text-destructive"
                                        >
                                            {{ generalForm.errors.website }}
                                        </p>
                                    </div>
                                    <!-- The letterhead block. Until these fields existed every
                       invoice, bill and credit note this company sent printed a
                       name and a logo and nothing a reader could post a cheque
                       to. -->
                                    <div class="space-y-2 md:col-span-2">
                                        <Label for="address-line1"
                                            >Street address</Label
                                        >
                                        <Input
                                            id="address-line1"
                                            v-model="generalForm.address.line1"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                            placeholder="Building, street"
                                        />
                                        <Input
                                            id="address-line2"
                                            v-model="generalForm.address.line2"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                            placeholder="Area, landmark (optional)"
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Printed on every document you issue.
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="address-city">City</Label>
                                        <Input
                                            id="address-city"
                                            v-model="generalForm.address.city"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="address-state"
                                            >Province</Label
                                        >
                                        <Input
                                            id="address-state"
                                            v-model="generalForm.address.state"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="address-postal"
                                            >Postal code</Label
                                        >
                                        <Input
                                            id="address-postal"
                                            v-model="
                                                generalForm.address.postal_code
                                            "
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="address-country"
                                            >Country</Label
                                        >
                                        <Input
                                            id="address-country"
                                            v-model="
                                                generalForm.address.country
                                            "
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="language">Language</Label>
                                        <Input
                                            id="language"
                                            v-model="generalForm.language"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="locale">Locale</Label>
                                        <Input
                                            id="locale"
                                            v-model="generalForm.locale"
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        />
                                    </div>
                                    <div class="space-y-2 md:col-span-2">
                                        <Label for="company-logo"
                                            >Company logo</Label
                                        >
                                        <div
                                            class="flex items-center gap-4 rounded-lg bg-muted/40 p-3"
                                        >
                                            <div
                                                class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-background ring-1 ring-border"
                                            >
                                                <img
                                                    v-if="logoPreview"
                                                    :src="logoPreview"
                                                    :alt="`${company.name} logo preview`"
                                                    class="h-full w-full object-contain"
                                                />
                                                <Building2
                                                    v-else
                                                    class="h-6 w-6 text-muted-foreground"
                                                />
                                            </div>
                                            <Input
                                                id="company-logo"
                                                type="file"
                                                accept="image/png,image/jpeg,image/webp"
                                                :disabled="
                                                    !company.can_manage_company
                                                "
                                                @change="selectLogo"
                                            />
                                        </div>
                                        <p
                                            v-if="generalForm.errors.logo"
                                            class="text-xs text-destructive"
                                        >
                                            {{ generalForm.errors.logo }}
                                        </p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="company-stamp">Company stamp</Label>
                                        <div class="flex items-center gap-4 rounded-lg bg-muted/40 p-3">
                                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-background ring-1 ring-border">
                                                <img v-if="stampPreview" :src="stampPreview" alt="Company stamp preview" class="h-full w-full object-contain" />
                                                <Building2 v-else class="h-6 w-6 text-muted-foreground" />
                                            </div>
                                            <div class="min-w-0 flex-1 space-y-2">
                                                <Input id="company-stamp" type="file" accept="image/png,image/jpeg,image/webp" :disabled="!company.can_manage_company" @change="selectStamp" />
                                                <Button v-if="stampPreview && company.can_manage_company" type="button" variant="outline" size="sm" @click="removeStamp">Remove</Button>
                                            </div>
                                        </div>
                                        <p class="text-xs text-muted-foreground">PNG, JPG or WebP, up to 1 MB. Transparent PNG works best.</p>
                                        <p v-if="generalForm.errors.stamp" class="text-xs text-destructive">{{ generalForm.errors.stamp }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="company-signature">Signature (optional)</Label>
                                        <div class="flex items-center gap-4 rounded-lg bg-muted/40 p-3">
                                            <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-background ring-1 ring-border">
                                                <img v-if="signaturePreview" :src="signaturePreview" alt="Signature preview" class="h-full w-full object-contain" />
                                                <Building2 v-else class="h-6 w-6 text-muted-foreground" />
                                            </div>
                                            <div class="min-w-0 flex-1 space-y-2">
                                                <Input id="company-signature" type="file" accept="image/png,image/jpeg,image/webp" :disabled="!company.can_manage_company" @change="selectSignature" />
                                                <Button v-if="signaturePreview && company.can_manage_company" type="button" variant="outline" size="sm" @click="removeSignature">Remove</Button>
                                            </div>
                                        </div>
                                        <p v-if="generalForm.errors.signature" class="text-xs text-destructive">{{ generalForm.errors.signature }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="signer-name">Signer name</Label>
                                        <Input id="signer-name" v-model="generalForm.signer_name" :disabled="!company.can_manage_company" />
                                        <p v-if="generalForm.errors.signer_name" class="text-xs text-destructive">{{ generalForm.errors.signer_name }}</p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="signer-title">Signer title</Label>
                                        <Input id="signer-title" v-model="generalForm.signer_title" :disabled="!company.can_manage_company" />
                                        <p v-if="generalForm.errors.signer_title" class="text-xs text-destructive">{{ generalForm.errors.signer_title }}</p>
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <Label>Stamp on</Label>
                                        <div class="grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                                            <label v-for="option in stampDocumentOptions" :key="option.key" class="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    :checked="generalForm.stamp_documents[option.key]"
                                                    :disabled="!company.can_manage_company"
                                                    @update:checked="(value: boolean) => (generalForm.stamp_documents[option.key] = value)"
                                                />
                                                {{ option.label }}
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-if="company.can_manage_company"
                                    class="flex justify-end border-t border-border pt-5"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="generalForm.processing"
                                    >
                                        {{
                                            generalForm.processing
                                                ? 'Saving…'
                                                : 'Save company details'
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="users" class="space-y-5">
                    <Card v-if="company.can_manage_users" variant="form">
                        <CardHeader>
                            <CardTitle>Add user</CardTitle>
                            <CardDescription
                                >Create a login and assign the user’s company
                                role.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <form
                                novalidate
                                class="space-y-4"
                                @submit.prevent="createUser"
                            >
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="new-user-name"
                                            >Full name</Label
                                        >
                                        <Input
                                            id="new-user-name"
                                            v-model="createUserForm.name"
                                            autocomplete="name"
                                            :disabled="
                                                createUserForm.processing
                                            "
                                        />
                                        <p
                                            v-if="createUserForm.errors.name"
                                            class="text-xs text-destructive"
                                        >
                                            {{ createUserForm.errors.name }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="new-user-email"
                                            >Email address</Label
                                        >
                                        <Input
                                            id="new-user-email"
                                            v-model="createUserForm.email"
                                            type="email"
                                            autocomplete="email"
                                            :disabled="
                                                createUserForm.processing
                                            "
                                        />
                                        <p
                                            v-if="createUserForm.errors.email"
                                            class="text-xs text-destructive"
                                        >
                                            {{ createUserForm.errors.email }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="new-user-role">Role</Label>
                                        <Select
                                            v-model="createUserForm.role"
                                            :disabled="
                                                createUserForm.processing
                                            "
                                        >
                                            <SelectTrigger id="new-user-role"
                                                ><SelectValue
                                                    placeholder="Select a role"
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="role in invitationRoles"
                                                    :key="role.value"
                                                    :value="role.value"
                                                    >{{
                                                        role.label
                                                    }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                        <p
                                            v-if="createUserForm.errors.role"
                                            class="text-xs text-destructive"
                                        >
                                            {{ createUserForm.errors.role }}
                                        </p>
                                    </div>
                                    <div class="hidden md:block" />
                                    <div class="space-y-2">
                                        <Label for="new-user-password"
                                            >Password</Label
                                        >
                                        <Input
                                            id="new-user-password"
                                            v-model="createUserForm.password"
                                            type="password"
                                            autocomplete="new-password"
                                            :disabled="
                                                createUserForm.processing
                                            "
                                        />
                                        <p
                                            v-if="
                                                createUserForm.errors.password
                                            "
                                            class="text-xs text-destructive"
                                        >
                                            {{ createUserForm.errors.password }}
                                        </p>
                                    </div>
                                    <div class="space-y-2">
                                        <Label
                                            for="new-user-password-confirmation"
                                            >Confirm password</Label
                                        >
                                        <Input
                                            id="new-user-password-confirmation"
                                            v-model="
                                                createUserForm.password_confirmation
                                            "
                                            type="password"
                                            autocomplete="new-password"
                                            :disabled="
                                                createUserForm.processing
                                            "
                                        />
                                    </div>
                                </div>
                                <div
                                    class="flex justify-end border-t border-border pt-4"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="
                                            createUserForm.processing ||
                                            !createUserForm.name ||
                                            !createUserForm.email ||
                                            !createUserForm.password
                                        "
                                    >
                                        <UserPlus class="mr-2 h-4 w-4" />
                                        {{
                                            createUserForm.processing
                                                ? 'Creating…'
                                                : 'Create user'
                                        }}
                                    </Button>
                                </div>
                                <p
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    The Owner role cannot be assigned here.
                                    Password rules are enforced by the server.
                                </p>
                            </form>
                        </CardContent>
                    </Card>

                    <Card variant="detail">
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>Users & permissions</CardTitle>
                                <CardDescription
                                    >{{ users.length }} active
                                    {{
                                        users.length === 1 ? 'user' : 'users'
                                    }}</CardDescription
                                >
                            </div>
                            <Button
                                v-if="company.can_manage_users"
                                variant="outline"
                                @click="router.get(`/${company.slug}/users`)"
                            >
                                Manage roles
                                <ExternalLink class="ml-2 h-3.5 w-3.5" />
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <LedgerRegister
                                :data="users"
                                :columns="userColumns"
                                :expanded="(row) => expandedUserId === row.id"
                            >
                                <template #empty
                                    >Nobody has been added to this company
                                    yet.</template
                                >

                                <template #cell-name="{ row }">
                                    <div class="font-medium">
                                        {{ row.name }}
                                    </div>
                                    <div class="text-xs text-text-secondary">
                                        {{ row.email }}
                                    </div>
                                </template>

                                <template #cell-role="{ row }">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <MetaChip tone="neutral" bare>{{
                                            roleLabel(row.role)
                                        }}</MetaChip>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            class="h-7 px-2 text-xs"
                                            @click.stop="
                                                toggleUserPermissions(row.id)
                                            "
                                        >
                                            {{ row.permissions.length }} module
                                            permissions
                                            <ChevronDown
                                                class="ml-1 h-3.5 w-3.5 transition-transform"
                                                :class="
                                                    expandedUserId === row.id
                                                        ? 'rotate-180'
                                                        : ''
                                                "
                                            />
                                        </Button>
                                    </div>
                                </template>

                                <template #cell-access="{ row }">
                                    <span class="text-sm text-text-secondary">{{
                                        roleDescription(row.role)
                                    }}</span>
                                </template>

                                <template #cell-joined_at="{ row }">{{
                                    formatDate(row.joined_at)
                                }}</template>

                                <template #row-detail="{ row }">
                                    <div
                                        v-if="row.capabilities.length"
                                        class="mb-6 grid gap-2 sm:grid-cols-2 xl:grid-cols-3"
                                    >
                                        <div
                                            v-for="capability in row.capabilities"
                                            :key="capability.label"
                                            class="flex items-start gap-2 rounded-md border border-rule-default bg-surface-canvas p-3"
                                        >
                                            <!--
                        A capability this role does not have is not a failure.
                        It is the shape of the role, and painting it red said
                        something was wrong with a permission set that is
                        working exactly as configured. The tick and the words
                        carry the distinction; colour only recedes the half
                        that is not in force.
                      -->
                                            <Check
                                                v-if="capability.allowed"
                                                class="mt-0.5 h-4 w-4 shrink-0 text-text-primary"
                                            />
                                            <span
                                                v-else
                                                class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-rule-default text-xs font-semibold text-text-secondary"
                                                >–</span
                                            >
                                            <div>
                                                <p
                                                    class="text-sm font-medium"
                                                    :class="
                                                        capability.allowed
                                                            ? ''
                                                            : 'text-text-secondary'
                                                    "
                                                >
                                                    {{ capability.label }}
                                                </p>
                                                <p
                                                    class="text-xs text-text-secondary"
                                                >
                                                    {{
                                                        capability.allowed
                                                            ? 'Allowed'
                                                            : 'Not allowed'
                                                    }}<span
                                                        v-if="capability.detail"
                                                    >
                                                        ·
                                                        {{
                                                            capability.detail
                                                        }}</span
                                                    >
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        v-if="row.permissions.length"
                                        class="grid gap-5 lg:grid-cols-2 xl:grid-cols-3"
                                    >
                                        <section
                                            v-for="[
                                                group,
                                                permissions,
                                            ] in Object.entries(
                                                permissionGroups(
                                                    row.permissions,
                                                ),
                                            )"
                                            :key="group"
                                            class="space-y-2"
                                        >
                                            <h4
                                                class="text-xs font-semibold tracking-wide text-text-secondary uppercase"
                                            >
                                                {{ group }}
                                            </h4>
                                            <ul class="space-y-1.5">
                                                <li
                                                    v-for="permission in permissions"
                                                    :key="permission"
                                                    class="flex items-start gap-2 text-sm"
                                                >
                                                    <Check
                                                        class="mt-0.5 h-3.5 w-3.5 shrink-0 text-status-success"
                                                    />
                                                    <span>{{
                                                        permissionLabel(
                                                            permission,
                                                        )
                                                    }}</span>
                                                </li>
                                            </ul>
                                        </section>
                                    </div>
                                    <p
                                        v-else
                                        class="text-sm text-text-secondary"
                                    >
                                        No permissions are currently granted to
                                        this role.
                                    </p>
                                </template>
                            </LedgerRegister>

                            <div class="rounded-lg bg-muted/40 p-4">
                                <div class="flex gap-3">
                                    <KeyRound
                                        class="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground"
                                    />
                                    <div>
                                        <p class="text-sm font-medium">
                                            Role rules are enforced server-side
                                        </p>
                                        <p
                                            class="mt-1 text-sm leading-6 text-muted-foreground"
                                        >
                                            Owners and Managers can manage the
                                            team. Managers cannot change or
                                            remove an Owner. Financial data
                                            stays hidden from Operations Clerks
                                            and Agents.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="currencies">
                    <Card variant="detail">
                        <CardHeader>
                            <CardTitle>Currencies</CardTitle>
                            <CardDescription
                                >Manual rates use 1 secondary currency = X
                                {{ company.base_currency }}.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <CompanyCurrencies
                                :company="company"
                                :enabled="companyCurrencies"
                                :available="availableCurrencies"
                                :can-manage="company.can_manage_company"
                            />
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="modules">
                    <Card variant="form">
                        <CardHeader>
                            <CardTitle>Optional modules</CardTitle>
                            <CardDescription
                                >Turn operational areas on or off for this
                                company.</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-5">
                            <div
                                class="divide-y divide-border rounded-lg border border-border"
                            >
                                <div
                                    class="flex items-center justify-between gap-5 p-5"
                                >
                                    <div>
                                        <p class="font-medium">Inventory</p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Items, warehouses, stock levels, and
                                            stock movements.
                                        </p>
                                    </div>
                                    <Switch
                                        v-model:checked="
                                            moduleSettingsForm.inventory
                                        "
                                        :disabled="
                                            !company.can_manage_company ||
                                            moduleSettingsForm.processing
                                        "
                                    />
                                </div>
                                <div
                                    class="flex items-center justify-between gap-5 p-5"
                                >
                                    <div>
                                        <p class="font-medium">Payroll</p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Employees, payroll periods,
                                            payslips, payments, and advances.
                                        </p>
                                    </div>
                                    <Switch
                                        v-model:checked="
                                            moduleSettingsForm.payroll
                                        "
                                        :disabled="
                                            !company.can_manage_company ||
                                            moduleSettingsForm.processing
                                        "
                                    />
                                </div>
                            </div>
                            <p
                                v-if="
                                    moduleSettingsForm.errors.inventory ||
                                    moduleSettingsForm.errors.payroll
                                "
                                class="text-sm text-destructive"
                            >
                                {{
                                    moduleSettingsForm.errors.inventory ||
                                    moduleSettingsForm.errors.payroll
                                }}
                            </p>
                            <div
                                v-if="company.can_manage_company"
                                class="flex justify-end"
                            >
                                <Button
                                    :disabled="moduleSettingsForm.processing"
                                    @click="saveModuleSettings"
                                >
                                    <Settings2 class="mr-2 h-4 w-4" />
                                    {{
                                        moduleSettingsForm.processing
                                            ? 'Saving…'
                                            : 'Save module settings'
                                    }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="accounting">
                    <Card variant="form">
                        <CardHeader>
                            <CardTitle>Fiscal year & periods</CardTitle>
                            <CardDescription
                                >Choose how transactions are grouped into
                                financial reporting periods.</CardDescription
                            >
                        </CardHeader>
                        <CardContent>
                            <form
                                novalidate
                                class="space-y-6"
                                @submit.prevent="saveFiscalYearSettings"
                            >
                                <div class="grid gap-5 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="fiscal-month"
                                            >Fiscal year starts</Label
                                        >
                                        <Select
                                            v-model="
                                                fiscalYearForm.fiscal_year_start_month
                                            "
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        >
                                            <SelectTrigger id="fiscal-month"
                                                ><SelectValue
                                                    placeholder="Select month"
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="month in months"
                                                    :key="month.value"
                                                    :value="month.value"
                                                    >{{
                                                        month.label
                                                    }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label for="period-type"
                                            >Reporting periods</Label
                                        >
                                        <Select
                                            v-model="
                                                fiscalYearForm.default_period_type
                                            "
                                            :disabled="
                                                !company.can_manage_company
                                            "
                                        >
                                            <SelectTrigger id="period-type"
                                                ><SelectValue
                                                    placeholder="Select period type"
                                            /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="period in periodTypes"
                                                    :key="period.value"
                                                    :value="period.value"
                                                    >{{
                                                        period.label
                                                    }}</SelectItem
                                                >
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>

                                <div
                                    class="flex items-center justify-between gap-5 rounded-lg bg-muted/40 p-4"
                                >
                                    <div>
                                        <p class="text-sm font-medium">
                                            Create fiscal years automatically
                                        </p>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Create the required year and periods
                                            when a transaction is posted.
                                        </p>
                                    </div>
                                    <Switch
                                        v-model:checked="
                                            fiscalYearForm.auto_create_fiscal_year
                                        "
                                        :disabled="!company.can_manage_company"
                                    />
                                </div>

                                <div
                                    v-if="company.can_manage_company"
                                    class="flex justify-end border-t border-border pt-5"
                                >
                                    <Button
                                        type="submit"
                                        :disabled="fiscalYearForm.processing"
                                    >
                                        {{
                                            fiscalYearForm.processing
                                                ? 'Saving…'
                                                : 'Save accounting settings'
                                        }}
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    </PageShell>
</template>
