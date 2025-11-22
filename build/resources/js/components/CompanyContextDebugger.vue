<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Separator } from '@/components/ui/separator'
import { CollapsibleContent, CollapsibleTrigger, Collapsible } from '@/components/ui/collapsible'
import { useToast } from '@/components/ui/toast/use-toast'
import { ChevronDown, RefreshCcw, Bug, AlertTriangle, CheckCircle } from 'lucide-vue-next'

const props = defineProps<{
  showDebugger?: boolean
}>()

interface CompanyContextDebug {
  status: string
  data: {
    active_company: any
    user_companies: any[]
    debug_info: any
  }
}

const debugInfo = ref<CompanyContextDebug | null>(null)
const loading = ref(false)
const error = ref('')
const isOpen = ref(false)
const { toast } = useToast()

// Auto-fetch on mount if in debug mode
onMounted(() => {
  if (props.showDebugger) {
    fetchDebugInfo()
  }
})

const fetchDebugInfo = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const response = await fetch('/api/company/status', {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }
    
    debugInfo.value = await response.json()
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Unknown error occurred'
    toast({
      title: 'Debug Info Error',
      description: error.value,
      variant: 'destructive',
    })
  } finally {
    loading.value = false
  }
}

const refreshDebugInfo = () => {
  fetchDebugInfo()
  toast({
    title: 'Debug Info Refreshed',
    description: 'Company context debug information has been updated.',
  })
}

const testCompanyResolution = async (userId: string) => {
  try {
    const response = await fetch('/api/company-context/test-resolution', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      body: JSON.stringify({ user_id: userId })
    })
    
    if (!response.ok) {
      throw new Error(`Test failed: ${response.statusText}`)
    }
    
    const result = await response.json()
    
    toast({
      title: 'Resolution Test Completed',
      description: `Resolution time: ${result.resolution.resolution_time_ms}ms`,
    })
    
  } catch (e) {
    const errorMessage = e instanceof Error ? e.message : 'Unknown error'
    toast({
      title: 'Resolution Test Failed',
      description: errorMessage,
      variant: 'destructive',
    })
  }
}

const getResolutionStepStatus = (step: any) => {
  if (step.available || step.user_has_access) return 'success'
  return 'warning'
}

const getResolutionStepIcon = (status: string) => {
  switch (status) {
    case 'success': return CheckCircle
    case 'warning': return AlertTriangle
    default: return Bug
  }
}

const activeCompany = computed(() => debugInfo.value?.data?.active_company)
const userCompanies = computed(() => debugInfo.value?.data?.user_companies || [])
const resolutionSteps = computed(() => debugInfo.value?.data?.debug_info?.resolution_steps || {})
</script>

<template>
  <div v-if="showDebugger" class="fixed bottom-4 right-4 z-50">
    <Collapsible v-model:open="isOpen">
      <CollapsibleTrigger asChild>
        <Button variant="outline" size="sm" class="shadow-lg">
          <Bug class="w-4 h-4 mr-2" />
          Company Debug
          <ChevronDown class="w-4 h-4 ml-2" :class="{ 'rotate-180': isOpen }" />
        </Button>
      </CollapsibleTrigger>
      
      <CollapsibleContent class="mt-2">
        <Card class="w-96 max-h-96 overflow-y-auto shadow-xl">
          <CardHeader>
            <div class="flex items-center justify-between">
              <CardTitle class="text-sm">Company Context Debug</CardTitle>
              <Button
                variant="ghost"
                size="sm"
                @click="refreshDebugInfo"
                :disabled="loading"
              >
                <RefreshCcw class="w-3 h-3" :class="{ 'animate-spin': loading }" />
              </Button>
            </div>
            <CardDescription class="text-xs">
              Real-time company context information
            </CardDescription>
          </CardHeader>
          
          <CardContent class="space-y-3">
            <!-- Error Display -->
            <div v-if="error" class="text-xs text-red-600 bg-red-50 p-2 rounded">
              {{ error }}
            </div>
            
            <!-- Loading State -->
            <div v-if="loading" class="text-xs text-gray-500">
              Loading debug information...
            </div>
            
            <!-- Active Company -->
            <div v-if="activeCompany">
              <h4 class="text-xs font-medium mb-1">Active Company</h4>
              <div class="bg-green-50 border border-green-200 rounded p-2">
                <div class="text-xs font-medium">{{ activeCompany.name }}</div>
                <div class="text-xs text-gray-600">{{ activeCompany.id }}</div>
                <div class="text-xs text-gray-500">
                  Role: {{ activeCompany.user_role || 'N/A' }}
                </div>
              </div>
            </div>
            
            <!-- No Active Company -->
            <div v-else-if="!loading && debugInfo">
              <h4 class="text-xs font-medium mb-1">Active Company</h4>
              <div class="bg-yellow-50 border border-yellow-200 rounded p-2">
                <div class="text-xs text-yellow-800">No active company</div>
              </div>
            </div>
            
            <!-- Resolution Steps -->
            <div v-if="Object.keys(resolutionSteps).length > 0">
              <h4 class="text-xs font-medium mb-1">Resolution Steps</h4>
              <div class="space-y-1">
                <div
                  v-for="(step, stepName) in resolutionSteps"
                  :key="stepName"
                  class="flex items-center space-x-2 text-xs"
                >
                  <component
                    :is="getResolutionStepIcon(getResolutionStepStatus(step))"
                    class="w-3 h-3"
                    :class="{
                      'text-green-500': getResolutionStepStatus(step) === 'success',
                      'text-yellow-500': getResolutionStepStatus(step) === 'warning',
                    }"
                  />
                  <span class="capitalize">{{ stepName }}</span>
                  <Badge
                    variant="outline"
                    class="text-xs"
                    :class="{
                      'bg-green-50 text-green-700': getResolutionStepStatus(step) === 'success',
                      'bg-yellow-50 text-yellow-700': getResolutionStepStatus(step) === 'warning',
                    }"
                  >
                    {{ getResolutionStepStatus(step) === 'success' ? 'OK' : 'SKIP' }}
                  </Badge>
                </div>
              </div>
            </div>
            
            <!-- User Companies -->
            <div v-if="userCompanies.length > 0">
              <h4 class="text-xs font-medium mb-1">
                Available Companies ({{ userCompanies.length }})
              </h4>
              <div class="max-h-20 overflow-y-auto space-y-1">
                <div
                  v-for="company in userCompanies"
                  :key="company.id"
                  class="text-xs p-1 border rounded"
                  :class="{
                    'bg-blue-50 border-blue-200': company.id === activeCompany?.id,
                    'bg-gray-50': company.id !== activeCompany?.id,
                  }"
                >
                  <div class="font-medium">{{ company.name }}</div>
                  <div class="text-gray-500">{{ company.user_role }}</div>
                </div>
              </div>
            </div>
            
            <!-- Quick Actions -->
            <Separator />
            <div class="space-y-1">
              <Button
                variant="outline"
                size="sm"
                class="w-full text-xs h-7"
                @click="refreshDebugInfo"
                :disabled="loading"
              >
                Refresh Debug Info
              </Button>
              <Button
                variant="outline"
                size="sm"
                class="w-full text-xs h-7"
                @click="router.visit('/api/company-context/health')"
              >
                View Health Check
              </Button>
            </div>
          </CardContent>
        </Card>
      </CollapsibleContent>
    </Collapsible>
  </div>
</template>