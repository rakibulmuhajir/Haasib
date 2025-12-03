<?php

namespace App\Constants;

class Tables
{
    public const COMPANIES = 'auth.companies';
    public const USERS = 'auth.users';
    public const COMPANY_USER = 'auth.company_user';
    public const ROLES = 'roles';
    public const PERMISSIONS = 'permissions';
    public const MODEL_HAS_ROLES = 'model_has_roles';
    public const ROLE_HAS_PERMISSIONS = 'role_has_permissions';
    public const COMMAND_IDEMPOTENCY = 'command_idempotency';
}
