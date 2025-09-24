// src/utils/fieldMap.ts
export const fieldMap: Record<string, string> = {
  // Customer field mappings
  taxId: 'tax_number',
  customerNumber: 'customer_number',
  postalCode: 'postal_code',
  stateProvince: 'state_province',
  addressLine1: 'address_line_1',
  addressLine2: 'address_line_2',
  
  // Add other model mappings as needed
}

// Reverse mapping for backend to frontend
export const reverseFieldMap: Record<string, string> = Object.entries(fieldMap).reduce(
  (acc, [front, back]) => {
    acc[back] = front
    return acc
  },
  {} as Record<string, string>
)

// Address field mappings
export const addressFieldMap: Record<string, string> = {
  addressLine1: 'address_line_1',
  addressLine2: 'address_line_2',
  postalCode: 'postal_code',
  stateProvince: 'state_province',
}

// Map frontend field name to backend field name
export function mapFieldName(field: string): string {
  return fieldMap[field] || field
}

// Map backend field name to frontend field name
export function reverseMapFieldName(field: string): string {
  return reverseFieldMap[field] || field
}

// Check if field is an address field
export function isAddressField(field: string): boolean {
  return ['address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country_id']
    .includes(field.replace('address.', ''))
}

// Convert nested address path to flat field
export function addressPathToField(path: string): string {
  if (path.startsWith('address.')) {
    return path.substring(8) // Remove 'address.' prefix
  }
  return path
}

// Convert flat field to nested address path
export function fieldToAddressPath(field: string): string {
  if (['address_line_1', 'address_line_2', 'city', 'state_province', 'postal_code', 'country_id'].includes(field)) {
    return `address.${field}`
  }
  return field
}