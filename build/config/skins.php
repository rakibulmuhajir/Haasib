<?php

/*
|--------------------------------------------------------------------------
| Skins
|--------------------------------------------------------------------------
|
| A skin is a PALETTE, not a second design system. The grammar — rules rather
| than elevation, tabular figures, a visible focus ring, a non-colour indicator
| on every state, red reserved for adverse — is house policy and applies to
| every skin. A skin restates colour, and may adjust radius, type and default
| density. It does not get to re-decide what red means.
|
| This file is the single registry. Adding a skin means: one entry here, one
| token block in resources/css/app.css under [data-skin="your-id"], and
| nothing else. The blade template reads this list to apply the skin before
| first paint, and it is shared to the front end so the picker can offer it.
|
| `ground` is the page background applied inline in <head>, before the
| stylesheet loads. It exists so a page does not render on white and then
| repaint onto paper. It MUST match --surface-canvas for the same skin in
| app.css; nothing enforces that, so change them together.
|
*/

return [

    /*
    | The skin applied when a visitor has expressed no preference. 'default' is
    | the stock theme and has no [data-skin] attribute at all.
    */
    'default' => 'default',

    'available' => [

        'default' => [
            'label' => 'Default',
            'description' => 'The stock theme.',
            'ground' => [
                'light' => 'oklch(1 0 0)',
                'dark' => 'oklch(0.145 0 0)',
            ],
        ],

        'ledger' => [
            'label' => 'Ledger',
            'description' => 'Green-bar ledger paper, red structural rules, struck balances.',
            'ground' => [
                'light' => 'hsl(40 22% 98%)',
                'dark' => 'hsl(200 12% 7%)',
            ],
        ],

    ],

];
