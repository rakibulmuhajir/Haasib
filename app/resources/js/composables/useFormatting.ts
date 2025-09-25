import { formatDate, formatMoney } from '@/Utils/formatting'

export function useFormatting() {
  const formatCustomerSince = (dateString: string): string => {
    const date = new Date(dateString)
    const month = date.toLocaleDateString('en-US', { month: 'short' }).toUpperCase()
    const year = date.getFullYear().toString().slice(-2)
    return `${month.slice(0, 3)}'${year}`
  }

  return {
    formatDate,
    formatMoney,
    formatCustomerSince
  }
}