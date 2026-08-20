<script setup lang="ts">
/**
 * The contents of the company dropdown — the part that is the same wherever the
 * dropdown is opened from. Callers supply their own DropdownMenuTrigger and
 * DropdownMenuContent, because a sidebar trigger and a header trigger want
 * different widths, sides and offsets.
 */
import {
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { useCompanySwitcher } from '@/composables/useCompanySwitcher';
import { Building2, Check, Plus } from 'lucide-vue-next';

const { companies, currentCompany, canCreateCompanies, switchCompany, createCompany } =
    useCompanySwitcher();
</script>

<template>
    <DropdownMenuLabel class="text-xs text-muted-foreground">
        Your Companies
    </DropdownMenuLabel>
    <DropdownMenuItem
        v-for="company in companies"
        :key="company.id"
        class="gap-2 p-2"
        @click="switchCompany(company.slug)"
    >
        <div class="flex size-6 items-center justify-center rounded-sm border">
            <Building2 class="size-4 shrink-0" />
        </div>
        <div class="flex-1">
            <div class="font-medium">{{ company.name }}</div>
            <div class="text-xs text-muted-foreground">{{ company.slug }}</div>
        </div>
        <!-- The tick is the non-colour indicator for "this is the one you are
             in"; the row is not otherwise distinguished. -->
        <Check v-if="currentCompany?.id === company.id" class="size-4" />
    </DropdownMenuItem>
    <DropdownMenuSeparator v-if="canCreateCompanies" />
    <DropdownMenuItem v-if="canCreateCompanies" class="gap-2 p-2" @click="createCompany">
        <div class="flex size-6 items-center justify-center rounded-md border border-dashed">
            <Plus class="size-4" />
        </div>
        <div class="font-medium text-muted-foreground">Add Company</div>
    </DropdownMenuItem>
</template>
