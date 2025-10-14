// Utility functions for field mapping
export function fieldToAddressPath(field: string): string {
  // Convert field names to address paths
  // This is a simple implementation - adjust according to your needs
  return field.replace(/_/g, '.')
}

export function mapFieldName(field: string): string {
  // Map field names - simple implementation
  return field
}

export function isAddressField(field: string): boolean {
  // Check if field is an address field
  return field.includes('address') || field.includes('city') || field.includes('state') || field.includes('zip')
}