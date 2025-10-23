<template>
    <div class="simple-quick-links">
        <h3 class="quick-links-title">{{ title }}</h3>
        
        <div class="quick-links-list">
            <a
                v-for="link in quickLinks"
                :key="link.label"
                :href="link.url"
                class="quick-link-text"
                @click="handleLinkClick($event, link)"
            >
                {{ link.label }}
            </a>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

interface QuickLink {
    label: string
    icon: string
    url: string
    action?: () => void
}

interface Props {
    links?: QuickLink[]
    title?: string
}

const props = withDefaults(defineProps<Props>(), {
    title: 'Quick Actions'
})

const quickLinks = computed<QuickLink[]>(() => props.links || [])

const handleLinkClick = (event: MouseEvent, link: QuickLink) => {
    if (link.action) {
        event.preventDefault()
        link.action()
    } else {
        // Let Inertia handle the navigation
        router.visit(link.url)
    }
}
</script>

<style scoped>
.simple-quick-links {
    padding: 1rem 0;
}

.quick-links-title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #374151;
    margin: 0 0 0.75rem 0;
}

.quick-links-list {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.quick-link-text {
    display: block;
    padding: 0.25rem 0;
    color: #3b82f6;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: color 0.2s ease;
}

.quick-link-text:hover {
    color: #2563eb;
    text-decoration: underline;
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .quick-links-title {
        color: #f9fafb;
    }
    
    .quick-link-text {
        color: #60a5fa;
    }
    
    .quick-link-text:hover {
        color: #93c5fd;
    }
}
</style>