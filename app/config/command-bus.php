<?php

return [
    'user.create' => App\Actions\DevOps\UserCreate::class,
    'user.delete' => App\Actions\DevOps\UserDelete::class,
    'company.create' => App\Actions\DevOps\CompanyCreate::class,
    'company.delete' => App\Actions\DevOps\CompanyDelete::class,
    'company.assign' => App\Actions\DevOps\CompanyAssign::class,
    'company.unassign' => App\Actions\DevOps\CompanyUnassign::class,
];

