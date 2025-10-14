<?php

// app/Enums/CompanyRole.php

namespace App\Enums;

enum CompanyRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Viewer = 'viewer';
}
