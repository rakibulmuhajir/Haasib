<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import TreeSelect from 'primevue/treeselect'
import Divider from 'primevue/divider'
import Message from 'primevue/message'

const { t } = useI18n()

const emit = defineEmits(['close'])

const visible = ref(false)
const searchQuery = ref('')
const selectedIndex = ref(0)
const loading = ref(false)
const error = ref('')

// Command categories and items
const commands = ref([
    {
        label: 'Navigation',
        icon: 'fas fa-compass',
        items: [
            {
                id: 'dashboard',
                label: 'Dashboard',
                icon: 'fas fa-chart-line',
                shortcut: 'Ctrl+D',
                keywords: ['home', 'overview', 'main'],
                action: () => navigateTo('/dashboard')
            },
            {
                id: 'invoices',
                label: 'Invoices',
                icon: 'fas fa-file-invoice',
                shortcut: 'Ctrl+I',
                keywords: ['billing', 'sales', 'revenue'],
                action: () => navigateTo('/invoicing')
            },
            {
                id: 'customers',
                label: 'Customers',
                icon: 'fas fa-users',
                shortcut: 'Ctrl+C',
                keywords: ['clients', 'contacts'],
                action: () => navigateTo('/customers')
            },
            {
                id: 'reports',
                label: 'Reports',
                icon: 'fas fa-chart-bar',
                shortcut: 'Ctrl+R',
                keywords: ['analytics', 'insights'],
                action: () => navigateTo('/reports')
            }
        ]
    },
    {
        label: 'Actions',
        icon: 'fas fa-bolt',
        items: [
            {
                id: 'create-invoice',
                label: 'Create Invoice',
                icon: 'fas fa-plus',
                shortcut: 'Ctrl+N',
                keywords: ['new', 'add', 'invoice'],
                action: () => navigateTo('/invoicing/create')
            },
            {
                id: 'create-customer',
                label: 'Create Customer',
                icon: 'fas fa-user-plus',
                shortcut: 'Ctrl+Shift+C',
                keywords: ['new', 'add', 'customer', 'client'],
                action: () => navigateTo('/customers/create')
            },
            {
                id: 'record-payment',
                label: 'Record Payment',
                icon: 'fas fa-credit-card',
                shortcut: 'Ctrl+P',
                keywords: ['payment', 'receive', 'collect'],
                action: () => navigateTo('/payments/record')
            },
            {
                id: 'generate-report',
                label: 'Generate Report',
                icon: 'fas fa-file-export',
                shortcut: 'Ctrl+G',
                keywords: ['export', 'download', 'report'],
                action: () => navigateTo('/reports/generate')
            }
        ]
    },
    {
        label: 'System',
        icon: 'fas fa-cog',
        items: [
            {
                id: 'settings',
                label: 'Settings',
                icon: 'fas fa-cog',
                shortcut: 'Ctrl+,',
                keywords: ['preferences', 'config'],
                action: () => navigateTo('/settings')
            },
            {
                id: 'help',
                label: 'Help & Documentation',
                icon: 'fas fa-question-circle',
                shortcut: 'F1',
                keywords: ['docs', 'support', 'guide'],
                action: () => openHelp()
            },
            {
                id: 'logout',
                label: 'Logout',
                icon: 'fas fa-sign-out-alt',
                shortcut: 'Ctrl+L',
                keywords: ['signout', 'exit'],
                action: () => logout()
            }
        ]
    }
])

// Keyboard shortcuts
const shortcuts = computed(() => {
    const shortcutMap = {}
    commands.value.forEach(category => {
        category.items.forEach(command => {
            if (command.shortcut) {
                shortcutMap[command.shortcut.toLowerCase()] = command
            }
        })
    })
    return shortcutMap
})

// Filtered commands based on search query
const filteredCommands = computed(() => {
    if (!searchQuery.value.trim()) {
        return commands.value
    }

    const query = searchQuery.value.toLowerCase()
    const filtered = []

    commands.value.forEach(category => {
        const matchingItems = category.items.filter(command => {
            const labelMatch = command.label.toLowerCase().includes(query)
            const keywordMatch = command.keywords?.some(keyword => keyword.includes(query))
            return labelMatch || keywordMatch
        })

        if (matchingItems.length > 0) {
            filtered.push({
                ...category,
                items: matchingItems
            })
        }
    })

    return filtered
})

// Flatten commands for keyboard navigation
const allCommands = computed(() => {
    const flat = []
    filteredCommands.value.forEach(category => {
        category.items.forEach(command => {
            flat.push({
                ...command,
                category: category.label
            })
        })
    })
    return flat
})

// Methods
const open = () => {
    visible.value = true
    searchQuery.value = ''
    selectedIndex.value = 0
    nextTick(() => {
        document.getElementById('command-palette-input')?.focus()
    })
}

const close = () => {
    visible.value = false
    searchQuery.value = ''
    selectedIndex.value = 0
    emit('close')
}

const navigateTo = (url) => {
    router.visit(url)
    close()
}

const openHelp = () => {
    window.open('/help', '_blank')
    close()
}

const logout = async () => {
    try {
        await fetch('/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            }
        })
        window.location.href = '/'
    } catch (error) {
        error.value = 'Failed to logout'
    }
    close()
}

const selectCommand = (command) => {
    if (command.action) {
        command.action()
    }
}

const selectHighlightedCommand = () => {
    const commands = allCommands.value
    if (commands[selectedIndex.value]) {
        selectCommand(commands[selectedIndex.value])
    }
}

const moveSelection = (direction) => {
    const commands = allCommands.value
    if (direction === 'down') {
        selectedIndex.value = Math.min(selectedIndex.value + 1, commands.length - 1)
    } else {
        selectedIndex.value = Math.max(selectedIndex.value - 1, 0)
    }
}

// Keyboard event handlers
const handleKeydown = (event) => {
    if (!visible.value) return

    switch (event.key) {
        case 'Escape':
            event.preventDefault()
            close()
            break
        case 'ArrowDown':
            event.preventDefault()
            moveSelection('down')
            break
        case 'ArrowUp':
            event.preventDefault()
            moveSelection('up')
            break
        case 'Enter':
            event.preventDefault()
            selectHighlightedCommand()
            break
    }
}

const handleGlobalKeydown = (event) => {
    // Check for Ctrl+K to open command palette
    if (event.ctrlKey && event.key === 'k') {
        event.preventDefault()
        open()
    }

    // Check for other shortcuts when palette is closed
    if (!visible.value) {
        const shortcut = getShortcutString(event)
        const command = shortcuts.value[shortcut]
        if (command) {
            event.preventDefault()
            selectCommand(command)
        }
    }
}

const getShortcutString = (event) => {
    const parts = []
    if (event.ctrlKey) parts.push('ctrl')
    if (event.shiftKey) parts.push('shift')
    if (event.altKey) parts.push('alt')
    parts.push(event.key.toLowerCase())
    return parts.join('+')
}

// Lifecycle
onMounted(() => {
    document.addEventListener('keydown', handleGlobalKeydown)
    document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
    document.removeEventListener('keydown', handleGlobalKeydown)
    document.removeEventListener('keydown', handleKeydown)
})

// Expose open method to parent
defineExpose({
    open
})
</script>

<template>
    <Dialog 
        v-model:visible="visible" 
        modal 
        :header="null"
        :style="{ width: '640px' }"
        :closable="false"
        contentStyle="padding: 0"
        @hide="close"
    >
        <div class="bg-white dark:bg-gray-800 rounded-lg">
            <!-- Search Input -->
            <div class="p-4 border-b border-gray-200 dark:border-gray-700">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <InputText 
                        id="command-palette-input"
                        v-model="searchQuery"
                        placeholder="Type a command or search..."
                        class="w-full pl-10"
                        size="large"
                        @keydown.enter="selectHighlightedCommand"
                        @keydown.arrow-down.prevent="moveSelection('down')"
                        @keydown.arrow-up.prevent="moveSelection('up')"
                        @keydown.escape="close"
                    />
                </div>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Type to search, use ↑↓ to navigate, Enter to select
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        Press <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs">Esc</kbd> to close
                    </span>
                </div>
            </div>

            <!-- Error Message -->
            <Message v-if="error" severity="error" :closable="false" class="m-4">
                {{ error }}
            </Message>

            <!-- Commands List -->
            <div class="max-h-96 overflow-y-auto">
                <div v-if="allCommands.length === 0" class="p-8 text-center">
                    <i class="fas fa-search text-3xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400">
                        No commands found for "{{ searchQuery }}"
                    </p>
                </div>

                <div v-else>
                    <div v-for="(category, categoryIndex) in filteredCommands" :key="category.label">
                        <!-- Category Header -->
                        <div class="px-4 py-2 bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">
                            {{ category.label }}
                        </div>

                        <!-- Command Items -->
                        <div 
                            v-for="(command, commandIndex) in category.items" 
                            :key="command.id"
                            class="group"
                        >
                            <div 
                                class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors"
                                :class="{
                                    'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500': 
                                        allCommands.findIndex(c => c.id === command.id) === selectedIndex
                                }"
                                @click="selectCommand(command)"
                                @mouseenter="selectedIndex = allCommands.findIndex(c => c.id === command.id)"
                            >
                                <div class="flex items-center flex-1">
                                    <i :class="command.icon" class="text-gray-600 dark:text-gray-400 w-5"></i>
                                    <span class="ml-3 text-gray-900 dark:text-white font-medium">
                                        {{ command.label }}
                                    </span>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <span v-if="command.shortcut" class="text-xs text-gray-500 dark:text-gray-400">
                                        <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">
                                            {{ command.shortcut }}
                                        </kbd>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            Press <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs">Ctrl+K</kbd> to open
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ allCommands.length }} commands available
                    </div>
                </div>
            </div>
        </div>
    </Dialog>
</template>

<style scoped>
kbd {
    font-family: ui-monospace, SFMono-Regular, "SF Mono", Consolas, "Liberation Mono", Menlo, monospace;
    font-size: 0.75rem;
    line-height: 1;
}

:deep(.p-dialog) {
    border-radius: 0.75rem;
}

:deep(.p-dialog-content) {
    border-radius: 0 0 0.75rem 0.75rem;
}

/* Custom scrollbar for command list */
:deep(.p-dialog-content)::-webkit-scrollbar {
    width: 6px;
}

:deep(.p-dialog-content)::-webkit-scrollbar-track {
    background: transparent;
}

:deep(.p-dialog-content)::-webkit-scrollbar-thumb {
    background-color: rgb(156 163 175);
    border-radius: 3px;
}

:deep(.p-dialog-content)::-webkit-scrollbar-thumb:hover {
    background-color: rgb(107 114 128);
}
</style>