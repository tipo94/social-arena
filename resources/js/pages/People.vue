<template>
  <div class="page-container">
    <div class="content-wrapper max-w-6xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="section-header text-neutral-900">People</h1>
        <p class="section-subtitle text-neutral-600">
          Discover and connect with fellow book lovers
        </p>
        
                 <!-- Navigation tabs -->
         <div class="mt-6 border-b border-gray-200">
                      <nav class="-mb-px flex">
             <RouterLink 
               to="/dashboard" 
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm mr-8"
               active-class="border-blue-500 text-blue-600"
             >
               🏠 Feed
             </RouterLink>
             <RouterLink 
               to="/people" 
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm mr-8"
               active-class="border-blue-500 text-blue-600"
             >
               👥 People
             </RouterLink>
             <RouterLink 
               to="/profile" 
               class="border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
               active-class="border-blue-500 text-blue-600"
             >
               👤 Profile
             </RouterLink>
           </nav>
        </div>
      </div>
      
      <!-- Search bar -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center space-x-4">
          <div class="flex-1">
            <input
              v-model="searchQuery"
              @input="handleSearch"
              type="text"
              placeholder="Search for people by name or interests..."
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
            />
          </div>
          <button
            @click="handleSearch"
            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            Search
          </button>
        </div>
      </div>

      <!-- Users grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- Loading state -->
        <div v-if="loading" class="col-span-full text-center py-12">
          <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
          <p class="mt-4 text-gray-600">Loading people...</p>
        </div>

        <!-- Error state -->
        <div v-else-if="error" class="col-span-full text-center py-12">
          <div class="text-red-600 mb-4">❌</div>
          <p class="text-red-600">{{ error }}</p>
          <button @click="loadUsers" class="mt-4 btn btn-primary">Try Again</button>
        </div>

        <!-- User cards -->
        <div
          v-else
          v-for="user in users"
          :key="user.id"
          class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 hover:shadow-md transition-shadow"
        >
          <div class="text-center">
            <!-- Avatar -->
            <div class="mb-4">
              <img
                :src="user.avatar_url"
                :alt="user.display_name"
                class="w-16 h-16 rounded-full mx-auto object-cover"
              />
            </div>

            <!-- User info -->
            <h3 class="text-lg font-semibold text-gray-900 mb-1">{{ user.display_name }}</h3>
            <p class="text-sm text-gray-600 mb-2">{{ user.full_name }}</p>
            
            <!-- Bio -->
            <p v-if="user.profile?.bio" class="text-sm text-gray-600 mb-4 line-clamp-2">
              {{ user.profile.bio }}
            </p>

            <!-- Stats -->
            <div class="flex justify-center text-xs text-gray-500 mb-4">
              <div class="mr-4">
                <span class="font-medium">{{ user.profile?.books_read_count || 0 }}</span>
                <span>books</span>
              </div>
              <div class="mr-4">
                <span class="font-medium">{{ user.profile?.friends_count || 0 }}</span>
                <span>friends</span>
              </div>
              <div>
                <span class="font-medium">{{ user.profile?.reviews_written_count || 0 }}</span>
                <span>reviews</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex">
              <RouterLink
                :to="`/profile/${user.id}`"
                class="flex-1 px-3 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm font-medium mr-2"
              >
                View Profile
              </RouterLink>
              <button
                v-if="user.id !== authStore.user?.id"
                @click="sendFriendRequest(user)"
                class="flex-1 px-3 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm font-medium"
                :disabled="friendRequestLoading[user.id]"
              >
                <span v-if="friendRequestLoading[user.id]">...</span>
                <span v-else>Add Friend</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!loading && !error && users.length === 0" class="col-span-full text-center py-12">
          <div class="text-gray-400 mb-4">👥</div>
          <p class="text-gray-600">No people found</p>
          <p class="text-sm text-gray-500 mt-2">Try adjusting your search terms</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

interface User {
  id: number
  name: string
  display_name: string
  full_name: string
  avatar_url: string
  profile?: {
    bio?: string
    books_read_count?: number
    friends_count?: number
    reviews_written_count?: number
  }
}

const authStore = useAuthStore()

const searchQuery = ref('')
const users = ref<User[]>([])
const loading = ref(false)
const error = ref('')
const friendRequestLoading = reactive<Record<number, boolean>>({})

const loadUsers = async () => {
  loading.value = true
  error.value = ''
  
  try {
    const query = searchQuery.value ? `?search=${encodeURIComponent(searchQuery.value)}` : ''
    const response = await window.axios.get(`/api/users/search${query}`)
    
    if (response.data.success) {
      users.value = response.data.data || []
    } else {
      error.value = response.data.message || 'Failed to load users'
    }
  } catch (err: any) {
    console.error('Error loading users:', err)
    error.value = err.response?.data?.message || 'Failed to load users'
  } finally {
    loading.value = false
  }
}

const handleSearch = () => {
  loadUsers()
}

const sendFriendRequest = async (user: User) => {
  friendRequestLoading[user.id] = true
  
  try {
    const response = await window.axios.post('/api/friends/request', {
      user_id: user.id
    })
    
    if (response.data.success) {
      // Show success message or update UI
      console.log('Friend request sent successfully')
    } else {
      console.error('Failed to send friend request:', response.data.message)
    }
  } catch (err: any) {
    console.error('Error sending friend request:', err)
  } finally {
    friendRequestLoading[user.id] = false
  }
}

onMounted(() => {
  loadUsers()
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style> 