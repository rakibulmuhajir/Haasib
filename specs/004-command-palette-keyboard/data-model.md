# Data Model: Command Palette - Keyboard-First Interface

**Feature Branch**: `004-command-palette-keyboard`
**Generated**: 2025-10-13
**Source**: Extracted from `spec.md` key entities

## Overview

The Command Palette feature introduces several new entities to support keyboard-driven command execution with natural language processing, autocomplete, and contextual suggestions. All entities maintain company tenancy and RBAC integrity.

## Entity Definitions

### Command
Represents an individual action that can be executed through the command palette.

**Attributes:**
- `id` (UUID, Primary Key)
- `company_id` (UUID, Foreign Key to companies table, for tenancy)
- `name` (String, Unique within company, CLI-style name)
- `description` (String, User-friendly description)
- `parameters` (JSON, Schema defining required/optional parameters)
- `required_permissions` (JSON Array, List of permission slugs needed)
- `is_active` (Boolean, Default true)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

**Relationships:**
- Belongs to Company (tenancy)
- Has many Command Templates
- Has many Command Executions
- Has many Command History entries

### Command Template
Predefined command structures with saved parameter sets for quick reuse.

**Attributes:**
- `id` (UUID, Primary Key)
- `command_id` (UUID, Foreign Key to commands)
- `user_id` (UUID, Foreign Key to users, who created the template)
- `name` (String, Template name)
- `parameter_values` (JSON, Pre-filled parameter values)
- `is_shared` (Boolean, Whether other users can see this template)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

**Relationships:**
- Belongs to Command
- Belongs to User

### Command History
Records of previously executed commands for audit and repeat execution.

**Attributes:**
- `id` (UUID, Primary Key)
- `user_id` (UUID, Foreign Key to users)
- `command_id` (UUID, Foreign Key to commands)
- `company_id` (UUID, Foreign Key to companies, for tenancy)
- `executed_at` (Timestamp)
- `input_text` (String, The natural language input)
- `parameters_used` (JSON, Actual parameters passed)
- `execution_status` (Enum: success, failed, partial)
- `result_summary` (Text, Brief result description)
- `audit_reference` (UUID, Link to audit log entry)
- `created_at` (Timestamp)

**Relationships:**
- Belongs to User
- Belongs to Command
- Belongs to Company (tenancy)

### Command Suggestion
Context-aware recommendations generated during typing.

**Attributes:**
- `id` (UUID, Primary Key) - May be temporary/session-based
- `user_id` (UUID, Foreign Key to users)
- `company_id` (UUID, Foreign Key to companies)
- `input_text` (String, Current typed input)
- `suggested_commands` (JSON Array, List of command IDs with confidence scores)
- `context_data` (JSON, Current app context: page, permissions, etc.)
- `created_at` (Timestamp)

**Relationships:**
- Belongs to User
- Belongs to Company
- References Commands (through suggested_commands)

### Command Execution
Tracks the actual running of commands, including status and timing.

**Attributes:**
- `id` (UUID, Primary Key)
- `command_id` (UUID, Foreign Key to commands)
- `user_id` (UUID, Foreign Key to users)
- `company_id` (UUID, Foreign Key to companies)
- `idempotency_key` (String, For preventing duplicate executions)
- `status` (Enum: pending, running, completed, failed)
- `started_at` (Timestamp)
- `completed_at` (Timestamp, Nullable)
- `parameters` (JSON, Parameters passed to execution)
- `result` (JSON, Execution result data)
- `error_message` (Text, Nullable, Error details if failed)
- `audit_reference` (UUID, Link to audit log entry)
- `created_at` (Timestamp)
- `updated_at` (Timestamp)

**Relationships:**
- Belongs to Command
- Belongs to User
- Belongs to Company

### Audit Reference
Links command executions to the audit logging system.

**Note**: This entity may be a reference table or integrated with existing audit logs rather than a separate table. It ensures traceability of command executions through the audit system.

**Attributes:**
- `id` (UUID, Primary Key)
- `execution_id` (UUID, Foreign Key to command_executions)
- `audit_log_id` (UUID, Foreign Key to audit_logs table)
- `reference_type` (String, e.g., 'command_execution')
- `created_at` (Timestamp)

**Relationships:**
- Belongs to Command Execution
- Belongs to Audit Log (existing system)

## Database Schema Considerations

- All tables use UUID primary keys for global uniqueness
- Company tenancy enforced via `company_id` on all relevant tables
- RLS policies applied to ensure users only see their company's data
- Indexes on frequently queried fields: `company_id`, `user_id`, `command_id`, `status`
- JSON columns for flexible parameter storage and context data
- Audit references ensure all command executions are traceable

## Migration Strategy

1. Create new tables in the appropriate schema (likely `public` or feature-specific)
2. Add foreign key constraints with proper cascading
3. Create indexes for performance
4. Implement RLS policies for tenancy
5. Seed initial command definitions from existing command-bus actions
