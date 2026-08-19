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
| There is one skin. The stock shadcn theme used to be registered here as
| 'default' and was the default; it was removed deliberately. A second theme is
| somewhere a half-converted page can hide — "it looks fine on default" — and
| the point of this pass is that no element renders undecided. The machinery
| stays because adding a skin later should not mean rebuilding it: one entry
| here plus one token block under [data-skin="your-id"] in app.css.
|
| `ground` is the page background applied inline in <head>, before the
| stylesheet loads. It exists so a page does not render on white and then
| repaint onto paper. It MUST match --surface-canvas for the same skin in
| app.css; nothing enforces that, so change them together.
|
*/

return [

    /*
    | The skin every page renders in. Applied as a static attribute on <html>
    | in app.blade.php — not read from localStorage, because there is nothing
    | to choose between.
    */
    'default' => 'ledger',

    'available' => [

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
