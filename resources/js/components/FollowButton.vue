<template>
  <div class="follow-button">
    <!-- Main follow button -->
    <button
      v-if="!isLoading && followStatus === 'not_following'"
      @click="handleFollow"
      :disabled="isLoading"
      class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
    >
      <UserPlusIcon class="w-4 h-4 mr-2" />
      Follow
    </button>

    <!-- Following button with dropdown -->
    <div v-else-if="!isLoading && followStatus === 'following'" class="relative">
      <button
        @click="toggleDropdown"
        :disabled="isLoading"
        class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors duration-200"
      >
        <CheckIcon class="w-4 h-4 mr-2 text-green-600" />
        Following
        <ChevronDownIcon class="w-4 h-4 ml-1" />
      </button>

      <!-- Dropdown menu -->
      <div
        v-if="showDropdown"
        v-click-outside="closeDropdown"
        class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
      >
        <button
          @click="handleUnfollow"
          class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center"
        >
          <UserMinusIcon class="w-4 h-4 mr-2" />
          Unfollow
        </button>
        
        <div class="border-t border-gray-100 my-1"></div>
        
        <button
          v-if="follow"
          @click="toggleMute"
          class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center"
        >
          <SpeakerXMarkIcon v-if="!follow.is_muted" class="w-4 h-4 mr-2" />
          <SpeakerWaveIcon v-else class="w-4 h-4 mr-2" />
          {{ follow.is_muted ? 'Unmute' : 'Mute' }}
        </button>
        
        <button
          v-if="follow"
          @click="toggleNotifications"
          class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center"
        >
          <BellSlashIcon v-if="follow.show_notifications" class="w-4 h-4 mr-2" />
          <BellIcon v-else class="w-4 h-4 mr-2" />
          {{ follow.show_notifications ? 'Turn off notifications' : 'Turn on notifications' }}
        </button>
        
        <button
          v-if="follow"
          @click="toggleCloseFriend"
          class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center"
        >
          <HeartIcon v-if="!follow.is_close_friend" class="w-4 h-4 mr-2" />
          <HeartIcon v-else class="w-4 h-4 mr-2 text-red-500 fill-current" />
          {{ follow.is_close_friend ? 'Remove from close friends' : 'Add to close friends' }}
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

    <!-- Compact mode -->
    <button
      v-if="compact && !isLoading"
      @click="followStatus === 'following' ? handleUnfollow() : handleFollow()"
      :disabled="isLoading"
      class="inline-flex items-center p-2 border border-transparent rounded-full shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors duration-200"
      :class="followStatus === 'following' 
        ? 'bg-green-600 hover:bg-green-700 focus:ring-green-500' 
        : 'bg-blue-600 hover:bg-blue-700 focus:ring-blue-500'"
    >
      <CheckIcon v-if="followStatus === 'following'" class="w-4 h-4" />
      <UserPlusIcon v-else class="w-4 h-4" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import {
  UserPlusIcon,
  UserMinusIcon,
  CheckIcon,
  ChevronDownIcon,
  BellIcon,
  BellSlashIcon,
  SpeakerWaveIcon,
  SpeakerXMarkIcon,
  HeartIcon
} from '@heroicons/vue/24/outline'
import { followService } from '@/services/followService'
import type { Follow, FollowSettings } from '@/types/social'
import type { User } from '@/types/auth'

interface Props {
  user: User
  compact?: boolean
  initialFollowStatus?: 'following' | 'not_following'
  initialFollow?: Follow
}

interface Emits {
  (e: 'follow', follow: Follow): void
  (e: 'unfollow', userId: number): void
  (e: 'settings-updated', follow: Follow): void
}

const props = withDefaults(defineProps<Props>(), {
  compact: false,
  initialFollowStatus: 'not_following'
})

const emit = defineEmits<Emits>()

// State
const isLoading = ref(false)
const followStatus = ref<'following' | 'not_following'>(props.initialFollowStatus)
const follow = ref<Follow | null>(props.initialFollow || null)
const showDropdown = ref(false)
const error = ref<string | null>(null)

// Computed
const isCurrentUser = computed(() => {
  // You'll need to get current user from auth store
  return false // TODO: Implement current user check
})

// Methods
const handleFollow = async () => {
  if (isLoading.value || isCurrentUser.value) return

  isLoading.value = true
  error.value = null

  try {
    const result = await followService.followUser(props.user.id)
    
    if ('success' in result && result.success) {
      followStatus.value = 'following'
      follow.value = result.data
      emit('follow', result.data)
    } else {
      error.value = result.message || 'Failed to follow user'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to follow user'
  } finally {
    isLoading.value = false
  }
}

const handleUnfollow = async () => {
  if (isLoading.value || isCurrentUser.value) return

  isLoading.value = true
  error.value = null
  showDropdown.value = false

  try {
    const result = await followService.unfollowUser(props.user.id)
    
    if ('success' in result && result.success) {
      followStatus.value = 'not_following'
      follow.value = null
      emit('unfollow', props.user.id)
    } else {
      error.value = result.message || 'Failed to unfollow user'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to unfollow user'
  } finally {
    isLoading.value = false
  }
}

const updateFollowSettings = async (settings: FollowSettings) => {
  if (!follow.value || isLoading.value) return

  isLoading.value = true
  error.value = null

  try {
    const result = await followService.updateFollowSettings(follow.value.id, settings)
    
    if ('success' in result && result.success) {
      follow.value = result.data
      emit('settings-updated', result.data)
    } else {
      error.value = result.message || 'Failed to update settings'
    }
  } catch (err: any) {
    error.value = err.message || 'Failed to update settings'
  } finally {
    isLoading.value = false
  }
}

const toggleMute = () => {
  if (!follow.value) return
  updateFollowSettings({ is_muted: !follow.value.is_muted })
  showDropdown.value = false
}

const toggleNotifications = () => {
  if (!follow.value) return
  updateFollowSettings({ show_notifications: !follow.value.show_notifications })
  showDropdown.value = false
}

const toggleCloseFriend = () => {
  if (!follow.value) return
  updateFollowSettings({ is_close_friend: !follow.value.is_close_friend })
  showDropdown.value = false
}

const toggleDropdown = () => {
  showDropdown.value = !showDropdown.value
}

const closeDropdown = () => {
  showDropdown.value = false
}

// Watch for prop changes
watch(() => props.initialFollowStatus, (newStatus) => {
  followStatus.value = newStatus
})

watch(() => props.initialFollow, (newFollow) => {
  follow.value = newFollow
})

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
.follow-button {
  position: relative;
}

/* Animation for state changes */
.follow-button button {
  transition: all 0.2s ease-in-out;
}

/* Dropdown animation */
.follow-button .absolute {
  animation: fadeIn 0.15s ease-out;
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
</style> 