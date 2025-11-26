<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'

interface Currency {
  code: string
  name: string
  symbol: string
}

interface Totals {
  subtotal: number
  discount_amount: number
  tax_amount: number
  shipping_amount: number
  total_amount: number
}

interface InvoiceTotalsProps {
  totals: Totals
  currency: Currency
  baseCurrency: Currency
  exchangeRate: number
  showBaseCurrency?: boolean
}

const props = withDefaults(defineProps<InvoiceTotalsProps>(), {
  showBaseCurrency: false
})

const formatAmount = (amount: number, currency: Currency) => {
  const decimals = currency.code === 'JPY' ? 0 : 2
  return currency.symbol + amount.toFixed(decimals)
}

const baseCurrencyAmounts = computed(() => {
  return {
    subtotal: props.totals.subtotal * props.exchangeRate,
    discount_amount: props.totals.discount_amount * props.exchangeRate,
    tax_amount: props.totals.tax_amount * props.exchangeRate,
    shipping_amount: props.totals.shipping_amount * props.exchangeRate,
    total_amount: props.totals.total_amount * props.exchangeRate,
  }
})
</script>

<template>
  <Card>
    <CardHeader>
      <CardTitle class="flex items-center justify-between">
        <span>Invoice Totals</span>
        <div v-if="showBaseCurrency" class="flex items-center gap-2 text-sm font-normal">
          <span class="text-muted-foreground">Exchange Rate:</span>
          <span>1 {{ currency.code }} = {{ exchangeRate }} {{ baseCurrency.code }}</span>
        </div>
      </CardTitle>
    </CardHeader>
    <CardContent>
      <div class="space-y-3">
        <!-- Invoice Currency Totals -->
        <div class="grid grid-cols-2 gap-2">
          <div class="text-sm font-medium text-muted-foreground">Subtotal:</div>
          <div class="text-sm text-right">{{ formatAmount(totals.subtotal, currency) }}</div>
          
          <div v-if="totals.discount_amount > 0" class="text-sm font-medium text-muted-foreground">Discount:</div>
          <div v-if="totals.discount_amount > 0" class="text-sm text-right text-red-600">
            -{{ formatAmount(totals.discount_amount, currency) }}
          </div>
          
          <div v-if="totals.tax_amount > 0" class="text-sm font-medium text-muted-foreground">Tax:</div>
          <div v-if="totals.tax_amount > 0" class="text-sm text-right">{{ formatAmount(totals.tax_amount, currency) }}</div>
          
          <div v-if="totals.shipping_amount > 0" class="text-sm font-medium text-muted-foreground">Shipping:</div>
          <div v-if="totals.shipping_amount > 0" class="text-sm text-right">{{ formatAmount(totals.shipping_amount, currency) }}</div>
        </div>
        
        <Separator />
        
        <div class="grid grid-cols-2 gap-2">
          <div class="text-base font-semibold">Total ({{ currency.code }}):</div>
          <div class="text-base font-semibold text-right">{{ formatAmount(totals.total_amount, currency) }}</div>
        </div>

        <!-- Base Currency Totals (if different currency) -->
        <div v-if="showBaseCurrency" class="space-y-3">
          <Separator />
          
          <div class="text-sm font-medium text-muted-foreground mb-2">
            Converted to {{ baseCurrency.code }} (Base Currency)
          </div>
          
          <div class="grid grid-cols-2 gap-2 text-sm">
            <div class="text-muted-foreground">Subtotal:</div>
            <div class="text-right">{{ formatAmount(baseCurrencyAmounts.subtotal, baseCurrency) }}</div>
            
            <div v-if="totals.discount_amount > 0" class="text-muted-foreground">Discount:</div>
            <div v-if="totals.discount_amount > 0" class="text-right text-red-600">
              -{{ formatAmount(baseCurrencyAmounts.discount_amount, baseCurrency) }}
            </div>
            
            <div v-if="totals.tax_amount > 0" class="text-muted-foreground">Tax:</div>
            <div v-if="totals.tax_amount > 0" class="text-right">{{ formatAmount(baseCurrencyAmounts.tax_amount, baseCurrency) }}</div>
            
            <div v-if="totals.shipping_amount > 0" class="text-muted-foreground">Shipping:</div>
            <div v-if="totals.shipping_amount > 0" class="text-right">{{ formatAmount(baseCurrencyAmounts.shipping_amount, baseCurrency) }}</div>
          </div>
          
          <Separator />
          
          <div class="grid grid-cols-2 gap-2">
            <div class="text-base font-semibold">Total ({{ baseCurrency.code }}):</div>
            <div class="text-base font-semibold text-right">{{ formatAmount(baseCurrencyAmounts.total_amount, baseCurrency) }}</div>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>