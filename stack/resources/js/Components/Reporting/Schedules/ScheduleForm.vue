<template>
  <div class="space-y-6">
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Basic Information -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Schedule Name *
          </label>
          <InputText
            v-model="form.name"
            placeholder="Enter schedule name"
            :class="{ 'p-invalid': errors.name }"
            class="w-full"
          />
          <small v-if="errors.name" class="text-red-500">{{ errors.name }}</small>
        </div>
        
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Template *
          </label>
          <Dropdown
            v-model="form.template_id"
            :options="templates"
            optionLabel="name"
            optionValue="template_id"
            placeholder="Select template"
            :class="{ 'p-invalid': errors.template_id }"
            class="w-full"
            @change="onTemplateChange"
          />
          <small v-if="errors.template_id" class="text-red-500">{{ errors.template_id }}</small>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
          Description
        </label>
        <Textarea
          v-model="form.description"
          placeholder="Enter schedule description"
          :rows="3"
          class="w-full"
        />
      </div>

      <!-- Schedule Settings -->
      <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          Schedule Configuration
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Frequency *
            </label>
            <Dropdown
              v-model="form.frequency"
              :options="frequencyOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Select frequency"
              :class="{ 'p-invalid': errors.frequency }"
              class="w-full"
              @change="onFrequencyChange"
            />
            <small v-if="errors.frequency" class="text-red-500">{{ errors.frequency }}</small>
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Timezone *
            </label>
            <Dropdown
              v-model="form.timezone"
              :options="timezoneOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Select timezone"
              :class="{ 'p-invalid': errors.timezone }"
              class="w-full"
              filter
            />
            <small v-if="errors.timezone" class="text-red-500">{{ errors.timezone }}</small>
          </div>
        </div>

        <!-- Custom Cron Expression -->
        <div v-if="form.frequency === 'custom'" class="mt-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Cron Expression *
          </label>
          <InputText
            v-model="form.custom_cron"
            placeholder="0 2 * * 1"
            :class="{ 'p-invalid': errors.custom_cron }"
            class="w-full"
          />
          <small class="text-gray-500">
            Format: minute hour day month weekday. Example: "0 2 * * 1" = Every Monday at 2 AM
          </small>
          <small v-if="errors.custom_cron" class="text-red-500">{{ errors.custom_cron }}</small>
        </div>

        <!-- Run Time for non-custom frequencies -->
        <div v-else class="mt-4">
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Run Time *
          </label>
          <Calendar
            v-model="form.run_time"
            :showTime="true"
            :showSeconds="false"
            :timeOnly="true"
            placeholder="Select run time"
            :class="{ 'p-invalid': errors.run_time }"
            class="w-full"
          />
          <small v-if="errors.run_time" class="text-red-500">{{ errors.run_time }}</small>
        </div>
      </div>

      <!-- Report Parameters -->
      <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          Report Parameters
        </h3>
        
        <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4 space-y-4">
          <!-- Date Range Type -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Date Range Type *
            </label>
            <Dropdown
              v-model="form.parameters.date_range_type"
              :options="dateRangeTypeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Select date range"
              :class="{ 'p-invalid': errors['parameters.date_range_type'] }"
              class="w-full"
              @change="onDateRangeTypeChange"
            />
            <small v-if="errors['parameters.date_range_type']" class="text-red-500">
              {{ errors['parameters.date_range_type'] }}
            </small>
          </div>

          <!-- Custom Date Range -->
          <div v-if="form.parameters.date_range_type === 'custom'">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Start Date *
                </label>
                <Calendar
                  v-model="form.parameters.date_range.start"
                  dateFormat="yy-mm-dd"
                  placeholder="Select start date"
                  :class="{ 'p-invalid': errors['parameters.date_range.start'] }"
                  class="w-full"
                />
                <small v-if="errors['parameters.date_range.start']" class="text-red-500">
                  {{ errors['parameters.date_range.start'] }}
                </small>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  End Date *
                </label>
                <Calendar
                  v-model="form.parameters.date_range.end"
                  dateFormat="yy-mm-dd"
                  placeholder="Select end date"
                  :class="{ 'p-invalid': errors['parameters.date_range.end'] }"
                  class="w-full"
                />
                <small v-if="errors['parameters.date_range.end']" class="text-red-500">
                  {{ errors['parameters.date_range.end'] }}
                </small>
              </div>
            </div>
          </div>

          <!-- Export Format -->
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Export Format *
            </label>
            <Dropdown
              v-model="form.parameters.export_format"
              :options="exportFormatOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="Select export format"
              :class="{ 'p-invalid': errors['parameters.export_format'] }"
              class="w-full"
            />
            <small v-if="errors['parameters.export_format']" class="text-red-500">
              {{ errors['parameters.export_format'] }}
            </small>
          </div>
        </div>
      </div>

      <!-- Delivery Configuration -->
      <div>
        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">
          Delivery Configuration
        </h3>
        
        <div class="space-y-4">
          <div v-for="(channel, index) in form.delivery_channels" :key="index" class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
            <div class="flex items-center justify-between mb-4">
              <h4 class="font-medium text-gray-900 dark:text-white">Delivery Channel {{ index + 1 }}</h4>
              <Button
                icon="pi pi-trash"
                size="small"
                severity="danger"
                @click="removeDeliveryChannel(index)"
                v-if="form.delivery_channels.length > 1"
              />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Channel Type *
                </label>
                <Dropdown
                  v-model="channel.type"
                  :options="deliveryChannelOptions"
                  optionLabel="label"
                  optionValue="value"
                  placeholder="Select channel type"
                  class="w-full"
                  @change="onDeliveryChannelTypeChange(index)"
                />
              </div>
            </div>

            <!-- Email Configuration -->
            <div v-if="channel.type === 'email'" class="mt-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Recipients *
                </label>
                <Chips
                  v-model="channel.recipients"
                  placeholder="Add email addresses"
                  class="w-full"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Subject
                </label>
                <InputText
                  v-model="channel.subject"
                  placeholder="Report is ready"
                  class="w-full"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Message
                </label>
                <Textarea
                  v-model="channel.message"
                  placeholder="Your scheduled report is attached."
                  :rows="3"
                  class="w-full"
                />
              </div>
              
              <div class="flex items-center">
                <Checkbox
                  v-model="channel.include_download_link"
                  binary
                  inputId="includeDownloadLink"
                />
                <label for="includeDownloadLink" class="ml-2">Include Download Link</label>
              </div>
            </div>

            <!-- SFTP Configuration -->
            <div v-else-if="channel.type === 'sftp'" class="mt-4 space-y-4">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Host *
                  </label>
                  <InputText
                    v-model="channel.host"
                    placeholder="sftp.example.com"
                    class="w-full"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Port
                  </label>
                  <InputNumber
                    v-model="channel.port"
                    :min="1"
                    :max="65535"
                    placeholder="22"
                    class="w-full"
                  />
                </div>
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Username *
                  </label>
                  <InputText
                    v-model="channel.username"
                    placeholder="username"
                    class="w-full"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Password *
                  </label>
                  <Password
                    v-model="channel.password"
                    placeholder="password"
                    class="w-full"
                    :feedback="false"
                  />
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Remote Path
                </label>
                <InputText
                  v-model="channel.path"
                  placeholder="/reports/"
                  class="w-full"
                />
              </div>
            </div>

            <!-- Webhook Configuration -->
            <div v-else-if="channel.type === 'webhook'" class="mt-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Webhook URL *
                </label>
                <InputText
                  v-model="channel.url"
                  placeholder="https://api.example.com/webhook"
                  class="w-full"
                />
              </div>
              
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Method
                  </label>
                  <Dropdown
                    v-model="channel.method"
                    :options="webhookMethodOptions"
                    optionLabel="label"
                    optionValue="value"
                    class="w-full"
                  />
                </div>
                
                <div>
                  <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                    Timeout (seconds)
                  </label>
                  <InputNumber
                    v-model="channel.timeout"
                    :min="5"
                    :max="300"
                    placeholder="30"
                    class="w-full"
                  />
                </div>
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Retry Count
                </label>
                <InputNumber
                  v-model="channel.retry_count"
                  :min="0"
                  :max="10"
                  placeholder="3"
                  class="w-full"
                />
              </div>
            </div>

            <!-- In-App Configuration -->
            <div v-else-if="channel.type === 'in_app'" class="mt-4 space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Notification Type
                </label>
                <Dropdown
                  v-model="channel.notification_type"
                  :options="notificationTypeOptions"
                  optionLabel="label"
                  optionValue="value"
                  class="w-full"
                />
              </div>
              
              <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                  Message
                </label>
                <Textarea
                  v-model="channel.message"
                  placeholder="Report generated successfully"
                  :rows="3"
                  class="w-full"
                />
              </div>
            </div>
          </div>

          <!-- Add Delivery Channel Button -->
          <Button
            icon="pi pi-plus"
            label="Add Delivery Channel"
            severity="secondary"
            @click="addDeliveryChannel"
            class="w-full"
          />
        </div>
      </div>

      <!-- Form Actions -->
      <div class="flex justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
        <Button
          label="Cancel"
          severity="secondary"
          @click="$emit('cancel')"
          :disabled="loading"
        />
        
        <Button
          label="Save"
          type="submit"
          :loading="loading"
          :disabled="!isFormValid"
        />
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useToast } from 'primevue/usetoast'

const props = defineProps({
  schedule: {
    type: Object,
    default: null
  },
  templates: {
    type: Array,
    default: () => []
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['save', 'cancel'])

const toast = useToast()

// Form data
const form = ref({
  name: '',
  description: '',
  template_id: '',
  frequency: '',
  timezone: '',
  custom_cron: '',
  run_time: null,
  parameters: {
    date_range_type: 'current_month',
    date_range: {
      start: null,
      end: null
    },
    export_format: 'json',
    filters: {}
  },
  delivery_channels: [
    {
      type: 'email',
      recipients: [],
      subject: '',
      message: '',
      include_download_link: true,
      host: '',
      port: 22,
      username: '',
      password: '',
      path: '/reports/',
      url: '',
      method: 'POST',
      timeout: 30,
      retry_count: 3,
      notification_type: 'info'
    }
  ]
})

const errors = ref({})

// Options
const frequencyOptions = ref([
  { label: 'Daily', value: 'daily' },
  { label: 'Weekly', value: 'weekly' },
  { label: 'Monthly', value: 'monthly' },
  { label: 'Quarterly', value: 'quarterly' },
  { label: 'Yearly', value: 'yearly' },
  { label: 'Custom', value: 'custom' }
])

const timezoneOptions = ref([
  { label: 'UTC', value: 'UTC' },
  { label: 'Eastern Time (ET)', value: 'America/New_York' },
  { label: 'Central Time (CT)', value: 'America/Chicago' },
  { label: 'Mountain Time (MT)', value: 'America/Denver' },
  { label: 'Pacific Time (PT)', value: 'America/Los_Angeles' },
  { label: 'European Central Time (CET)', value: 'Europe/Paris' },
  { label: 'British Summer Time (BST)', value: 'Europe/London' },
  { label: 'Japan Standard Time (JST)', value: 'Asia/Tokyo' },
  { label: 'Australian Eastern Time (AET)', value: 'Australia/Sydney' }
])

const dateRangeTypeOptions = ref([
  { label: 'Current Month', value: 'current_month' },
  { label: 'Last Month', value: 'last_month' },
  { label: 'Current Quarter', value: 'current_quarter' },
  { label: 'Last Quarter', value: 'last_quarter' },
  { label: 'Current Year', value: 'current_year' },
  { label: 'Last Year', value: 'last_year' },
  { label: 'Custom Range', value: 'custom' }
])

const exportFormatOptions = ref([
  { label: 'JSON', value: 'json' },
  { label: 'PDF', value: 'pdf' },
  { label: 'Excel', value: 'xlsx' },
  { label: 'CSV', value: 'csv' }
])

const deliveryChannelOptions = ref([
  { label: 'Email', value: 'email' },
  { label: 'SFTP', value: 'sftp' },
  { label: 'Webhook', value: 'webhook' },
  { label: 'In-App Notification', value: 'in_app' }
])

const webhookMethodOptions = ref([
  { label: 'POST', value: 'POST' },
  { label: 'PUT', value: 'PUT' },
  { label: 'PATCH', value: 'PATCH' }
])

const notificationTypeOptions = ref([
  { label: 'Info', value: 'info' },
  { label: 'Success', value: 'success' },
  { label: 'Warning', value: 'warning' },
  { label: 'Error', value: 'error' }
])

// Computed
const isFormValid = computed(() => {
  return form.value.name.trim() !== '' &&
         form.value.template_id !== '' &&
         form.value.frequency !== '' &&
         form.value.timezone !== '' &&
         (form.value.frequency !== 'custom' || form.value.custom_cron.trim() !== '') &&
         form.value.parameters.date_range_type !== '' &&
         form.value.parameters.export_format !== '' &&
         form.value.delivery_channels.length > 0 &&
         form.value.delivery_channels.every(channel => channel.type !== '')
})

// Methods
const initializeForm = () => {
  if (props.schedule) {
    form.value = {
      ...props.schedule,
      parameters: {
        date_range_type: 'current_month',
        date_range: {
          start: null,
          end: null
        },
        export_format: 'json',
        filters: {},
        ...props.schedule.parameters
      },
      delivery_channels: props.schedule.delivery_channels?.length > 0 
        ? [...props.schedule.delivery_channels]
        : [form.value.delivery_channels[0]]
    }
  } else {
    // Reset to defaults for new schedule
    form.value = {
      name: '',
      description: '',
      template_id: '',
      frequency: '',
      timezone: 'UTC',
      custom_cron: '',
      run_time: null,
      parameters: {
        date_range_type: 'current_month',
        date_range: {
          start: null,
          end: null
        },
        export_format: 'json',
        filters: {}
      },
      delivery_channels: [
        {
          type: 'email',
          recipients: [],
          subject: '',
          message: '',
          include_download_link: true,
          host: '',
          port: 22,
          username: '',
          password: '',
          path: '/reports/',
          url: '',
          method: 'POST',
          timeout: 30,
          retry_count: 3,
          notification_type: 'info'
        }
      ]
    }
  }
  errors.value = {}
}

const onTemplateChange = () => {
  // Reset parameters when template changes
  form.value.parameters.filters = {}
}

const onFrequencyChange = () => {
  // Reset custom cron when frequency changes away from custom
  if (form.value.frequency !== 'custom') {
    form.value.custom_cron = ''
  }
}

const onDateRangeTypeChange = () => {
  // Reset custom date range when type changes away from custom
  if (form.value.parameters.date_range_type !== 'custom') {
    form.value.parameters.date_range = {
      start: null,
      end: null
    }
  }
}

const onDeliveryChannelTypeChange = (index) => {
  // Reset type-specific fields when channel type changes
  const channel = form.value.delivery_channels[index]
  
  // Reset all fields
  Object.keys(channel).forEach(key => {
    if (key !== 'type') {
      const defaultValue = getDefaultChannelValue(key)
      channel[key] = defaultValue
    }
  })
}

const getDefaultChannelValue = (key) => {
  const defaults = {
    recipients: [],
    subject: '',
    message: '',
    include_download_link: true,
    host: '',
    port: 22,
    username: '',
    password: '',
    path: '/reports/',
    url: '',
    method: 'POST',
    timeout: 30,
    retry_count: 3,
    notification_type: 'info'
  }
  return defaults[key] || ''
}

const addDeliveryChannel = () => {
  form.value.delivery_channels.push({
    type: 'email',
    recipients: [],
    subject: '',
    message: '',
    include_download_link: true,
    host: '',
    port: 22,
    username: '',
    password: '',
    path: '/reports/',
    url: '',
    method: 'POST',
    timeout: 30,
    retry_count: 3,
    notification_type: 'info'
  })
}

const removeDeliveryChannel = (index) => {
  if (form.value.delivery_channels.length > 1) {
    form.value.delivery_channels.splice(index, 1)
  }
}

const validateForm = () => {
  errors.value = {}
  
  if (!form.value.name.trim()) {
    errors.value.name = 'Schedule name is required'
  }
  
  if (!form.value.template_id) {
    errors.value.template_id = 'Template is required'
  }
  
  if (!form.value.frequency) {
    errors.value.frequency = 'Frequency is required'
  }
  
  if (!form.value.timezone) {
    errors.value.timezone = 'Timezone is required'
  }
  
  if (form.value.frequency === 'custom' && !form.value.custom_cron.trim()) {
    errors.value.custom_cron = 'Cron expression is required for custom frequency'
  }
  
  if (!form.value.parameters.date_range_type) {
    errors.value['parameters.date_range_type'] = 'Date range type is required'
  }
  
  if (form.value.parameters.date_range_type === 'custom') {
    if (!form.value.parameters.date_range.start) {
      errors.value['parameters.date_range.start'] = 'Start date is required'
    }
    if (!form.value.parameters.date_range.end) {
      errors.value['parameters.date_range.end'] = 'End date is required'
    }
  }
  
  if (!form.value.parameters.export_format) {
    errors.value['parameters.export_format'] = 'Export format is required'
  }
  
  // Validate delivery channels
  form.value.delivery_channels.forEach((channel, index) => {
    if (!channel.type) {
      errors.value[`delivery_channels.${index}.type`] = 'Channel type is required'
    }
    
    if (channel.type === 'email' && (!channel.recipients || channel.recipients.length === 0)) {
      errors.value[`delivery_channels.${index}.recipients`] = 'At least one recipient is required'
    }
    
    if (channel.type === 'sftp') {
      if (!channel.host) errors.value[`delivery_channels.${index}.host`] = 'Host is required'
      if (!channel.username) errors.value[`delivery_channels.${index}.username`] = 'Username is required'
      if (!channel.password) errors.value[`delivery_channels.${index}.password`] = 'Password is required'
    }
    
    if (channel.type === 'webhook' && !channel.url) {
      errors.value[`delivery_channels.${index}.url`] = 'Webhook URL is required'
    }
  })
  
  return Object.keys(errors.value).length === 0
}

const handleSubmit = () => {
  if (!validateForm()) {
    return
  }
  
  const submitData = {
    ...form.value,
    delivery_channels: form.value.delivery_channels.filter(channel => channel.type !== '')
  }
  
  emit('save', submitData)
}

// Watch for schedule prop changes
watch(() => props.schedule, () => {
  initializeForm()
}, { immediate: true })

// Lifecycle
onMounted(() => {
  initializeForm()
})
</script>