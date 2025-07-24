<template>
  <div class="notification-center">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200">
      <div class="flex items-center space-x-3">
        <h2 class="text-lg font-semibold text-gray-900">Notifications</h2>
        <span 
          v-if="unreadCount > 0" 
          class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800"
        >
          {{ unreadCount }}
        </span>
      </div>
      
      <div class="flex items-center space-x-2">
        <!-- Filter dropdown -->
        <div class="relative">
          <button
            @click="showFilters = !showFilters"
            class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
          >
            <FunnelIcon class="w-5 h-5" />
          </button>
          
          <div
            v-if="showFilters"
            class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
          >
            <button
              v-for="filter in filterOptions"
              :key="filter.value"
              @click="selectFilter(filter.value)"
              class="w-full px-4 py-2 text-left text-sm hover:bg-gray-50 flex items-center justify-between"
              :class="{ 'text-blue-600 bg-blue-50': activeFilter === filter.value }"
            >
              <span>{{ filter.label }}</span>
              <span v-if="filter.count > 0" class="text-xs text-gray-500">{{ filter.count }}</span>
            </button>
          </div>
        </div>
        
        <!-- Mark all as read -->
        <button
          v-if="unreadCount > 0"
          @click="markAllAsRead"
          :disabled="isMarkingRead"
          class="text-sm text-blue-600 hover:text-blue-800 disabled:opacity-50"
        >
          Mark all read
        </button>
        
        <!-- Settings -->
        <button
          @click="$emit('settings')"
          class="p-2 text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100"
        >
          <CogIcon class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- Notifications list -->
    <div class="flex-1 overflow-y-auto">
      <div v-if="isLoading && notifications.length === 0" class="p-4">
        <div class="space-y-4">
          <div v-for="i in 5" :key="i" class="animate-pulse">
            <div class="flex space-x-3">
              <div class="w-10 h-10 bg-gray-200 rounded-full"></div>
              <div class="flex-1 space-y-2">
                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                <div class="h-3 bg-gray-200 rounded w-1/2"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="filteredNotifications.length === 0" class="p-8 text-center">
        <BellIcon class="w-12 h-12 mx-auto text-gray-300 mb-4" />
        <h3 class="text-lg font-medium text-gray-900 mb-2">No notifications</h3>
        <p class="text-gray-500">
          {{ activeFilter === 'all' ? "You're all caught up!" : `No ${activeFilter} notifications` }}
        </p>
      </div>

      <div v-else class="divide-y divide-gray-200">
        <NotificationItem
          v-for="notification in filteredNotifications"
          :key="notification.id"
          :notification="notification"
          @click="handleNotificationClick"
          @mark-read="markAsRead"
          @mark-unread="markAsUnread"
          @dismiss="dismissNotification"
          @delete="deleteNotification"
        />
      </div>

      <!-- Load more -->
      <div v-if="hasMore && !isLoadingMore" class="p-4">
        <button
          @click="loadMore"
          class="w-full py-2 text-sm text-blue-600 hover:text-blue-800"
        >
          Load more notifications
        </button>
      </div>

      <div v-if="isLoadingMore" class="p-4">
        <div class="animate-pulse space-y-4">
          <div v-for="i in 3" :key="i" class="flex space-x-3">
            <div class="w-10 h-10 bg-gray-200 rounded-full"></div>
            <div class="flex-1 space-y-2">
              <div class="h-4 bg-gray-200 rounded w-3/4"></div>
              <div class="h-3 bg-gray-200 rounded w-1/2"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import {
  BellIcon,
  FunnelIcon,
  CogIcon
} from '@heroicons/vue/24/outline'
import { useNotificationStore } from '@/stores/notification'
import type { Notification } from '@/types/notifications'
import NotificationItem from './NotificationItem.vue'

interface Props {
  autoRefresh?: boolean
  refreshInterval?: number
  maxHeight?: string
}

interface Emits {
  (e: 'notification-click', notification: Notification): void
  (e: 'settings'): void
}

const props = withDefaults(defineProps<Props>(), {
  autoRefresh: true,
  refreshInterval: 30000, // 30 seconds
  maxHeight: '500px'
})

const emit = defineEmits<Emits>()

const notificationStore = useNotificationStore()

// State
const showFilters = ref(false)
const activeFilter = ref('all')
const isMarkingRead = ref(false)
const refreshTimer = ref<NodeJS.Timeout>()

// Computed
const notifications = computed(() => notificationStore.notifications)
const unreadCount = computed(() => notificationStore.unreadCount)
const isLoading = computed(() => notificationStore.isLoading)
const isLoadingMore = computed(() => notificationStore.isLoadingMore)
const hasMore = computed(() => notificationStore.hasMore)

const filterOptions = computed(() => [
  { value: 'all', label: 'All', count: notifications.value.length },
  { value: 'unread', label: 'Unread', count: unreadCount.value },
  { value: 'like', label: 'Likes', count: notifications.value.filter(n => n.type === 'like').length },
  { value: 'comment', label: 'Comments', count: notifications.value.filter(n => n.type === 'comment').length },
  { value: 'follow', label: 'Follows', count: notifications.value.filter(n => n.type === 'follow').length },
  { value: 'friend_request', label: 'Friend Requests', count: notifications.value.filter(n => n.type === 'friend_request').length },
])

const filteredNotifications = computed(() => {
  let filtered = notifications.value

  if (activeFilter.value === 'unread') {
    filtered = filtered.filter(n => n.is_unread)
  } else if (activeFilter.value !== 'all') {
    filtered = filtered.filter(n => n.type === activeFilter.value)
  }

  return filtered
})

// Methods
const loadNotifications = async () => {
  await notificationStore.fetchNotifications()
}

const loadMore = async () => {
  await notificationStore.loadMore()
}

const selectFilter = (filter: string) => {
  activeFilter.value = filter
  showFilters.value = false
}

const markAllAsRead = async () => {
  isMarkingRead.value = true
  try {
    await notificationStore.markAllAsRead()
  } finally {
    isMarkingRead.value = false
  }
}

const markAsRead = async (notification: Notification) => {
  await notificationStore.markAsRead(notification.id)
}

const markAsUnread = async (notification: Notification) => {
  await notificationStore.markAsUnread(notification.id)
}

const dismissNotification = async (notification: Notification) => {
  await notificationStore.dismissNotification(notification.id)
}

const deleteNotification = async (notification: Notification) => {
  await notificationStore.deleteNotification(notification.id)
}

const handleNotificationClick = (notification: Notification) => {
  // Mark as read if unread
  if (notification.is_unread) {
    markAsRead(notification)
  }
  
  // Navigate to notification target
  if (notification.action_url) {
    // Use router to navigate
    // this.$router.push(notification.action_url)
  }
  
  emit('notification-click', notification)
}

const startAutoRefresh = () => {
  if (props.autoRefresh && props.refreshInterval > 0) {
    refreshTimer.value = setInterval(() => {
      notificationStore.fetchUnreadCount()
    }, props.refreshInterval)
  }
}

const stopAutoRefresh = () => {
  if (refreshTimer.value) {
    clearInterval(refreshTimer.value)
    refreshTimer.value = undefined
  }
}

// Close filter dropdown when clicking outside
const handleClickOutside = (event: Event) => {
  if (showFilters.value) {
    showFilters.value = false
  }
}

// Lifecycle
onMounted(() => {
  loadNotifications()
  startAutoRefresh()
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  stopAutoRefresh()
  document.removeEventListener('click', handleClickOutside)
})

// Watch for filter changes
watch(activeFilter, () => {
  // Could implement server-side filtering here
})
</script>

<style scoped>
.notification-center {
  @apply bg-white rounded-lg shadow-lg border border-gray-200 flex flex-col;
  height: v-bind(maxHeight);
}
</style> 