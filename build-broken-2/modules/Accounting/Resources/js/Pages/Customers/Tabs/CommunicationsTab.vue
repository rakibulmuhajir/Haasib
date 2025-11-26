<script setup>
import { ref, computed, onMounted } from 'vue'
import { useToast } from "@/components/ui/toast/use-toast"
import { useI18n } from 'vue-i18n'
// import DataTable from 'primevue/datatable'
// import Column from 'primevue/column'
// import Button from 'primevue/button'
// import Dialog from 'primevue/dialog'
// import Dropdown from 'primevue/dropdown'
// import Calendar from 'primevue/calendar'
// import Tag from 'primevue/tag'
// import Textarea from 'primevue/textarea'
// import Toast from 'primevue/toast'
import { useForm } from '@inertiajs/vue3'

const props = defineProps({
    customer: Object,
    communications: Array,
    can: Object
})

const emit = defineEmits(['refresh'])

const toast = useToast()
const { t } = useI18n()

// Dialog states
const communicationDialog = ref(false)

// Form
const form = useForm({
    type: 'note',
    subject: '',
    content: '',
    direction: 'outbound',
    communication_date: new Date(),
    notes: ''
})

// Filters
const filters = ref({
    type: '',
    direction: '',
    date_from: null,
    date_to: null,
    search: ''
})

// Options
const communicationTypes = [
    { label: 'All Types', value: '' },
    { label: 'Email', value: 'email' },
    { label: 'Phone Call', value: 'phone' },
    { label: 'Meeting', value: 'meeting' },
    { label: 'Letter', value: 'letter' },
    { label: 'SMS', value: 'sms' },
    { label: 'Note', value: 'note' },
    { label: 'Other', value: 'other' }
]

const directions = [
    { label: 'All Directions', value: '' },
    { label: 'Inbound', value: 'inbound' },
    { label: 'Outbound', value: 'outbound' }
]

// Computed properties
const hasCommunications = computed(() => props.communications && props.communications.length > 0)

const filteredCommunications = computed(() => {
    if (!props.communications) return []
    
    return props.communications.filter(comm => {
        // Type filter
        if (filters.value.type && comm.type !== filters.value.type) return false
        
        // Direction filter
        if (filters.value.direction && comm.direction !== filters.value.direction) return false
        
        // Date range filter
        if (filters.value.date_from) {
            const commDate = new Date(comm.communication_date)
            if (commDate < filters.value.date_from) return false
        }
        if (filters.value.date_to) {
            const commDate = new Date(comm.communication_date)
            if (commDate > filters.value.date_to) return false
        }
        
        // Search filter
        if (filters.value.search) {
            const searchLower = filters.value.search.toLowerCase()
            return comm.subject?.toLowerCase().includes(searchLower) ||
                   comm.content?.toLowerCase().includes(searchLower) ||
                   comm.notes?.toLowerCase().includes(searchLower)
        }
        
        return true
    })
})

const recentCommunications = computed(() => {
    return filteredCommunications.value.slice(0, 5)
})

const communicationStats = computed(() => {
    const stats = {
        total: filteredCommunications.value.length,
        by_type: {},
        by_direction: {},
        this_month: 0,
        this_week: 0
    }
    
    const now = new Date()
    const thisMonth = new Date(now.getFullYear(), now.getMonth(), 1)
    const thisWeek = new Date(now.getFullYear(), now.getMonth(), now.getDate() - now.getDay())
    
    filteredCommunications.value.forEach(comm => {
        // By type
        stats.by_type[comm.type] = (stats.by_type[comm.type] || 0) + 1
        
        // By direction
        stats.by_direction[comm.direction] = (stats.by_direction[comm.direction] || 0) + 1
        
        // Time periods
        const commDate = new Date(comm.communication_date)
        if (commDate >= thisMonth) stats.this_month++
        if (commDate >= thisWeek) stats.this_week++
    })
    
    return stats
})

// Methods
const openCommunicationDialog = () => {
    form.reset()
    form.clearErrors()
    form.communication_date = new Date()
    communicationDialog.value = true
}

const saveCommunication = () => {
    form.post(route('customers.communications.store', props.customer.id), {
        onSuccess: () => {
            communicationDialog.value = false
            form.reset()
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: 'Communication logged successfully',
                life: 3000
            })
            emit('refresh')
        },
        onError: (errors) => {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: 'Please check the form for errors',
                life: 3000
            })
        }
    })
}

const clearFilters = () => {
    filters.value = {
        type: '',
        direction: '',
        date_from: null,
        date_to: null,
        search: ''
    }
}

const getCommunicationIcon = (type) => {
    const icons = {
        email: 'pi pi-envelope',
        phone: 'pi pi-phone',
        meeting: 'pi pi-users',
        letter: 'pi pi-file',
        sms: 'pi pi-mobile',
        note: 'pi pi-comment',
        other: 'pi pi-info-circle'
    }
    return icons[type] || 'pi pi-info-circle'
}

const getCommunicationTypeLabel = (type) => {
    const typeObj = communicationTypes.find(t => t.value === type)
    return typeObj ? typeObj.label : type
}

const getDirectionColor = (direction) => {
    switch (direction) {
        case 'inbound': return 'success'
        case 'outbound': return 'info'
        default: return 'secondary'
    }
}

const formatCommunicationDate = (dateString) => {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now - date
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))
    
    if (diffDays === 0) {
        return 'Today ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
    } else if (diffDays === 1) {
        return 'Yesterday ' + date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
    } else if (diffDays < 7) {
        return date.toLocaleDateString('en-US', { weekday: 'short' }) + ' ' + 
               date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
    } else {
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric', 
            year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined 
        })
    }
}

const getRelativeTime = (dateString) => {
    const date = new Date(dateString)
    const now = new Date()
    const diffMs = now - date
    const diffMins = Math.floor(diffMs / (1000 * 60))
    const diffHours = Math.floor(diffMs / (1000 * 60 * 60))
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24))
    
    if (diffMins < 60) {
        return `${diffMins} minute${diffMins !== 1 ? 's' : ''} ago`
    } else if (diffHours < 24) {
        return `${diffHours} hour${diffHours !== 1 ? 's' : ''} ago`
    } else if (diffDays < 30) {
        return `${diffDays} day${diffDays !== 1 ? 's' : ''} ago`
    } else {
        return date.toLocaleDateString('en-US', { 
            month: 'short', 
            day: 'numeric',
            year: 'numeric'
        })
    }
}
</script>

<template>
    <div>
        <Toast />
        
        <div class="flex justify-between items-center mb-4">
            <div>
                <h3 class="text-lg font-medium text-gray-900">Communication History</h3>
                <p class="text-sm text-gray-600">Track all interactions and communications with this customer</p>
            </div>
            
            <Button
                v-if="can.create"
                label="Log Communication"
                icon="pi pi-plus"
                @click="openCommunicationDialog"
            />
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                <div class="text-blue-600 text-sm font-medium">Total Communications</div>
                <div class="text-2xl font-bold text-blue-800">{{ communicationStats.total }}</div>
            </div>
            
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <div class="text-green-600 text-sm font-medium">This Month</div>
                <div class="text-2xl font-bold text-green-800">{{ communicationStats.this_month }}</div>
            </div>
            
            <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
                <div class="text-purple-600 text-sm font-medium">This Week</div>
                <div class="text-2xl font-bold text-purple-800">{{ communicationStats.this_week }}</div>
            </div>
            
            <div class="bg-orange-50 p-4 rounded-lg border border-orange-200">
                <div class="text-orange-600 text-sm font-medium">Inbound vs Outbound</div>
                <div class="text-lg font-bold text-orange-800">
                    {{ communicationStats.by_direction.inbound || 0 }} / {{ communicationStats.by_direction.outbound || 0 }}
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-gray-50 p-4 rounded-lg mb-6">
            <div class="flex flex-wrap gap-4 items-end">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <Dropdown
                        v-model="filters.type"
                        :options="communicationTypes"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Filter by type"
                        class="w-48"
                        @change="clearFilters"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Direction</label>
                    <Dropdown
                        v-model="filters.direction"
                        :options="directions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Filter by direction"
                        class="w-48"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                    <Calendar
                        v-model="filters.date_from"
                        placeholder="Start date"
                        class="w-48"
                        dateFormat="mm/dd/yy"
                    />
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                    <Calendar
                        v-model="filters.date_to"
                        placeholder="End date"
                        class="w-48"
                        dateFormat="mm/dd/yy"
                    />
                </div>
                
                <div class="flex-1 min-w-64">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <InputText
                        v-model="filters.search"
                        placeholder="Search subject, content, or notes..."
                        class="w-full"
                    />
                </div>
                
                <Button
                    label="Clear Filters"
                    icon="pi pi-filter-slash"
                    severity="secondary"
                    @click="clearFilters"
                    :disabled="!filters.type && !filters.direction && !filters.date_from && !filters.date_to && !filters.search"
                />
            </div>
        </div>

        <!-- Recent Communications Timeline -->
        <div v-if="hasCommunications" class="space-y-4">
            <h4 class="text-md font-medium text-gray-900">
                Recent Communications ({{ filteredCommunications.length }})
            </h4>
            
            <div class="relative">
                <!-- Timeline line -->
                <div class="absolute left-6 top-0 bottom-0 w-0.5 bg-gray-200"></div>
                
                <!-- Timeline items -->
                <div class="space-y-6">
                    <div 
                        v-for="communication in filteredCommunications" 
                        :key="communication.id"
                        class="relative flex items-start"
                    >
                        <!-- Timeline dot -->
                        <div class="flex-shrink-0 w-12 h-12 bg-white border-2 border-gray-200 rounded-full flex items-center justify-center z-10">
                            <i :class="getCommunicationIcon(communication.type)" class="text-gray-600"></i>
                        </div>
                        
                        <!-- Content -->
                        <div class="ml-4 flex-1 bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <Tag 
                                        :value="getCommunicationTypeLabel(communication.type)" 
                                        severity="secondary"
                                        size="small"
                                    />
                                    <Tag 
                                        :value="communication.direction" 
                                        :severity="getDirectionColor(communication.direction)"
                                        size="small"
                                    />
                                </div>
                                <div class="text-sm text-gray-500">
                                    {{ getRelativeTime(communication.communication_date) }}
                                </div>
                            </div>
                            
                            <div v-if="communication.subject" class="font-medium text-gray-900 mb-1">
                                {{ communication.subject }}
                            </div>
                            
                            <div v-if="communication.content" class="text-gray-700 mb-2">
                                {{ communication.content }}
                            </div>
                            
                            <div v-if="communication.notes" class="text-sm text-gray-600 italic">
                                {{ communication.notes }}
                            </div>
                            
                            <div class="text-xs text-gray-500 mt-2">
                                {{ formatCommunicationDate(communication.communication_date) }}
                                <span v-if="communication.created_by">
                                    • by {{ communication.created_by.name }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="text-center py-12 text-gray-500">
            <i class="pi pi-comments text-4xl mb-4 text-gray-300"></i>
            <h3 class="text-lg font-medium text-gray-900 mb-2">No communications found</h3>
            <p class="text-sm text-gray-600 mb-4">
                Start tracking your interactions by logging the first communication.
            </p>
            <Button
                v-if="can.create"
                label="Log First Communication"
                icon="pi pi-plus"
                @click="openCommunicationDialog"
            />
        </div>

        <!-- Log Communication Dialog -->
        <Dialog
            v-model:visible="communicationDialog"
            :style="{ width: '600px' }"
            header="Log Communication"
            :modal="true"
        >
            <form @submit.prevent="saveCommunication">
                <div class="space-y-4">
                    <!-- Type and Direction -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Communication Type *
                            </label>
                            <Dropdown
                                v-model="form.type"
                                :options="communicationTypes.filter(t => t.value !== '')"
                                optionLabel="label"
                                optionValue="value"
                                :class="{ 'p-invalid': form.errors.type }"
                                class="w-full"
                                placeholder="Select type"
                                required
                            />
                            <small v-if="form.errors.type" class="text-red-500">
                                {{ form.errors.type }}
                            </small>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Direction *
                            </label>
                            <Dropdown
                                v-model="form.direction"
                                :options="directions.filter(d => d.value !== '')"
                                optionLabel="label"
                                optionValue="value"
                                :class="{ 'p-invalid': form.errors.direction }"
                                class="w-full"
                                placeholder="Select direction"
                                required
                            />
                            <small v-if="form.errors.direction" class="text-red-500">
                                {{ form.errors.direction }}
                            </small>
                        </div>
                    </div>

                    <!-- Date and Subject -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Communication Date *
                        </label>
                        <Calendar
                            v-model="form.communication_date"
                            :class="{ 'p-invalid': form.errors.communication_date }"
                            class="w-full"
                            dateFormat="mm/dd/yy"
                            showTime
                            required
                        />
                        <small v-if="form.errors.communication_date" class="text-red-500">
                            {{ form.errors.communication_date }}
                        </small>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Subject
                        </label>
                        <InputText
                            v-model="form.subject"
                            :class="{ 'p-invalid': form.errors.subject }"
                            class="w-full"
                            placeholder="Brief subject or topic"
                        />
                        <small v-if="form.errors.subject" class="text-red-500">
                            {{ form.errors.subject }}
                        </small>
                    </div>

                    <!-- Content -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Content *
                        </label>
                        <Textarea
                            v-model="form.content"
                            :class="{ 'p-invalid': form.errors.content }"
                            class="w-full"
                            rows="4"
                            placeholder="Details of the communication..."
                            required
                        />
                        <small v-if="form.errors.content" class="text-red-500">
                            {{ form.errors.content }}
                        </small>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Internal Notes
                        </label>
                        <Textarea
                            v-model="form.notes"
                            :class="{ 'p-invalid': form.errors.notes }"
                            class="w-full"
                            rows="2"
                            placeholder="Internal notes for team members..."
                        />
                        <small v-if="form.errors.notes" class="text-red-500">
                            {{ form.errors.notes }}
                        </small>
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <Button
                        label="Cancel"
                        icon="pi pi-times"
                        text
                        @click="communicationDialog = false"
                        :disabled="form.processing"
                    />
                    <Button
                        label="Log Communication"
                        icon="pi pi-check"
                        type="submit"
                        :loading="form.processing"
                    />
                </div>
            </form>
        </Dialog>
    </div>
</template>

<style scoped>
.timeline-dot {
    position: relative;
    z-index: 10;
}
</style>