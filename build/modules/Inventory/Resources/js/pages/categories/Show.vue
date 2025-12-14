<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import PageShell from '@/components/PageShell.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { BreadcrumbItem } from '@/types'
import { Pencil, ArrowLeft, Package, FolderTree } from 'lucide-vue-next'

interface CompanyRef {
  id: string
  name: string
  slug: string
}

interface Parent {
  id: string
  name: string
  code: string
}

interface Item {
  id: string
  sku: string
  name: string
  item_type: string
  selling_price: number
  currency: string
}

interface ChildCategory {
  id: string
  code: string
  name: string
  items_count: number
}

interface Category {
  id: string
  code: string
  name: string
  description: string | null
  parent: Parent | null
  is_active: boolean
  sort_order: number
  items_count: number
}

const props = defineProps<{
  company: CompanyRef
  category: Category
  items: Item[]
  children: ChildCategory[]
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: `/${props.company.slug}` },
  { title: 'Item Categories', href: `/${props.company.slug}/item-categories` },
  { title: props.category.name, href: `/${props.company.slug}/item-categories/${props.category.id}` },
]

const formatCurrency = (amount: number, currency: string) => {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency || 'USD',
  }).format(amount)
}
</script>

<template>
  <Head :title="category.name" />

  <PageShell
    :title="category.name"
    :breadcrumbs="breadcrumbs"
  >
    <template #actions>
      <Button variant="outline" @click="router.get(`/${company.slug}/item-categories`)">
        <ArrowLeft class="mr-2 h-4 w-4" />
        Back
      </Button>
      <Button @click="router.get(`/${company.slug}/item-categories/${category.id}/edit`)">
        <Pencil class="mr-2 h-4 w-4" />
        Edit
      </Button>
    </template>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- Main Content -->
      <div class="lg:col-span-2 space-y-6">
        <!-- Category Info -->
        <Card>
          <CardHeader>
            <div class="flex items-center justify-between">
              <CardTitle class="flex items-center gap-2">
                <FolderTree class="h-5 w-5" />
                {{ category.name }}
              </CardTitle>
              <Badge :variant="category.is_active ? 'success' : 'secondary'">
                {{ category.is_active ? 'Active' : 'Inactive' }}
              </Badge>
            </div>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <p class="text-muted-foreground">Code</p>
                <p class="font-medium">{{ category.code }}</p>
              </div>
              <div>
                <p class="text-muted-foreground">Parent</p>
                <p class="font-medium">{{ category.parent?.name ?? 'None (top level)' }}</p>
              </div>
            </div>
            <div v-if="category.description">
              <p class="text-muted-foreground text-sm">Description</p>
              <p>{{ category.description }}</p>
            </div>
          </CardContent>
        </Card>

        <!-- Subcategories -->
        <Card v-if="children.length > 0">
          <CardHeader>
            <CardTitle>Subcategories</CardTitle>
          </CardHeader>
          <CardContent>
            <div class="space-y-2">
              <div
                v-for="child in children"
                :key="child.id"
                class="flex items-center justify-between py-2 px-3 border rounded-lg cursor-pointer hover:bg-muted/50"
                @click="router.get(`/${company.slug}/item-categories/${child.id}`)"
              >
                <div>
                  <p class="font-medium">{{ child.name }}</p>
                  <p class="text-sm text-muted-foreground">{{ child.code }}</p>
                </div>
                <Badge variant="secondary">{{ child.items_count }} items</Badge>
              </div>
            </div>
          </CardContent>
        </Card>

        <!-- Items in Category -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Package class="h-5 w-5" />
              Items in Category
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div v-if="items.length === 0" class="text-center py-8 text-muted-foreground">
              No items in this category
            </div>
            <div v-else class="space-y-2">
              <div
                v-for="item in items"
                :key="item.id"
                class="flex items-center justify-between py-2 px-3 border rounded-lg cursor-pointer hover:bg-muted/50"
                @click="router.get(`/${company.slug}/items/${item.id}`)"
              >
                <div>
                  <p class="font-medium">{{ item.name }}</p>
                  <p class="text-sm text-muted-foreground">{{ item.sku }}</p>
                </div>
                <div class="text-right">
                  <p class="font-medium">{{ formatCurrency(item.selling_price, item.currency) }}</p>
                  <Badge variant="secondary" class="text-xs">{{ item.item_type }}</Badge>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>

      <!-- Sidebar -->
      <div class="space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Summary</CardTitle>
          </CardHeader>
          <CardContent class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Total Items</span>
              <span class="font-medium text-lg">{{ category.items_count }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Subcategories</span>
              <span class="font-medium text-lg">{{ children.length }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-muted-foreground">Sort Order</span>
              <span class="font-medium">{{ category.sort_order }}</span>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </PageShell>
</template>
