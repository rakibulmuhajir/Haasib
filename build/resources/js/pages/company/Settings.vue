<script setup lang="ts">
import PageShell from '@/components/PageShell.vue';
import CompanyCurrencies from '@/components/company/CompanyCurrencies.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
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
import { formatDateTime } from '@/lib/datetime';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    Building,
    Calculator,
    Calendar,
    ChevronRight,
    CreditCard,
    FileText,
    Rocket,
    Settings,
    Shield,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { ref } from 'vue';

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
    language?: string | null;
    locale?: string | null;
    is_active: boolean;
    created_at: string;
    current_user_role: string;
    can_manage_company: boolean;
    can_manage_users: boolean;
    settings?: {
        fiscal_year_start_month?: number;
        auto_create_fiscal_year?: boolean;
        default_period_type?: string;
        modules?: Record<string, boolean>;
        contact_email?: string | null;
        contact_phone?: string | null;
        website?: string | null;
    };
}

const page = usePage();
const props = page.props as any;

const company = ref<Company>(props.company);
const companyCurrencies = props.companyCurrencies || [];
const availableCurrencies = props.availableCurrencies || [];
const formatDate = (value: string) => formatDateTime(value, { mode: 'date' });

const generalForm = useForm({
    name: company.value.name,
    logo: null as File | null,
    contact_email: company.value.settings?.contact_email || '',
    contact_phone: company.value.settings?.contact_phone || '',
    website: company.value.settings?.website || '',
    language: company.value.language || 'en',
    locale: company.value.locale || 'en_US',
});
const saveGeneralSettings = () =>
    generalForm
        .transform((data) => ({ ...data, _method: 'patch' }))
        .post(`/${company.value.slug}/settings`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                generalForm.logo = null;
            },
        });
const logoPreview = ref(company.value.logo_url || '');
const selectLogo = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0] || null;
    generalForm.logo = file;
    if (file) logoPreview.value = URL.createObjectURL(file);
};

// Fiscal year form
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
});

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

const periodTypes = [
    { value: 'monthly', label: 'Monthly' },
    { value: 'quarterly', label: 'Quarterly' },
    { value: 'yearly', label: 'Yearly' },
];

const saveFiscalYearSettings = () => {
    fiscalYearForm.patch(`/${company.value.slug}/settings`, {
        onSuccess: () => {
            // Update local company data with response
            company.value = {
                ...company.value,
                settings: fiscalYearForm.data(),
            };
        },
    });
};

const saveModuleSettings = () => {
    moduleSettingsForm.patch(`/${company.value.slug}/settings/modules`, {
        preserveScroll: true,
        onSuccess: () => {
            const modules = {
                ...(company.value.settings?.modules ?? {}),
                inventory: moduleSettingsForm.inventory,
            };
            company.value = {
                ...company.value,
                settings: { ...(company.value.settings ?? {}), modules },
            };
        },
    });
};

const settingsSections = [
    {
        title: 'General',
        description: 'Company information, logo, and basic settings',
        icon: Building,
        href: '#general-settings',
        color: 'text-blue-600',
    },
    {
        title: 'Setup Wizard',
        description: 'Review optional setup steps and industry defaults',
        icon: Rocket,
        href:
            company.value.industry_code === 'fuel_station'
                ? `/${company.value.slug}/fuel/onboarding`
                : `/${company.value.slug}/onboarding`,
        color: 'text-indigo-600',
    },
    {
        title: 'Users & Permissions',
        description: 'Manage team members and their access levels',
        icon: Users,
        href: `/${company.value.slug}/users`,
        color: 'text-green-600',
        disabled: !company.value.can_manage_users,
    },
    {
        title: 'Tax Settings',
        description: 'Configure VAT, tax rates, and tax compliance',
        icon: Calculator,
        href: `/${company.value.slug}/tax/settings`,
        color: 'text-purple-600',
        badge: {
            text: 'New',
            variant: 'default' as const,
        },
    },
    {
        title: 'Accounting',
        description: 'Chart of accounts, fiscal years, and accounting periods',
        icon: CreditCard,
        href: '#fiscal-year-settings', // Scroll to fiscal year settings
        color: 'text-orange-600',
        disabled: false,
    },
    {
        title: 'Security',
        description: 'Security settings and two-factor authentication',
        icon: Shield,
        href: '#', // Will be implemented later
        color: 'text-red-600',
        disabled: true, // Not implemented yet
    },
];

const quickActions = [
    {
        title: 'Enable Saudi VAT',
        description: 'Quick setup for 15% Saudi Arabia VAT compliance',
        icon: Calculator,
        href: `/${company.value.slug}/tax/settings`,
        variant: 'default' as const,
        action: 'enable-saudi-vat',
        condition: true, // Always show for Saudi companies
    },
    {
        title: 'Manage Tax Rates',
        description: 'Configure different tax rates and jurisdictions',
        icon: Calculator,
        href: `/${company.value.slug}/tax/settings`,
        variant: 'outline' as const,
    },
    {
        title: 'View Documentation',
        description: 'Learn how to set up taxes and compliance',
        icon: FileText,
        href: '/docs/tax-management',
        variant: 'outline' as const,
    },
];

const getRoleDisplayName = (role: string) => {
    const roleNames: Record<string, string> = {
        owner: 'Owner',
        admin: 'Administrator',
        accountant: 'Accountant',
        member: 'Member',
    };
    return roleNames[role] || role;
};

const getRoleBadgeVariant = (role: string) => {
    const variants: Record<
        string,
        'default' | 'secondary' | 'destructive' | 'outline'
    > = {
        owner: 'default',
        admin: 'secondary',
        accountant: 'outline',
        member: 'secondary',
    };
    return variants[role] || 'secondary';
};
</script>

<template>
    <Head title="Company Settings" />

    <PageShell :title="`${company.name} Settings`">
        <div class="mx-auto max-w-7xl">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">
                    {{ company.name }} Settings
                </h1>
                <p class="mt-2 text-gray-600">
                    Manage your company configuration, users, and settings.
                </p>
            </div>
            <!-- Company Overview -->
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Building class="h-5 w-5" />
                        {{ company.name }}
                    </CardTitle>
                    <CardDescription>
                        Company overview and your current role in this
                        organization
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4"
                    >
                        <div>
                            <div class="text-sm font-medium text-gray-500">
                                Industry
                            </div>
                            <div class="text-sm">
                                {{
                                    company.industry_name ||
                                    company.industry ||
                                    company.industry_code ||
                                    'Not specified'
                                }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">
                                Country
                            </div>
                            <div class="text-sm">
                                {{ company.country || 'Not specified' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">
                                Base Currency
                            </div>
                            <div class="text-sm">
                                {{ company.base_currency }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">
                                Status
                            </div>
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="
                                        company.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        company.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">
                                Created
                            </div>
                            <div class="text-sm">
                                {{ formatDate(company.created_at) }}
                            </div>
                        </div>
                        <div>
                            <div class="text-sm font-medium text-gray-500">
                                Your Role
                            </div>
                            <div>
                                <Badge
                                    :variant="
                                        getRoleBadgeVariant(
                                            company.current_user_role,
                                        )
                                    "
                                >
                                    {{
                                        getRoleDisplayName(
                                            company.current_user_role,
                                        )
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card id="general-settings" class="mt-8">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><Building class="h-5 w-5" />General</CardTitle
                    >
                    <CardDescription
                        >Company identity and contact details.</CardDescription
                    >
                </CardHeader>
                <CardContent>
                    <form
                        class="space-y-4"
                        @submit.prevent="saveGeneralSettings"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Company Name</Label
                                ><Input
                                    v-model="generalForm.name"
                                    :disabled="!company.can_manage_company"
                                />
                                <p
                                    v-if="generalForm.errors.name"
                                    class="text-xs text-destructive"
                                >
                                    {{ generalForm.errors.name }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label>Base Currency</Label
                                ><Input
                                    :model-value="company.base_currency"
                                    disabled
                                />
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label>Company Logo</Label>
                                <div class="flex items-center gap-3">
                                    <img
                                        v-if="logoPreview"
                                        :src="logoPreview"
                                        :alt="`${company.name} logo preview`"
                                        class="h-14 w-14 rounded-md border object-contain"
                                    />
                                    <Input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        :disabled="!company.can_manage_company"
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
                                <Label>Contact Email</Label
                                ><Input
                                    v-model="generalForm.contact_email"
                                    type="email"
                                    :disabled="!company.can_manage_company"
                                />
                                <p
                                    v-if="generalForm.errors.contact_email"
                                    class="text-xs text-destructive"
                                >
                                    {{ generalForm.errors.contact_email }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label>Contact Phone</Label
                                ><Input
                                    v-model="generalForm.contact_phone"
                                    :disabled="!company.can_manage_company"
                                />
                                <p
                                    v-if="generalForm.errors.contact_phone"
                                    class="text-xs text-destructive"
                                >
                                    {{ generalForm.errors.contact_phone }}
                                </p>
                            </div>
                            <div class="space-y-2 md:col-span-2">
                                <Label>Website</Label
                                ><Input
                                    v-model="generalForm.website"
                                    type="url"
                                    :disabled="!company.can_manage_company"
                                />
                                <p
                                    v-if="generalForm.errors.website"
                                    class="text-xs text-destructive"
                                >
                                    {{ generalForm.errors.website }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label>Language</Label
                                ><Input
                                    v-model="generalForm.language"
                                    :disabled="!company.can_manage_company"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Locale</Label
                                ><Input
                                    v-model="generalForm.locale"
                                    :disabled="!company.can_manage_company"
                                />
                            </div>
                        </div>
                        <div
                            v-if="company.can_manage_company"
                            class="flex justify-end"
                        >
                            <Button
                                type="submit"
                                :disabled="generalForm.processing"
                                >Save General Settings</Button
                            >
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="mt-8">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2"
                        ><TrendingUp class="h-5 w-5" />Currencies</CardTitle
                    >
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

            <!-- Module Settings -->
            <Card class="mt-8">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2">
                        <Settings class="h-5 w-5" />
                        Modules
                    </CardTitle>
                    <CardDescription>
                        Enable or disable optional modules for this company.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center justify-between gap-6">
                        <div>
                            <div class="font-medium">Inventory</div>
                            <div class="text-sm text-muted-foreground">
                                Items, categories, warehouses, stock levels, and
                                stock movements.
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Switch
                                id="inventory-module"
                                v-model:checked="moduleSettingsForm.inventory"
                                :disabled="
                                    !company.can_manage_company ||
                                    moduleSettingsForm.processing
                                "
                            />
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="
                                    !company.can_manage_company ||
                                    moduleSettingsForm.processing
                                "
                                @click="saveModuleSettings"
                            >
                                Save
                            </Button>
                        </div>
                    </div>
                    <div
                        v-if="moduleSettingsForm.errors.inventory"
                        class="mt-2 text-sm text-destructive"
                    >
                        {{ moduleSettingsForm.errors.inventory }}
                    </div>
                </CardContent>
            </Card>

            <!-- Quick Actions -->
            <Card>
                <CardHeader>
                    <CardTitle>Quick Actions</CardTitle>
                    <CardDescription>
                        Common tasks and quick setup options for your company
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div
                        class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
                    >
                        <div v-for="action in quickActions" :key="action.title">
                            <Link :href="action.href">
                                <Card
                                    class="cursor-pointer transition-shadow hover:shadow-md"
                                >
                                    <CardContent class="p-4">
                                        <div class="flex items-start space-x-3">
                                            <div
                                                class="rounded-lg bg-gray-100 p-2"
                                            >
                                                <component
                                                    :is="action.icon"
                                                    class="h-5 w-5 text-gray-600"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <h4 class="text-sm font-medium">
                                                    {{ action.title }}
                                                </h4>
                                                <p
                                                    class="mt-1 text-sm text-gray-500"
                                                >
                                                    {{ action.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            </Link>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Settings Sections -->
            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-semibold">Settings</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Configure different aspects of your company
                    </p>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Card
                        v-for="section in settingsSections"
                        :key="section.title"
                        :class="{
                            'cursor-not-allowed opacity-50': section.disabled,
                        }"
                    >
                        <Link :href="section.disabled ? '#' : section.href">
                            <CardContent class="p-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="rounded-lg bg-gray-100 p-2">
                                            <component
                                                :is="section.icon"
                                                :class="`h-5 w-5 ${section.color}`"
                                            />
                                        </div>
                                        <div>
                                            <h3 class="font-medium">
                                                {{ section.title }}
                                            </h3>
                                            <p
                                                class="mt-1 text-sm text-gray-500"
                                            >
                                                {{ section.description }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <Badge
                                            v-if="section.badge"
                                            :variant="section.badge.variant"
                                        >
                                            {{ section.badge.text }}
                                        </Badge>
                                        <ChevronRight
                                            class="h-4 w-4 text-gray-400"
                                        />
                                    </div>
                                </div>
                            </CardContent>
                        </Link>
                    </Card>
                </div>
            </div>

            <!-- Tax Settings Spotlight -->
            <Card class="border-purple-200 bg-purple-50">
                <CardHeader>
                    <CardTitle class="text-purple-900">
                        <Calculator class="mr-2 inline h-5 w-5" />
                        Tax Management Setup
                    </CardTitle>
                    <CardDescription class="text-purple-700">
                        Configure VAT compliance and tax settings for your
                        business
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div
                            class="rounded-lg border border-purple-200 bg-white p-4"
                        >
                            <h4 class="font-medium text-purple-900">
                                Saudi VAT (15%)
                            </h4>
                            <p class="mt-1 text-sm text-purple-700">
                                Configure standard Saudi Arabia VAT rates and
                                registration numbers
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-purple-200 bg-white p-4"
                        >
                            <h4 class="font-medium text-purple-900">
                                Multiple Tax Rates
                            </h4>
                            <p class="mt-1 text-sm text-purple-700">
                                Support for different tax jurisdictions and
                                compound tax calculations
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-purple-200 bg-white p-4"
                        >
                            <h4 class="font-medium text-purple-900">
                                Exemptions
                            </h4>
                            <p class="mt-1 text-sm text-purple-700">
                                Handle zero-rated supplies and tax-exempt
                                customers/vendors
                            </p>
                        </div>
                    </div>
                    <div class="pt-2">
                        <Link :href="`/${company.slug}/tax/settings`">
                            <Button
                                variant="default"
                                class="bg-purple-600 hover:bg-purple-700"
                            >
                                <Calculator class="mr-2 h-4 w-4" />
                                Configure Tax Settings
                            </Button>
                        </Link>
                    </div>
                </CardContent>
            </Card>

            <!-- Fiscal Year Settings -->
            <Card
                id="fiscal-year-settings"
                class="border-orange-200 bg-orange-50"
            >
                <CardHeader>
                    <CardTitle class="text-orange-900">
                        <Calendar class="mr-2 inline h-5 w-5" />
                        Fiscal Year & Accounting Periods
                    </CardTitle>
                    <CardDescription class="text-orange-700">
                        Configure your company's fiscal year and automatic
                        accounting period creation
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <form
                        @submit.prevent="saveFiscalYearSettings"
                        class="space-y-4"
                    >
                        <!-- Fiscal Year Start Month -->
                        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                            <div>
                                <Label for="fiscal_year_start_month"
                                    >Fiscal Year Start Month</Label
                                >
                                <Select
                                    v-model="
                                        fiscalYearForm.fiscal_year_start_month
                                    "
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select month"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="month in months"
                                            :key="month.value"
                                            :value="month.value"
                                        >
                                            {{ month.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="mt-1 text-sm text-orange-700">
                                    Month when your fiscal year begins (most
                                    companies use January)
                                </p>
                            </div>

                            <!-- Period Type -->
                            <div>
                                <Label for="default_period_type"
                                    >Default Accounting Period Type</Label
                                >
                                <Select
                                    v-model="fiscalYearForm.default_period_type"
                                >
                                    <SelectTrigger>
                                        <SelectValue
                                            placeholder="Select period type"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="period in periodTypes"
                                            :key="period.value"
                                            :value="period.value"
                                        >
                                            {{ period.label }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <p class="mt-1 text-sm text-orange-700">
                                    How accounting periods are automatically
                                    created
                                </p>
                            </div>
                        </div>

                        <!-- Auto Create Fiscal Year -->
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <Label for="auto_create_fiscal_year"
                                    >Auto-Create Fiscal Years</Label
                                >
                                <p class="text-sm text-orange-700">
                                    Automatically create fiscal years when
                                    transactions are posted
                                </p>
                            </div>
                            <Switch
                                id="auto_create_fiscal_year"
                                v-model="fiscalYearForm.auto_create_fiscal_year"
                            />
                        </div>

                        <!-- Save Button -->
                        <div class="pt-4">
                            <Button
                                type="submit"
                                variant="default"
                                class="bg-orange-600 hover:bg-orange-700"
                                :disabled="fiscalYearForm.processing"
                            >
                                <TrendingUp class="mr-2 h-4 w-4" />
                                {{
                                    fiscalYearForm.processing
                                        ? 'Saving...'
                                        : 'Save Fiscal Year Settings'
                                }}
                            </Button>
                        </div>
                    </form>

                    <!-- Info Cards -->
                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div
                            class="rounded-lg border border-orange-200 bg-white p-4"
                        >
                            <h4 class="mb-2 font-medium text-orange-900">
                                <Calendar class="mr-1 inline h-4 w-4" />
                                Current Settings
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div>
                                    <strong>Start:</strong>
                                    {{
                                        months.find(
                                            (m) =>
                                                m.value ===
                                                (company.settings
                                                    ?.fiscal_year_start_month ??
                                                    1),
                                        )?.label
                                    }}
                                </div>
                                <div>
                                    <strong>Periods:</strong>
                                    {{
                                        company.settings?.default_period_type ||
                                        'monthly'
                                    }}
                                </div>
                                <div>
                                    <strong>Auto-create:</strong>
                                    {{
                                        company.settings
                                            ?.auto_create_fiscal_year
                                            ? 'Yes'
                                            : 'No'
                                    }}
                                </div>
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-orange-200 bg-white p-4"
                        >
                            <h4 class="mb-2 font-medium text-orange-900">
                                <TrendingUp class="mr-1 inline h-4 w-4" />
                                Impact
                            </h4>
                            <p class="mt-1 text-sm text-orange-700">
                                These settings determine how transactions are
                                organized and reported in your financial
                                statements.
                            </p>
                        </div>
                        <div
                            class="rounded-lg border border-orange-200 bg-white p-4"
                        >
                            <h4 class="mb-2 font-medium text-orange-900">
                                <CreditCard class="mr-1 inline h-4 w-4" />
                                Fiscal Years
                            </h4>
                            <p class="mt-1 text-sm text-orange-700">
                                When enabled, the system will automatically
                                create fiscal years as needed for posting
                                transactions.
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </PageShell>
</template>
