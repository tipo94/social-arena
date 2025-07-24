import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authService } from '@/services/authService'
import { useAuthStore } from './auth'
import type { PrivacySettings, ApiResponse } from '@/types/auth'

export const usePrivacyStore = defineStore('privacy', () => {
  // State
  const privacySettings = ref<PrivacySettings | null>(null)
  const isLoading = ref(false)
  const lastFetched = ref<Date | null>(null)

  // Computed
  const isPrivateProfile = computed(() => {
    return privacySettings.value?.profile_privacy?.is_private_profile || false
  })

  const profileVisibility = computed(() => {
    return privacySettings.value?.profile_privacy?.profile_visibility || 'public'
  })

  const canReceiveFriendRequests = computed(() => {
    return privacySettings.value?.interaction_privacy?.allow_friend_requests || true
  })

  const whoCanSeeReadingActivity = computed(() => {
    return privacySettings.value?.activity_privacy?.reading_activity_visibility || 'public'
  })

  const whoCanSendMessages = computed(() => {
    return privacySettings.value?.interaction_privacy?.allow_messages_from || 'everyone'
  })

  // Actions
  const fetchPrivacySettings = async (force = false) => {
    const authStore = useAuthStore()
    
    if (!authStore.isAuthenticated) {
      return { success: false, message: 'User not authenticated' }
    }

    // Return cached data if recent (unless forced)
    if (!force && privacySettings.value && lastFetched.value) {
      const timeSinceLastFetch = Date.now() - lastFetched.value.getTime()
      if (timeSinceLastFetch < 5 * 60 * 1000) { // 5 minutes
        return { success: true, data: privacySettings.value }
      }
    }

    isLoading.value = true
    try {
      const response = await authService.getPrivacySettings()
      
      if (response.success && response.data) {
        privacySettings.value = response.data as PrivacySettings
        lastFetched.value = new Date()
        
        return { 
          success: true, 
          data: privacySettings.value,
          message: 'Privacy settings loaded successfully' 
        }
      } else {
        return {
          success: false,
          message: response.message || 'Failed to load privacy settings'
        }
      }
    } catch (error) {
      console.error('Privacy settings fetch error:', error)
      return {
        success: false,
        message: 'Network error while loading privacy settings'
      }
    } finally {
      isLoading.value = false
    }
  }

  const updatePrivacySettings = async (settings: Partial<PrivacySettings>) => {
    const authStore = useAuthStore()
    
    if (!authStore.isAuthenticated) {
      return { success: false, message: 'User not authenticated' }
    }

    isLoading.value = true
    try {
      const response = await authService.updatePrivacySettings(settings)
      
      if (response.success) {
        // Merge the updated settings with existing ones
        if (privacySettings.value) {
          privacySettings.value = {
            ...privacySettings.value,
            ...settings
          }
        } else {
          // If we don't have existing settings, fetch them
          await fetchPrivacySettings(true)
        }
        
        lastFetched.value = new Date()
        
        return { 
          success: true, 
          data: privacySettings.value,
          message: response.message || 'Privacy settings updated successfully' 
        }
      } else {
        return {
          success: false,
          message: response.message || 'Failed to update privacy settings',
          errors: response.errors
        }
      }
    } catch (error) {
      console.error('Privacy settings update error:', error)
      return {
        success: false,
        message: 'Network error while updating privacy settings',
        errors: { general: ['Network error'] }
      }
    } finally {
      isLoading.value = false
    }
  }

  const updateProfileVisibility = async (visibility: 'public' | 'friends' | 'friends_of_friends' | 'private') => {
    return await updatePrivacySettings({
      profile_privacy: {
        ...privacySettings.value?.profile_privacy,
        profile_visibility: visibility
      }
    })
  }

  const togglePrivateProfile = async () => {
    const currentValue = privacySettings.value?.profile_privacy?.is_private_profile || false
    
    return await updatePrivacySettings({
      profile_privacy: {
        ...privacySettings.value?.profile_privacy,
        is_private_profile: !currentValue
      }
    })
  }

  const updateReadingActivityVisibility = async (visibility: 'public' | 'friends' | 'friends_of_friends' | 'private') => {
    return await updatePrivacySettings({
      activity_privacy: {
        ...privacySettings.value?.activity_privacy,
        reading_activity_visibility: visibility
      }
    })
  }

  const updateMessagePermissions = async (allowFrom: 'everyone' | 'friends' | 'friends_of_friends' | 'nobody') => {
    return await updatePrivacySettings({
      interaction_privacy: {
        ...privacySettings.value?.interaction_privacy,
        allow_messages_from: allowFrom
      }
    })
  }

  const toggleFriendRequests = async () => {
    const currentValue = privacySettings.value?.interaction_privacy?.allow_friend_requests || true
    
    return await updatePrivacySettings({
      interaction_privacy: {
        ...privacySettings.value?.interaction_privacy,
        allow_friend_requests: !currentValue
      }
    })
  }

  const resetToDefaults = async () => {
    const defaultSettings: PrivacySettings = {
      profile_privacy: {
        is_private_profile: false,
        profile_visibility: 'public',
        contact_info_visibility: 'friends',
        location_visibility: 'friends',
        birth_date_visibility: 'friends',
        search_visibility: 'everyone'
      },
      activity_privacy: {
        show_reading_activity: true,
        show_online_status: true,
        show_last_activity: true,
        reading_activity_visibility: 'public',
        post_visibility_default: 'public'
      },
      social_privacy: {
        show_friends_list: true,
        show_mutual_friends: true,
        friends_list_visibility: 'friends',
        who_can_see_posts: 'public',
        who_can_tag_me: 'friends'
      },
      interaction_privacy: {
        allow_friend_requests: true,
        allow_group_invites: true,
        allow_book_recommendations: true,
        allow_messages_from: 'everyone',
        friend_request_visibility: 'everyone',
        who_can_find_me: 'everyone'
      },
      content_privacy: {
        book_lists_visibility: 'public',
        reviews_visibility: 'public',
        reading_goals_visibility: 'public',
        reading_history_visibility: 'friends'
      }
    }

    return await updatePrivacySettings(defaultSettings)
  }

  const clearPrivacyData = () => {
    privacySettings.value = null
    lastFetched.value = null
  }

  // Initialize privacy settings when auth store is ready
  const initialize = async () => {
    const authStore = useAuthStore()
    if (authStore.isAuthenticated) {
      await fetchPrivacySettings()
    }
  }

  return {
    // State
    privacySettings,
    isLoading,
    lastFetched,
    
    // Computed
    isPrivateProfile,
    profileVisibility,
    canReceiveFriendRequests,
    whoCanSeeReadingActivity,
    whoCanSendMessages,
    
    // Actions
    fetchPrivacySettings,
    updatePrivacySettings,
    updateProfileVisibility,
    togglePrivateProfile,
    updateReadingActivityVisibility,
    updateMessagePermissions,
    toggleFriendRequests,
    resetToDefaults,
    clearPrivacyData,
    initialize
  }
}) 