<template>
    <div class="inline-editable">
        <label v-if="label" class="block text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">
            {{ label }}
        </label>

        <div v-if="!isEditing" class="flex items-center gap-2 group">
            <slot name="display">
                <div
                    class="flex-1 cursor-pointer"
                    :class="{
                        'hover:bg-gray-50 dark:hover:bg-gray-800 p-2 rounded': displayValue === '',
                        'group-hover:bg-gray-50 dark:group-hover:bg-gray-800 p-2 rounded': displayValue !== ''
                    }"
                    @click="startEditing"
                    tabindex="0"
                    @keydown.enter.prevent="startEditing"
                    role="button"
                    :aria-label="displayValue === '' ? `Add ${label || 'field'}` : `Edit ${label || 'field'}`"
                >
                    <span v-if="displayValue !== ''">{{ displayValue }}</span>
                    <span v-else class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400">
                        + Click to add {{ label || 'field' }}
                    </span>
                </div>
            </slot>

            <button
                v-if="displayValue !== ''"
                type="button"
                @click="startEditing"
                class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 opacity-0 group-hover:opacity-100 transition-opacity"
                :aria-label="`Edit ${label || 'field'}`"
            >
                <i class="fas fa-pen text-xs mr-1" aria-hidden="true"></i>
                edit
            </button>
        </div>

        <div v-else class="flex items-start gap-2">
            <div class="flex-1">
                <input
                    v-if="type === 'text'"
                    v-model="localValue"
                    class="w-full p-2 border rounded"
                    :placeholder="placeholder"
                    ref="inputRef"
                    @keyup.enter="onEnterKey"
                    @keyup.esc="onEscapeKey"
                    :aria-label="`Edit ${label || 'field'} input`"
                />
                <textarea
                    v-else-if="type === 'textarea'"
                    v-model="localValue"
                    class="w-full p-2 border rounded"
                    :placeholder="placeholder"
                    ref="inputRef"
                    @keyup.enter="onEnterKey"
                    @keyup.esc="onEscapeKey"
                    :aria-label="`Edit ${label || 'field'} input`"
                />
                <input
                    v-else-if="type === 'number'"
                    v-model="localValue"
                    type="number"
                    :step="step || 'any'"
                    :min="min"
                    class="w-full p-2 border rounded"
                    :placeholder="placeholder"
                    ref="inputRef"
                    @keyup.enter="onEnterKey"
                    @keyup.esc="onEscapeKey"
                    :aria-label="`Edit ${label || 'field'} input`"
                />
                <input
                    v-else-if="type === 'date'"
                    v-model="localValue"
                    type="date"
                    class="w-full p-2 border rounded"
                    :placeholder="placeholder"
                    ref="inputRef"
                    @keyup.enter="onEnterKey"
                    @keyup.esc="onEscapeKey"
                    :aria-label="`Edit ${label || 'field'} input`"
                />
                <select
                    v-else-if="type === 'select'"
                    v-model="localValue"
                    class="w-full p-2 border rounded"
                    ref="inputRef"
                    @keyup.enter="onEnterKey"
                    @keyup.esc="onEscapeKey"
                    :aria-label="`Edit ${label || 'field'} input`"
                >
                    <option v-for="option in options" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>
                <div v-if="error" class="text-xs text-red-600 mt-1" role="alert">{{ error }}</div>
            </div>

            <div class="flex-shrink-0 flex items-center gap-2 mt-1 edit-buttons">
                <button
                    type="button"
                    class="text-xs text-green-600 hover:text-green-800 dark:text-green-400 dark:hover:text-green-300"
                    @click="onSave"
                    :disabled="saving"
                    :aria-disabled="saving"
                    :aria-label="`Save ${label || 'field'}`"
                >
                    <i class="fas fa-check text-xs mr-1" aria-hidden="true"></i>
                    <span v-if="saving">saving…</span>
                    <span v-else>save</span>
                </button>

                <button
                    type="button"
                    class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                    @click="onCancel"
                    :disabled="saving"
                    :aria-label="`Cancel editing ${label || 'field'}`"
                >
                    <i class="fas fa-times text-xs mr-1" aria-hidden="true"></i>
                    cancel
                </button>
            </div>
        </div>

        <Dialog
            v-model:visible="showAdditionalInfoModal"
            :style="{ width: '500px' }"
            :modal="true"
            :closable="false"
            header="Additional Information Required"
        >
            <div class="space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    {{ additionalInfoModalDescription }}
                </p>

                <div v-for="field in additionalInfoFields" :key="field.name" class="space-y-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                        {{ field.label }}
                        <span v-if="field.required" class="text-red-500">*</span>
                    </label>

                    <input
                        v-if="field.type === 'text'"
                        v-model="additionalInfoValues[field.name]"
                        :type="field.type"
                        :placeholder="field.placeholder"
                        class="w-full p-2 border rounded dark:bg-gray-800 dark:border-gray-600"
                        :required="field.required"
                    />

                    <textarea
                        v-else-if="field.type === 'textarea'"
                        v-model="additionalInfoValues[field.name]"
                        :placeholder="field.placeholder"
                        :rows="field.rows || 3"
                        class="w-full p-2 border rounded dark:bg-gray-800 dark:border-gray-600"
                        :required="field.required"
                    />

                    <select
                        v-else-if="field.type === 'select'"
                        v-model="additionalInfoValues[field.name]"
                        class="w-full p-2 border rounded dark:bg-gray-800 dark:border-gray-600"
                        :required="field.required"
                    >
                        <option value="">Select an option...</option>
                        <option v-for="option in field.options" :key="option.value" :value="option.value">
                            {{ option.label }}
                        </option>
                    </select>

                    <p v-if="field.helpText" class="text-xs text-gray-500 dark:text-gray-400">
                        {{ field.helpText }}
                    </p>

                    <p v-if="additionalInfoErrors[field.name]" class="text-xs text-red-600">
                        {{ additionalInfoErrors[field.name] }}
                    </p>
                </div>
            </div>

            <template #footer>
                <Button
                    label="Cancel"
                    icon="pi pi-times"
                    severity="secondary"
                    outlined
                    @click="cancelAdditionalInfo"
                />
                <Button
                    label="Save"
                    icon="pi pi-check"
                    @click="saveAdditionalInfo"
                    :loading="savingAdditionalInfo"
                />
            </template>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch, type PropType } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import { http } from '@/lib/http'

const props = defineProps({
    modelValue: { type: [String, Number, Object] as PropType<any>, default: '' },
    label: { type: String, default: '' },
    type: { type: String as PropType<'text' | 'textarea' | 'number' | 'date' | 'select'>, default: 'text' },
    options: { type: Array as PropType<Array<{ label: string; value: any }>>, default: () => [] },
    placeholder: { type: String, default: '' },
    step: { type: String, default: 'any' },
    min: { type: [String, Number], default: undefined },
    saving: { type: Boolean, default: false },
    editing: { type: Boolean, default: false },
    validate: { type: Function as PropType<(val: any) => string | null>, default: null },
    actionType: { type: String, default: '' },
    context: { type: Object as PropType<Record<string, any>>, default: () => ({}) },
    apiUrl: { type: String, default: '/api/invoicing-requirements' },
})

const emit = defineEmits(['update:modelValue', 'save', 'cancel', 'update:editing'])

const isEditing = ref(false)
const localValue = ref(props.modelValue)
const error = ref<string | null>(null)
const inputRef = ref<HTMLElement | null>(null)

const showAdditionalInfoModal = ref(false)
const savingAdditionalInfo = ref(false)
const additionalInfoModalDescription = ref('')
const additionalInfoFields = ref<Array<any>>([])
const additionalInfoValues = ref<Record<string, any>>({})
const additionalInfoErrors = ref<Record<string, string>>({})
const pendingValue = ref<any>(null)

watch(
    () => props.modelValue,
    (v) => {
        localValue.value = v
    }
)

watch(
    () => props.editing,
    (newValue) => {
        isEditing.value = newValue
        if (newValue) {
            localValue.value = props.modelValue
            nextTick(() => {
                if (inputRef.value && 'focus' in inputRef.value) {
                    ;(inputRef.value as HTMLElement).focus()
                }
            })
        }
    }
)

const displayValue = computed(() => {
    if (props.type === 'select') {
        const found = props.options.find((o) => o.value === props.modelValue)
        return found ? found.label : ''
    }
    return props.modelValue ?? ''
})

const startEditing = async () => {
    if (props.actionType) {
        try {
            const response = await http.get(props.apiUrl, {
                params: {
                    action_type: props.actionType,
                    entity_type: props.context.entityType || 'entity',
                    entity_id: props.context.entityId || '',
                    current_status: props.context.currentStatus || '',
                    new_status: props.modelValue,
                    context: {
                        ...props.context,
                        new_value: props.modelValue,
                    },
                },
            })

            const requirements = response.data.data

            if (requirements.requiresAdditionalInfo && requirements.fields.length > 0) {
                pendingValue.value = props.modelValue
                additionalInfoFields.value = requirements.fields
                additionalInfoModalDescription.value =
                    requirements.description || `Additional information is required to ${props.actionType.replace('-', ' ')}.`
                showAdditionalInfoModal.value = true
                return
            }
        } catch (err) {
            console.error('Failed to check requirements:', err)
        }
    }

    emit('update:editing', true)
}

const onSave = async () => {
    error.value = props.validate ? props.validate(localValue.value) : null
    if (error.value) return

    if (props.actionType && Object.keys(props.context).length > 0) {
        try {
            const response = await http.get(props.apiUrl, {
                params: {
                    action_type: props.actionType,
                    entity_type: props.context.entityType || 'entity',
                    entity_id: props.context.entityId || '',
                    current_status: props.context.currentStatus || '',
                    new_status: localValue.value,
                    context: {
                        ...props.context,
                        new_value: localValue.value,
                    },
                },
            })

            const requirements = response.data.data

            if (requirements.requiresAdditionalInfo && requirements.fields.length > 0) {
                pendingValue.value = localValue.value
                additionalInfoFields.value = requirements.fields
                additionalInfoModalDescription.value =
                    requirements.description || `Additional information is required to ${props.actionType.replace('-', ' ')}.`
                showAdditionalInfoModal.value = true
                return
            }
        } catch (err) {
            console.error('Failed to check requirements:', err)
            alert('Failed to check requirements. Please try again.')
            return
        }
    }

    emit('save', localValue.value)
}

const onCancel = () => {
    emit('update:editing', false)
    localValue.value = props.modelValue
    error.value = null
    emit('cancel')
}

const onEnterKey = () => {
    if (props.type !== 'textarea') {
        onSave()
    }
}

const onEscapeKey = () => {
    onCancel()
}

const saveAdditionalInfo = async () => {
    try {
        const response = await http.post(`${props.apiUrl}/validate`, {
            action_type: props.actionType,
            fields: additionalInfoFields.value,
            values: additionalInfoValues.value,
        })

        if (!response.data.success) {
            additionalInfoErrors.value = response.data.errors
            return
        }

        savingAdditionalInfo.value = true

        emit('save', pendingValue.value, {
            additionalInfo: additionalInfoValues.value,
            actionType: props.actionType,
        })

        showAdditionalInfoModal.value = false
        additionalInfoValues.value = {}
        additionalInfoErrors.value = {}
        pendingValue.value = null
    } catch (err) {
        console.error('Failed to validate additional info:', err)
        alert('Failed to validate additional information. Please try again.')
    } finally {
        savingAdditionalInfo.value = false
    }
}

const cancelAdditionalInfo = () => {
    showAdditionalInfoModal.value = false
    additionalInfoValues.value = {}
    additionalInfoErrors.value = {}
    pendingValue.value = null
    emit('update:editing', false)
}
</script>

<style scoped>
.inline-editable input,
.inline-editable textarea,
.inline-editable select {
    background: transparent;
    border: 1px solid #e5e7eb;
    padding: 0.375rem 0.5rem;
    border-radius: 0.375rem;
}

.inline-editable textarea {
    min-height: 3rem;
}

.group button {
    transition: opacity 0.2s ease-in-out;
}

.inline-editable .edit-buttons {
    opacity: 1 !important;
    visibility: visible !important;
}
</style>
