# Phase 0: Research - Company Registration Multi-Company Creation

**Feature**: Company Registration - Multi-Company Creation  
**Date**: 2025-10-07  
**Status**: In Progress  

## Legacy System Analysis

Based on analysis of the `/home/banna/projects/Haasib/app` directory, the legacy system provides comprehensive patterns for multi-company user management:

### User-Company Relationship Model
- **Many-to-Many Relationship**: Users can belong to multiple companies via `auth.company_user` pivot table
- **Role-Based Access**: Users have specific roles (owner, admin, accountant, viewer) per company
- **Invitation System**: Owner users can invite other users to join their company with specific roles
- **Audit Trail**: Company relationships track who invited whom and when

### Company Structure
- **Core Company Entity**: `auth.companies` table with name, slug, currency, timezone
- **Company Creator**: Each company tracks the creating user via `created_by_user_id`
- **Multi-Currency Support**: Companies have primary and secondary currencies
- **Context Switching**: System supports switching between company contexts

### Role System
```php
enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin'; 
    case Accountant = 'accountant';
    case Viewer = 'viewer';
}
```

### Command Bus Integration
- Company creation uses `CompanyCreate` action
- Role assignments use `CompanyAssign` action  
- User invitations use `CompanyInvite` action
- All operations follow command-bus supremacy principle

## Technical Requirements Identified

### From Specification
1. **Company Creation**: Name, currency, timezone requirements
2. **Fiscal Year Management**: Auto-creation with monthly periods
3. **Chart of Accounts**: Core account types and detailed accounts
4. **Context Switching**: UI and CLI company context management
5. **Data Isolation**: Strict cross-company data prevention

### From User Input
1. **Multi-Company Membership**: Users belong to multiple companies
2. **Invitation System**: Owners can invite users with different positions
3. **Role Hierarchy**: Different permission levels per company

### From Legacy Analysis
1. **Permission Integration**: Spatie Laravel Permission package
2. **ServiceContext Pattern**: User context and tenancy management
3. **Audit Requirements**: All company mutations must be auditable
4. **CLI Parity**: Command-line interface for all operations

## Constitutional Alignment Assessment

### ✅ Compliant Areas
- **Command-Bus Supremacy**: Legacy system already implements this pattern
- **RBAC Integrity**: Role-based permissions are well-defined
- **Tenancy & RLS**: Company_id scoping is established pattern
- **Module Governance**: Clear separation of concerns in legacy code

### ⚠️ Areas Requiring Attention
- **Translation Support**: Need to ensure all user-facing strings use locale files
- **PrimeVue v4 Compliance**: UI components must follow current standards
- **Testing Strategy**: Need comprehensive test coverage for multi-company scenarios

## Dependencies and Constraints

### Existing Dependencies
- Laravel 12 with PHP 8.2+
- Spatie Laravel Permission package
- PostgreSQL 16 with RLS
- Vue 3 + Inertia.js v2

### Integration Points
- Existing user management system
- Current permission framework
- ServiceContext pattern for tenancy
- Command bus infrastructure

## Open Questions for Clarification

1. **Fiscal Year Calendar**: Should fiscal years follow calendar year or configurable start dates?
2. **Chart of Accounts Templates**: Should we provide industry-specific account templates?
3. **Company Switching**: How should UI handle rapid company switching (dropdown, sidebar, separate pages)?
4. **Permission Granularity**: Are the four roles (owner, admin, accountant, viewer) sufficient or need additional granularity?

## Research Summary

The legacy application provides a solid foundation for multi-company management with well-established patterns for user-company relationships, role-based access control, and command-bus integration. The new implementation should leverage these existing patterns while ensuring compliance with current constitutional requirements and technology stack standards.

**Key Finding**: No major architectural blockers identified. Implementation can proceed with confidence leveraging existing patterns.