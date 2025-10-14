<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick, watch } from 'vue'
import { router, usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import axios from 'axios'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Divider from 'primevue/divider'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Badge from 'primevue/badge'

const { t } = useI18n()
const page = usePage()

const emit = defineEmits(['close', 'commandExecuted'])

const visible = ref(false)
const searchQuery = ref('')
const selectedIndex = ref(0)
const loading = ref(false)
const error = ref('')
const suggestions = ref([])
const availableCommands = ref([])
const commandHistory = ref([])
const commandTemplates = ref([])
const showHistory = ref(false)
const showTemplates = ref(false)
const executionResult = ref(null)

// Current context for suggestions
const currentContext = computed(() => {
    return {
        page: page.props.currentPage || 'dashboard',
        recent_actions: commandHistory.value.slice(0, 5).map(h => h.command_name)
    }
})

// Filtered commands based on search query and suggestions
const displayCommands = computed(() => {
    if (showHistory.value) {
        return commandHistory.value.slice(0, 10)
    }
    
    if (showTemplates.value) {
        return commandTemplates.value
    }
    
    if (suggestions.value.length > 0) {
        return suggestions.value
    }
    
    if (searchQuery.value.trim()) {
        return availableCommands.value.filter(command => 
            command.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
            command.description.toLowerCase().includes(searchQuery.value.toLowerCase())
        )
    }
    
    return availableCommands.value.slice(0, 10)
})

// Flatten commands for keyboard navigation
const allCommands = computed(() => {
    return displayCommands.value.map(command => ({
        ...command,
        category: command.category || 'Commands'
    }))
})

// API methods
const loadAvailableCommands = async () => {
    try {
        loading.value = true
        const response = await axios.get('/api/commands')
        availableCommands.value = response.data.data || []
    } catch (err) {
        console.error('Failed to load commands:', err)
        error.value = 'Failed to load commands'
    } finally {
        loading.value = false
    }
}

const loadSuggestions = async (query) => {
    if (query.length < 2) {
        suggestions.value = []
        return
    }

    try {
        loading.value = true
        const response = await axios.get('/api/commands/suggestions', {
            params: {
                input: query,
                context: currentContext.value
            }
        })
        suggestions.value = response.data.data || []
    } catch (err) {
        console.error('Failed to load suggestions:', err)
        // Don't show error for suggestions, just continue without them
    } finally {
        loading.value = false
    }
}

const loadCommandHistory = async () => {
    try {
        const response = await axios.get('/api/commands/history', {
            params: { per_page: 20 }
        })
        commandHistory.value = response.data.data || []
    } catch (err) {
        console.error('Failed to load command history:', err)
    }
}

const loadCommandTemplates = async () => {
    try {
        const response = await axios.get('/api/commands/templates')
        commandTemplates.value = response.data.data || []
    } catch (err) {
        console.error('Failed to load command templates:', err)
    }
}

const executeCommand = async (command) => {
    try {
        loading.value = true
        error.value = ''
        
        const commandData = command.name || command.command_name
        const parameters = command.parameter_values || {}
        
        const response = await axios.post('/api/commands/execute', {
            command_name: commandData,
            parameters: parameters
        })

        executionResult.value = response.data
        
        if (response.data.success) {
            emit('commandExecuted', {
                command: command,
                result: response.data
            })
            
            // Reload history to include the executed command
            await loadCommandHistory()
            
            // Show success briefly
            setTimeout(() => {
                close()
            }, 1000)
        } else {
            error.value = response.data.error || 'Command execution failed'
        }
    } catch (err) {
        console.error('Failed to execute command:', err)
        error.value = err.response?.data?.error || 'Failed to execute command'
    } finally {
        loading.value = false
    }
}

// UI methods
const open = async () => {
    visible.value = true
    searchQuery.value = ''
    selectedIndex.value = 0
    error.value = ''
    executionResult.value = null
    showHistory.value = false
    showTemplates.value = false
    suggestions.value = []
    
    await loadAvailableCommands()
    await loadCommandHistory()
    await loadCommandTemplates()
    
    nextTick(() => {
        document.getElementById('command-palette-input')?.focus()
    })
}

const close = () => {
    visible.value = false
    searchQuery.value = ''
    selectedIndex.value = 0
    error.value = ''
    executionResult.value = null
    showHistory.value = false
    showTemplates.value = false
    suggestions.value = []
    emit('close')
}

const selectCommand = (command) => {
    if (command.command_name) {
        executeCommand(command)
    } else if (command.name) {
        executeCommand(command)
    } else if (command.action) {
        command.action()
        close()
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

const toggleHistory = () => {
    showHistory.value = !showHistory.value
    showTemplates.value = false
    selectedIndex.value = 0
}

const toggleTemplates = () => {
    showTemplates.value = !showTemplates.value
    showHistory.value = false
    selectedIndex.value = 0
}

const getCategoryIcon = (category) => {
    const icons = {
        'user': 'fas fa-user',
        'customer': 'fas fa-users',
        'invoice': 'fas fa-file-invoice',
        'company': 'fas fa-building',
        'general': 'fas fa-cog'
    }
    return icons[category] || 'fas fa-terminal'
}

const formatCommandName = (name) => {
    return name.replace(/\./g, ' • ')
}

// Watch search query for suggestions
watch(searchQuery, (newQuery) => {
    if (newQuery.trim().length >= 2) {
        loadSuggestions(newQuery)
        showHistory.value = false
        showTemplates.value = false
    } else {
        suggestions.value = []
    }
    selectedIndex.value = 0
})

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
        case 'Tab':
            event.preventDefault()
            if (event.shiftKey) {
                // Cycle backwards through views
                if (showTemplates.value) {
                    toggleTemplates()
                    toggleHistory()
                } else if (showHistory.value) {
                    toggleHistory()
                    toggleTemplates()
                } else {
                    toggleHistory()
                }
            } else {
                // Cycle forwards through views
                if (showHistory.value) {
                    toggleHistory()
                    toggleTemplates()
                } else if (showTemplates.value) {
                    toggleTemplates()
                } else {
                    toggleHistory()
                }
            }
            break
        case 'h':
            if (event.ctrlKey) {
                event.preventDefault()
                toggleHistory()
            }
            break
        case 't':
            if (event.ctrlKey) {
                event.preventDefault()
                toggleTemplates()
            }
            break
    }
}

const handleGlobalKeydown = (event) => {
    // Check for Ctrl+K or Cmd+K to open command palette
    if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
        event.preventDefault()
        open()
    }
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
                        :disabled="loading"
                        @keydown.enter="selectHighlightedCommand"
                        @keydown.arrow-down.prevent="moveSelection('down')"
                        @keydown.arrow-up.prevent="moveSelection('up')"
                        @keydown.escape="close"
                        @keydown.tab.prevent
                        @keydown.ctrl.h.prevent="toggleHistory"
                        @keydown.ctrl.t.prevent="toggleTemplates"
                    />
                    <ProgressSpinner v-if="loading" class="absolute right-3 top-1/2 transform -translate-y-1/2" style="width: 20px; height: 20px" />
                </div>
                
                <!-- View Tabs -->
                <div class="mt-3 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <button 
                            @click="showHistory = false; showTemplates = false"
                            :class="['px-3 py-1 text-xs rounded-full transition-colors', {
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300': !showHistory && !showTemplates,
                                'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400': showHistory || showTemplates
                            }]"
                        >
                            Commands
                        </button>
                        <button 
                            @click="toggleHistory"
                            :class="['px-3 py-1 text-xs rounded-full transition-colors', {
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300': showHistory,
                                'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400': !showHistory
                            }]"
                        >
                            History
                        </button>
                        <button 
                            @click="toggleTemplates"
                            :class="['px-3 py-1 text-xs rounded-full transition-colors', {
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300': showTemplates,
                                'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400': !showTemplates
                            }]"
                        >
                            Templates
                        </button>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Press <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs">Esc</kbd> to close
                    </div>
                </div>
            </div>

            <!-- Error Message -->
            <Message v-if="error" severity="error" :closable="false" class="m-4">
                {{ error }}
            </Message>

            <!-- Success Message -->
            <Message v-if="executionResult?.success" severity="success" :closable="false" class="m-4">
                Command executed successfully!
            </Message>

            <!-- Commands List -->
            <div class="max-h-96 overflow-y-auto">
                <!-- No Results -->
                <div v-if="allCommands.length === 0 && !loading" class="p-8 text-center">
                    <i class="fas fa-search text-3xl text-gray-400 mb-4"></i>
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ showHistory ? 'No command history found' : 
                           showTemplates ? 'No templates found' :
                           searchQuery ? `No commands found for "${searchQuery}"` :
                           'No commands available' }}
                    </p>
                </div>

                <!-- Command Items -->
                <div v-else>
                    <div 
                        v-for="(command, index) in allCommands" 
                        :key="command.id || command.command_name || index"
                        class="group border-b border-gray-100 dark:border-gray-700 last:border-b-0"
                    >
                        <div 
                            class="flex items-center px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors"
                            :class="{
                                'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500': index === selectedIndex,
                                'bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500': command.execution_status === 'success',
                                'bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500': command.execution_status === 'failed'
                            }"
                            @click="selectCommand(command)"
                            @mouseenter="selectedIndex = index"
                        >
                            <div class="flex items-center flex-1 min-w-0">
                                <!-- Icon -->
                                <i 
                                    :class="[
                                        getCategoryIcon(command.category || command.name?.split('.')[0]),
                                        'text-gray-600 dark:text-gray-400 w-5 flex-shrink-0'
                                    ]"
                                ></i>
                                
                                <!-- Command Info -->
                                <div class="ml-3 flex-1 min-w-0">
                                    <!-- Command Name -->
                                    <div class="font-medium text-gray-900 dark:text-white truncate">
                                        {{ command.name ? formatCommandName(command.name) : command.command_name }}
                                    </div>
                                    
                                    <!-- Description or Status -->
                                    <div class="text-sm text-gray-600 dark:text-gray-400 truncate">
                                        <span v-if="command.description">{{ command.description }}</span>
                                        <span v-else-if="command.execution_status" class="capitalize">
                                            {{ command.execution_status }} {{ command.executed_at ? `• ${new Date(command.executed_at).toLocaleDateString()}` : '' }}
                                        </span>
                                        <span v-else-if="command.is_shared" class="text-blue-600 dark:text-blue-400">
                                            Shared by {{ command.created_by }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Right Side Info -->
                            <div class="flex items-center space-x-3 flex-shrink-0 ml-3">
                                <!-- Confidence Badge for Suggestions -->
                                <Badge 
                                    v-if="command.confidence" 
                                    :value="Math.round(command.confidence * 100) + '%'"
                                    severity="secondary"
                                    size="small"
                                />
                                
                                <!-- Match Type -->
                                <span v-if="command.match_type" class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                                    {{ command.match_type }}
                                </span>
                                
                                <!-- Execution Status -->
                                <i 
                                    v-if="command.execution_status === 'success'" 
                                    class="fas fa-check-circle text-green-500"
                                ></i>
                                <i 
                                    v-else-if="command.execution_status === 'failed'" 
                                    class="fas fa-times-circle text-red-500"
                                ></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-3 border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                        <span>
                            <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">Ctrl+K</kbd> to open
                        </span>
                        <span>
                            <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">↑↓</kbd> to navigate
                        </span>
                        <span>
                            <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">Enter</kbd> to select
                        </span>
                        <span>
                            <kbd class="px-1 py-0.5 bg-gray-100 dark:bg-gray-700 rounded">Tab</kbd> to switch views
                        </span>
                    </div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        {{ allCommands.length }} {{ showHistory ? 'history items' : showTemplates ? 'templates' : 'commands' }}
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