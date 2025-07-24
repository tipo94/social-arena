<template>
  <div class="bg-white shadow rounded-lg p-6">
    <h3 class="text-lg font-medium text-gray-900 mb-6">Privacy Settings</h3>
    
    <form @submit.prevent="handleSubmit" class="space-y-8">
      <!-- Profile Privacy -->
      <div>
        <h4 class="text-md font-medium text-gray-900 mb-4">Profile Privacy</h4>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <label class="text-sm font-medium text-gray-700">Private Profile</label>
              <p class="text-sm text-gray-500">Make your profile visible only to people you approve</p>
            </div>
            <button
              type="button"
              @click="togglePrivateProfile"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                privacyStore.isPrivateProfile ? 'bg-indigo-600' : 'bg-gray-200'
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  privacyStore.isPrivateProfile ? 'translate-x-5' : 'translate-x-0'
                ]"
              />
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Profile Visibility</label>
            <select
              v-model="profilePrivacy.profile_visibility"
              @change="updateProfileVisibility"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public - Anyone can see your profile</option>
              <option value="friends">Friends - Only friends can see your profile</option>
              <option value="friends_of_friends">Friends of Friends - Friends and their friends</option>
              <option value="private">Private - Only you can see your profile</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Contact Information Visibility</label>
            <select
              v-model="profilePrivacy.contact_info_visibility"
              @change="updateProfileVisibility"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public</option>
              <option value="friends">Friends Only</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="private">Private</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Activity Privacy -->
      <div>
        <h4 class="text-md font-medium text-gray-900 mb-4">Activity Privacy</h4>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <label class="text-sm font-medium text-gray-700">Show Reading Activity</label>
              <p class="text-sm text-gray-500">Let others see what you're reading</p>
            </div>
            <button
              type="button"
              @click="toggleShowReadingActivity"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                activityPrivacy.show_reading_activity ? 'bg-indigo-600' : 'bg-gray-200'
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  activityPrivacy.show_reading_activity ? 'translate-x-5' : 'translate-x-0'
                ]"
              />
            </button>
          </div>

          <div class="flex items-center justify-between">
            <div class="flex-1">
              <label class="text-sm font-medium text-gray-700">Show Online Status</label>
              <p class="text-sm text-gray-500">Let others see when you're online</p>
            </div>
            <button
              type="button"
              @click="toggleShowOnlineStatus"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                activityPrivacy.show_online_status ? 'bg-indigo-600' : 'bg-gray-200'
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  activityPrivacy.show_online_status ? 'translate-x-5' : 'translate-x-0'
                ]"
              />
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Reading Activity Visibility</label>
            <select
              v-model="activityPrivacy.reading_activity_visibility"
              @change="updateReadingActivityVisibility"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public</option>
              <option value="friends">Friends Only</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="private">Private</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Social Privacy -->
      <div>
        <h4 class="text-md font-medium text-gray-900 mb-4">Social Privacy</h4>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <label class="text-sm font-medium text-gray-700">Show Friends List</label>
              <p class="text-sm text-gray-500">Allow others to see your friends list</p>
            </div>
            <button
              type="button"
              @click="toggleShowFriendsList"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                socialPrivacy.show_friends_list ? 'bg-indigo-600' : 'bg-gray-200'
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  socialPrivacy.show_friends_list ? 'translate-x-5' : 'translate-x-0'
                ]"
              />
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Who Can See Your Posts</label>
            <select
              v-model="socialPrivacy.who_can_see_posts"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public</option>
              <option value="friends">Friends</option>
              <option value="close_friends">Close Friends</option>
              <option value="private">Only Me</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Who Can Tag You</label>
            <select
              v-model="socialPrivacy.who_can_tag_me"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="everyone">Everyone</option>
              <option value="friends">Friends</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="nobody">Nobody</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Interaction Privacy -->
      <div>
        <h4 class="text-md font-medium text-gray-900 mb-4">Interaction Privacy</h4>
        <div class="space-y-4">
          <div class="flex items-center justify-between">
            <div class="flex-1">
              <label class="text-sm font-medium text-gray-700">Allow Friend Requests</label>
              <p class="text-sm text-gray-500">Let people send you friend requests</p>
            </div>
            <button
              type="button"
              @click="toggleFriendRequests"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                interactionPrivacy.allow_friend_requests ? 'bg-indigo-600' : 'bg-gray-200'
              ]"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  interactionPrivacy.allow_friend_requests ? 'translate-x-5' : 'translate-x-0'
                ]"
              />
            </button>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Who Can Message You</label>
            <select
              v-model="interactionPrivacy.allow_messages_from"
              @change="updateMessagePermissions"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="everyone">Everyone</option>
              <option value="friends">Friends Only</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="nobody">Nobody</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Content Privacy -->
      <div>
        <h4 class="text-md font-medium text-gray-900 mb-4">Content Privacy</h4>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Book Lists Visibility</label>
            <select
              v-model="contentPrivacy.book_lists_visibility"
              @change="updateBookListsVisibility"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public</option>
              <option value="friends">Friends Only</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="private">Private</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Reviews Visibility</label>
            <select
              v-model="contentPrivacy.reviews_visibility"
              @change="updateReviewsVisibility"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public</option>
              <option value="friends">Friends Only</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="private">Private</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Reading Goals Visibility</label>
            <select
              v-model="contentPrivacy.reading_goals_visibility"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public</option>
              <option value="friends">Friends Only</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="private">Private</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Reading History Visibility</label>
            <select
              v-model="contentPrivacy.reading_history_visibility"
              class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
              <option value="public">Public</option>
              <option value="friends">Friends Only</option>
              <option value="friends_of_friends">Friends of Friends</option>
              <option value="private">Private</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Error/Success Messages -->
      <div v-if="errorMessage" class="rounded-md bg-red-50 p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-red-800">{{ errorMessage }}</h3>
          </div>
        </div>
      </div>

      <div v-if="successMessage" class="rounded-md bg-green-50 p-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-green-800">{{ successMessage }}</h3>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="flex justify-end space-x-2">
        <button
          type="button"
          @click="makeProfileCompletelyPrivate"
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          Make Completely Private
        </button>
        <button
          type="button"
          @click="resetToDefaults"
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8 8 0 018 0 8 8 0 008 16c0 1.54.39 3.04.9 4.342M11.658 8H20M8 20H3" />
          </svg>
          Reset to Defaults
        </button>
      </div>

      <!-- Submit Button -->
      <div class="flex justify-end">
        <button
          type="submit"
          :disabled="isLoading"
          class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg 
            v-if="isLoading"
            class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" 
            fill="none" 
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          {{ isLoading ? 'Saving...' : 'Save Privacy Settings' }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePrivacyStore } from '@/stores/privacy'
import type { User } from '@/types/auth'

interface Props {
  user: User
}

const props = defineProps<Props>()
const emit = defineEmits<{
  updated: []
}>()

const privacyStore = usePrivacyStore()

const isLoading = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const clearMessages = () => {
  successMessage.value = ''
  errorMessage.value = ''
}

const showSuccessMessage = (message: string) => {
  successMessage.value = message
  setTimeout(() => {
    successMessage.value = ''
  }, 3000)
}

const showErrorMessage = (message: string) => {
  errorMessage.value = message
  setTimeout(() => {
    errorMessage.value = ''
  }, 5000)
}

// Computed properties for easy access to settings
const profilePrivacy = computed(() => privacyStore.privacySettings?.profile_privacy)
const activityPrivacy = computed(() => privacyStore.privacySettings?.activity_privacy)
const socialPrivacy = computed(() => privacyStore.privacySettings?.social_privacy)
const interactionPrivacy = computed(() => privacyStore.privacySettings?.interaction_privacy)
const contentPrivacy = computed(() => privacyStore.privacySettings?.content_privacy)

// Profile Privacy Handlers
const updateProfileVisibility = async (visibility: 'public' | 'friends' | 'friends_of_friends' | 'private') => {
  clearMessages()
  
  try {
    const response = await privacyStore.updateProfileVisibility(visibility)
    
    if (response.success) {
      showSuccessMessage('Profile visibility updated successfully!')
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update profile visibility')
    }
  } catch (error) {
    console.error('Profile visibility update error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

const togglePrivateProfile = async () => {
  clearMessages()
  
  try {
    const response = await privacyStore.togglePrivateProfile()
    
    if (response.success) {
      const newState = privacyStore.isPrivateProfile ? 'private' : 'public'
      showSuccessMessage(`Profile is now ${newState}`)
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update profile privacy')
    }
  } catch (error) {
    console.error('Private profile toggle error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

// Activity Privacy Handlers
const updateReadingActivityVisibility = async (visibility: 'public' | 'friends' | 'friends_of_friends' | 'private') => {
  clearMessages()
  
  try {
    const response = await privacyStore.updateReadingActivityVisibility(visibility)
    
    if (response.success) {
      showSuccessMessage('Reading activity visibility updated successfully!')
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update reading activity visibility')
    }
  } catch (error) {
    console.error('Reading activity visibility update error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

const toggleShowOnlineStatus = async () => {
  clearMessages()
  
  try {
    const currentValue = activityPrivacy.value?.show_online_status ?? true
    const response = await privacyStore.updatePrivacySettings({
      activity_privacy: {
        ...activityPrivacy.value,
        show_online_status: !currentValue
      }
    })
    
    if (response.success) {
      const newState = !currentValue ? 'visible' : 'hidden'
      showSuccessMessage(`Online status is now ${newState}`)
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update online status visibility')
    }
  } catch (error) {
    console.error('Online status toggle error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

const toggleShowReadingActivity = async () => {
  clearMessages()
  
  try {
    const currentValue = activityPrivacy.value?.show_reading_activity ?? true
    const response = await privacyStore.updatePrivacySettings({
      activity_privacy: {
        ...activityPrivacy.value,
        show_reading_activity: !currentValue
      }
    })
    
    if (response.success) {
      const newState = !currentValue ? 'enabled' : 'disabled'
      showSuccessMessage(`Reading activity sharing is now ${newState}`)
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update reading activity setting')
    }
  } catch (error) {
    console.error('Reading activity toggle error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

// Social Privacy Handlers
const toggleShowFriendsList = async () => {
  clearMessages()
  
  try {
    const currentValue = socialPrivacy.value?.show_friends_list ?? true
    const response = await privacyStore.updatePrivacySettings({
      social_privacy: {
        ...socialPrivacy.value,
        show_friends_list: !currentValue
      }
    })
    
    if (response.success) {
      const newState = !currentValue ? 'visible' : 'hidden'
      showSuccessMessage(`Friends list is now ${newState}`)
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update friends list visibility')
    }
  } catch (error) {
    console.error('Friends list toggle error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

// Interaction Privacy Handlers
const toggleFriendRequests = async () => {
  clearMessages()
  
  try {
    const response = await privacyStore.toggleFriendRequests()
    
    if (response.success) {
      const newState = privacyStore.canReceiveFriendRequests ? 'enabled' : 'disabled'
      showSuccessMessage(`Friend requests are now ${newState}`)
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update friend request settings')
    }
  } catch (error) {
    console.error('Friend requests toggle error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

const updateMessagePermissions = async (allowFrom: 'everyone' | 'friends' | 'friends_of_friends' | 'nobody') => {
  clearMessages()
  
  try {
    const response = await privacyStore.updateMessagePermissions(allowFrom)
    
    if (response.success) {
      showSuccessMessage('Message permissions updated successfully!')
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update message permissions')
    }
  } catch (error) {
    console.error('Message permissions update error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

// Content Privacy Handlers
const updateBookListsVisibility = async (visibility: 'public' | 'friends' | 'friends_of_friends' | 'private') => {
  clearMessages()
  
  try {
    const response = await privacyStore.updatePrivacySettings({
      content_privacy: {
        ...contentPrivacy.value,
        book_lists_visibility: visibility
      }
    })
    
    if (response.success) {
      showSuccessMessage('Book lists visibility updated successfully!')
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update book lists visibility')
    }
  } catch (error) {
    console.error('Book lists visibility update error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

const updateReviewsVisibility = async (visibility: 'public' | 'friends' | 'friends_of_friends' | 'private') => {
  clearMessages()
  
  try {
    const response = await privacyStore.updatePrivacySettings({
      content_privacy: {
        ...contentPrivacy.value,
        reviews_visibility: visibility
      }
    })
    
    if (response.success) {
      showSuccessMessage('Reviews visibility updated successfully!')
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to update reviews visibility')
    }
  } catch (error) {
    console.error('Reviews visibility update error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

// Quick Actions
const makeProfileCompletelyPrivate = async () => {
  clearMessages()
  
  try {
    const privateSettings = {
      profile_privacy: {
        ...profilePrivacy.value,
        is_private_profile: true,
        profile_visibility: 'private' as const,
        contact_info_visibility: 'private' as const,
        location_visibility: 'private' as const,
        birth_date_visibility: 'private' as const,
        search_visibility: 'nobody' as const
      },
      activity_privacy: {
        ...activityPrivacy.value,
        show_reading_activity: false,
        show_online_status: false,
        show_last_activity: false,
        reading_activity_visibility: 'private' as const
      },
      social_privacy: {
        ...socialPrivacy.value,
        show_friends_list: false,
        show_mutual_friends: false,
        friends_list_visibility: 'private' as const,
        who_can_see_posts: 'private' as const
      },
      interaction_privacy: {
        ...interactionPrivacy.value,
        allow_friend_requests: false,
        allow_messages_from: 'friends' as const,
        who_can_find_me: 'nobody' as const
      },
      content_privacy: {
        ...contentPrivacy.value,
        book_lists_visibility: 'private' as const,
        reviews_visibility: 'private' as const,
        reading_goals_visibility: 'private' as const,
        reading_history_visibility: 'private' as const
      }
    }
    
    const response = await privacyStore.updatePrivacySettings(privateSettings)
    
    if (response.success) {
      showSuccessMessage('Profile is now completely private!')
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to make profile private')
    }
  } catch (error) {
    console.error('Make profile private error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

const resetToDefaults = async () => {
  clearMessages()
  
  try {
    const response = await privacyStore.resetToDefaults()
    
    if (response.success) {
      showSuccessMessage('Privacy settings reset to defaults!')
      emit('updated')
    } else {
      showErrorMessage(response.message || 'Failed to reset privacy settings')
    }
  } catch (error) {
    console.error('Reset privacy settings error:', error)
    showErrorMessage('An unexpected error occurred')
  }
}

onMounted(async () => {
  // Load privacy settings if not already loaded
  if (!privacyStore.privacySettings) {
    await privacyStore.fetchPrivacySettings()
  }
})
</script> 