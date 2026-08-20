/**
 * A payment method is stored as a key (e.g. `bank_transfer`) and displayed as
 * a name (e.g. "Bank Transfer"). Both `bill-payments/Show.vue` and
 * `payments/Index.vue` read the display name from here so the two pages
 * cannot drift apart.
 */
export function paymentMethodLabel(method: string): string {
  switch (method) {
    case 'cash':
      return 'Cash'
    case 'bank_transfer':
      return 'Bank Transfer'
    case 'card':
      return 'Card'
    case 'cheque':
    case 'check':
      return 'Cheque'
    default: {
      const text = (method || '').replace(/_/g, ' ')
      return text.charAt(0).toUpperCase() + text.slice(1)
    }
  }
}
