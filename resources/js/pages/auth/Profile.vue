<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header with Cover Image -->
    <div class="relative bg-white shadow">
      <div class="relative h-48 bg-gradient-to-r from-purple-500 to-blue-600">
        <button
          v-if="isOwnProfile"
          @click="showCoverImageUpload = true"
          class="absolute bottom-4 right-4 bg-black bg-opacity-50 text-white p-2 rounded-full hover:bg-opacity-70 transition-opacity"
        >
          <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>
      
      <!-- Profile Header -->
      <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="relative -mt-16 pb-6">
          <div class="flex items-end space-x-5">
            <!-- Avatar -->
            <div class="relative">
              <img
                :src="user?.avatar_url || '/default-avatar.png'"
                :alt="user?.name"
                class="w-32 h-32 rounded-full border-4 border-white bg-white"
              />
              <button
                v-if="isOwnProfile"
                @click="showAvatarUpload = true"
                class="absolute bottom-2 right-2 bg-indigo-600 text-white p-2 rounded-full hover:bg-indigo-700 transition-colors"
              >
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                </svg>
              </button>
            </div>
            
            <!-- User Info -->
            <div class="flex-1 min-w-0 pt-8">
              <div class="flex items-center">
                <h1 class="text-2xl font-bold text-gray-900 truncate">
                  {{ user?.name }}
                </h1>
                <span v-if="user?.profile?.is_verified" class="ml-2 text-blue-500">
                  <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                  </svg>
                </span>
              </div>
              <p class="text-sm text-gray-500">@{{ user?.username }}</p>
              <p v-if="user?.profile?.bio" class="mt-2 text-sm text-gray-700">{{ user.profile.bio }}</p>
              
              <!-- Stats -->
              <div class="mt-4 flex space-x-6 text-sm text-gray-500">
                <span v-if="user?.profile?.friends_count">
                  <strong class="text-gray-900">{{ user.profile.friends_count }}</strong> Friends
                </span>
                <span v-if="user?.profile?.posts_count">
                  <strong class="text-gray-900">{{ user.profile.posts_count }}</strong> Posts
                </span>
                <span>
                  <strong class="text-gray-900">{{ formatDate(user?.created_at) }}</strong> Joined
                </span>
              </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex space-x-3 pt-8">
              <button
                v-if="isOwnProfile"
                @click="activeTab = 'edit'"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
              >
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                </svg>
                Edit Profile
              </button>
              
              <template v-else>
                <button
                  v-if="canSendFriendRequest"
                  @click="addFriend"
                  class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Add Friend
                </button>
                
                <button
                  v-if="canSendMessage"
                  @click="sendMessage"
                  class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Message
                </button>
              </template>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
      <div class="border-b border-gray-200">
        <nav class="-mb-px flex space-x-8">
          <button
            v-for="tab in availableTabs"
            :key="tab.id"
            @click="activeTab = tab.id"
            :class="[
              'py-2 px-1 border-b-2 font-medium text-sm',
              activeTab === tab.id
                ? 'border-indigo-500 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            ]"
          >
            {{ tab.name }}
          </button>
        </nav>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- About Tab -->
      <div v-show="activeTab === 'about'" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Basic Info -->
        <div class="lg:col-span-2 space-y-6">
          <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">About</h3>
            <div class="space-y-4">
              <div v-if="user?.profile?.bio">
                <p class="text-gray-700">{{ user.profile.bio }}</p>
              </div>
              
              <div v-if="canViewField('location') && user?.profile?.location" class="flex items-center text-sm text-gray-600">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd" />
                </svg>
                {{ user.profile.location }}
              </div>
              
              <div v-if="canViewField('website') && user?.profile?.website" class="flex items-center text-sm text-gray-600">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M12.586 4.586a2 2 0 112.828 2.828l-3 3a2 2 0 01-2.828 0 1 1 0 00-1.414 1.414 4 4 0 005.656 0l3-3a4 4 0 00-5.656-5.656l-1.5 1.5a1 1 0 101.414 1.414l1.5-1.5zm-5 5a2 2 0 012.828 0 1 1 0 101.414-1.414 4 4 0 00-5.656 0l-3 3a4 4 0 105.656 5.656l1.5-1.5a1 1 0 10-1.414-1.414l-1.5 1.5a2 2 0 11-2.828-2.828l3-3z" clip-rule="evenodd" />
                </svg>
                <a :href="user.profile.website" target="_blank" class="text-indigo-600 hover:text-indigo-500">
                  {{ user.profile.website }}
                </a>
              </div>
              
              <div v-if="canViewField('occupation') && user?.profile?.occupation" class="flex items-center text-sm text-gray-600">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd" />
                </svg>
                {{ user.profile.occupation }}
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
          <!-- Friends -->
          <div v-if="canViewField('friends_list')" class="bg-white shadow rounded-lg p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-lg font-medium text-gray-900">Friends</h3>
              <span class="text-sm text-gray-500">{{ user?.profile?.friends_count || 0 }}</span>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <!-- Friend avatars would go here -->
              <div v-for="i in 6" :key="i" class="w-12 h-12 bg-gray-200 rounded-full"></div>
            </div>
          </div>

          <!-- Reading Activity -->
          <div v-if="canViewField('reading_activity')" class="bg-white shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Activity</h3>
            <div class="text-sm text-gray-500">
              Reading activity would be displayed here
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Profile Tab -->
      <div v-show="activeTab === 'edit'" class="max-w-3xl">
        <ProfileEditForm 
          v-if="user" 
          :user="user" 
          @updated="handleProfileUpdated"
          @avatar-updated="handleAvatarUploaded"
        />
      </div>

      <!-- Privacy Tab -->
      <div v-show="activeTab === 'privacy'" class="max-w-3xl">
        <PrivacySettings v-if="user" :user="user" @updated="handlePrivacyUpdated" />
      </div>

      <!-- Security Tab -->
      <div v-show="activeTab === 'security'" class="max-w-3xl">
        <SecuritySettings v-if="user" :user="user" />
      </div>
    </div>

    <!-- Avatar Upload Modal -->
    <AvatarUploadModal 
      v-if="showAvatarUpload" 
      @close="showAvatarUpload = false"
      @uploaded="handleAvatarUploaded"
    />

    <!-- Cover Image Upload Modal -->
    <CoverImageUploadModal 
      v-if="showCoverImageUpload" 
      @close="showCoverImageUpload = false"
      @uploaded="handleCoverImageUploaded"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { usePrivacyStore } from '@/stores/privacy'
import authService from '@/services/authService'
import type { User } from '@/types/auth'

// Components
import ProfileEditForm from '@/components/ProfileEditForm.vue'
import PrivacySettings from '@/components/PrivacySettings.vue'
import SecuritySettings from '@/components/SecuritySettings.vue'
import AvatarUploadModal from '@/components/AvatarUploadModal.vue'
import CoverImageUploadModal from '@/components/CoverImageUploadModal.vue'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const privacyStore = usePrivacyStore()

// Local state
const profileUser = ref<User | null>(null)
const isLoadingProfile = ref(false)
const activeTab = ref('about')
const showAvatarUpload = ref(false)
const showCoverImageUpload = ref(false)

// Computed properties
const userId = computed(() => {
  const routeUserId = route.params.userId as string
  return routeUserId ? parseInt(routeUserId) : null
})

const isOwnProfile = computed(() => {
  return !userId.value || userId.value === authStore.user?.id
})

const user = computed(() => {
  return isOwnProfile.value ? authStore.user : profileUser.value
})

const canViewProfile = computed(() => {
  if (!user.value) return false
  if (isOwnProfile.value) return true
  
  // Check privacy settings
  const visibility = user.value.privacy_context?.relationship
  return visibility !== 'private'
})

const userStats = computed(() => {
  if (!user.value?.profile) return { posts: 0, friends: 0, reading: 0 }
  
  return {
    posts: user.value.profile.posts_count || 0,
    friends: user.value.profile.friends_count || 0,
    reading: 42 // This would come from reading activity data
  }
})

const canSendFriendRequest = computed(() => {
  return !isOwnProfile.value && user.value?.privacy_context?.can_send_friend_request
})

const canSendMessage = computed(() => {
  return !isOwnProfile.value && user.value?.privacy_context?.can_send_message
})

const availableTabs = computed(() => {
  const tabs = [
    { id: 'about', name: 'About' }
  ]
  
  if (isOwnProfile.value) {
    tabs.push(
      { id: 'edit', name: 'Edit Profile' },
      { id: 'privacy', name: 'Privacy' },
      { id: 'security', name: 'Security' }
    )
  }
  
  return tabs
})

const canViewField = (fieldType: string): boolean => {
  if (isOwnProfile.value) return true
  
  // This would implement the privacy checking logic
  // For now, return true for basic fields
  return true
}

const formatDate = (dateString?: string): string => {
  if (!dateString) return ''
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long'
  })
}

// Methods
const fetchUserProfile = async (id: number) => {
  if (id === authStore.user?.id) {
    // For own profile, use data from auth store
    profileUser.value = authStore.user
    return
  }

  isLoadingProfile.value = true
  try {
    const response = await authService.getProfile(id)
    
    if (response.success && response.data?.user) {
      profileUser.value = response.data.user
    } else {
      console.error('Failed to load user profile:', response.message)
      router.push('/404')
    }
  } catch (error) {
    console.error('Error loading user profile:', error)
    router.push('/404')
  } finally {
    isLoadingProfile.value = false
  }
}

const handleAvatarUploaded = async (avatarUrl: string) => {
  showAvatarUpload.value = false
  
  if (isOwnProfile.value) {
    // Refresh user data to get updated avatar
    await authStore.refreshUser()
  }
}

const handleCoverImageUploaded = async (coverImageUrl: string) => {
  showCoverImageUpload.value = false
  
  if (isOwnProfile.value) {
    // Refresh user data to get updated cover image
    await authStore.refreshUser()
  }
}

const handleProfileUpdated = async () => {
  if (isOwnProfile.value) {
    // Refresh user data after profile update
    await authStore.refreshUser()
  }
}

const handlePrivacyUpdated = async () => {
  // Refresh privacy settings after update
  await privacyStore.fetchPrivacySettings(true)
  
  if (isOwnProfile.value) {
    // Also refresh user data to get updated privacy context
    await authStore.refreshUser()
  }
}

const addFriend = async () => {
  if (!user.value) return
  
  try {
    // This would be implemented in a friends store/service
    console.log('Send friend request to:', user.value.id)
  } catch (error) {
    console.error('Error sending friend request:', error)
  }
}

const sendMessage = async () => {
  if (!user.value) return
  
  try {
    // This would navigate to messages with this user
    console.log('Send message to:', user.value.id)
    router.push(`/messages/${user.value.id}`)
  } catch (error) {
    console.error('Error starting conversation:', error)
  }
}

// Lifecycle
onMounted(async () => {
  // Ensure auth store is initialized
  if (!authStore.sessionChecked) {
    await authStore.checkSession()
  }

  // Load user profile if viewing someone else's profile
  if (userId.value) {
    await fetchUserProfile(userId.value)
  }

  // Load privacy settings if viewing own profile
  if (isOwnProfile.value) {
    await privacyStore.fetchPrivacySettings()
  }
})

// Watch for route changes
watch(userId, async (newUserId) => {
  if (newUserId) {
    await fetchUserProfile(newUserId)
  }
}, { immediate: false })
</script> 