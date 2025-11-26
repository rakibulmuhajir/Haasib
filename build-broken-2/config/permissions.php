<?php

/*
|--------------------------------------------------------------------------
| Global Permissions
|--------------------------------------------------------------------------
|
| All permissions are defined here ONCE. They are GLOBAL.
| Roles are company-scoped, but permissions are not.
|
| Naming convention: module.model.action (dot notation)
|
| When you add a new feature, add its permissions here and run:
| php artisan rbac:sync-permissions
|
*/

use App\Constants\Permissions;

return Permissions::getAllByModule();
