<script setup>
import { ref, computed, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useI18n } from 'vue-i18n'
import Button from 'primevue/button'
import Card from 'primevue/card'
import Avatar from 'primevue/avatar'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Message from 'primevue/message'
import ProgressSpinner from 'primevue/progressspinner'
import Divider from 'primevue/divider'

const { t } = useI18n()
const page = usePage()

const loading = ref(false)
const users = ref([])
const selectedUser = ref(null)
const showLoginDialog = ref(false)
const loginForm = ref({
    email: '',
    password: '',
    remember: false
})
const loginError = ref('')

// Computed properties
const setupStatus = computed(() => page.props.setupStatus || {})
const isSetupComplete = computed(() => setupStatus.value?.initialized === true)
const hasUsers = computed(() => users.value.length > 0)

// Methods
const loadUsers = async () => {
    try {
        const response = await fetch('/api/v1/users')
        const data = await response.json()
        
        if (response.ok) {
            users.value = data.data || []
        }
    } catch (error) {
        console.error('Failed to load users:', error)
    }
}

const selectUser = (user) => {
    selectedUser.value = user
    showLoginDialog.value = true
    loginForm.value.email = user.email
    loginError.value = ''
}

const login = async () => {
    if (!loginForm.value.email || !loginForm.value.password) {
        loginError.value = 'Email and password are required'
        return
    }

    loading.value = true
    loginError.value = ''

    try {
        const response = await fetch('/api/v1/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            },
            body: JSON.stringify(loginForm.value)
        })

        const data = await response.json()

        if (response.ok) {
            // Redirect to dashboard or intended URL
            window.location.href = data.redirect || '/dashboard'
        } else {
            loginError.value = data.message || 'Login failed'
        }
    } catch (error) {
        loginError.value = 'Network error. Please try again.'
        console.error('Login error:', error)
    } finally {
        loading.value = false
    }
}

const createNewUser = () => {
    window.location.href = '/register'
}

const goToSetup = () => {
    window.location.href = '/setup'
}

// Lifecycle
onMounted(async () => {
    if (!isSetupComplete.value) {
        // Redirect to setup if not initialized
        goToSetup()
        return
    }

    await loadUsers()
})
</script>

<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 dark:from-gray-900 dark:to-gray-800">
        <!-- Header -->
        <div class="bg-white dark:bg-gray-800 shadow-sm border-b border-gray-200 dark:border-gray-700">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center">
                        <i class="fas fa-chart-line text-2xl text-blue-600 dark:text-blue-400 mr-3"></i>
                        <h1 class="text-xl font-semibold text-gray-900 dark:text-white">
                            {{ t('setup.title') }}
                        </h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <Button 
                            @click="goToSetup"
                            icon="fas fa-cog"
                            text
                            :label="$t('common.actions')"
                        />
                        <Button 
                            @click="createNewUser"
                            icon="fas fa-user-plus"
                            :label="$t('auth.register')"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-4xl mx-auto px-4 py-12">
            <!-- Welcome Message -->
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-4">
                    {{ t('setup.subtitle') }}
                </h2>
                <p class="text-lg text-gray-600 dark:text-gray-400">
                    {{ isSetupComplete ? t('setup.select_user') : 'Platform setup is required' }}
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="loading && !users.length" class="flex justify-center py-12">
                <ProgressSpinner />
            </div>

            <!-- No Users State -->
            <div v-else-if="!hasUsers && isSetupComplete" class="text-center py-12">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 max-w-md mx-auto">
                    <i class="fas fa-users text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                        {{ t('setup.no_accounts') }}
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        Get started by creating your first user account
                    </p>
                    <Button 
                        @click="createNewUser"
                        icon="fas fa-user-plus"
                        :label="$t('auth.register')"
                        size="large"
                    />
                </div>
            </div>

            <!-- Users Grid -->
            <div v-else-if="hasUsers" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card 
                    v-for="user in users" 
                    :key="user.id"
                    class="hover:shadow-lg transition-shadow duration-200 cursor-pointer"
                    @click="selectUser(user)"
                >
                    <template #content>
                        <div class="text-center">
                            <Avatar 
                                :label="user.name.charAt(0).toUpperCase()"
                                size="xlarge"
                                class="mb-4 mx-auto"
                                :style="{ backgroundColor: '#3B82F6', color: 'white' }"
                            />
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                                {{ user.name }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">
                                {{ user.email }}
                            </p>
                            <div class="flex items-center justify-center space-x-2 mb-4">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                    <i class="fas fa-circle text-green-400 mr-1" style="font-size: 8px;"></i>
                                    Active
                                </span>
                                <span v-if="user.companies_count" class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ user.companies_count }} {{ user.companies_count === 1 ? 'Company' : 'Companies' }}
                                </span>
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                Last login: {{ user.last_login_at ? new Date(user.last_login_at).toLocaleDateString() : 'Never' }}
                            </div>
                        </div>
                    </template>
                </Card>
            </div>

            <!-- Setup Required -->
            <div v-else class="text-center py-12">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-8 max-w-md mx-auto">
                    <i class="fas fa-cog text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">
                        Setup Required
                    </h3>
                    <p class="text-gray-600 dark:text-gray-400 mb-4">
                        The platform needs to be initialized before use
                    </p>
                    <Button 
                        @click="goToSetup"
                        icon="fas fa-cog"
                        label="Go to Setup"
                        size="large"
                    />
                </div>
            </div>
        </div>

        <!-- Login Dialog -->
        <Dialog 
            v-model:visible="showLoginDialog" 
            modal 
            :header="t('auth.login')"
            :style="{ width: '450px' }"
        >
            <div v-if="selectedUser" class="space-y-4">
                <div class="flex items-center space-x-3 mb-4">
                    <Avatar 
                        :label="selectedUser.name.charAt(0).toUpperCase()"
                        :style="{ backgroundColor: '#3B82F6', color: 'white' }"
                    />
                    <div>
                        <h4 class="font-medium text-gray-900 dark:text-white">
                            {{ selectedUser.name }}
                        </h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ selectedUser.email }}
                        </p>
                    </div>
                </div>

                <Divider />

                <Message v-if="loginError" severity="error" :closable="false">
                    {{ loginError }}
                </Message>

                <div class="space-y-4">
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ t('auth.email') }}
                        </label>
                        <InputText 
                            id="email"
                            v-model="loginForm.email"
                            type="email"
                            class="w-full"
                            :disabled="loading"
                        />
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ t('auth.password') }}
                        </label>
                        <Password 
                            id="password"
                            v-model="loginForm.password"
                            :feedback="false"
                            class="w-full"
                            :disabled="loading"
                            @keyup.enter="login"
                        />
                    </div>
                </div>
            </div>

            <template #footer>
                <Button 
                    @click="showLoginDialog = false"
                    :label="$t('common.cancel')"
                    text
                />
                <Button 
                    @click="login"
                    :label="t('auth.login')"
                    :loading="loading"
                    icon="fas fa-sign-in-alt"
                />
            </template>
        </Dialog>
    </div>
</template>