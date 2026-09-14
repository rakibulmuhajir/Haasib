<script setup lang="ts">
defineProps<{
    notices: Array<{
        name: string;
        direction: string;
        group_number: string | null;
    }>;
}>();
</script>

<template>
    <section
        v-if="notices.length"
        class="mb-4 space-y-2 border border-border bg-muted/30 p-4"
        aria-label="Travelling party changes"
    >
        <h2 class="text-sm font-semibold">
            Travelling party for next services
        </h2>
        <ul class="space-y-1 text-sm">
            <li v-for="(notice, index) in notices" :key="index">
                <span class="font-medium">{{ notice.name }}</span>
                {{
                    notice.direction === 'out'
                        ? 'is travelling with'
                        : 'joined from'
                }}
                {{
                    notice.group_number
                        ? `group ${notice.group_number}`
                        : 'another group'
                }}.
            </li>
        </ul>
        <p class="text-xs text-muted-foreground">
            Earlier visa and transport purchases remain unchanged. For the next
            hotel service, use the selected travelling party and book the
            required rooms/beds. No previous charges are moved or deducted.
        </p>
    </section>
</template>
