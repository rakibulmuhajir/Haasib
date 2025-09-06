// /home/banna/projects/Haasib/app/resources/js/types/index.d.ts

// A generic option for select dropdowns
export interface RoleOption {
  value: string;
  label: string;
}

// Represents a member of a company
export interface CompanyMember {
  id: number;
  name: string;
  email: string;
  role: string;
}

// Represents a user's membership in a company
export interface UserMembership {
  id: number;
  name: string;
  slug: string;
  role: string;
  created_at: string;
  updated_at: string;
}

// Represents a navigation link item
export interface NavLinkItem {
  id: string;
  label: string;
  route: string;
  match: string;
  group: 'general' | 'admin';
  includeInHeader: boolean;
  includeInSidebar: boolean;
  superadmin?: boolean;
}
