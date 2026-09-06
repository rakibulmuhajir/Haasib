<?php

return [
    'operations' => [
        /*
         * One Operations workspace, several useful levels of detail. The mapping is
         * configuration rather than a row of role checks scattered through a
         * controller and a Vue page. Permissions still decide whether the
         * feature may be opened; this decides what a permitted user receives.
         */
        'presentation_by_role' => [
            'owner' => 'operational',
            'manager' => 'operational',
            'accountant' => 'summary',
            'operations' => 'operational',
            'agent' => 'agent_operational',
            'super_admin' => 'operational',
        ],

        'default_presentation' => 'summary',

        // These roles land on Operations when they open the Umrah module.
        'landing_roles' => ['operations'],

        /*
         * Travel timestamps are stored as local wall-clock values. This zone
         * is only used to decide what "today" means for Saudi operations; it
         * never converts or rewrites a displayed flight/hotel time.
         */
        'operational_timezone' => 'Asia/Riyadh',
    ],
];
