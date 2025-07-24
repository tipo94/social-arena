import { createPinia } from 'pinia'
import { useAuthStore } from './auth'
import { usePrivacyStore } from './privacy'

// Create pinia instance
export const pinia = createPinia()

// Store initialization
export const initializeStores = async () => {
  try {
    // Initialize auth store first
    const authStore = useAuthStore()
    await authStore.initialize()
    
    // Initialize privacy store after auth is ready
    const privacyStore = usePrivacyStore()
    if (authStore.isAuthenticated) {
      await privacyStore.initialize()
    }
    
    console.log('Stores initialized successfully')
  } catch (error) {
    console.error('Error initializing stores:', error)
  }
}

// Export stores for easy access
export { useAuthStore, usePrivacyStore } 