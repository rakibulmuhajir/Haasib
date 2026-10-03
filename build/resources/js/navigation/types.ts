import type { NavGroup } from '@/types';

export interface NavContext {
    slug: string | null;
    isFuelStationCompany: boolean;
    isUmrahCompany: boolean;
    isInventoryEnabled: boolean;
    isPayrollEnabled: boolean;
    currentCompanyRole: string | null;
    fuelNavigation?: { allowed: string[]; hasInvestors: boolean } | null;
    // What this person may set up, grouped -- the Settings menu (App\Services\SettingsMenu).
    settingsMenu?: Array<{ title: string; href: string | null; items: Array<{ title: string; description: string; href: string }> }> | null;
    t: (key: string) => string;
}

export interface ModuleNavConfig {
    id: string;
    label: string;
    mode?: 'extend' | 'replace';
    isEnabled?: (context: NavContext) => boolean;
    getNavGroups: (context: NavContext) => NavGroup[];
}
