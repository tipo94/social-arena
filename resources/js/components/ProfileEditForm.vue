<template>
  <div class="bg-white shadow rounded-lg p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-6">Edit Profile</h3>
    
    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Basic Information -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
          <label for="name" class="block text-sm font-medium text-gray-700">Full Name</label>
          <input
            id="name"
            v-model="formData.name"
            type="text"
            required
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.name }"
          />
          <p v-if="errors.name" class="mt-1 text-sm text-red-600">
            {{ errors.name[0] }}
          </p>
        </div>

        <div>
          <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
          <input
            id="username"
            v-model="formData.username"
            type="text"
            required
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.username }"
          />
          <p v-if="errors.username" class="mt-1 text-sm text-red-600">
            {{ errors.username[0] }}
          </p>
        </div>

        <div>
          <label for="first_name" class="block text-sm font-medium text-gray-700">First Name</label>
          <input
            id="first_name"
            v-model="formData.first_name"
            type="text"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.first_name }"
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
            type="text"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.last_name }"
          />
          <p v-if="errors.last_name" class="mt-1 text-sm text-red-600">
            {{ errors.last_name[0] }}
          </p>
        </div>
      </div>

      <!-- Bio -->
      <div>
        <label for="bio" class="block text-sm font-medium text-gray-700">Bio</label>
        <textarea
          id="bio"
          v-model="formData.bio"
          rows="3"
          class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
          :class="{ 'border-red-300': errors.bio }"
          placeholder="Tell us about yourself..."
        ></textarea>
        <p v-if="errors.bio" class="mt-1 text-sm text-red-600">
          {{ errors.bio[0] }}
        </p>
        <p class="mt-2 text-sm text-gray-500">
          {{ bioLength }}/500 characters
        </p>
      </div>

      <!-- Contact Information -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
          <label for="location" class="block text-sm font-medium text-gray-700">Location</label>
          <input
            id="location"
            v-model="formData.location"
            type="text"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.location }"
            placeholder="City, Country"
          />
          <p v-if="errors.location" class="mt-1 text-sm text-red-600">
            {{ errors.location[0] }}
          </p>
        </div>

        <div>
          <label for="website" class="block text-sm font-medium text-gray-700">Website</label>
          <input
            id="website"
            v-model="formData.website"
            type="url"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.website }"
            placeholder="https://example.com"
          />
          <p v-if="errors.website" class="mt-1 text-sm text-red-600">
            {{ errors.website[0] }}
          </p>
        </div>

        <div>
          <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
          <input
            id="phone"
            v-model="formData.phone"
            type="tel"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.phone }"
            placeholder="+1 (555) 123-4567"
          />
          <p v-if="errors.phone" class="mt-1 text-sm text-red-600">
            {{ errors.phone[0] }}
          </p>
        </div>

        <div>
          <label for="birth_date" class="block text-sm font-medium text-gray-700">Birth Date</label>
          <input
            id="birth_date"
            v-model="formData.birth_date"
            type="date"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.birth_date }"
          />
          <p v-if="errors.birth_date" class="mt-1 text-sm text-red-600">
            {{ errors.birth_date[0] }}
          </p>
        </div>
      </div>

      <!-- Professional Information -->
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
          <label for="occupation" class="block text-sm font-medium text-gray-700">Occupation</label>
          <input
            id="occupation"
            v-model="formData.occupation"
            type="text"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.occupation }"
            placeholder="Your job title"
          />
          <p v-if="errors.occupation" class="mt-1 text-sm text-red-600">
            {{ errors.occupation[0] }}
          </p>
        </div>

        <div>
          <label for="education" class="block text-sm font-medium text-gray-700">Education</label>
          <input
            id="education"
            v-model="formData.education"
            type="text"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.education }"
            placeholder="Your education background"
          />
          <p v-if="errors.education" class="mt-1 text-sm text-red-600">
            {{ errors.education[0] }}
          </p>
        </div>

        <div>
          <label for="gender" class="block text-sm font-medium text-gray-700">Gender</label>
          <select
            id="gender"
            v-model="formData.gender"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.gender }"
          >
            <option value="">Select gender</option>
            <option value="male">Male</option>
            <option value="female">Female</option>
            <option value="non-binary">Non-binary</option>
            <option value="other">Other</option>
            <option value="prefer-not-to-say">Prefer not to say</option>
          </select>
          <p v-if="errors.gender" class="mt-1 text-sm text-red-600">
            {{ errors.gender[0] }}
          </p>
        </div>

        <div>
          <label for="timezone" class="block text-sm font-medium text-gray-700">Timezone</label>
          <select
            id="timezone"
            v-model="formData.timezone"
            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            :class="{ 'border-red-300': errors.timezone }"
          >
            <option value="">Select timezone</option>
            <option value="UTC">UTC</option>
            <option value="America/New_York">Eastern Time</option>
            <option value="America/Chicago">Central Time</option>
            <option value="America/Denver">Mountain Time</option>
            <option value="America/Los_Angeles">Pacific Time</option>
            <option value="Europe/London">London</option>
            <option value="Europe/Paris">Paris</option>
            <option value="Asia/Tokyo">Tokyo</option>
            <!-- Add more timezones as needed -->
          </select>
          <p v-if="errors.timezone" class="mt-1 text-sm text-red-600">
            {{ errors.timezone[0] }}
          </p>
        </div>
      </div>

      <!-- Social Links -->
      <div>
        <h4 class="text-sm font-medium text-gray-700 mb-4">Social Links</h4>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div>
            <label for="social_twitter" class="block text-sm font-medium text-gray-700">Twitter</label>
            <input
              id="social_twitter"
              v-model="formData.social_links.twitter"
              type="url"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              placeholder="https://twitter.com/username"
            />
          </div>

          <div>
            <label for="social_linkedin" class="block text-sm font-medium text-gray-700">LinkedIn</label>
            <input
              id="social_linkedin"
              v-model="formData.social_links.linkedin"
              type="url"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              placeholder="https://linkedin.com/in/username"
            />
          </div>

          <div>
            <label for="social_github" class="block text-sm font-medium text-gray-700">GitHub</label>
            <input
              id="social_github"
              v-model="formData.social_links.github"
              type="url"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              placeholder="https://github.com/username"
            />
          </div>

          <div>
            <label for="social_instagram" class="block text-sm font-medium text-gray-700">Instagram</label>
            <input
              id="social_instagram"
              v-model="formData.social_links.instagram"
              type="url"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
              placeholder="https://instagram.com/username"
            />
          </div>
        </div>
      </div>

      <!-- Error Messages -->
      <div v-if="errorMessage" class="rounded-md bg-red-50 p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">
              {{ errorMessage }}
            </h3>
          </div>
        </div>
      </div>

      <!-- Success Message -->
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
          </div>
        </div>
      </div>

      <!-- Submit Button -->
      <div class="flex justify-end space-x-3">
        <button
          type="button"
          @click="resetForm"
          class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
        >
          Reset
        </button>
        
        <button
          type="submit"
          :disabled="isSubmitting"
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg 
            v-if="isSubmitting"
            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" 
            fill="none" 
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isSubmitting ? 'Saving...' : 'Save Changes' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import type { User, ProfileUpdateData, ValidationErrors } from '@/types/auth'

interface Props {
  user: User
}

const props = defineProps<Props>()
const emit = defineEmits<{
  updated: []
}>()

const authStore = useAuthStore()

const isSubmitting = ref(false)
const successMessage = ref('')
const errorMessage = ref('')
const errors = reactive<ValidationErrors>({})

const formData = reactive<ProfileUpdateData>({
  name: '',
  first_name: '',
  last_name: '',
  username: '',
  bio: '',
  location: '',
  website: '',
  birth_date: '',
  gender: '',
  occupation: '',
  education: '',
  phone: '',
  social_links: {}
})

const hasChanges = computed(() => {
  const profile = props.user.profile
  return (
    formData.name !== props.user.name ||
    formData.first_name !== props.user.first_name ||
    formData.last_name !== props.user.last_name ||
    formData.username !== props.user.username ||
    formData.bio !== (profile?.bio || '') ||
    formData.location !== (profile?.location || '') ||
    formData.website !== (profile?.website || '') ||
    formData.birth_date !== (profile?.birth_date || '') ||
    formData.gender !== (profile?.gender || '') ||
    formData.occupation !== (profile?.occupation || '') ||
    formData.education !== (profile?.education || '') ||
    formData.phone !== (profile?.phone || '') ||
    JSON.stringify(formData.social_links) !== JSON.stringify(profile?.social_links || {})
  )
})

const clearMessages = () => {
  successMessage.value = ''
  errorMessage.value = ''
  Object.keys(errors).forEach(key => {
    delete errors[key]
  })
}

const loadFormData = () => {
  const profile = props.user.profile
  
  formData.name = props.user.name || ''
  formData.first_name = props.user.first_name || ''
  formData.last_name = props.user.last_name || ''
  formData.username = props.user.username || ''
  formData.bio = profile?.bio || ''
  formData.location = profile?.location || ''
  formData.website = profile?.website || ''
  formData.birth_date = profile?.birth_date || ''
  formData.gender = profile?.gender || ''
  formData.occupation = profile?.occupation || ''
  formData.education = profile?.education || ''
  formData.phone = profile?.phone || ''
  formData.social_links = { ...profile?.social_links } || {}
}

const handleSubmit = async () => {
  if (!hasChanges.value) {
    errorMessage.value = 'No changes to save.'
    return
  }

  clearMessages()
  isSubmitting.value = true

  try {
    const response = await authStore.updateProfile(formData)

    if (response.success) {
      successMessage.value = response.message || 'Profile updated successfully!'
      emit('updated')
      
      setTimeout(() => {
        successMessage.value = ''
      }, 3000)
    } else {
      if (response.errors) {
        Object.assign(errors, response.errors)
      }
      errorMessage.value = response.message || 'Failed to update profile. Please try again.'
    }
  } catch (error) {
    console.error('Profile update error:', error)
    errorMessage.value = 'An unexpected error occurred. Please try again.'
  } finally {
    isSubmitting.value = false
  }
}

const resetForm = () => {
  loadFormData()
  clearMessages()
}

const addSocialLink = () => {
  if (!formData.social_links) {
    formData.social_links = {}
  }
  formData.social_links[''] = ''
}

const removeSocialLink = (key: string) => {
  if (formData.social_links) {
    delete formData.social_links[key]
  }
}

onMounted(() => {
  loadFormData()
})
</script> 