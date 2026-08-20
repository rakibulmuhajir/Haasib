import type { NavGroup } from '@/types';

export interface NavContext {
    slug: string | null;
    isFuelStationCompany: boolean;
    isUmrahCompany: boolean;
    isInventoryEnabled: boolean;
    isPayrollEnabled: boolean;
    currentCompanyRole: string | null;
    t: (key: string) => string;
}

export interface ModuleNavConfig {
    id: string;
    label: string;
    mode?: 'extend' | 'replace';
    isEnabled?: (context: NavContext) => boolean;
    getNavGroups: (context: NavContext) => NavGroup[];
}
