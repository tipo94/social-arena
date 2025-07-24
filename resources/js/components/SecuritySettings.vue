<template>
  <div class="space-y-6">
    <!-- Change Password -->
    <div class="bg-white shadow rounded-lg p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-6">Change Password</h3>
      
      <form @submit.prevent="handlePasswordChange" class="space-y-4">
        <div>
          <label for="current_password" class="block text-sm font-medium text-gray-700">Current Password</label>
          <input
            id="current_password"
            v-model="passwordForm.current_password"
            type="password"
            required
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': passwordErrors.current_password }"
          />
          <p v-if="passwordErrors.current_password" class="mt-1 text-sm text-red-600">
            {{ passwordErrors.current_password[0] }}
          </p>
        </div>

        <div>
          <label for="new_password" class="block text-sm font-medium text-gray-700">New Password</label>
          <input
            id="new_password"
            v-model="passwordForm.password"
            type="password"
            required
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': passwordErrors.password }"
          />
          <p v-if="passwordErrors.password" class="mt-1 text-sm text-red-600">
            {{ passwordErrors.password[0] }}
          </p>
        </div>

        <div>
          <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
          <input
            id="password_confirmation"
            v-model="passwordForm.password_confirmation"
            type="password"
            required
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': passwordErrors.password_confirmation }"
          />
          <p v-if="passwordErrors.password_confirmation" class="mt-1 text-sm text-red-600">
            {{ passwordErrors.password_confirmation[0] }}
          </p>
        </div>

        <div v-if="passwordError" class="rounded-md bg-red-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">{{ passwordError }}</h3>
            </div>
          </div>
        </div>

        <div v-if="passwordSuccess" class="rounded-md bg-green-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-green-800">{{ passwordSuccess }}</h3>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
                      <button
              type="submit"
              :disabled="authStore.isLoading"
              class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <svg 
                v-if="authStore.isLoading"
                class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" 
                fill="none" 
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              {{ authStore.isLoading ? 'Changing Password...' : 'Change Password' }}
          </button>
        </div>
      </form>
    </div>

    <!-- Active Sessions -->
    <div class="bg-white shadow rounded-lg p-6">
      <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-medium text-gray-900">Active Sessions</h3>
                  <button
            @click="logoutAllSessions"
            :disabled="authStore.isLoading"
            class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50"
          >
            {{ authStore.isLoading ? 'Logging Out...' : 'Log Out All Sessions' }}
        </button>
      </div>
      
      <div class="space-y-4">
        <div class="flex items-center justify-between p-4 border border-gray-200 rounded-lg">
          <div class="flex items-center">
            <div class="flex-shrink-0">
              <div class="w-3 h-3 bg-green-400 rounded-full"></div>
            </div>
            <div class="ml-4">
              <p class="text-sm font-medium text-gray-900">Current Session</p>
              <p class="text-sm text-gray-500">This device • Active now</p>
            </div>
          </div>
        </div>
        
        <p class="text-sm text-gray-500">
          You're currently logged in on this device. Other active sessions will be shown here when available.
        </p>
      </div>
    </div>

    <!-- Account Deletion -->
    <div class="bg-white shadow rounded-lg p-6">
      <h3 class="text-lg font-medium text-gray-900 mb-4">Delete Account</h3>
      
      <div class="bg-red-50 border border-red-200 rounded-md p-4 mb-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">
              This action cannot be undone
            </h3>
            <div class="mt-2 text-sm text-red-700">
              <p>
                Deleting your account will permanently remove all your data, including your profile, posts, and messages. This action cannot be reversed.
              </p>
            </div>
          </div>
        </div>
      </div>

      <button
        @click="showDeleteModal = true"
        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
      >
        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd" />
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
        </svg>
        Delete Account
      </button>
    </div>

    <!-- Delete Account Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto">
      <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div 
          class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
          @click="showDeleteModal = false"
        ></div>

        <!-- Modal -->
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
          <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div class="sm:flex sm:items-start">
              <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.732 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
              </div>
              <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                  Delete Account
                </h3>
                <div class="mt-2">
                  <p class="text-sm text-gray-500">
                    Are you sure you want to delete your account? This action cannot be undone and will permanently remove all your data.
                  </p>
                </div>
              </div>
            </div>
          </div>
          <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
            <button
                              type="button"
                @click="handleAccountDeletion"
                :disabled="authStore.isLoading"
                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
              >
                {{ authStore.isLoading ? 'Deleting...' : 'Delete Account' }}
            </button>
            <button
              type="button"
              @click="showDeleteModal = false"
              class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { User, PasswordChangeData, ValidationErrors } from '@/types/auth'

interface Props {
  user: User
}

const props = defineProps<Props>()
const router = useRouter()
const authStore = useAuthStore()

const passwordError = ref('')
const passwordSuccess = ref('')
const passwordErrors = reactive<ValidationErrors>({})

const showDeleteModal = ref(false)

const passwordForm = reactive<PasswordChangeData>({
  current_password: '',
  password: '',
  password_confirmation: '',
})

const clearPasswordMessages = () => {
  passwordError.value = ''
  passwordSuccess.value = ''
  Object.keys(passwordErrors).forEach(key => {
    delete passwordErrors[key]
  })
}

const clearPasswordForm = () => {
  passwordForm.current_password = ''
  passwordForm.password = ''
  passwordForm.password_confirmation = ''
}

const handlePasswordChange = async () => {
  clearPasswordMessages()

  try {
    const response = await authStore.changePassword(passwordForm)

    if (response.success) {
      passwordSuccess.value = response.message || 'Password changed successfully!'
      clearPasswordForm()
      
      setTimeout(() => {
        passwordSuccess.value = ''
      }, 3000)
    } else {
      if (response.errors) {
        Object.assign(passwordErrors, response.errors)
      }
      passwordError.value = response.message || 'Failed to change password. Please try again.'
    }
  } catch (error) {
    console.error('Password change error:', error)
    passwordError.value = 'An unexpected error occurred. Please try again.'
  }
}

const logoutAllSessions = async () => {
  try {
    const response = await authStore.logout(true) // logout all sessions

    if (response.success) {
      // Redirect to login page after logging out all sessions
      router.push('/auth/login')
    } else {
      console.error('Failed to logout all sessions:', response.message)
    }
  } catch (error) {
    console.error('Logout all sessions error:', error)
  }
}

const handleAccountDeletion = () => {
  // Redirect to account deletion page instead of handling here
  // This allows for more comprehensive deletion flow with password confirmation
  router.push('/account/delete')
}
</script> 