import { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

export interface NavItem {
    title: string;
    href?: NonNullable<InertiaLinkProps['href']>;  // Optional for parent menus
    icon?: LucideIcon;
    isActive?: boolean;
    external?: boolean;
    badge?: string | number;
    children?: NavItem[];  // Sub-menu items (one level only)
}

export interface SkinOption {
    id: string;
    label: string;
    description: string | null;
}

export interface NavGroup {
    label: string;
    collapsible?: boolean;
    items: NavItem[];
}

export type AppPageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    /** Local only: offer the skin preview switch. */
    skinPreview?: boolean;
    /** The skin registry from config/skins.php. */
    skins?: SkinOption[];
};

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
}

export type BreadcrumbItemType = BreadcrumbItem;
