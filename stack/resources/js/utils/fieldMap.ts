export function fieldToAddressPath(field: string): string {
    return field.replace(/_/g, '.')
}

export function mapFieldName(field: string): string {
    return field
}

export function isAddressField(field: string): boolean {
    return field.includes('address') || field.includes('city') || field.includes('state') || field.includes('zip')
}
