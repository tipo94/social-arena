<template>
  <div class="visibility-selector">
    <!-- Main Visibility Options -->
    <div class="visibility-options">
      <button
        v-for="option in visibilityOptions"
        :key="option.value"
        type="button"
        :disabled="disabled || option.disabled"
        :class="[
          'visibility-option',
          {
            'visibility-selected': modelValue === option.value,
            'visibility-disabled': disabled || option.disabled
          }
        ]"
        @click="selectVisibility(option.value)"
      >
        <div class="visibility-icon">
          <component :is="option.icon" />
        </div>
        <div class="visibility-content">
          <div class="visibility-name">{{ option.label }}</div>
          <div class="visibility-description">{{ option.description }}</div>
        </div>
        
        <!-- Selection Indicator -->
        <div v-if="modelValue === option.value" class="visibility-indicator">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
        </div>
      </button>
    </div>

    <!-- Custom Audience Selection -->
    <div v-if="modelValue === 'custom'" class="custom-audience-section mt-4">
      <label class="block text-sm font-medium text-neutral-700 mb-2">
        Select Specific People
      </label>
      
      <div class="friend-search">
        <input
          v-model="friendSearchQuery"
          type="text"
          placeholder="Search friends..."
          class="input w-full"
          :disabled="disabled"
          @input="searchFriends"
        />
      </div>

      <!-- Selected Friends -->
      <div v-if="selectedFriends.length" class="selected-friends mt-3">
        <div class="text-xs font-medium text-neutral-600 mb-2">
          Selected ({{ selectedFriends.length }})
        </div>
        <div class="friend-chips">
          <div
            v-for="friend in selectedFriends"
            :key="friend.id"
            class="friend-chip"
          >
            <img :src="friend.avatar_url" :alt="friend.name" class="friend-chip-avatar" />
            <span class="friend-chip-name">{{ friend.name }}</span>
            <button
              type="button"
              @click="removeFriend(friend.id)"
              :disabled="disabled"
              class="friend-chip-remove"
            >
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      <!-- Friend Search Results -->
      <div v-if="friendSearchResults.length" class="friend-results mt-3">
        <div class="text-xs font-medium text-neutral-600 mb-2">
          Add People
        </div>
        <div class="friend-list">
          <button
            v-for="friend in friendSearchResults"
            :key="friend.id"
            type="button"
            :disabled="disabled || isFriendSelected(friend.id)"
            class="friend-result"
            @click="addFriend(friend)"
          >
            <img :src="friend.avatar_url" :alt="friend.name" class="friend-result-avatar" />
            <div class="friend-result-info">
              <div class="friend-result-name">{{ friend.name }}</div>
              <div class="friend-result-username">@{{ friend.username }}</div>
            </div>
            <div v-if="isFriendSelected(friend.id)" class="friend-result-selected">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
              </svg>
            </div>
          </button>
        </div>
      </div>

      <!-- No Friends Message -->
      <div v-if="friendSearchQuery && !friendSearchResults.length" class="no-friends-message">
        <div class="text-sm text-neutral-500 text-center py-4">
          No friends found matching "{{ friendSearchQuery }}"
        </div>
      </div>
    </div>

    <!-- Close Friends Note -->
    <div v-if="modelValue === 'close_friends'" class="close-friends-note mt-3">
      <div class="flex items-start space-x-2 p-3 bg-green-50 border border-green-200 rounded-lg">
        <svg class="w-5 h-5 text-green-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <div class="text-sm text-green-800">
          <div class="font-medium">Close Friends</div>
          <div class="mt-1">Only people in your close friends list will see this post. You can manage your close friends list in your profile settings.</div>
        </div>
      </div>
    </div>

    <!-- Group Post Note -->
    <div v-if="modelValue === 'group' && groupId" class="group-note mt-3">
      <div class="flex items-start space-x-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
        <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <div class="text-sm text-blue-800">
          <div class="font-medium">Group Post</div>
          <div class="mt-1">This post will only be visible to members of the selected group.</div>
        </div>
      </div>
    </div>

    <!-- Selected Visibility Summary -->
    <div v-if="selectedOption" class="visibility-summary mt-4">
      <div class="flex items-center justify-between p-3 bg-neutral-50 rounded-lg border border-neutral-200">
        <div class="flex items-center space-x-3">
          <component :is="selectedOption.icon" class="w-5 h-5 text-neutral-600" />
          <div>
            <div class="text-sm font-medium text-neutral-900">{{ selectedOption.label }}</div>
            <div class="text-xs text-neutral-600">{{ getAudienceDescription() }}</div>
          </div>
        </div>
        
        <button
          v-if="modelValue === 'custom' && selectedFriends.length"
          type="button"
          @click="clearCustomAudience"
          :disabled="disabled"
          class="text-xs text-primary-600 hover:text-primary-700"
        >
          Clear Selection
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, defineComponent, h } from 'vue'
import { useAuthStore } from '@/stores/auth'

interface Friend {
  id: number
  name: string
  username: string
  avatar_url: string
  is_close_friend?: boolean
}

interface VisibilityOption {
  value: string
  label: string
  description: string
  icon: any
  disabled?: boolean
}

interface Props {
  modelValue: string
  customAudience?: number[]
  groupId?: number | null
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  customAudience: () => [],
  groupId: null,
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'update:customAudience': [value: number[]]
  change: [value: string]
}>()

const authStore = useAuthStore()

// Refs
const friendSearchQuery = ref('')
const friendSearchResults = ref<Friend[]>([])
const selectedFriends = ref<Friend[]>([])

// Icon components
const PublicIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z'
  })
]))

const FriendsIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z'
  })
]))

const CloseFriendsIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z'
  })
]))

const FriendsOfFriendsIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'
  })
]))

const PrivateIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'
  })
]))

const CustomIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'
  }),
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M15 12a3 3 0 11-6 0 3 3 0 016 0z'
  })
]))

const GroupIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'
  })
]))

// Computed
const visibilityOptions = computed((): VisibilityOption[] => {
  const baseOptions: VisibilityOption[] = [
    {
      value: 'public',
      label: 'Public',
      description: 'Anyone can see this post',
      icon: PublicIcon,
    },
    {
      value: 'friends',
      label: 'Friends',
      description: 'Only your friends can see this post',
      icon: FriendsIcon,
    },
    {
      value: 'close_friends',
      label: 'Close Friends',
      description: 'Only your close friends can see this post',
      icon: CloseFriendsIcon,
    },
    {
      value: 'friends_of_friends',
      label: 'Friends of Friends',
      description: 'Your friends and their friends can see this post',
      icon: FriendsOfFriendsIcon,
    },
    {
      value: 'custom',
      label: 'Custom',
      description: 'Choose specific people who can see this post',
      icon: CustomIcon,
    },
    {
      value: 'private',
      label: 'Private',
      description: 'Only you can see this post',
      icon: PrivateIcon,
    },
  ]

  // Add group option if groupId is provided
  if (props.groupId) {
    baseOptions.splice(1, 0, {
      value: 'group',
      label: 'Group Members',
      description: 'Only group members can see this post',
      icon: GroupIcon,
    })
  }

  return baseOptions
})

const selectedOption = computed(() => {
  return visibilityOptions.value.find(option => option.value === props.modelValue)
})

// Methods
const selectVisibility = (value: string) => {
  if (props.disabled) return
  
  emit('update:modelValue', value)
  emit('change', value)
  
  // Clear custom audience if switching away from custom
  if (value !== 'custom') {
    selectedFriends.value = []
    emit('update:customAudience', [])
  }
}

const searchFriends = async () => {
  if (!friendSearchQuery.value.trim()) {
    friendSearchResults.value = []
    return
  }

  // In a real app, this would call an API to search friends
  // For now, we'll use mock data
  const mockFriends = getMockFriends()
  
  friendSearchResults.value = mockFriends.filter(friend =>
    friend.name.toLowerCase().includes(friendSearchQuery.value.toLowerCase()) ||
    friend.username.toLowerCase().includes(friendSearchQuery.value.toLowerCase())
  ).filter(friend => !isFriendSelected(friend.id))
}

const addFriend = (friend: Friend) => {
  if (props.disabled || isFriendSelected(friend.id)) return
  
  selectedFriends.value.push(friend)
  updateCustomAudience()
  
  // Remove from search results
  friendSearchResults.value = friendSearchResults.value.filter(f => f.id !== friend.id)
}

const removeFriend = (friendId: number) => {
  if (props.disabled) return
  
  selectedFriends.value = selectedFriends.value.filter(f => f.id !== friendId)
  updateCustomAudience()
  
  // Re-run search to potentially add back to results
  searchFriends()
}

const isFriendSelected = (friendId: number): boolean => {
  return selectedFriends.value.some(f => f.id === friendId)
}

const updateCustomAudience = () => {
  const audienceIds = selectedFriends.value.map(f => f.id)
  emit('update:customAudience', audienceIds)
}

const clearCustomAudience = () => {
  if (props.disabled) return
  
  selectedFriends.value = []
  updateCustomAudience()
  friendSearchQuery.value = ''
  friendSearchResults.value = []
}

const getAudienceDescription = (): string => {
  if (!selectedOption.value) return ''
  
  switch (props.modelValue) {
    case 'custom':
      const count = selectedFriends.value.length
      if (count === 0) return 'No one selected'
      return `${count} ${count === 1 ? 'person' : 'people'} selected`
    
    case 'close_friends':
      return 'Visible to your close friends list'
    
    case 'group':
      return 'Visible to group members only'
    
    default:
      return selectedOption.value.description
  }
}

const getMockFriends = (): Friend[] => {
  // Mock friend data for development
  return [
    { id: 1, name: 'John Doe', username: 'johndoe', avatar_url: '/api/placeholder/32/32', is_close_friend: true },
    { id: 2, name: 'Jane Smith', username: 'janesmith', avatar_url: '/api/placeholder/32/32', is_close_friend: false },
    { id: 3, name: 'Book Lover', username: 'booklover', avatar_url: '/api/placeholder/32/32', is_close_friend: true },
    { id: 4, name: 'Reading Fan', username: 'readingfan', avatar_url: '/api/placeholder/32/32', is_close_friend: false },
    { id: 5, name: 'Story Teller', username: 'storyteller', avatar_url: '/api/placeholder/32/32', is_close_friend: false },
    { id: 6, name: 'Novel Writer', username: 'novelwriter', avatar_url: '/api/placeholder/32/32', is_close_friend: true },
    { id: 7, name: 'Poetry Lover', username: 'poetrylover', avatar_url: '/api/placeholder/32/32', is_close_friend: false },
    { id: 8, name: 'Classic Reader', username: 'classicreader', avatar_url: '/api/placeholder/32/32', is_close_friend: false },
  ]
}

// Watchers
watch(() => props.customAudience, (newAudience) => {
  if (newAudience && newAudience.length > 0) {
    // Load selected friends based on custom audience IDs
    // In a real app, this would fetch friend data from API
    const mockFriends = getMockFriends()
    selectedFriends.value = mockFriends.filter(friend => newAudience.includes(friend.id))
  } else {
    selectedFriends.value = []
  }
}, { immediate: true })

// Lifecycle
onMounted(() => {
  // Initialize selected friends if custom audience is provided
  if (props.modelValue === 'custom' && props.customAudience?.length) {
    const mockFriends = getMockFriends()
    selectedFriends.value = mockFriends.filter(friend => props.customAudience!.includes(friend.id))
  }
})
</script>

<style scoped>
.visibility-selector {
  @apply w-full;
}

.visibility-options {
  @apply grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3;
}

.visibility-option {
  @apply relative flex items-center p-4 border-2 border-neutral-200 rounded-lg bg-white hover:border-primary-300 hover:bg-primary-50 transition-all duration-200 text-left;
}

.visibility-option:focus {
  @apply outline-none ring-2 ring-primary-500 ring-opacity-50;
}

.visibility-selected {
  @apply border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-opacity-20;
}

.visibility-disabled {
  @apply opacity-50 cursor-not-allowed;
}

.visibility-icon {
  @apply flex-shrink-0 w-8 h-8 flex items-center justify-center text-neutral-600 mr-3;
}

.visibility-selected .visibility-icon {
  @apply text-primary-600;
}

.visibility-content {
  @apply flex-1 min-w-0;
}

.visibility-name {
  @apply text-sm font-medium text-neutral-900 mb-1;
}

.visibility-description {
  @apply text-xs text-neutral-500;
}

.visibility-indicator {
  @apply absolute top-2 right-2 w-5 h-5 bg-primary-500 text-white rounded-full flex items-center justify-center;
}

.custom-audience-section {
  @apply border-t border-neutral-200 pt-4;
}

.friend-search {
  @apply relative;
}

.selected-friends {
  @apply p-3 bg-neutral-50 rounded-lg border border-neutral-200;
}

.friend-chips {
  @apply flex flex-wrap gap-2;
}

.friend-chip {
  @apply flex items-center space-x-2 bg-white border border-neutral-200 rounded-full px-3 py-1.5 text-sm;
}

.friend-chip-avatar {
  @apply w-6 h-6 rounded-full;
}

.friend-chip-name {
  @apply font-medium text-neutral-900;
}

.friend-chip-remove {
  @apply text-neutral-400 hover:text-red-500 transition-colors;
}

.friend-results {
  @apply border border-neutral-200 rounded-lg;
}

.friend-list {
  @apply max-h-60 overflow-y-auto divide-y divide-neutral-200;
}

.friend-result {
  @apply flex items-center p-3 hover:bg-neutral-50 transition-colors w-full text-left;
}

.friend-result:disabled {
  @apply opacity-50 cursor-not-allowed;
}

.friend-result-avatar {
  @apply w-10 h-10 rounded-full mr-3;
}

.friend-result-info {
  @apply flex-1 min-w-0;
}

.friend-result-name {
  @apply text-sm font-medium text-neutral-900 truncate;
}

.friend-result-username {
  @apply text-xs text-neutral-500;
}

.friend-result-selected {
  @apply text-green-600;
}

.no-friends-message {
  @apply border border-neutral-200 rounded-lg;
}

.close-friends-note,
.group-note {
  @apply text-sm;
}

.visibility-summary {
  @apply border-t border-neutral-200 pt-4;
}
</style> 