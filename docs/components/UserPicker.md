# UserPicker

## Description
A specialized picker component for selecting users. Built on top of EntityPicker with user-specific defaults and configurations. Provides an intuitive interface for browsing and selecting users with their contact information, role, department, and activity metrics.

## Props
| Prop | Type | Required | Default | Description |
|------|------|----------|---------|-------------|
| modelValue | number \| string \| null | No | null | Selected user ID |
| users | User[] | Yes | - | Array of users to display |
| optionLabel | string | No | 'name' | Field to use as display label |
| optionValue | string | No | 'id' | Field to use as value |
| optionDisabled | (user: User) => boolean | No | - | Function to determine if option is disabled |
| placeholder | string | No | 'Select a user...' | Placeholder text when no selection |
| filterPlaceholder | string | No | 'Search users...' | Filter input placeholder |
| filterFields | string[] | No | ['name', 'email', 'role', 'department'] | Fields to search when filtering |
| showClear | boolean | No | true | Show clear button |
| disabled | boolean | No | false | Disable the picker |
| loading | boolean | No | false | Show loading state |
| error | string | No | - | Error message to display |
| showBalance | boolean | No | false | Show user balance display (typically false for users) |
| showStats | boolean | No | false | Show user statistics |
| allowCreate | boolean | No | true | Show create user button |

## Events
| Event | Payload | Description |
|-------|---------|-------------|
| update:modelValue | (value: number \| string \| null) => void | Emitted when selection changes |
| change | (user: User \| null) => void | Emitted when selection changes with full user object |
| filter | (event: Event) => void | Emitted when filter is applied |
| show | () => void | Emitted when dropdown is shown |
| hide | () => void | Emitted when dropdown is hidden |
| create-user | () => void | Emitted when create user button is clicked |
| view-user | (user: User) => void | Emitted when view user action is clicked |

## Usage Examples

### Basic Usage
```vue
<template>
  <UserPicker
    v-model="form.assigned_to"
    :users="users"
    @change="onUserSelected"
  />
</template>

<script setup>
import { ref } from 'vue'
import UserPicker from '@/Components/UI/Forms/UserPicker.vue'

const form = ref({
  assigned_to: null
})

const users = ref([
  {
    id: 1,
    name: 'John Smith',
    email: 'john.smith@company.com',
    role: 'Developer',
    department: 'Engineering',
    status: 'active',
    task_count: 8,
    project_count: 3
  }
])

const onUserSelected = (user) => {
  console.log('Selected user:', user)
}
</script>
```

### In a Task Assignment Context
```vue
<template>
  <div class="space-y-4">
    <label class="block text-sm font-medium text-gray-700">
      Assign To
    </label>
    
    <UserPicker
      v-model="task.assigned_user_id"
      :users="activeUsers"
      :error="task.errors.assigned_user_id"
      :disabled="task.status === 'completed'"
      placeholder="Select a team member..."
      @change="onAssignmentChange"
    />
    
    <div v-if="selectedUser" class="p-3 bg-green-50 rounded-lg border border-green-200">
      <div class="flex items-center space-x-3">
        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-sm font-medium"
             :style="{ backgroundColor: getAvatarColor(selectedUser.name) }">
          {{ getInitials(selectedUser.name) }}
        </div>
        <div>
          <p class="font-medium text-green-900">{{ selectedUser.name }}</p>
          <p class="text-sm text-green-700">{{ selectedUser.role }} " {{ selectedUser.department }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  task: Object,
  users: Array
})

const emit = defineEmits(['assignment-change'])

const activeUsers = computed(() => {
  return props.users.filter(user => user.status === 'active')
})

const selectedUser = computed(() => {
  return props.users.find(u => u.id === props.task.assigned_user_id)
})

const onAssignmentChange = (user) => {
  emit('assignment-change', user)
}
</script>
```

### With User Activity Statistics
```vue
<template>
  <UserPicker
    v-model="selectedUserId"
    :users="users"
    :showStats="true"
    @view-user="viewUserProfile"
  />
</template>

<script setup>
import { ref } from 'vue'

const viewUserProfile = (user) => {
  router.visit(`/users/${user.id}`)
}
</script>
```

### In a Team Selection Context
```vue
<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Project Manager
      </label>
      <UserPicker
        v-model="team.project_manager_id"
        :users="managers"
        :filterFields="['name', 'email', 'role']"
        placeholder="Select project manager..."
      />
    </div>
    
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-2">
        Team Lead
      </label>
      <UserPicker
        v-model="team.team_lead_id"
        :users="leads"
        :filterFields="['name', 'email', 'role']"
        placeholder="Select team lead..."
      />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  team: Object,
  users: Array
})

const managers = computed(() => {
  return props.users.filter(user => user.role === 'manager' || user.role === 'admin')
})

const leads = computed(() => {
  return props.users.filter(user => user.role === 'lead' || user.role === 'senior')
})
</script>
```

## Features
- **User-specific defaults**: Pre-configured for user entities with role and department display
- **Role and department display**: Shows user role and department in the subtitle
- **Activity metrics**: Displays task count and project count in statistics
- **Contact information**: Shows email in the main display
- **Status indicators**: Visual status badges for user state
- **Avatar generation**: Creates colored initials avatars when no image is available
- **Quick actions**: Direct link to view user profiles
- **Search functionality**: Filter by name, email, role, or department
- **Create new**: Built-in option to add new users

## User Interface
```typescript
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
```

## Default Configuration
- Entity Type: 'user'
- Option Label: 'name'
- Option Value: 'id'
- Filter Fields: ['name', 'email', 'role', 'department']
- Default Icon: 'pi pi-users'
- Header Title: 'Select User'
- Create Button: 'New User'
- Show Balance: false (typically not needed for users)

## Dependencies
- EntityPicker component
- PrimeVue components (indirectly through EntityPicker)
- StatusBadge component

## Methods
The component exposes the following methods through template refs:
- `show()` - Open the dropdown
- `hide()` - Close the dropdown
- `focus()` - Focus the input element

## Notes
- This component is a wrapper around EntityPicker with user-specific configuration
- Uses id as the default value field but can be configured to use user_id
- The component automatically handles avatar color generation based on user name
- Statistics section shows task count and project count when enabled
- Balance display is disabled by default as it's typically not relevant for users
- Role and department information appears in the subtitle by default for users