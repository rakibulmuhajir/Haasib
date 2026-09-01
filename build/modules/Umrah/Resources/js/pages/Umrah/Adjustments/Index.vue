<script setup lang="ts">
/**
 * Every change made to a group's figures after it was created.
 *
 * Making one starts from the trip, because an adjustment is always about
 * one trip. This is the other direction: at month end somebody wants all
 * of them at once, and until now that meant opening groups one at a time.
 */
import MoneyText from '@/components/MoneyText.vue'
import PageShell from '@/components/PageShell.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { formatDateTime } from '@/lib/datetime'
import type { BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { Calculator, Scale } from 'lucide-vue-next'
import { ref } from 'vue'

const props = defineProps<{
    company: { name: string; slug: string; base_currency: string }
    adjustments: Array<{
        id: string
        date: string
        group_id: string
        group: string
        group_name: string
        side: 'charge' | 'cost'
        amount: number
        reason: string | null
        reason_category: string | null
    }>
    reasonLabels: Record<string, string>
    groups: Array<{ id: string; label: string }>
    canAdjust: boolean
}>()

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Umrah', href: `/${props.company.slug}/umrah` },
    { title: 'Adjustments', href: `/${props.company.slug}/umrah/adjustments` },
]

const chosenGroup = ref('')

const openGroup = (groupId: string) =>
    router.get(`/${props.company.slug}/umrah/groups/${groupId}/accounting`)
</script>

<template>
    <Head title="Adjustments" />
    <PageShell
        title="Adjustments"
        description="Every change made to a group's charges or supplier costs since it was created."
        :breadcrumbs="breadcrumbs"
        :icon="Scale"
    >
        <Card v-if="canAdjust">
            <CardHeader><CardTitle>Adjust a group</CardTitle></CardHeader>
            <CardContent class="flex flex-wrap items-end gap-3">
                <div class="min-w-0 space-y-1.5">
                    <Label>Which trip?</Label>
                    <Select v-model="chosenGroup">
                        <SelectTrigger class="w-80"
                            ><SelectValue placeholder="Choose a group"
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="group in groups"
                                :key="group.id"
                                :value="group.id"
                            >
                                {{ group.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <Button :disabled="!chosenGroup" @click="openGroup(chosenGroup)">
                    <Calculator class="mr-2 h-4 w-4" />
                    Adjust charges
                </Button>
            </CardContent>
        </Card>

        <Card variant="register">
            <CardHeader><CardTitle>What has been changed</CardTitle></CardHeader>
            <CardContent>
                <p
                    v-if="!adjustments.length"
                    class="py-6 text-center text-sm text-muted-foreground"
                >
                    Nothing has been adjusted yet.
                </p>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs text-muted-foreground">
                                <th class="py-2 pr-3 font-medium">Date</th>
                                <th class="py-2 pr-3 font-medium">Group</th>
                                <th class="py-2 pr-3 font-medium">What changed</th>
                                <th class="py-2 pr-3 text-right font-medium">By</th>
                                <th class="py-2 pr-3 font-medium">Why</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in adjustments"
                                :key="row.id"
                                class="cursor-pointer border-b last:border-0 hover:bg-muted/40"
                                @click="openGroup(row.group_id)"
                            >
                                <td class="py-2 pr-3 whitespace-nowrap">
                                    {{ formatDateTime(row.date, { mode: 'date' }) }}
                                </td>
                                <td class="py-2 pr-3">
                                    <div class="font-medium">{{ row.group }}</div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.group_name }}
                                    </div>
                                </td>
                                <td class="py-2 pr-3">
                                    {{
                                        row.side === 'charge'
                                            ? "What the agent is charged"
                                            : 'What the suppliers charge us'
                                    }}
                                </td>
                                <td class="py-2 pr-3 text-right">
                                    <MoneyText
                                        :amount="row.amount"
                                        :currency="company.base_currency"
                                        class="font-medium"
                                    />
                                </td>
                                <td class="py-2 pr-3">
                                    <StatusBadge
                                        v-if="row.reason_category"
                                        :status="row.reason_category"
                                        :fallback="row.reason_category"
                                    />
                                    <div class="text-xs text-muted-foreground">
                                        {{ row.reason }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </PageShell>
</template>
