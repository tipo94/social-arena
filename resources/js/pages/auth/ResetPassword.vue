<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="mx-auto h-12 w-auto flex justify-center">
          <h1 class="text-3xl font-bold text-gray-900">AI-Book</h1>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Reset your password
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Enter your new password below
        </p>
      </div>

      <form class="mt-8 space-y-6" @submit.prevent="handleResetPassword">
        <div class="rounded-md shadow-sm -space-y-px">
          <div>
            <label for="email" class="sr-only">Email address</label>
            <input
              id="email"
              v-model="formData.email"
              name="email"
              type="email"
              autocomplete="email"
              required
              readonly
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm bg-gray-50"
              placeholder="Email address"
            />
          </div>
          
          <div>
            <label for="password" class="sr-only">New Password</label>
            <input
              id="password"
              v-model="formData.password"
              name="password"
              type="password"
              autocomplete="new-password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-300': errors.password }"
              placeholder="New password"
            />
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">
              {{ errors.password[0] }}
            </p>
          </div>

          <div>
            <label for="password_confirmation" class="sr-only">Confirm New Password</label>
            <input
              id="password_confirmation"
              v-model="formData.password_confirmation"
              name="password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-300': errors.password_confirmation }"
              placeholder="Confirm new password"
            />
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">
              {{ errors.password_confirmation[0] }}
            </p>
          </div>
        </div>

        <!-- Success message -->
        <div v-if="successMessage" class="rounded-md bg-green-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-green-800">
                {{ successMessage }}
              </h3>
              <p class="mt-2 text-sm text-green-700">
                You can now sign in with your new password.
              </p>
            </div>
          </div>
        </div>

        <!-- Error message -->
        <div v-if="generalError" class="rounded-md bg-red-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-red-800">
                {{ generalError }}
              </h3>
            </div>
          </div>
        </div>

        <!-- Invalid token error -->
        <div v-if="invalidToken" class="rounded-md bg-yellow-50 p-4">
          <div class="flex">
            <div class="flex-shrink-0">
              <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3">
              <h3 class="text-sm font-medium text-yellow-800">
                Invalid or expired reset link
              </h3>
              <p class="mt-2 text-sm text-yellow-700">
                This password reset link is invalid or has expired. Please request a new one.
              </p>
            </div>
          </div>
        </div>

        <div v-if="!invalidToken">
          <button
            type="submit"
            :disabled="isLoading || !!successMessage"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
              <svg 
                v-if="!isLoading"
                class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" 
                fill="currentColor" 
                viewBox="0 0 20 20"
              >
                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
              </svg>
              
              <svg 
                v-else
                class="animate-spin h-5 w-5 text-indigo-500" 
                fill="none" 
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
            </span>
            {{ isLoading ? 'Resetting Password...' : 'Reset Password' }}
          </button>
        </div>

        <div class="text-center space-y-2">
          <router-link 
            v-if="successMessage"
            to="/auth/login" 
            class="font-medium text-indigo-600 hover:text-indigo-500"
          >
            Sign in now
          </router-link>
          
          <template v-else>
            <router-link 
              to="/auth/login" 
              class="font-medium text-indigo-600 hover:text-indigo-500"
            >
              Back to login
            </router-link>
            
            <span class="mx-2 text-gray-400">•</span>
            
            <router-link 
              to="/auth/forgot-password" 
              class="font-medium text-indigo-600 hover:text-indigo-500"
            >
              Request new reset link
            </router-link>
          </template>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import authService from '@/services/authService'
import type { ValidationErrors } from '@/types/auth'

const route = useRoute()
const router = useRouter()

const isLoading = ref(false)
const generalError = ref('')
const successMessage = ref('')
const invalidToken = ref(false)
const errors = reactive<ValidationErrors>({})

const formData = reactive({
  email: '',
  token: '',
  password: '',
  password_confirmation: '',
})

const clearMessages = () => {
  Object.keys(errors).forEach(key => {
    delete errors[key]
  })
  generalError.value = ''
  successMessage.value = ''
}

const handleResetPassword = async () => {
  clearMessages()
  isLoading.value = true

  try {
    const response = await authService.resetPassword(formData)

    if (response.success) {
      successMessage.value = response.message || 'Your password has been reset successfully!'
      
      // Redirect to login after 3 seconds
      setTimeout(() => {
        router.push('/auth/login')
      }, 3000)
    } else {
      if (response.errors) {
        Object.assign(errors, response.errors)
      }
      generalError.value = response.message || 'Failed to reset password. Please try again.'
      
      // Check if token is invalid
      if (response.message?.includes('token') || response.message?.includes('expired')) {
        invalidToken.value = true
      }
    }
  } catch (error) {
    console.error('Reset password error:', error)
    generalError.value = 'An unexpected error occurred. Please try again.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  // Get email and token from query parameters
  formData.email = route.query.email as string || ''
  formData.token = route.query.token as string || ''

  // Validate that we have the required parameters
  if (!formData.email || !formData.token) {
    invalidToken.value = true
    generalError.value = 'Invalid reset link. Missing email or token.'
  }
})
</script> 