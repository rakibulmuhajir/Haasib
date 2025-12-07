# Schema Contract — System & Infrastructure (sys)

Single source of truth for system utilities, configuration, API access, webhooks, audit logging, and background jobs.

## Guardrails
- Schema: `sys` on `pgsql`.
- UUID primary keys with `public.gen_random_uuid()` default.
- Soft deletes only where appropriate (API keys, webhooks).
- RLS required with company isolation where applicable.
- Models must set `$connection = 'pgsql'`, schema-qualified `$table`.
- Sensitive data (API keys, secrets) must be encrypted at rest.
- Audit logs are append-only; never delete or modify.
- Background jobs use Laravel's queue system; this is supplementary tracking.

## Tables

### sys.settings
- Purpose: company-specific configuration key-value store.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid not null FK → `auth.companies.id` (CASCADE/CASCADE).
  - `group` varchar(100) not null (category, e.g., 'invoicing', 'tax', 'notifications').
  - `key` varchar(255) not null.
  - `value` text nullable.
  - `value_type` varchar(20) not null default 'string'. Enum: string, integer, decimal, boolean, json, encrypted.
  - `is_encrypted` boolean not null default false.
  - `description` text nullable.
  - `is_readonly` boolean not null default false (system settings).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique (`company_id`, `group`, `key`).
  - Index: `company_id`; (`company_id`, `group`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'sys.settings'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','group','key','value','value_type','is_encrypted','description','is_readonly'];`
  - `$casts = ['company_id'=>'string','is_encrypted'=>'boolean','is_readonly'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];`
- Validation:
  - `group`: required|string|max:100.
  - `key`: required|string|max:255; unique per company+group.
  - `value_type`: required|in:string,integer,decimal,boolean,json,encrypted.
- Business rules:
  - Use accessor/mutator for type conversion and encryption.
  - Readonly settings cannot be changed via UI.
  - Cache frequently accessed settings.

### sys.api_keys
- Purpose: API authentication for external integrations.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid nullable FK → `auth.companies.id` (CASCADE/CASCADE).
  - `user_id` uuid nullable FK → `auth.users.id` (CASCADE/CASCADE).
  - `name` varchar(255) not null.
  - `key_prefix` varchar(8) not null (visible part, e.g., 'hb_live_').
  - `key_hash` varchar(255) not null (hashed secret).
  - `permissions` jsonb not null default '[]'. Array of permission strings.
  - `rate_limit` integer not null default 1000 (requests per minute).
  - `allowed_ips` jsonb nullable. Array of IP addresses/CIDRs.
  - `last_used_at` timestamp nullable.
  - `last_used_ip` inet nullable.
  - `expires_at` timestamp nullable.
  - `is_active` boolean not null default true.
  - `revoked_at` timestamp nullable.
  - `revoked_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `revoke_reason` varchar(255) nullable.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Unique `key_hash`.
  - Index: `company_id`; `user_id`; (`is_active`, `expires_at`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'sys.api_keys'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','user_id','name','key_prefix','key_hash','permissions','rate_limit','allowed_ips','expires_at','is_active','revoked_at','revoked_by_user_id','revoke_reason','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','user_id'=>'string','permissions'=>'array','rate_limit'=>'integer','allowed_ips'=>'array','last_used_at'=>'datetime','expires_at'=>'datetime','is_active'=>'boolean','revoked_at'=>'datetime','revoked_by_user_id'=>'string','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
  - `$hidden = ['key_hash'];`
- Validation:
  - `name`: required|string|max:255.
  - `permissions`: array.
  - `rate_limit`: integer|min:1|max:10000.
  - `expires_at`: nullable|date|after:now.
- Business rules:
  - Full key shown only once on creation.
  - Key format: `hb_{env}_{random}` (e.g., `hb_live_abc123xyz`).
  - Hash key with bcrypt before storage.
  - company_id null for super-admin keys.
  - Revoked keys cannot be reactivated.

### sys.webhooks
- Purpose: outbound webhook configuration.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid nullable FK → `auth.companies.id` (CASCADE/CASCADE).
  - `name` varchar(255) not null.
  - `url` varchar(500) not null.
  - `method` varchar(10) not null default 'POST'. Enum: POST, PUT, PATCH.
  - `headers` jsonb not null default '{}'. Custom headers.
  - `events` jsonb not null default '[]'. Array of event names.
  - `secret` varchar(255) nullable (for signature verification).
  - `is_active` boolean not null default true.
  - `retry_count` integer not null default 3.
  - `retry_delay_seconds` integer not null default 60.
  - `timeout_seconds` integer not null default 30.
  - `last_triggered_at` timestamp nullable.
  - `last_status_code` integer nullable.
  - `failure_count` integer not null default 0.
  - `created_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; (`is_active`); (`company_id`, `is_active`).
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'sys.webhooks'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','name','url','method','headers','events','secret','is_active','retry_count','retry_delay_seconds','timeout_seconds','created_by_user_id'];`
  - `$casts = ['company_id'=>'string','headers'=>'array','events'=>'array','is_active'=>'boolean','retry_count'=>'integer','retry_delay_seconds'=>'integer','timeout_seconds'=>'integer','last_triggered_at'=>'datetime','last_status_code'=>'integer','failure_count'=>'integer','created_by_user_id'=>'string','created_at'=>'datetime','updated_at'=>'datetime'];`
  - `$hidden = ['secret'];`
- Validation:
  - `name`: required|string|max:255.
  - `url`: required|url|max:500.
  - `method`: required|in:POST,PUT,PATCH.
  - `events`: required|array|min:1.
  - `retry_count`: integer|min:0|max:10.
  - `timeout_seconds`: integer|min:5|max:120.
- Business rules:
  - Sign payloads with HMAC-SHA256 using secret.
  - Auto-disable after consecutive failures (configurable threshold).
  - company_id null for system-wide webhooks.

### sys.webhook_deliveries
- Purpose: webhook delivery log and retry tracking.
- Columns:
  - `id` uuid PK.
  - `webhook_id` uuid not null FK → `sys.webhooks.id` (CASCADE/CASCADE).
  - `event_name` varchar(100) not null.
  - `payload` jsonb not null.
  - `status` varchar(20) not null default 'pending'. Enum: pending, success, failed, cancelled.
  - `attempt_count` integer not null default 0.
  - `max_attempts` integer not null default 3.
  - `last_attempt_at` timestamp nullable.
  - `next_attempt_at` timestamp nullable.
  - `response_status` integer nullable.
  - `response_body` text nullable.
  - `response_time_ms` integer nullable.
  - `error_message` text nullable.
  - `created_at` timestamp not null default now().
- Indexes/constraints:
  - PK `id`.
  - Index: `webhook_id`; (`status`, `next_attempt_at`); `created_at`.
- RLS: inherited from webhook (or none for processing).
- Model:
  - `$connection = 'pgsql'; $table = 'sys.webhook_deliveries'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['webhook_id','event_name','payload','status','attempt_count','max_attempts','last_attempt_at','next_attempt_at','response_status','response_body','response_time_ms','error_message'];`
  - `$casts = ['webhook_id'=>'string','payload'=>'array','attempt_count'=>'integer','max_attempts'=>'integer','last_attempt_at'=>'datetime','next_attempt_at'=>'datetime','response_status'=>'integer','response_time_ms'=>'integer','created_at'=>'datetime'];`
- Business rules:
  - Retry with exponential backoff.
  - Success = 2xx status code.
  - Cleanup old deliveries after retention period.

### sys.audit_log
- Purpose: immutable audit trail of significant actions.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid nullable FK → `auth.companies.id` (SET NULL/CASCADE).
  - `user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `action` varchar(100) not null.
  - `entity_type` varchar(100) nullable (e.g., 'acct.invoices').
  - `entity_id` uuid nullable.
  - `old_values` jsonb nullable.
  - `new_values` jsonb nullable.
  - `metadata` jsonb not null default '{}'. Additional context.
  - `ip_address` inet nullable.
  - `user_agent` text nullable.
  - `session_id` varchar(255) nullable.
  - `created_at` timestamp not null default now().
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `user_id`; (`entity_type`, `entity_id`); `action`; `created_at`.
- RLS: company_id + super-admin override (read-only for non-admins).
- Model:
  - `$connection = 'pgsql'; $table = 'sys.audit_log'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','user_id','action','entity_type','entity_id','old_values','new_values','metadata','ip_address','user_agent','session_id'];`
  - `$casts = ['company_id'=>'string','user_id'=>'string','entity_id'=>'string','old_values'=>'array','new_values'=>'array','metadata'=>'array','created_at'=>'datetime'];`
  - Model should be read-only (no update/delete).
- Validation: N/A (system-generated).
- Business rules:
  - Never delete or modify audit records.
  - Log significant actions: create, update, delete, login, logout, permission changes.
  - Sensitive values (passwords) should be masked.
  - Retention policy: keep for compliance period (7 years typical).

### sys.notifications
- Purpose: user notifications/alerts.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid nullable FK → `auth.companies.id` (CASCADE/CASCADE).
  - `user_id` uuid not null FK → `auth.users.id` (CASCADE/CASCADE).
  - `type` varchar(100) not null.
  - `title` varchar(255) not null.
  - `message` text not null.
  - `data` jsonb not null default '{}'. Action URLs, entity references.
  - `channel` varchar(30) not null default 'database'. Enum: database, email, push, sms.
  - `priority` varchar(20) not null default 'normal'. Enum: low, normal, high, urgent.
  - `is_read` boolean not null default false.
  - `read_at` timestamp nullable.
  - `sent_at` timestamp nullable.
  - `created_at` timestamp not null default now().
- Indexes/constraints:
  - PK `id`.
  - Index: `user_id`; (`user_id`, `is_read`); (`user_id`, `created_at`); `type`.
- RLS: user_id matches current user OR super-admin.
- Model:
  - `$connection = 'pgsql'; $table = 'sys.notifications'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','user_id','type','title','message','data','channel','priority','is_read','read_at','sent_at'];`
  - `$casts = ['company_id'=>'string','user_id'=>'string','data'=>'array','is_read'=>'boolean','read_at'=>'datetime','sent_at'=>'datetime','created_at'=>'datetime'];`
- Business rules:
  - Use Laravel notifications; this is for database channel.
  - Mark as read on view.
  - Cleanup old read notifications after retention period.

### sys.background_jobs
- Purpose: track long-running background jobs beyond Laravel's queue.
- Columns:
  - `id` uuid PK.
  - `company_id` uuid nullable FK → `auth.companies.id` (SET NULL/CASCADE).
  - `user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `job_type` varchar(100) not null.
  - `job_name` varchar(255) not null.
  - `payload` jsonb not null default '{}'.
  - `status` varchar(30) not null default 'pending'. Enum: pending, queued, running, completed, failed, cancelled.
  - `progress` integer not null default 0; check 0-100.
  - `progress_message` varchar(255) nullable.
  - `result` jsonb nullable.
  - `error_message` text nullable.
  - `error_trace` text nullable.
  - `queued_at` timestamp nullable.
  - `started_at` timestamp nullable.
  - `completed_at` timestamp nullable.
  - `cancelled_at` timestamp nullable.
  - `cancelled_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `retries` integer not null default 0.
  - `max_retries` integer not null default 3.
  - `created_at`, `updated_at` timestamps.
- Indexes/constraints:
  - PK `id`.
  - Index: `company_id`; `user_id`; (`status`); (`company_id`, `status`); `job_type`.
- RLS: company_id + super-admin override.
- Model:
  - `$connection = 'pgsql'; $table = 'sys.background_jobs'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['company_id','user_id','job_type','job_name','payload','status','progress','progress_message','result','error_message','error_trace','queued_at','started_at','completed_at','cancelled_at','cancelled_by_user_id','retries','max_retries'];`
  - `$casts = ['company_id'=>'string','user_id'=>'string','payload'=>'array','progress'=>'integer','result'=>'array','queued_at'=>'datetime','started_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime','cancelled_by_user_id'=>'string','retries'=>'integer','max_retries'=>'integer','created_at'=>'datetime','updated_at'=>'datetime'];`
- Business rules:
  - Track jobs like report generation, imports, exports.
  - Update progress for long-running jobs.
  - User can cancel pending/running jobs.
  - Cleanup completed jobs after retention period.

### sys.failed_jobs
- Purpose: failed job registry for debugging.
- Columns:
  - `id` uuid PK.
  - `queue` varchar(100) not null default 'default'.
  - `job_type` varchar(255) not null.
  - `payload` jsonb not null.
  - `exception` text not null.
  - `failed_at` timestamp not null default now().
  - `resolved_at` timestamp nullable.
  - `resolved_by_user_id` uuid nullable FK → `auth.users.id` (SET NULL/CASCADE).
  - `resolution_notes` text nullable.
- Indexes/constraints:
  - PK `id`.
  - Index: `queue`; `job_type`; `failed_at`; (`resolved_at` IS NULL).
- RLS: super-admin only.
- Model:
  - `$connection = 'pgsql'; $table = 'sys.failed_jobs'; $keyType = 'string'; public $incrementing = false;`
  - `$fillable = ['queue','job_type','payload','exception','resolved_at','resolved_by_user_id','resolution_notes'];`
  - `$casts = ['payload'=>'array','failed_at'=>'datetime','resolved_at'=>'datetime','resolved_by_user_id'=>'string'];`
- Business rules:
  - Mirrors Laravel's failed_jobs but with resolution tracking.
  - Alert on failure threshold.
  - Retry or resolve failed jobs.

### sys.schema_versions
- Purpose: track applied database migrations.
- Columns:
  - `id` uuid PK.
  - `module_code` varchar(100) not null (e.g., '00_core', '10_accounting').
  - `version` varchar(50) not null.
  - `checksum` varchar(64) not null (SHA-256 of migration file).
  - `applied_by` varchar(255) not null.
  - `applied_at` timestamp not null default now().
  - `notes` text nullable.
- Indexes/constraints:
  - PK `id`.
  - Unique (`module_code`, `version`).
  - Index: `module_code`.
- RLS: None (system table).
- Business rules:
  - Updated by migration scripts.
  - Used for version checks and rollback tracking.

## Webhook Events

Standard events to subscribe to:

| Event | Payload |
|-------|---------|
| invoice.created | Invoice object |
| invoice.paid | Invoice + payment |
| invoice.overdue | Invoice object |
| bill.created | Bill object |
| bill.paid | Bill + payment |
| payment.received | Payment object |
| customer.created | Customer object |
| customer.updated | Customer old/new |

## Audit Actions

Standard actions to log:

| Action | Description |
|--------|-------------|
| user.login | User logged in |
| user.logout | User logged out |
| user.password_changed | Password changed |
| entity.created | Record created |
| entity.updated | Record updated |
| entity.deleted | Record deleted |
| permission.granted | Permission added |
| permission.revoked | Permission removed |
| setting.changed | Setting modified |

## Form Behaviors

### Settings Page
- Group settings by category
- Type-appropriate inputs (text, number, toggle, JSON editor)
- Readonly indicator for system settings
- Reset to default action

### API Keys Page
- List active keys (masked)
- Create new key (show full key once)
- Set permissions, expiry, IP restrictions
- Revoke with reason

### Webhooks Page
- List webhooks with status
- Test webhook action
- View delivery history
- Retry failed deliveries

### Audit Log Viewer
- Filter by date, user, action, entity
- Search in values
- Export to CSV
- Cannot modify records

### Background Jobs Monitor
- List jobs by status
- View progress for running jobs
- Cancel pending/running jobs
- View error details for failed jobs

## Out of Scope (v1)
- Two-factor authentication setup storage.
- OAuth provider configuration.
- Feature flags system.
- A/B testing framework.
- Usage analytics/metrics.
- Multi-tenant billing/subscriptions.

## Extending
- Add new notification types here first.
- Add new webhook events here first.
- Feature flags would add `sys.feature_flags` and `sys.feature_flag_overrides`.
- OAuth would add `sys.oauth_clients` and `sys.oauth_tokens`.
