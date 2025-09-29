<template>
  <EntityPicker
    ref="dropdown"
    v-model="selectedUserId"
    :entities="users"
    entity-type="user"
    :optionLabel="optionLabel"
    :optionValue="optionValue"
    :optionDisabled="optionDisabled"
    :placeholder="placeholder"
    :filterPlaceholder="filterPlaceholder"
    :filterFields="filterFields"
    :showClear="showClear"
    :disabled="disabled"
    :loading="loading"
    :error="error"
    :showBalance="showBalance"
    :showStats="showStats"
    :allowCreate="allowCreate"
    @change="onChange"
    @filter="onFilter"
    @show="onShow"
    @hide="onHide"
    @create-entity="createUser"
    @view-entity="viewUser"
  />
</template>

<script setup lang="ts">
import { computed, nextTick } from 'vue'
import EntityPicker from './EntityPicker.vue'

interface User {
  id?: number
  user_id?: number
  name: string
  email?: string
  role?: string
  department?: string
  status?: string
  avatar?: string
  task_count?: number
  project_count?: number
  [key: string]: any
}

interface Props {
  modelValue?: number | string | null
  users: User[]
  optionLabel?: string
  optionValue?: string
  optionDisabled?: (user: User) => boolean
  placeholder?: string
  filterPlaceholder?: string
  filterFields?: string[]
  showClear?: boolean
  disabled?: boolean
  loading?: boolean
  error?: string
  showBalance?: boolean
  showStats?: boolean
  allowCreate?: boolean
}

interface Emits {
  (e: 'update:modelValue', value: number | string | null): void
  (e: 'change', user: User | null): void
  (e: 'filter', event: Event): void
  (e: 'show'): void
  (e: 'hide'): void
  (e: 'create-user'): void
  (e: 'view-user', user: User): void
}

const props = withDefaults(defineProps<Props>(), {
  optionLabel: 'name',
  optionValue: 'id',
  placeholder: 'Select a user...',
  filterPlaceholder: 'Search users...',
  filterFields: () => ['name', 'email', 'role', 'department'],
  showClear: true,
  disabled: false,
  loading: false,
  showBalance: false,
  showStats: false,
  allowCreate: true
})

const emit = defineEmits<Emits>()

const dropdown = ref()

const selectedUserId = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const selectedUser = computed(() => {
  if (!selectedUserId.value) return null
  
  return props.users.find(user => {
    const userId = user[props.optionValue]
    return userId === selectedUserId.value
  }) || null
})

const onChange = (user: User | null) => {
  emit('change', user)
}

const onFilter = (event: Event) => {
  emit('filter', event)
}

const onShow = () => {
  emit('show')
}

const onHide = () => {
  emit('hide')
}

const createUser = () => {
  emit('create-user')
}

const viewUser = (user: User) => {
  emit('view-user', user)
}

// Expose dropdown methods
const show = () => {
  dropdown.value?.show()
}

const hide = () => {
  dropdown.value?.hide()
}

const focus = () => {
  nextTick(() => {
    dropdown.value?.focus()
  })
}

defineExpose({
  show,
  hide,
  focus
})
</script>