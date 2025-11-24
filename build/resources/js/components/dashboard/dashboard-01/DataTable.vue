<script lang="ts">
import { z } from "zod"
import DraggableRow from "./DraggableRow.vue"
import DragHandle from "./DragHandle.vue"

export const schema = z.object({
  id: z.string(),
  customer: z.string(),
  invoice: z.string(),
  amount: z.string(),
  status: z.string(),
  date: z.string(),
  description: z.string(),
})
</script>

<script setup lang="ts">
import { ref, h, computed, watch } from 'vue'
import type {
  ColumnDef,
  ColumnFiltersState,
  SortingState,
  VisibilityState,
} from "@tanstack/vue-table"
import { RestrictToVerticalAxis } from "@dnd-kit/abstract/modifiers"
import {
  IconChevronDown,
  IconChevronLeft,
  IconChevronRight,
  IconChevronsLeft,
  IconChevronsRight,
  IconCircleCheckFilled,
  IconDotsVertical,
  IconLayoutColumns,
  IconLoader,
  IconPlus,
  IconClock,
  IconAlertTriangle,
} from "@tabler/icons-vue"
import {
  FlexRender,
  getCoreRowModel,
  getFilteredRowModel,
  getPaginationRowModel,
  getSortedRowModel,
  useVueTable,
} from "@tanstack/vue-table"
import { DragDropProvider } from "dnd-kit-vue"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Label } from "@/components/ui/label"
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select"
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table"
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs"

const props = defineProps<{
  data: TableData[]
}>()

interface TableData {
  id: string
  customer: string
  invoice: string
  amount: string
  status: string
  date: string
  description: string
}

const tabViews = [
  { value: "recent", label: "Recent Invoices", filter: (row: TableData) => true, empty: "No invoices found." },
  { value: "pending", label: "Pending Payments", filter: (row: TableData) => {
    const status = (row.status || '').toLowerCase()
    return status.includes("pending") || status.includes("unpaid") || status.includes("draft") || status.includes("sent") || status.includes("partial")
  }, empty: "No pending invoices found." },
  { value: "overdue", label: "Overdue Items", filter: (row: TableData) => {
    const status = (row.status || '').toLowerCase()
    return status.includes("overdue")
  }, empty: "No overdue invoices found." },
]

const activeTab = ref(tabViews[0].value)

const filteredData = computed(() => {
  const tab = tabViews.find(({ value }) => value === activeTab.value) ?? tabViews[0]
  return (props.data || []).filter(tab.filter)
})

const statusCounts = computed(() => {
  const normalized = (props.data || []).map((row) => (row.status || '').toLowerCase())

  return {
    pending: normalized.filter((status) =>
      status.includes('pending') ||
      status.includes('unpaid') ||
      status.includes('draft') ||
      status.includes('sent') ||
      status.includes('partial')
    ).length,
    overdue: normalized.filter((status) => status.includes('overdue')).length,
  }
})

const sorting = ref<SortingState>([])
const columnFilters = ref<ColumnFiltersState>([])
const columnVisibility = ref<VisibilityState>({})
const rowSelection = ref({})

const columns: ColumnDef<TableData>[] = [
  {
    id: "drag",
    header: () => null,
    cell: ({ row }) => h(DragHandle),
  },
  {
    id: "select",
    header: ({ table }) => h(Checkbox, {
      "modelValue": table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate"),
      "onUpdate:modelValue": value => table.toggleAllPageRowsSelected(!!value),
      "aria-label": "Select all",
    }),
    cell: ({ row }) => h(Checkbox, {
      "modelValue": row.getIsSelected(),
      "onUpdate:modelValue": value => row.toggleSelected(!!value),
      "aria-label": "Select row",
    }),
    enableSorting: false,
    enableHiding: false,
  },
  {
    accessorKey: "customer",
    header: "Customer",
    cell: ({ row }) => h("div", { class: "font-medium" }, String(row.getValue("customer"))),
    enableHiding: false,
  },
  {
    accessorKey: "invoice",
    header: "Invoice #",
    cell: ({ row }) => h("div", { class: "font-mono text-sm" }, String(row.getValue("invoice"))),
  },
  {
    accessorKey: "amount",
    header: "Amount",
    cell: ({ row }) => h("div", { class: "font-semibold" }, String(row.getValue("amount"))),
  },
  {
    accessorKey: "status",
    header: "Status",
    cell: ({ row }) => {
      const status = (row.getValue("status") as string) || ''
      const normalized = status.toLowerCase().replace(/\s+/g, '_')
      const statusConfig = {
        paid: { icon: IconCircleCheckFilled, class: "text-emerald-500", variant: "default" as const },
        pending: { icon: IconClock, class: "text-yellow-500", variant: "secondary" as const },
        unpaid: { icon: IconClock, class: "text-yellow-500", variant: "secondary" as const },
        sent: { icon: IconClock, class: "text-yellow-500", variant: "secondary" as const },
        draft: { icon: IconClock, class: "text-yellow-500", variant: "secondary" as const },
        partially_paid: { icon: IconClock, class: "text-blue-500", variant: "secondary" as const },
        overdue: { icon: IconAlertTriangle, class: "text-red-500", variant: "destructive" as const },
      }
      const config = statusConfig[normalized as keyof typeof statusConfig] || statusConfig.pending
      
      return h("div", { class: "flex items-center gap-2" }, [
        h(config.icon, { class: `h-4 w-4 ${config.class}` }),
        h(Badge, { variant: config.variant }, () => status),
      ])
    },
  },
  {
    accessorKey: "date",
    header: "Date",
    cell: ({ row }) => h("div", { class: "text-sm" }, String(row.getValue("date"))),
  },
  {
    accessorKey: "description",
    header: "Description",
    cell: ({ row }) => h("div", { class: "text-sm text-muted-foreground" }, String(row.getValue("description"))),
  },
  {
    id: "actions",
    cell: () => h(DropdownMenu, {}, {
      default: () => [
        h(DropdownMenuTrigger, { asChild: true }, {
          default: () => h(Button, {
            variant: "ghost",
            class: "h-8 w-8 p-0",
          }, {
            default: () => [
              h("span", { class: "sr-only" }, "Open menu"),
              h(IconDotsVertical, { class: "h-4 w-4" }),
            ],
          }),
        }),
        h(DropdownMenuContent, { align: "end" }, {
          default: () => [
            h(DropdownMenuItem, {}, () => "View Details"),
            h(DropdownMenuItem, {}, () => "Send Reminder"),
            h(DropdownMenuItem, {}, () => "Mark as Paid"),
            h(DropdownMenuSeparator, {}),
            h(DropdownMenuItem, {}, () => "Delete"),
          ],
        }),
      ],
    }),
  },
]

const table = useVueTable({
  get data() {
    return filteredData.value
  },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: (updaterOrValue) => {
    sorting.value = typeof updaterOrValue === "function"
      ? updaterOrValue(sorting.value)
      : updaterOrValue
  },
  onColumnFiltersChange: (updaterOrValue) => {
    columnFilters.value = typeof updaterOrValue === "function"
      ? updaterOrValue(columnFilters.value)
      : updaterOrValue
  },
  onColumnVisibilityChange: (updaterOrValue) => {
    columnVisibility.value = typeof updaterOrValue === "function"
      ? updaterOrValue(columnVisibility.value)
      : updaterOrValue
  },
  onRowSelectionChange: (updaterOrValue) => {
    rowSelection.value = typeof updaterOrValue === "function"
      ? updaterOrValue(rowSelection.value)
      : updaterOrValue
  },
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
})

watch(activeTab, () => {
  rowSelection.value = {}
  table.setPageIndex(0)
})
  </script>

<template>
  <Tabs
    v-model:modelValue="activeTab"
    :default-value="tabViews[0].value"
    class="w-full flex-col justify-start gap-6"
  >
    <div class="flex items-center justify-between px-4 lg:px-6">
      <Label for="view-selector" class="sr-only">
        View
      </Label>
      <Select v-model:modelValue="activeTab">
        <SelectTrigger
          id="view-selector"
          class="flex w-fit @4xl/main:hidden"
          size="sm"
        >
          <SelectValue placeholder="Select a view" />
        </SelectTrigger>
        <SelectContent>
          <SelectItem
            v-for="tab in tabViews"
            :key="tab.value"
            :value="tab.value"
          >
            {{ tab.label }}
          </SelectItem>
        </SelectContent>
      </Select>
      <TabsList class="**:data-[slot=badge]:bg-muted-foreground/30 hidden **:data-[slot=badge]:size-5 **:data-[slot=badge]:rounded-full **:data-[slot=badge]:px-1 @4xl/main:flex">
        <TabsTrigger
          v-for="tab in tabViews"
          :key="tab.value"
          :value="tab.value"
        >
          {{ tab.label }}
          <Badge v-if="tab.value === 'pending'" variant="secondary">
            {{ statusCounts.pending }}
          </Badge>
          <Badge v-else-if="tab.value === 'overdue'" variant="secondary">
            {{ statusCounts.overdue }}
          </Badge>
        </TabsTrigger>
      </TabsList>
      <div class="flex items-center gap-2">
        <DropdownMenu>
          <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm">
              <IconLayoutColumns />
              <span class="hidden lg:inline">Columns</span>
              <IconChevronDown />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end" class="w-56">
            <template v-for="column in table.getAllColumns().filter((column) => typeof column.accessorFn !== 'undefined' && column.getCanHide())" :key="column.id">
              <DropdownMenuCheckboxItem
                class="capitalize"
                :model-value="column.getIsVisible()"
                @update:model-value="(value) => {
                  column.toggleVisibility(!!value)
                }"
              >
                {{ column.id }}
              </DropdownMenuCheckboxItem>
            </template>
          </DropdownMenuContent>
        </DropdownMenu>
        <Button variant="default" size="sm">
          <IconPlus />
          <span class="hidden lg:inline">New Invoice</span>
        </Button>
      </div>
    </div>
    <TabsContent
      v-for="tab in tabViews"
      :key="tab.value"
      :value="tab.value"
      class="relative flex flex-col gap-4 overflow-auto px-4 lg:px-6"
    >
      <div class="overflow-hidden rounded-lg border">
        <DragDropProvider :modifiers="[RestrictToVerticalAxis]">
          <Table>
            <TableHeader class="bg-muted sticky top-0 z-10">
              <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                <TableHead v-for="header in headerGroup.headers" :key="header.id" :col-span="header.colSpan">
                  <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                </TableHead>
              </TableRow>
            </TableHeader>
            <TableBody class="**:data-[slot=table-cell]:first:w-8">
              <template v-if="table.getRowModel().rows.length">
                <DraggableRow v-for="row in table.getRowModel().rows" :key="row.id" :row="row" :index="row.index" />
              </template>
              <TableRow v-else>
                <TableCell
                  :col-span="columns.length"
                  class="h-24 text-center"
                >
                  {{ tab.empty }}
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </DragDropProvider>
      </div>
      <div class="flex items-center justify-between px-4">
        <div class="text-muted-foreground hidden flex-1 text-sm lg:flex">
          {{ table.getFilteredSelectedRowModel().rows.length }} of
          {{ table.getFilteredRowModel().rows.length }} row(s) selected.
        </div>
        <div class="flex w-full items-center gap-8 lg:w-fit">
          <div class="hidden items-center gap-2 lg:flex">
            <Label for="rows-per-page" class="text-sm font-medium">
              Rows per page
            </Label>
            <Select
              :model-value="table.getState().pagination.pageSize"
              @update:model-value="(value) => {
                table.setPageSize(Number(value))
              }"
            >
              <SelectTrigger id="rows-per-page" size="sm" class="w-20">
                <SelectValue :placeholder="`${table.getState().pagination.pageSize}`" />
              </SelectTrigger>
              <SelectContent side="top">
                <SelectItem v-for="pageSize in [10, 20, 30, 40, 50]" :key="pageSize" :value="`${pageSize}`">
                  {{ pageSize }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
          <div class="flex w-fit items-center justify-center text-sm font-medium">
            Page {{ table.getState().pagination.pageIndex + 1 }} of
            {{ table.getPageCount() }}
          </div>
          <div class="ml-auto flex items-center gap-2 lg:ml-0">
            <Button
              variant="outline"
              class="hidden h-8 w-8 p-0 lg:flex"
              :disabled="!table.getCanPreviousPage()"
              @click="table.setPageIndex(0)"
            >
              <span class="sr-only">Go to first page</span>
              <IconChevronsLeft />
            </Button>
            <Button
              variant="outline"
              class="size-8"
              size="icon"
              :disabled="!table.getCanPreviousPage()"
              @click="table.previousPage()"
            >
              <span class="sr-only">Go to previous page</span>
              <IconChevronLeft />
            </Button>
            <Button
              variant="outline"
              class="size-8"
              size="icon"
              :disabled="!table.getCanNextPage()"
              @click="table.nextPage()"
            >
              <span class="sr-only">Go to next page</span>
              <IconChevronRight />
            </Button>
            <Button
              variant="outline"
              class="hidden size-8 lg:flex"
              size="icon"
              :disabled="!table.getCanNextPage()"
              @click="table.setPageIndex(table.getPageCount() - 1)"
            >
              <span class="sr-only">Go to last page</span>
              <IconChevronsRight />
            </Button>
          </div>
        </div>
      </div>
    </TabsContent>
  </Tabs>
</template>
