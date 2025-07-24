<template>
  <div class="page-container">
    <div class="content-wrapper max-w-6xl mx-auto">
      <!-- Header -->
      <div class="mb-8">
        <h1 class="section-header text-neutral-900">Dashboard</h1>
        <p class="section-subtitle text-neutral-600">
          Welcome back! Catch up with your reading community
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
      
      <!-- Main content grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main feed -->
        <div class="lg:col-span-2">
          <!-- Post creation form -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-start space-x-4">
              <div class="avatar avatar-md">
                <img :src="authStore.user?.avatar_url" :alt="authStore.user?.display_name" />
              </div>
              <div class="flex-1">
                <button
                  @click="openPostCreation('text')"
                  class="w-full text-left px-4 py-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors text-gray-600"
                >
                  What's on your reading list today?
                </button>
                <div class="flex items-center justify-between mt-3">
                  <div class="flex">
                                         <button 
                       @click="openPostCreation('image')"
                       class="flex items-center text-gray-600 hover:text-blue-600 transition-colors mr-4"
                     >
                       <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                       </svg>
                       <span class="text-sm">Photo</span>
                     </button>
                     <button 
                       @click="openPostCreation('book_review')"
                       class="flex items-center text-gray-600 hover:text-green-600 transition-colors mr-4"
                     >
                       <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253z"></path>
                       </svg>
                       <span class="text-sm">Book Review</span>
                     </button>
                     <button 
                       @click="openPostCreation('poll')"
                       class="flex items-center text-gray-600 hover:text-purple-600 transition-colors"
                     >
                       <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                         <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                       </svg>
                       <span class="text-sm">Poll</span>
                     </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Feed Display -->
          <FeedDisplay />
        </div>

        <!-- Sidebar -->
        <div class="lg:col-span-1">
          <!-- Quick Stats -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Reading Stats</h3>
            <div class="space-y-4">
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Books Read</span>
                <span class="font-semibold text-gray-900">{{ authStore.user?.profile?.books_read_count || 0 }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Reviews Written</span>
                <span class="font-semibold text-gray-900">{{ authStore.user?.profile?.reviews_written_count || 0 }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Friends</span>
                <span class="font-semibold text-gray-900">{{ authStore.user?.profile?.friends_count || 0 }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-gray-600">Profile Complete</span>
                <span class="font-semibold text-blue-600">{{ authStore.user?.profile?.profile_completion_percentage || 0 }}%</span>
              </div>
            </div>
          </div>

          <!-- Suggested Friends -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Suggested Friends</h3>
            <div class="text-center py-4">
              <div class="text-gray-500 mb-2">👥</div>
              <p class="text-sm text-gray-600">Friend suggestions coming soon!</p>
            </div>
          </div>

          <!-- Reading Goals -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Reading Goals</h3>
            <div class="text-center py-4">
              <div class="text-gray-500 mb-2">🎯</div>
              <p class="text-sm text-gray-600">Set your reading goals to track progress!</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Post Creation Modal -->
    <div v-if="showPostCreationModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-lg max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="p-6">
          <PostCreationForm
            :edit-mode="false"
            :initial-type="postType"
            @success="handlePostCreated"
            @cancel="closePostCreation"
            :on-close="closePostCreation"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useFeedStore } from '@/stores/feed'
import FeedDisplay from '@/components/FeedDisplay.vue'
import PostCreationForm from '@/components/PostCreationForm.vue'
import type { Post } from '@/types/posts'

const authStore = useAuthStore()
const feedStore = useFeedStore()
const showPostCreationModal = ref(false)
const postType = ref<string>('text')

const openPostCreation = (type: string = 'text') => {
  postType.value = type
  showPostCreationModal.value = true
}

const closePostCreation = () => {
  showPostCreationModal.value = false
  postType.value = 'text'
}

const handlePostCreated = (post: Post) => {
  // Add the new post to the feed
  feedStore.addPost(post)
  
  // Close the modal
  closePostCreation()
}
</script> 