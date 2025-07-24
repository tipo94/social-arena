import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import authService from '@/services/authService'
import type { User, LoginCredentials, RegisterData, ProfileUpdateData, PasswordChangeData } from '@/types/auth'

export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<User | null>(null)
  const isAuthenticated = ref(false)
  const isLoading = ref(false)
  const sessionChecked = ref(false)

  // Computed
  const isLoggedIn = computed(() => isAuthenticated.value && user.value !== null)
  const userDisplayName = computed(() => user.value?.display_name || user.value?.name || '')
  const userAvatar = computed(() => user.value?.avatar_url || '/default-avatar.png')
  const userProfile = computed(() => user.value?.profile)
  const isProfileComplete = computed(() => {
    if (!user.value?.profile) return false
    const profile = user.value.profile
    return !!(profile.bio && profile.location && profile.occupation)
  })

  // Watchers for persistence
  watch(user, (newUser) => {
    if (newUser) {
      localStorage.setItem('auth_user', JSON.stringify(newUser))
    } else {
      localStorage.removeItem('auth_user')
    }
  }, { deep: true })

  watch(isAuthenticated, (newValue) => {
    if (newValue) {
      localStorage.setItem('auth_authenticated', 'true')
    } else {
      localStorage.removeItem('auth_authenticated')
    }
  })

  // Actions
  const login = async (credentials: LoginCredentials) => {
    isLoading.value = true
    try {
      const response = await authService.login(credentials)
      
      if (response.success && response.data?.user) {
        user.value = response.data.user
        isAuthenticated.value = true
        
        return { 
          success: true, 
          user: response.data.user,
          message: 'Login successful' 
        }
      } else {
        return {
          success: false,
          message: response.message || 'Login failed',
          errors: response.errors
        }
      }
    } catch (error) {
      console.error('Login error:', error)
      return {
        success: false,
        message: 'An unexpected error occurred during login',
        errors: { general: ['Network error'] }
      }
    } finally {
      isLoading.value = false
    }
  }

  const register = async (data: RegisterData) => {
    isLoading.value = true
    try {
      const response = await authService.register(data)
      
      if (response.success && response.data?.user) {
        user.value = response.data.user
        isAuthenticated.value = true
        
        return { 
          success: true, 
          user: response.data.user,
          message: response.message || 'Registration successful' 
        }
      } else {
        return {
          success: false,
          message: response.message || 'Registration failed',
          errors: response.errors
        }
      }
    } catch (error) {
      console.error('Registration error:', error)
      return {
        success: false,
        message: 'An unexpected error occurred during registration',
        errors: { general: ['Network error'] }
      }
    } finally {
      isLoading.value = false
    }
  }

  const logout = async (logoutAll = false) => {
    isLoading.value = true
    try {
      const response = logoutAll 
        ? await authService.logoutAll()
        : await authService.logout()
      
      if (response.success) {
        clearAuthState()
        return { success: true, message: 'Logout successful' }
      } else {
        // Even if server logout fails, clear local state
        clearAuthState()
        return { success: true, message: 'Logged out locally' }
      }
    } catch (error) {
      console.error('Logout error:', error)
      // Clear local state even if server request fails
      clearAuthState()
      return { success: true, message: 'Logged out locally' }
    } finally {
      isLoading.value = false
    }
  }

  const checkSession = async () => {
    if (sessionChecked.value) return

    // Check if we have stored auth data
    const storedAuth = localStorage.getItem('auth_authenticated')
    const storedUser = localStorage.getItem('auth_user')
    
    if (storedAuth && storedUser) {
      try {
        user.value = JSON.parse(storedUser)
        isAuthenticated.value = true
      } catch (error) {
        console.error('Error parsing stored user data:', error)
        clearAuthState()
      }
    }

    // Verify session with server
    try {
      const response = await authService.getCurrentUser()
      
      if (response.success && response.data?.user) {
        user.value = response.data.user
        isAuthenticated.value = true
      } else {
        clearAuthState()
      }
    } catch (error: any) {
      // 401 errors are expected when no session exists - handle silently
      if (error?.response?.status === 401) {
        // No existing session - this is normal on first visit
        clearAuthState()
      } else {
        // Unexpected error - log for debugging
        console.error('Session check error:', error)
        clearAuthState()
      }
    } finally {
      sessionChecked.value = true
    }
  }

  const refreshUser = async () => {
    if (!isAuthenticated.value) return

    try {
      const response = await authService.getCurrentUser()
      
      if (response.success && response.data?.user) {
        user.value = response.data.user
        return { success: true, user: response.data.user }
      } else {
        clearAuthState()
        return { success: false, message: 'Failed to refresh user data' }
      }
    } catch (error) {
      console.error('User refresh error:', error)
      return { success: false, message: 'Network error during refresh' }
    }
  }

  const updateProfile = async (profileData: ProfileUpdateData) => {
    if (!isAuthenticated.value || !user.value) {
      return { success: false, message: 'User not authenticated' }
    }

    isLoading.value = true
    try {
      const response = await authService.updateProfile(profileData)
      
      if (response.success && response.data?.user) {
        user.value = response.data.user
        return { 
          success: true, 
          user: response.data.user,
          message: response.message || 'Profile updated successfully' 
        }
      } else {
        return {
          success: false,
          message: response.message || 'Failed to update profile',
          errors: response.errors
        }
      }
    } catch (error) {
      console.error('Profile update error:', error)
      return {
        success: false,
        message: 'An unexpected error occurred',
        errors: { general: ['Network error'] }
      }
    } finally {
      isLoading.value = false
    }
  }

  const updateAvatar = async (file: File) => {
    if (!isAuthenticated.value || !user.value) {
      return { success: false, message: 'User not authenticated' }
    }

    try {
      const response = await authService.updateAvatar(file)
      
      if (response.success) {
        // Refresh user data to get new avatar URL
        await refreshUser()
        return { 
          success: true, 
          message: response.message || 'Avatar updated successfully',
          avatar_url: response.data?.avatar_url
        }
      } else {
        return {
          success: false,
          message: response.message || 'Failed to update avatar',
          errors: response.errors
        }
      }
    } catch (error) {
      console.error('Avatar update error:', error)
      return {
        success: false,
        message: 'An unexpected error occurred',
        errors: { general: ['Network error'] }
      }
    }
  }

  const updateCoverImage = async (file: File) => {
    if (!isAuthenticated.value || !user.value) {
      return { success: false, message: 'User not authenticated' }
    }

    try {
      const response = await authService.updateCoverImage(file)
      
      if (response.success) {
        // Refresh user data to get new cover image URL
        await refreshUser()
        return { 
          success: true, 
          message: response.message || 'Cover image updated successfully',
          cover_image_url: response.data?.cover_image_url
        }
      } else {
        return {
          success: false,
          message: response.message || 'Failed to update cover image',
          errors: response.errors
        }
      }
    } catch (error) {
      console.error('Cover image update error:', error)
      return {
        success: false,
        message: 'An unexpected error occurred',
        errors: { general: ['Network error'] }
      }
    }
  }

  const changePassword = async (passwordData: PasswordChangeData) => {
    if (!isAuthenticated.value) {
      return { success: false, message: 'User not authenticated' }
    }

    isLoading.value = true
    try {
      const response = await authService.changePassword(passwordData)
      
      if (response.success) {
        return { 
          success: true, 
          message: response.message || 'Password changed successfully' 
        }
      } else {
        return {
          success: false,
          message: response.message || 'Failed to change password',
          errors: response.errors
        }
      }
    } catch (error) {
      console.error('Password change error:', error)
      return {
        success: false,
        message: 'An unexpected error occurred',
        errors: { general: ['Network error'] }
      }
    } finally {
      isLoading.value = false
    }
  }

  const clearAuthState = () => {
    user.value = null
    isAuthenticated.value = false
    localStorage.removeItem('auth_user')
    localStorage.removeItem('auth_authenticated')
  }

  // Initialize store
  const initialize = async () => {
    await checkSession()
  }

  return {
    // State
    user,
    isAuthenticated,
    isLoading,
    sessionChecked,
    
    // Computed
    isLoggedIn,
    userDisplayName,
    userAvatar,
    userProfile,
    isProfileComplete,
    
    // Actions
    login,
    register,
    logout,
    checkSession,
    refreshUser,
    updateProfile,
    updateAvatar,
    updateCoverImage,
    changePassword,
    clearAuthState,
    initialize
  }
}) 