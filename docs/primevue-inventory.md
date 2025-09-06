PrimeVue v4.3.9 — Local Inventory (source of truth)

This file is generated from the installed package at `app/node_modules/primevue` to serve as an offline reference. Use these names exactly when importing.

Core config and services
- Config: `primevue/config` (default export: plugin)
- Theme presets: `@primeuix/themes` (e.g., `@primeuix/themes/aura`)
- Toast: `primevue/toastservice`, component `primevue/toast`, composable `primevue/usetoast`
- Confirm: `primevue/confirmationservice`, component `primevue/confirmdialog`, composable `primevue/useconfirm`
- Tooltip: directive `primevue/tooltip`
- Dynamic dialog: `primevue/dialogservice`, component `primevue/dynamicdialog`, composable `primevue/usedialog`

Dialogs & overlays
- `dialog`, `confirmdialog`, `confirmpopup`, `overlaypanel`, `sidebar`, `drawer`, `popover`, `tooltip`

Navigation & menus
- `menu`, `menubar`, `tieredmenu`, `tabview` + `tabpanel` (and lower-level `tab`, `tabs`, `tablist`), `tabmenu`, `breadcrumb`, `steps`/`stepper`

Form inputs (common)
- `inputtext`, `textarea`, `password`, `checkbox`, `radiobutton`, `dropdown`, `multiselect`, `autocomplete`, `calendar` (and `datepicker`), `inputnumber`, `inputmask`, `chips`, `togglebutton`, `toggleswitch`, `selectbutton`

Data & feedback
- `datatable` + `column` + `paginator`, `treetable`, `tree`, `dataview`, `skeleton`, `inlinemessage`, `message`, `progressbar`, `progressspinner`, `badge`, `tag`

Layout & surfaces
- `panel`, `accordion` (and `accordiontab`), `card`, `fieldset`, `divider`, `toolbar`, `scrollpanel`, `drawer`, `dock`

Other useful
- `galleria`, `fileupload`, `knob`, `slider`, `rating`, `speeddial`, `chip`, `avatar`, `avatargroup`

Notes
- Import paths are kebab-cased folder names, e.g., `import TabView from 'primevue/tabview'`.
- Services are registered with `app.use(...)`; composables are imported and called inside setup.
- See also local README: `app/node_modules/primevue/README.md`.

