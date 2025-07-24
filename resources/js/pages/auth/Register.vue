<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
      <div>
        <div class="mx-auto h-12 w-auto flex justify-center">
          <h1 class="text-3xl font-bold text-gray-900">AI-Book</h1>
        </div>
        <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
          Create your account
        </h2>
        <p class="mt-2 text-center text-sm text-gray-600">
          Or
          <router-link 
            to="/auth/login" 
            class="font-medium text-indigo-600 hover:text-indigo-500"
          >
            sign in to your existing account
          </router-link>
        </p>
      </div>

      <form class="mt-8 space-y-6" @submit.prevent="handleRegister">
        <div class="space-y-4">
          <!-- Name Field -->
          <div>
            <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
            <input
              id="name"
              v-model="formData.name"
              name="name"
              type="text"
              autocomplete="name"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-300': errors.name }"
              placeholder="Enter your full name"
            />
            <p v-if="errors.name" class="mt-1 text-sm text-red-600">
              {{ errors.name[0] }}
            </p>
          </div>

          <!-- Email Field -->
          <div>
            <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
            <input
              id="email"
              v-model="formData.email"
              name="email"
              type="email"
              autocomplete="email"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-300': errors.email }"
              placeholder="Enter your email address"
            />
            <p v-if="errors.email" class="mt-1 text-sm text-red-600">
              {{ errors.email[0] }}
            </p>
            <p v-if="availabilityChecks.email.checking" class="mt-1 text-sm text-gray-500">
              Checking email availability...
            </p>
            <p v-if="availabilityChecks.email.message" class="mt-1 text-sm text-gray-500">
              {{ availabilityChecks.email.message }}
            </p>
          </div>

          <!-- Username Field -->
          <div>
            <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
            <input
              id="username"
              v-model="formData.username"
              name="username"
              type="text"
              autocomplete="username"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-300': errors.username }"
              placeholder="Choose a username"
            />
            <p v-if="errors.username" class="mt-1 text-sm text-red-600">
              {{ errors.username[0] }}
            </p>
            <p v-if="availabilityChecks.username.checking" class="mt-1 text-sm text-gray-500">
              Checking username availability...
            </p>
            <p v-if="availabilityChecks.username.message" class="mt-1 text-sm text-gray-500">
              {{ availabilityChecks.username.message }}
            </p>
          </div>

          <!-- First Name and Last Name -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
              <input
                id="first_name"
                v-model="formData.first_name"
                name="first_name"
                type="text"
                autocomplete="given-name"
                class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                :class="{ 'border-red-300': errors.first_name }"
                placeholder="First name"
              />
              <p v-if="errors.first_name" class="mt-1 text-sm text-red-600">
                {{ errors.first_name[0] }}
              </p>
            </div>

            <div>
              <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name</label>
              <input
                id="last_name"
                v-model="formData.last_name"
                name="last_name"
                type="text"
                autocomplete="family-name"
                class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
                :class="{ 'border-red-300': errors.last_name }"
                placeholder="Last name"
              />
              <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">
                {{ errors.last_name[0] }}
              </p>
            </div>
          </div>

          <!-- Password Field -->
          <div>
            <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
            <input
              id="password"
              v-model="formData.password"
              name="password"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-300': errors.password }"
              placeholder="Create a strong password"
            />
            <p v-if="errors.password" class="mt-1 text-sm text-red-600">
              {{ errors.password[0] }}
            </p>
          </div>

          <!-- Password Confirmation Field -->
          <div>
            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
            <input
              id="password_confirmation"
              v-model="formData.password_confirmation"
              name="password_confirmation"
              type="password"
              autocomplete="new-password"
              required
              class="mt-1 appearance-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-md focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm"
              :class="{ 'border-red-300': errors.password_confirmation }"
              placeholder="Confirm your password"
            />
            <p v-if="errors.password_confirmation" class="mt-1 text-sm text-red-600">
              {{ errors.password_confirmation[0] }}
            </p>
          </div>

          <!-- Terms of Service Acceptance -->
          <div class="flex items-center">
            <input
              id="terms_accepted"
              v-model="formData.terms_accepted"
              name="terms_accepted"
              type="checkbox"
              required
              class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              :class="{ 'border-red-300': errors.terms_accepted }"
            />
            <label for="terms_accepted" class="ml-2 block text-sm text-gray-900">
              I agree to the 
              <a href="/terms" target="_blank" class="text-indigo-600 hover:text-indigo-500">Terms of Service</a>
            </label>
          </div>
          <p v-if="errors.terms_accepted" class="mt-1 text-sm text-red-600">
            {{ errors.terms_accepted[0] }}
          </p>

          <!-- Privacy Policy Acceptance -->
          <div class="flex items-center">
            <input
              id="privacy_accepted"
              v-model="formData.privacy_accepted"
              name="privacy_accepted"
              type="checkbox"
              required
              class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded"
              :class="{ 'border-red-300': errors.privacy_accepted }"
            />
            <label for="privacy_accepted" class="ml-2 block text-sm text-gray-900">
              I agree to the 
              <a href="/privacy" target="_blank" class="text-indigo-600 hover:text-indigo-500">Privacy Policy</a>
            </label>
          </div>
          <p v-if="errors.privacy_accepted" class="mt-1 text-sm text-red-600">
            {{ errors.privacy_accepted[0] }}
          </p>
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

        <div>
          <button
            type="submit"
            :disabled="authStore.isLoading"
            class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span class="absolute left-0 inset-y-0 flex items-center pl-3">
              <svg 
                v-if="!authStore.isLoading"
                class="h-5 w-5 text-indigo-500 group-hover:text-indigo-400" 
                fill="currentColor" 
                viewBox="0 0 20 20"
              >
                <path d="M8 9a3 3 0 100-6 3 3 0 000 6zM8 11a6 6 0 016 6H2a6 6 0 016-6zM16 7a1 1 0 10-2 0v1h-1a1 1 0 100 2h1v1a1 1 0 102 0v-1h1a1 1 0 100-2h-1V7z" />
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
            {{ authStore.isLoading ? 'Creating Account...' : 'Create Account' }}
          </button>
        </div>

        <!-- Divider -->
        <div class="mt-6">
          <div class="relative">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-300" />
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-gray-50 text-gray-500">Or sign up with</span>
            </div>
          </div>

          <div class="mt-6 grid grid-cols-2 gap-3">
            <button
              type="button"
              @click="handleSocialLogin('google')"
              class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
            >
              <span class="sr-only">Sign up with Google</span>
              <svg class="w-5 h-5" viewBox="0 0 24 24">
                <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
              </svg>
            </button>

            <button
              type="button"
              @click="handleSocialLogin('github')"
              class="w-full inline-flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm bg-white text-sm font-medium text-gray-500 hover:bg-gray-50"
            >
              <span class="sr-only">Sign up with GitHub</span>
              <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 0C4.477 0 0 4.484 0 10.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.531 1.032 1.531 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0110 4.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.203 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.942.359.31.678.921.678 1.856 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0020 10.017C20 4.484 15.522 0 10 0z" clip-rule="evenodd" />
              </svg>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch } from 'vue'
import { debounce } from 'lodash-es'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import authService from '@/services/authService'
import type { RegisterData, ValidationErrors } from '@/types/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const generalError = ref('')
const errors = reactive<ValidationErrors>({})
const availabilityChecks = reactive({
  email: { checking: false, available: null as boolean | null, message: '' },
  username: { checking: false, available: null as boolean | null, message: '' }
})

const formData = reactive<RegisterData>({
  name: '',
  email: '',
  username: '',
  password: '',
  password_confirmation: '',
  first_name: '',
  last_name: '',
  privacy_accepted: false,
  terms_accepted: false,
})

const clearErrors = () => {
  Object.keys(errors).forEach(key => {
    delete errors[key]
  })
  generalError.value = ''
}

const clearAvailabilityCheck = (field: 'email' | 'username') => {
  availabilityChecks[field].available = null
  availabilityChecks[field].message = ''
}

const checkEmailAvailability = debounce(async (email: string) => {
  if (!email || !email.includes('@')) return
  
  availabilityChecks.email.checking = true
  try {
    const response = await authService.checkEmail(email)
    if (response.success && response.data) {
      availabilityChecks.email.available = response.data.available
      availabilityChecks.email.message = response.data.available 
        ? 'Email is available' 
        : 'Email is already taken'
    }
  } catch (error) {
    console.error('Email availability check error:', error)
  } finally {
    availabilityChecks.email.checking = false
  }
}, 500)

const checkUsernameAvailability = debounce(async (username: string) => {
  if (!username || username.length < 3) return
  
  availabilityChecks.username.checking = true
  try {
    const response = await authService.checkUsername(username)
    if (response.success && response.data) {
      availabilityChecks.username.available = response.data.available
      availabilityChecks.username.message = response.data.available 
        ? 'Username is available' 
        : 'Username is already taken'
    }
  } catch (error) {
    console.error('Username availability check error:', error)
  } finally {
    availabilityChecks.username.checking = false
  }
}, 500)

// Watch for changes to trigger availability checks
watch(() => formData.email, (newEmail) => {
  clearAvailabilityCheck('email')
  if (newEmail) {
    checkEmailAvailability(newEmail)
  }
})

watch(() => formData.username, (newUsername) => {
  clearAvailabilityCheck('username')
  if (newUsername) {
    checkUsernameAvailability(newUsername)
  }
})

const handleRegister = async () => {
  clearErrors()

  try {
    const response = await authStore.register(formData)

    if (response.success && response.user) {
      // Get redirect path from query or default to dashboard
      const redirectPath = route.query.redirect as string || '/dashboard'
      router.push(redirectPath)
    } else {
      if (response.errors) {
        Object.assign(errors, response.errors)
      }
      generalError.value = response.message || 'Registration failed. Please try again.'
    }
  } catch (error) {
    console.error('Registration error:', error)
    generalError.value = 'An unexpected error occurred. Please try again.'
  }
}

const handleSocialLogin = (provider: 'google' | 'github') => {
  const loginUrl = `${import.meta.env.VITE_API_URL || '/api'}/auth/social/redirect/${provider}`
  window.location.href = loginUrl
}
</script> 