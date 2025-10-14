<?php

return [
    // General
    'companies' => 'Companies',
    'company' => 'Company',
    'create_company' => 'Create Company',
    'edit_company' => 'Edit Company',
    'delete_company' => 'Delete Company',
    'company_details' => 'Company Details',
    'company_settings' => 'Company Settings',
    'company_information' => 'Company Information',

    // Company Fields
    'name' => 'Company Name',
    'slug' => 'URL Slug',
    'industry' => 'Industry',
    'country' => 'Country',
    'base_currency' => 'Base Currency',
    'currency' => 'Currency',
    'timezone' => 'Timezone',
    'language' => 'Language',
    'locale' => 'Locale',
    'settings' => 'Settings',
    'is_active' => 'Active',
    'created_at' => 'Created At',
    'updated_at' => 'Updated At',

    // Company Actions
    'create_new_company' => 'Create New Company',
    'edit_company_details' => 'Edit Company Details',
    'deactivate_company' => 'Deactivate Company',
    'activate_company' => 'Activate Company',
    'switch_company' => 'Switch Company',
    'switch_to_company' => 'Switch to :company',
    'current_company' => 'Current Company',

    // Validation Messages
    'name_required' => 'Company name is required.',
    'name_min' => 'Company name must be at least 2 characters long.',
    'name_max' => 'Company name may not be greater than 255 characters.',
    'name_unique' => 'A company with this name already exists.',
    'name_invalid' => 'Company name contains invalid characters.',
    'slug_invalid' => 'Company slug may only contain lowercase letters, numbers, and hyphens.',
    'slug_unique' => 'A company with this slug already exists.',
    'currency_required' => 'Currency is required.',
    'currency_invalid' => 'Invalid currency selected.',
    'country_required' => 'Country is required.',
    'country_invalid' => 'Invalid country selected.',
    'timezone_invalid' => 'Invalid timezone selected.',
    'language_invalid' => 'Invalid language selected.',
    'locale_invalid' => 'Locale must be in format like en_US.',

    // Business Logic Validation
    'cannot_deactivate_with_users' => 'Cannot deactivate company with active users. Please deactivate users first or transfer ownership.',
    'cannot_change_currency_with_transactions' => 'Cannot change currency after financial transactions have been created.',
    'cannot_change_timezone_with_fiscal_years' => 'Cannot change timezone after fiscal years have been created.',
    'max_users_limit_exceeded' => 'Maximum users limit (:limit) cannot be less than current users (:current).',
    'storage_format_invalid' => 'Storage limit must be in format like 1GB, 500MB, 2TB, etc.',
    'ownership_transfer_required' => 'New owner ID must be provided when transferring ownership.',

    // Success Messages
    'company_created' => 'Company ":name" created successfully!',
    'company_updated' => 'Company ":name" updated successfully!',
    'company_deleted' => 'Company ":name" deleted successfully!',
    'company_activated' => 'Company ":name" activated successfully!',
    'company_deactivated' => 'Company ":name" deactivated successfully!',
    'company_switched' => 'Switched to ":name" successfully!',

    // Company Context
    'context_switched' => 'Company context switched successfully!',
    'current_context' => 'Current Company Context',
    'available_companies' => 'Available Companies',
    'no_companies_available' => 'No companies available.',
    'select_company' => 'Select Company',

    // Company Members
    'members' => 'Members',
    'team_members' => 'Team Members',
    'invite_member' => 'Invite Member',
    'manage_members' => 'Manage Members',
    'member_role' => 'Member Role',
    'member_status' => 'Member Status',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'joined_at' => 'Joined At',
    'remove_member' => 'Remove Member',
    'update_role' => 'Update Role',
    'change_role' => 'Change Role',

    // Roles
    'roles' => 'Roles',
    'owner' => 'Owner',
    'admin' => 'Admin',
    'accountant' => 'Accountant',
    'manager' => 'Manager',
    'employee' => 'Employee',
    'viewer' => 'Viewer',
    'role_owner' => 'Owner',
    'role_admin' => 'Administrator',
    'role_accountant' => 'Accountant',
    'role_manager' => 'Manager',
    'role_employee' => 'Employee',
    'role_viewer' => 'Viewer',

    // Company Invitations
    'invitations' => 'Invitations',
    'invitation' => 'Invitation',
    'send_invitation' => 'Send Invitation',
    'invite_user' => 'Invite User',
    'invitation_sent' => 'Invitation sent successfully!',
    'invitation_accepted' => 'Invitation accepted!',
    'invitation_rejected' => 'Invitation rejected!',
    'invitation_expired' => 'Invitation expired!',
    'pending_invitations' => 'Pending Invitations',
    'accepted_invitations' => 'Accepted Invitations',
    'rejected_invitations' => 'Rejected Invitations',
    'expired_invitations' => 'Expired Invitations',
    'resend_invitation' => 'Resend Invitation',
    'cancel_invitation' => 'Cancel Invitation',
    'accept_invitation' => 'Accept Invitation',
    'reject_invitation' => 'Reject Invitation',

    // Invitation Fields
    'email' => 'Email',
    'role' => 'Role',
    'message' => 'Message',
    'expires_at' => 'Expires At',
    'created_at' => 'Created At',
    'accepted_at' => 'Accepted At',
    'status' => 'Status',
    'invited_by' => 'Invited By',
    'invitation_token' => 'Invitation Token',
    'invitation_url' => 'Invitation URL',

    // Company Settings
    'features' => 'Features',
    'preferences' => 'Preferences',
    'limits' => 'Limits',
    'accounting' => 'Accounting',
    'reporting' => 'Reporting',
    'invoicing' => 'Invoicing',
    'inventory' => 'Inventory',
    'project_management' => 'Project Management',
    'fleet_management' => 'Fleet Management',
    'pos' => 'Point of Sale',
    'rd_management' => 'R&D Management',
    'asset_management' => 'Asset Management',

    // Preferences
    'theme' => 'Theme',
    'theme_light' => 'Light',
    'theme_dark' => 'Dark',
    'theme_auto' => 'Auto',

    // Limits
    'max_users' => 'Maximum Users',
    'max_storage' => 'Maximum Storage',

    // Company Lists
    'all_companies' => 'All Companies',
    'active_companies' => 'Active Companies',
    'inactive_companies' => 'Inactive Companies',
    'my_companies' => 'My Companies',
    'search_companies' => 'Search Companies...',
    'filter_by_country' => 'Filter by Country',
    'filter_by_status' => 'Filter by Status',
    'sort_by' => 'Sort By',
    'sort_name' => 'Name',
    'sort_created_at' => 'Created Date',
    'sort_updated_at' => 'Updated Date',
    'sort_order' => 'Sort Order',
    'sort_asc' => 'Ascending',
    'sort_desc' => 'Descending',

    // Pagination
    'per_page' => 'Per Page',
    'showing' => 'Showing',
    'of' => 'of',
    'results' => 'results',
    'previous' => 'Previous',
    'next' => 'Next',
    'first' => 'First',
    'last' => 'Last',

    // Company Statistics
    'total_companies' => 'Total Companies',
    'active_users' => 'Active Users',
    'total_users' => 'Total Users',
    'total_invitations' => 'Total Invitations',
    'pending_invitations_count' => 'Pending Invitations',
    'company_created_this_month' => 'Companies Created This Month',
    'users_added_this_month' => 'Users Added This Month',

    // Company Features
    'enable_features' => 'Enable Features',
    'disabled_features' => 'Disabled Features',
    'feature_not_available' => 'This feature is not available for your current plan.',
    'upgrade_plan' => 'Upgrade Plan',

    // Company Permissions
    'companies_view' => 'View Companies',
    'companies_create' => 'Create Companies',
    'companies_update' => 'Update Companies',
    'companies_delete' => 'Delete Companies',
    'companies_export' => 'Export Companies',
    'companies_invite' => 'Invite Users',
    'companies_manage_members' => 'Manage Members',
    'companies_switch_context' => 'Switch Company Context',

    // Error Messages
    'company_not_found' => 'Company not found.',
    'access_denied' => 'Access denied.',
    'insufficient_permissions' => 'Insufficient permissions to perform this action.',
    'cannot_access_company' => 'You do not have access to this company.',
    'company_already_exists' => 'A company with this name already exists.',
    'invitation_already_sent' => 'An invitation has already been sent to this email.',
    'user_already_member' => 'This user is already a member of the company.',
    'cannot_remove_owner' => 'Cannot remove the company owner.',
    'cannot_invite_yourself' => 'You cannot invite yourself to the company.',

    // Confirmation Messages
    'confirm_delete_company' => 'Are you sure you want to delete this company? This action cannot be undone.',
    'confirm_remove_member' => 'Are you sure you want to remove this member from the company?',
    'confirm_deactivate_company' => 'Are you sure you want to deactivate this company?',
    'confirm_invite_resend' => 'Are you sure you want to resend this invitation?',

    // Instructions
    'company_create_instructions' => 'Fill in the company details below to create a new company.',
    'invitation_instructions' => 'Enter the email address of the person you want to invite to join your company.',
    'role_instructions' => 'Select the appropriate role for this team member.',

    // Placeholders
    'company_name_placeholder' => 'Enter company name',
    'email_placeholder' => 'Enter email address',
    'search_placeholder' => 'Search companies...',
    'message_placeholder' => 'Add a personal message (optional)',

    // Help Text
    'company_name_help' => 'This will be displayed throughout the application.',
    'slug_help' => 'Used in URLs and company references. Auto-generated from name.',
    'currency_help' => 'Default currency for all financial transactions.',
    'timezone_help' => 'Default timezone for date and time displays.',
    'language_help' => 'Default language for the company interface.',
    'role_help' => 'Determines what this user can access in the company.',

    // Company Dashboard
    'dashboard' => 'Dashboard',
    'company_dashboard' => 'Company Dashboard',
    'welcome_message' => 'Welcome to :company!',
    'quick_actions' => 'Quick Actions',
    'recent_activity' => 'Recent Activity',
    'company_overview' => 'Company Overview',
    'quick_stats' => 'Quick Statistics',

    // Navigation
    'company_navigation' => 'Company Navigation',
    'switcher_title' => 'Switch Company',
    'no_companies_yet' => 'No companies yet',
    'create_first_company' => 'Create your first company',

    // Empty States
    'no_companies_found' => 'No companies found.',
    'no_members_found' => 'No members found.',
    'no_invitations_found' => 'No invitations found.',
    'no_pending_invitations' => 'No pending invitations.',

    // Company Reports
    'reports' => 'Reports',
    'company_report' => 'Company Report',
    'member_report' => 'Member Report',
    'invitation_report' => 'Invitation Report',
    'generate_report' => 'Generate Report',
    'export_data' => 'Export Data',

    // Company Export
    'export_companies' => 'Export Companies',
    'export_members' => 'Export Members',
    'export_invitations' => 'Export Invitations',
    'export_format' => 'Export Format',
    'export_csv' => 'CSV',
    'export_excel' => 'Excel',
    'export_pdf' => 'PDF',
    'download_export' => 'Download Export',

    // Company Search
    'search_companies_by_name' => 'Search companies by name...',
    'search_members_by_name' => 'Search members by name or email...',
    'search_invitations_by_email' => 'Search invitations by email...',
    'clear_search' => 'Clear Search',
    'search_results' => 'Search Results',
    'no_search_results' => 'No results found for ":query".',

    // Company Filters
    'filter' => 'Filter',
    'clear_filters' => 'Clear Filters',
    'active_filters' => 'Active Filters',
    'filter_by_role' => 'Filter by Role',
    'filter_by_status' => 'Filter by Status',
    'filter_by_country' => 'Filter by Country',
    'filter_by_date_range' => 'Filter by Date Range',

    // Company Bulk Actions
    'bulk_actions' => 'Bulk Actions',
    'select_all' => 'Select All',
    'deselect_all' => 'Deselect All',
    'selected_items' => ':count items selected',
    'bulk_delete' => 'Delete Selected',
    'bulk_export' => 'Export Selected',
    'bulk_invite' => 'Send Invitations',
    'bulk_activate' => 'Activate Selected',
    'bulk_deactivate' => 'Deactivate Selected',

    // Company History
    'history' => 'History',
    'company_history' => 'Company History',
    'view_history' => 'View History',
    'activity_log' => 'Activity Log',
    'audit_trail' => 'Audit Trail',

    // Company Settings Advanced
    'advanced_settings' => 'Advanced Settings',
    'company_configuration' => 'Company Configuration',
    'security_settings' => 'Security Settings',
    'integration_settings' => 'Integration Settings',
    'notification_settings' => 'Notification Settings',
    'backup_settings' => 'Backup Settings',

    // Company Statuses
    'status_active' => 'Active',
    'status_inactive' => 'Inactive',
    'status_suspended' => 'Suspended',
    'status_archived' => 'Archived',

    // Company Categories
    'company_categories' => 'Company Categories',
    'business_type' => 'Business Type',
    'company_size' => 'Company Size',
    'revenue_range' => 'Revenue Range',
    'employee_count' => 'Employee Count',

    // Company Documents
    'documents' => 'Documents',
    'upload_document' => 'Upload Document',
    'company_documents' => 'Company Documents',
    'document_types' => 'Document Types',

    // Company Integrations
    'integrations' => 'Integrations',
    'connected_services' => 'Connected Services',
    'integration_settings' => 'Integration Settings',
    'connect_service' => 'Connect Service',
    'disconnect_service' => 'Disconnect Service',
];
