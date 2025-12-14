// directives/can.js
// Custom directive for permission checks in templates

import { usePage } from '@inertiajs/vue3'

export const vCan = {
    mounted(el, binding) {
        const page = usePage()
        const permissions = page.props.permissions || []

        const permission = binding.value

        if (!permissions.includes(permission)) {
            // Remove element from DOM if no permission
            el.parentNode?.removeChild(el)
        }
    }
}

// Register globally in app.js:
//
// import { vCan } from './directives/can'
// app.directive('can', vCan)
//
// Usage in templates:
//
// <button v-can="'accounts_invoice_create'">Create Invoice</button>
// <div v-can="'company_settings_update'">Settings Form</div>
