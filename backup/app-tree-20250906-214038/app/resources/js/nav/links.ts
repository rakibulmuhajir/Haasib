// Centralized navigation link definitions
export type NavLinkItem = {
  id: string
  label: string
  route: string
  match: string
  group: 'general' | 'admin'
  includeInHeader?: boolean
  includeInSidebar?: boolean
  superadmin?: boolean
}

export const navLinks: NavLinkItem[] = [
  {
    id: 'dashboard',
    label: 'Dashboard',
    route: 'dashboard',
    match: 'dashboard',
    group: 'general',
    includeInHeader: true,
    includeInSidebar: true,
  },
  {
    id: 'admin.dashboard',
    label: 'Admin',
    route: 'admin.dashboard',
    match: 'admin.*',
    group: 'admin',
    includeInHeader: true,
    includeInSidebar: true,
    superadmin: true,
  },
  {
    id: 'admin.companies.index',
    label: 'Companies',
    route: 'admin.companies.index',
    match: 'admin.companies.*',
    group: 'admin',
    includeInHeader: false,
    includeInSidebar: true,
    superadmin: true,
  },
  {
    id: 'admin.users.index',
    label: 'Users',
    route: 'admin.users.index',
    match: 'admin.users.*',
    group: 'admin',
    includeInHeader: false,
    includeInSidebar: true,
    superadmin: true,
  },
]

