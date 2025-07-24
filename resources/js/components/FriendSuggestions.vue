<template>
  <div class="friend-suggestions">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-xl font-semibold text-gray-900">People You May Know</h2>
        <p class="text-sm text-gray-600 mt-1">Based on mutual connections and activity</p>
      </div>
      
      <button
        @click="refreshSuggestions"
        :disabled="isLoading"
        class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <ArrowPathIcon :class="['w-4 h-4 mr-1', { 'animate-spin': isLoading }]" />
        Refresh
      </button>
    </div>

    <!-- Loading state -->
    <div v-if="isLoading && suggestions.length === 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div v-for="i in 6" :key="i" class="animate-pulse">
        <div class="bg-white rounded-lg border border-gray-200 p-4">
          <div class="flex items-center space-x-3 mb-3">
            <div class="w-12 h-12 bg-gray-200 rounded-full"></div>
            <div class="flex-1">
              <div class="h-4 bg-gray-200 rounded w-3/4 mb-2"></div>
              <div class="h-3 bg-gray-200 rounded w-1/2"></div>
            </div>
          </div>
          <div class="h-3 bg-gray-200 rounded w-full mb-2"></div>
          <div class="h-8 bg-gray-200 rounded w-full"></div>
        </div>
      </div>
    </div>

    <!-- Empty state -->
    <div v-else-if="!isLoading && suggestions.length === 0" class="text-center py-12">
      <UsersIcon class="w-16 h-16 mx-auto text-gray-300 mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">No suggestions available</h3>
      <p class="text-gray-600 mb-4">We couldn't find any people to suggest right now.</p>
      <button
        @click="refreshSuggestions"
        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
      >
        Try Again
      </button>
    </div>

    <!-- Suggestions grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="suggestion in suggestions"
        :key="suggestion.user.id"
        class="bg-white rounded-lg border border-gray-200 hover:border-gray-300 transition-colors duration-200 overflow-hidden"
      >
        <!-- User info -->
        <div class="p-4">
          <div class="flex items-center space-x-3 mb-3">
            <div class="relative">
              <img
                :src="suggestion.user.avatar_url || '/default-avatar.png'"
                :alt="suggestion.user.name"
                class="w-12 h-12 rounded-full object-cover"
              >
              <div
                v-if="suggestion.user.is_online"
                class="absolute bottom-0 right-0 w-3 h-3 bg-green-400 border-2 border-white rounded-full"
              ></div>
            </div>
            
            <div class="flex-1 min-w-0">
              <h3 class="text-sm font-semibold text-gray-900 truncate">
                {{ suggestion.user.name }}
              </h3>
              <p class="text-xs text-gray-500 truncate">
                @{{ suggestion.user.username }}
              </p>
            </div>
            
            <!-- Suggestion score badge -->
            <div
              v-if="suggestion.score >= 0.8"
              class="flex-shrink-0 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
              :class="{
                'bg-green-100 text-green-800': suggestion.score >= 0.9,
                'bg-blue-100 text-blue-800': suggestion.score >= 0.8 && suggestion.score < 0.9
              }"
            >
              {{ suggestion.score >= 0.9 ? 'High match' : 'Good match' }}
            </div>
          </div>

          <!-- Bio preview -->
          <p v-if="suggestion.user.profile?.bio" class="text-sm text-gray-600 mb-3 line-clamp-2">
            {{ suggestion.user.profile.bio }}
          </p>

          <!-- Mutual friends -->
          <div v-if="suggestion.mutual_friends_count > 0" class="mb-3">
            <div class="flex items-center text-xs text-gray-500 mb-2">
              <UsersIcon class="w-3 h-3 mr-1" />
              {{ suggestion.mutual_friends_count }} mutual friend{{ suggestion.mutual_friends_count === 1 ? '' : 's' }}
            </div>
            
            <!-- Mutual friends avatars -->
            <div class="flex -space-x-1 overflow-hidden">
              <img
                v-for="(friend, index) in suggestion.mutual_friends.slice(0, 3)"
                :key="friend.id"
                :src="friend.avatar_url || '/default-avatar.png'"
                :alt="friend.name"
                :title="friend.name"
                class="inline-block w-6 h-6 rounded-full ring-2 ring-white object-cover"
              />
              <div
                v-if="suggestion.mutual_friends.length > 3"
                class="inline-flex items-center justify-center w-6 h-6 text-xs font-medium text-gray-500 bg-gray-100 border-2 border-white rounded-full"
              >
                +{{ suggestion.mutual_friends.length - 3 }}
              </div>
            </div>
          </div>

          <!-- Suggestion reasons -->
          <div v-if="suggestion.reasons.length > 0" class="mb-4">
            <div class="flex flex-wrap gap-1">
              <span
                v-for="reason in suggestion.reasons.slice(0, 2)"
                :key="reason"
                class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"
              >
                {{ reason }}
              </span>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="flex space-x-2">
            <FriendRequestButton
              :user="suggestion.user"
              class="flex-1"
              @request-sent="handleRequestSent(suggestion.user.id)"
            />
            
            <button
              @click="dismissSuggestion(suggestion.user.id)"
              class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
            >
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Load more button -->
    <div v-if="!isLoading && suggestions.length > 0 && hasMore" class="text-center mt-6">
      <button
        @click="loadMoreSuggestions"
        :disabled="isLoadingMore"
        class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <div v-if="isLoadingMore" class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
        {{ isLoadingMore ? 'Loading...' : 'Load More' }}
      </button>
    </div>

    <!-- Algorithm info -->
    <div v-if="algorithmInfo" class="mt-6 p-4 bg-gray-50 rounded-lg">
      <div class="flex items-center text-sm text-gray-600">
        <InformationCircleIcon class="w-4 h-4 mr-2" />
        Suggestions computed {{ formatDistanceToNow(new Date(algorithmInfo.computed_at)) }} ago using {{ algorithmInfo.algorithm }}
      </div>
    </div>

    <!-- Error display -->
    <div v-if="error" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-md">
      <div class="flex">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-400 mr-2" />
        <div class="text-sm text-red-700">
          {{ error }}
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import {
  UsersIcon,
  ArrowPathIcon,
  XMarkIcon,
  InformationCircleIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { friendshipService } from '@/services/friendshipService'
import FriendRequestButton from './FriendRequestButton.vue'
import type { FriendSuggestion } from '@/types/social'
import { formatDistanceToNow } from 'date-fns'

interface Props {
  limit?: number
  autoRefresh?: boolean
  showAlgorithmInfo?: boolean
}

interface Emits {
  (e: 'suggestion-dismissed', userId: number): void
  (e: 'friend-request-sent', userId: number): void
}

const props = withDefaults(defineProps<Props>(), {
  limit: 12,
  autoRefresh: false,
  showAlgorithmInfo: true
})

const emit = defineEmits<Emits>()

// State
const suggestions = ref<FriendSuggestion[]>([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const error = ref<string | null>(null)
const hasMore = ref(true)
const currentPage = ref(1)
const algorithmInfo = ref<{ algorithm: string; computed_at: string } | null>(null)

// Computed
const displayedSuggestions = computed(() => suggestions.value)

// Methods
const loadSuggestions = async (refresh: boolean = false) => {
  if (refresh) {
    isLoading.value = true
    currentPage.value = 1
    hasMore.value = true
  } else {
    isLoadingMore.value = true
  }
  
  error.value = null

  try {
    const result = await friendshipService.getFriendSuggestions(props.limit)
    
    if ('success' in result && result.success) {
      if (refresh) {
        suggestions.value = result.data
      } else {
        suggestions.value = [...suggestions.value, ...result.data]
      }
      
      algorithmInfo.value = result.meta
      hasMore.value = result.data.length === props.limit
    } else {
      error.value = result.message || 'Failed to load friend suggestions'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to load friend suggestions'
  } finally {
    isLoading.value = false
    isLoadingMore.value = false
  }
}

const refreshSuggestions = () => {
  loadSuggestions(true)
}

const loadMoreSuggestions = () => {
  if (!hasMore.value || isLoadingMore.value) return
  currentPage.value++
  loadSuggestions(false)
}

const dismissSuggestion = (userId: number) => {
  // Remove suggestion from local state
  suggestions.value = suggestions.value.filter(s => s.user.id !== userId)
  emit('suggestion-dismissed', userId)
}

const handleRequestSent = (userId: number) => {
  // Remove suggestion after friend request is sent
  dismissSuggestion(userId)
  emit('friend-request-sent', userId)
}

// Lifecycle
onMounted(() => {
  loadSuggestions(true)
  
  // Set up auto-refresh if enabled
  if (props.autoRefresh) {
    const interval = setInterval(() => {
      if (!isLoading.value && !isLoadingMore.value) {
        refreshSuggestions()
      }
    }, 300000) // Refresh every 5 minutes
    
    // Cleanup on unmount
    return () => clearInterval(interval)
  }
})
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Smooth transitions for suggestion cards */
.friend-suggestions > div > div {
  transition: all 0.2s ease-in-out;
}

.friend-suggestions > div > div:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Animation for new suggestions */
@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.friend-suggestions > div > div {
  animation: slideIn 0.3s ease-out;
}

/* Staggered animation for multiple items */
.friend-suggestions > div > div:nth-child(1) { animation-delay: 0s; }
.friend-suggestions > div > div:nth-child(2) { animation-delay: 0.1s; }
.friend-suggestions > div > div:nth-child(3) { animation-delay: 0.2s; }
.friend-suggestions > div > div:nth-child(4) { animation-delay: 0.3s; }
.friend-suggestions > div > div:nth-child(5) { animation-delay: 0.4s; }
.friend-suggestions > div > div:nth-child(6) { animation-delay: 0.5s; }
</style> 