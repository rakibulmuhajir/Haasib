<?php

return array (
  'modules' => 
  array (
    'Accounting' => 
    array (
      'name' => 'Accounting',
      'namespace' => 'Modules\\Accounting',
      'provider' => 'Modules\\Accounting\\Providers\\AccountingServiceProvider',
      'schema' => 'acct',
      'description' => 'Accounting module',
      'version' => '1.0.0',
      'enabled' => true,
      'routes' => 
      array (
        'web' => true,
        'api' => true,
      ),
      'cli' => 
      array (
        'commands' => true,
        'palette' => true,
      ),
      'permissions' => 
      array (
      ),
    ),
  ),
  'auto_discovery' => 
  array (
    'enabled' => true,
    'path' => '/home/banna/projects/Haasib/build/modules',
    'namespace' => 'Modules\\',
  ),
  'loading_strategy' => 'company_context',
  'database' => 
  array (
    'default_schema' => 'acct',
    'uuid_primary_keys' => true,
    'company_isolation' => true,
    'soft_deletes' => true,
    'audit_trail' => true,
  ),
);
