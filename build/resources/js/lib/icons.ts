/**
 * Icon names, resolved from a string.
 *
 * Several dozen call sites pass a lucide icon as a string — `icon="FileText"` —
 * rather than importing the component. That is a reasonable thing to want, but
 * it must not be paid for with `import * as lucide`, which drags the entire
 * pack (well over a thousand icons) into the bundle whether or not any of them
 * render. This map is explicit, so the bundler ships exactly these and no more.
 *
 * Adding a name here is the price of using it as a string. That is deliberate:
 * an icon nobody can find in this file is an icon nobody knew the app used.
 */
import type { Component } from 'vue'
import {
    BarChart3,
    BookOpen,
    Building2,
    Bus,
    Calculator,
    Calendar,
    CalendarDays,
    CreditCard,
    DollarSign,
    Droplets,
    FileText,
    FolderTree,
    Fuel,
    Gauge,
    Globe,
    HandCoins,
    Hash,
    History,
    Landmark,
    Languages,
    Layers,
    Mail,
    Package,
    PackageCheck,
    Phone,
    Plane,
    Receipt,
    ReceiptText,
    RefreshCcw,
    ScrollText,
    Settings,
    Settings2,
    Store,
    TrendingUp,
    Truck,
    User,
    Users,
    UsersRound,
    Wallet,
    WalletCards,
    Wand2,
    Warehouse,
} from 'lucide-vue-next'

export const iconsByName: Record<string, Component> = {
    BarChart3,
    BookOpen,
    Building2,
    Bus,
    Calculator,
    Calendar,
    CalendarDays,
    CreditCard,
    DollarSign,
    Droplets,
    FileText,
    FolderTree,
    Fuel,
    Gauge,
    Globe,
    HandCoins,
    Hash,
    History,
    Landmark,
    Languages,
    Layers,
    Mail,
    Package,
    PackageCheck,
    Phone,
    Plane,
    Receipt,
    ReceiptText,
    RefreshCcw,
    ScrollText,
    Settings,
    Settings2,
    Store,
    TrendingUp,
    Truck,
    User,
    Users,
    UsersRound,
    Wallet,
    WalletCards,
    Wand2,
    Warehouse,
}

/** Returns undefined for an unknown name — a missing icon beats an empty box. */
export function resolveIcon(name: Component | string | undefined): Component | undefined {
    if (!name) return undefined
    if (typeof name !== 'string') return name

    return iconsByName[name]
}
