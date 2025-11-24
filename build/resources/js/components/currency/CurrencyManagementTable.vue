<script setup lang="ts">
import { ref, computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Plus, Edit, Trash2 } from 'lucide-vue-next'
import CurrencyAddForm from './CurrencyAddForm.vue'

interface Currency {
  id: string
  code: string
  name: string
  symbol: string
  is_base_currency: boolean
  default_exchange_rate: number
  is_active: boolean
  created_at: string
}

interface CurrencyManagementTableProps {
  currencies: Currency[]
  loading?: boolean
}

const props = withDefaults(defineProps<CurrencyManagementTableProps>(), {
  loading: false
})

const emit = defineEmits<{
  'add': [currency: any]
  'update': [id: string, data: any]
  'toggle': [id: string, active: boolean]
  'delete': [id: string]
  'setAsBase': [id: string]
}>()

const showAddDialog = ref(false)
const editingRate = ref<string | null>(null)
const newRates = ref<Record<string, number>>({})

const sortedCurrencies = computed(() => {
  return [...props.currencies].sort((a, b) => {
    // Base currency first
    if (a.is_base_currency && !b.is_base_currency) return -1
    if (!a.is_base_currency && b.is_base_currency) return 1
    
    // Then by active status
    if (a.is_active && !b.is_active) return -1
    if (!a.is_active && b.is_active) return 1
    
    // Finally by name
    return a.name.localeCompare(b.name)
  })
})

const handleAddCurrency = (currencyData: any) => {
  emit('add', currencyData)
  showAddDialog.value = false
}

const startEditRate = (currencyId: string, currentRate: number) => {
  editingRate.value = currencyId
  newRates.value[currencyId] = currentRate
}

const saveRate = (currencyId: string) => {
  const newRate = newRates.value[currencyId]
  if (newRate && newRate > 0) {
    emit('update', currencyId, { default_exchange_rate: newRate })
  }
  editingRate.value = null
  delete newRates.value[currencyId]
}

const cancelEditRate = () => {
  editingRate.value = null
  newRates.value = {}
}

const toggleActive = (currency: Currency) => {
  if (currency.is_base_currency) return // Cannot deactivate base currency
  emit('toggle', currency.id, !currency.is_active)
}

const deleteCurrency = (currency: Currency) => {
  if (currency.is_base_currency) return // Cannot delete base currency
  if (confirm(`Are you sure you want to delete ${currency.name}?`)) {
    emit('delete', currency.id)
  }
}

const setAsBase = (currency: Currency) => {
  if (currency.is_base_currency) return
  if (confirm(`Set ${currency.name} as the base currency? This cannot be undone.`)) {
    emit('setAsBase', currency.id)
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h3 class="text-lg font-semibold">Company Currencies</h3>
      
      <Dialog v-model:open="showAddDialog">
        <DialogTrigger asChild>
          <Button>
            <Plus class="h-4 w-4 mr-2" />
            Add Currency
          </Button>
        </DialogTrigger>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Currency</DialogTitle>
          </DialogHeader>
          <CurrencyAddForm 
            @save="handleAddCurrency"
            @cancel="showAddDialog = false"
          />
        </DialogContent>
      </Dialog>
    </div>

    <div class="border rounded-md">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Currency</TableHead>
            <TableHead>Symbol</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Default Rate</TableHead>
            <TableHead>Status</TableHead>
            <TableHead class="text-right">Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          <TableRow v-if="props.loading">
            <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
              Loading currencies...
            </TableCell>
          </TableRow>
          <TableRow v-else-if="sortedCurrencies.length === 0">
            <TableCell colspan="6" class="text-center py-8 text-muted-foreground">
              No currencies configured
            </TableCell>
          </TableRow>
          <TableRow v-else v-for="currency in sortedCurrencies" :key="currency.id">
            <TableCell>
              <div>
                <div class="font-medium">{{ currency.code }}</div>
                <div class="text-sm text-muted-foreground">{{ currency.name }}</div>
              </div>
            </TableCell>
            <TableCell>
              <span class="font-mono">{{ currency.symbol }}</span>
            </TableCell>
            <TableCell>
              <Badge v-if="currency.is_base_currency" variant="default">
                Base Currency
              </Badge>
              <Badge v-else variant="secondary">
                Foreign Currency
              </Badge>
            </TableCell>
            <TableCell>
              <div v-if="editingRate === currency.id" class="flex items-center gap-2">
                <Input
                  v-model.number="newRates[currency.id]"
                  type="number"
                  step="0.000001"
                  class="w-24"
                  @keyup.enter="saveRate(currency.id)"
                  @keyup.escape="cancelEditRate()"
                />
                <Button size="sm" @click="saveRate(currency.id)">Save</Button>
                <Button size="sm" variant="outline" @click="cancelEditRate()">Cancel</Button>
              </div>
              <div v-else class="flex items-center gap-2">
                <span>{{ currency.default_exchange_rate }}</span>
                <Button 
                  v-if="!currency.is_base_currency" 
                  size="sm" 
                  variant="ghost" 
                  @click="startEditRate(currency.id, currency.default_exchange_rate)"
                >
                  <Edit class="h-3 w-3" />
                </Button>
              </div>
            </TableCell>
            <TableCell>
              <div class="flex items-center gap-2">
                <Switch
                  :checked="currency.is_active"
                  @update:checked="toggleActive(currency)"
                  :disabled="currency.is_base_currency"
                />
                <span class="text-sm">{{ currency.is_active ? 'Active' : 'Inactive' }}</span>
              </div>
            </TableCell>
            <TableCell class="text-right">
              <div class="flex items-center justify-end gap-2">
                <Button
                  v-if="!currency.is_base_currency"
                  size="sm"
                  variant="outline"
                  @click="setAsBase(currency)"
                >
                  Set as Base
                </Button>
                <Button
                  v-if="!currency.is_base_currency"
                  size="sm"
                  variant="destructive"
                  @click="deleteCurrency(currency)"
                >
                  <Trash2 class="h-3 w-3" />
                </Button>
              </div>
            </TableCell>
          </TableRow>
        </TableBody>
      </Table>
    </div>
  </div>
</template>