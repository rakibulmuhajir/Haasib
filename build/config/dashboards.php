<?php

/**
 * Default tab/widget layouts per dashboard per company role.
 *
 * This answers "what does a brand-new user of this role see on day one" and
 * is the only place that answers it — the resolver falls back here whenever
 * a user has no saved layout (auth.dashboard_layouts). Same JSON shape as a
 * saved layout: an ordered list of tabs, each an ordered list of widget
 * placements with a span (6 or 12) and per-placement options.
 *
 * Company roles today: owner, manager, accountant, operations, agent.
 */

/*
 * Scoped deliberately to what real data already backs.
 *
 * umrah.needs_attention and umrah.transport_readiness stay registered and
 * tested but are absent from every default layout. Both are switched on by
 * adding a placement below -- no code, no migration, no build. That is the
 * point of a tab being a named layout rather than a component: narrowing the
 * dashboard costs an array entry, and so does widening it again.
 */

return [

    'umrah' => [
        'roles' => [

            'owner' => [
                [
                    'key' => 'upcoming',
                    'label' => 'Upcoming',
                    'widgets' => [
                        ['key' => 'umrah.departures', 'span' => 12, 'options' => ['limit' => 10]],
                    ],
                ],
                [
                    'key' => 'money',
                    'label' => 'Money',
                    'widgets' => [
                        ['key' => 'umrah.cash_position', 'span' => 12, 'options' => []],
                        ['key' => 'umrah.cash_book', 'span' => 12, 'options' => []],
                        ['key' => 'umrah.agent_balances', 'span' => 6, 'options' => []],
                        ['key' => 'umrah.vendor_balances', 'span' => 6, 'options' => []],
                    ],
                ],
            ],

            // "admin" in company terms is the 'manager' role -- same tabs as owner.
            'manager' => [
                [
                    'key' => 'upcoming',
                    'label' => 'Upcoming',
                    'widgets' => [
                        ['key' => 'umrah.departures', 'span' => 12, 'options' => ['limit' => 10]],
                    ],
                ],
                [
                    'key' => 'money',
                    'label' => 'Money',
                    'widgets' => [
                        ['key' => 'umrah.cash_position', 'span' => 12, 'options' => []],
                        ['key' => 'umrah.cash_book', 'span' => 12, 'options' => []],
                        ['key' => 'umrah.agent_balances', 'span' => 6, 'options' => []],
                        ['key' => 'umrah.vendor_balances', 'span' => 6, 'options' => []],
                    ],
                ],
            ],

            'operations' => [
                [
                    'key' => 'upcoming',
                    'label' => 'Upcoming',
                    'widgets' => [
                        ['key' => 'umrah.departures', 'span' => 12, 'options' => ['limit' => 10]],
                    ],
                ],
            ],

            'accountant' => [
                [
                    'key' => 'money',
                    'label' => 'Money',
                    'widgets' => [
                        ['key' => 'umrah.cash_position', 'span' => 12, 'options' => []],
                        ['key' => 'umrah.cash_book', 'span' => 12, 'options' => []],
                        ['key' => 'umrah.agent_balances', 'span' => 6, 'options' => []],
                        ['key' => 'umrah.vendor_balances', 'span' => 6, 'options' => []],
                    ],
                ],
            ],

            // Agent (member) dashboard is scoped to their own agent_id inside
            // each widget's resolve() -- never widened here.
            'agent' => [
                [
                    'key' => 'upcoming',
                    'label' => 'Upcoming',
                    'widgets' => [
                        ['key' => 'umrah.departures', 'span' => 12, 'options' => ['limit' => 10]],
                    ],
                ],
            ],

        ],
    ],

];
