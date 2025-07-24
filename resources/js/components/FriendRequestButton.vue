<template>
  <div class="friend-request-button">
    <!-- Send Friend Request -->
    <button
      v-if="!isLoading && friendshipStatus === 'none'"
      @click="showRequestModal = true"
      :disabled="isLoading"
      class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
    >
      <UserPlusIcon class="w-4 h-4 mr-2" />
      Add Friend
    </button>

    <!-- Pending Request (Sent) -->
    <div v-else-if="!isLoading && friendshipStatus === 'pending_sent'" class="relative">
      <button
        @click="toggleDropdown"
        :disabled="isLoading"
        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
      >
        <ClockIcon class="w-4 h-4 mr-2 text-yellow-600" />
        Request Sent
        <ChevronDownIcon class="w-4 h-4 ml-1" />
      </button>

      <!-- Dropdown for pending sent -->
      <div
        v-if="showDropdown"
        v-click-outside="closeDropdown"
        class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
      >
        <button
          @click="cancelRequest"
          class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center"
        >
          <XMarkIcon class="w-4 h-4 mr-2" />
          Cancel Request
        </button>
      </div>
    </div>

    <!-- Pending Request (Received) -->
    <div v-else-if="!isLoading && friendshipStatus === 'pending_received'" class="flex space-x-2">
      <button
        @click="acceptRequest"
        :disabled="isLoading"
        class="inline-flex items-center px-3 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
      >
        <CheckIcon class="w-4 h-4 mr-1" />
        Accept
      </button>
      
      <button
        @click="declineRequest"
        :disabled="isLoading"
        class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
      >
        <XMarkIcon class="w-4 h-4 mr-1" />
        Decline
      </button>
    </div>

    <!-- Friends -->
    <div v-else-if="!isLoading && friendshipStatus === 'friends'" class="relative">
      <button
        @click="toggleDropdown"
        :disabled="isLoading"
        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
      >
        <CheckIcon class="w-4 h-4 mr-2 text-green-600" />
        Friends
        <ChevronDownIcon class="w-4 h-4 ml-1" />
      </button>

      <!-- Dropdown for friends -->
      <div
        v-if="showDropdown"
        v-click-outside="closeDropdown"
        class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
      >
        <button
          @click="unfriend"
          class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center"
        >
          <UserMinusIcon class="w-4 h-4 mr-2" />
          Unfriend
        </button>
        
        <div class="border-t border-gray-100 my-1"></div>
        
        <button
          @click="blockUser"
          class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center"
        >
          <NoSymbolIcon class="w-4 h-4 mr-2" />
          Block
        </button>
      </div>
    </div>

    <!-- Blocked -->
    <div v-else-if="!isLoading && friendshipStatus === 'blocked'" class="relative">
      <button
        @click="toggleDropdown"
        :disabled="isLoading"
        class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-red-50 hover:bg-red-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
      >
        <NoSymbolIcon class="w-4 h-4 mr-2" />
        Blocked
        <ChevronDownIcon class="w-4 h-4 ml-1" />
      </button>

      <!-- Dropdown for blocked -->
      <div
        v-if="showDropdown"
        v-click-outside="closeDropdown"
        class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
      >
        <button
          @click="unblockUser"
          class="w-full px-4 py-2 text-left text-sm text-blue-600 hover:bg-blue-50 flex items-center"
        >
          <CheckIcon class="w-4 h-4 mr-2" />
          Unblock
        </button>
      </div>
    </div>

    <!-- Loading state -->
    <button
      v-if="isLoading"
      disabled
      class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white cursor-not-allowed"
    >
      <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-600 mr-2"></div>
      Loading...
    </button>

    <!-- Friend Request Modal -->
    <div
      v-if="showRequestModal"
      class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50"
      @click="showRequestModal = false"
    >
      <div
        class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white"
        @click.stop
      >
        <div class="mt-3">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-medium text-gray-900">
              Send Friend Request
            </h3>
            <button
              @click="showRequestModal = false"
              class="text-gray-400 hover:text-gray-600"
            >
              <XMarkIcon class="w-5 h-5" />
            </button>
          </div>
          
          <div class="mb-4">
            <p class="text-sm text-gray-600 mb-2">
              Send a friend request to {{ user.name }}
            </p>
            
            <div class="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
              <img
                :src="user.avatar_url || '/default-avatar.png'"
                :alt="user.name"
                class="w-12 h-12 rounded-full object-cover"
              >
              <div>
                <p class="font-medium text-gray-900">{{ user.name }}</p>
                <p class="text-sm text-gray-500">@{{ user.username }}</p>
              </div>
            </div>
          </div>
          
          <div class="mb-4">
            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
              Message (optional)
            </label>
            <textarea
              id="message"
              v-model="requestMessage"
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              placeholder="Add a personal message..."
              maxlength="500"
            ></textarea>
            <p class="text-xs text-gray-500 mt-1">
              {{ requestMessage.length }}/500 characters
            </p>
          </div>
          
          <div class="flex justify-end space-x-3">
            <button
              @click="showRequestModal = false"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
              Cancel
            </button>
            <button
              @click="sendRequest"
              :disabled="isLoading"
              class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed"
            >
              Send Request
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Error display -->
    <div v-if="error" class="mt-2 text-sm text-red-600">
      {{ error }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  UserPlusIcon,
  UserMinusIcon,
  CheckIcon,
  XMarkIcon,
  ChevronDownIcon,
  ClockIcon,
  NoSymbolIcon
} from '@heroicons/vue/24/outline'
import { friendshipService } from '@/services/friendshipService'
import type { Friendship, FriendshipStatus } from '@/types/social'
import type { User } from '@/types/auth'

interface Props {
  user: User
  initialStatus?: FriendshipStatus | 'none'
  initialFriendship?: Friendship
}

interface Emits {
  (e: 'request-sent', friendship: Friendship): void
  (e: 'request-accepted', friendship: Friendship): void
  (e: 'request-declined', friendshipId: number): void
  (e: 'request-cancelled', friendshipId: number): void
  (e: 'unfriended', friendshipId: number): void
  (e: 'blocked', friendship: Friendship): void
  (e: 'unblocked', friendship: Friendship): void
}

const props = withDefaults(defineProps<Props>(), {
  initialStatus: 'none'
})

const emit = defineEmits<Emits>()

// State
const isLoading = ref(false)
const friendshipStatus = ref<FriendshipStatus | 'none'>(props.initialStatus)
const friendship = ref<Friendship | null>(props.initialFriendship || null)
const showDropdown = ref(false)
const showRequestModal = ref(false)
const requestMessage = ref('')
const error = ref<string | null>(null)

// Computed
const isCurrentUser = computed(() => {
  // TODO: Implement current user check from auth store
  return false
})

// Methods
const sendRequest = async () => {
  if (isLoading.value || isCurrentUser.value) return

  isLoading.value = true
  error.value = null

  try {
    const result = await friendshipService.sendFriendRequest({
      user_id: props.user.id,
      message: requestMessage.value.trim() || undefined
    })
    
    if ('success' in result && result.success) {
      friendshipStatus.value = 'pending'
      friendship.value = result.data
      showRequestModal.value = false
      requestMessage.value = ''
      emit('request-sent', result.data)
    } else {
      error.value = result.message || 'Failed to send friend request'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to send friend request'
  } finally {
    isLoading.value = false
  }
}

const acceptRequest = async () => {
  if (!friendship.value || isLoading.value) return

  isLoading.value = true
  error.value = null

  try {
    const result = await friendshipService.acceptFriendRequest(friendship.value.id)
    
    if ('success' in result && result.success) {
      friendshipStatus.value = 'accepted'
      friendship.value = result.data
      emit('request-accepted', result.data)
    } else {
      error.value = result.message || 'Failed to accept friend request'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to accept friend request'
  } finally {
    isLoading.value = false
  }
}

const declineRequest = async () => {
  if (!friendship.value || isLoading.value) return

  isLoading.value = true
  error.value = null

  try {
    const result = await friendshipService.declineFriendRequest(friendship.value.id)
    
    if ('success' in result && result.success) {
      friendshipStatus.value = 'none'
      const friendshipId = friendship.value.id
      friendship.value = null
      emit('request-declined', friendshipId)
    } else {
      error.value = result.message || 'Failed to decline friend request'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to decline friend request'
  } finally {
    isLoading.value = false
  }
}

const cancelRequest = async () => {
  if (!friendship.value || isLoading.value) return

  isLoading.value = true
  error.value = null
  showDropdown.value = false

  try {
    const result = await friendshipService.cancelFriendRequest(friendship.value.id)
    
    if ('success' in result && result.success) {
      friendshipStatus.value = 'none'
      const friendshipId = friendship.value.id
      friendship.value = null
      emit('request-cancelled', friendshipId)
    } else {
      error.value = result.message || 'Failed to cancel friend request'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to cancel friend request'
  } finally {
    isLoading.value = false
  }
}

const unfriend = async () => {
  if (!friendship.value || isLoading.value) return

  isLoading.value = true
  error.value = null
  showDropdown.value = false

  try {
    const result = await friendshipService.unfriend(friendship.value.id)
    
    if ('success' in result && result.success) {
      friendshipStatus.value = 'none'
      const friendshipId = friendship.value.id
      friendship.value = null
      emit('unfriended', friendshipId)
    } else {
      error.value = result.message || 'Failed to unfriend user'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to unfriend user'
  } finally {
    isLoading.value = false
  }
}

const blockUser = async () => {
  if (isLoading.value) return

  isLoading.value = true
  error.value = null
  showDropdown.value = false

  try {
    const result = await friendshipService.blockUser(props.user.id)
    
    if ('success' in result && result.success) {
      friendshipStatus.value = 'blocked'
      friendship.value = result.data
      emit('blocked', result.data)
    } else {
      error.value = result.message || 'Failed to block user'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to block user'
  } finally {
    isLoading.value = false
  }
}

const unblockUser = async () => {
  if (!friendship.value || isLoading.value) return

  isLoading.value = true
  error.value = null
  showDropdown.value = false

  try {
    const result = await friendshipService.unblockUser(friendship.value.id)
    
    if ('success' in result && result.success) {
      friendshipStatus.value = 'none'
      friendship.value = result.data
      emit('unblocked', result.data)
    } else {
      error.value = result.message || 'Failed to unblock user'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to unblock user'
  } finally {
    isLoading.value = false
  }
}

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value
}

const closeDropdown = () => {
  showDropdown.value = false
}

// Watch for prop changes
watch(() => props.initialStatus, (newStatus) => {
  friendshipStatus.value = newStatus
})

watch(() => props.initialFriendship, (newFriendship) => {
  friendship.value = newFriendship
})

// Computed friendship status for display
const computedFriendshipStatus = computed(() => {
  if (!friendship.value) return 'none'
  
  if (friendship.value.status === 'pending') {
    // Check if current user sent or received the request
    // TODO: Get current user ID from auth store
    const currentUserId = 1 // Replace with actual current user ID
    return friendship.value.user_id === currentUserId ? 'pending_sent' : 'pending_received'
  }
  
  if (friendship.value.status === 'accepted') return 'friends'
  if (friendship.value.status === 'blocked') return 'blocked'
  
  return 'none'
})

// Update friendshipStatus based on computed value
watch(computedFriendshipStatus, (newStatus) => {
  friendshipStatus.value = newStatus
}, { immediate: true })

// Directives
const vClickOutside = {
  mounted(el: HTMLElement, binding: any) {
    el.clickOutsideEvent = (event: Event) => {
      if (!(el === event.target || el.contains(event.target as Node))) {
        binding.value()
      }
    }
    document.addEventListener('click', el.clickOutsideEvent)
  },
  unmounted(el: HTMLElement) {
    document.removeEventListener('click', el.clickOutsideEvent)
  }
}
</script>

<style scoped>
.friend-request-button {
  position: relative;
}

/* Animation for state changes */
.friend-request-button button {
  transition: all 0.2s ease-in-out;
}

/* Dropdown animation */
.friend-request-button .absolute {
  animation: fadeIn 0.15s ease-out;
}

/* Modal animation */
.fixed {
  animation: modalFadeIn 0.2s ease-out;
}

.relative.top-20 {
  animation: modalSlideIn 0.2s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes modalSlideIn {
  from {
    opacity: 0;
    transform: translateY(-16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style> 