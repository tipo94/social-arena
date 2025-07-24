<template>
  <div 
    class="notification-item"
    :class="{ 
      'notification-unread': notification.is_unread,
      'notification-dismissed': notification.is_dismissed
    }"
    @click="handleClick"
  >
    <div class="flex space-x-3 p-4">
      <!-- Actor avatar -->
      <div class="flex-shrink-0">
        <img
          v-if="notification.actor?.avatar_url"
          :src="notification.actor.avatar_url"
          :alt="notification.actor.name"
          class="w-10 h-10 rounded-full object-cover"
        />
        <div v-else class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center">
          <component :is="getNotificationIcon()" class="w-5 h-5 text-gray-600" />
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <!-- Title and message -->
            <p class="text-sm font-medium text-gray-900 mb-1">
              {{ notification.title }}
            </p>
            <p class="text-sm text-gray-600 mb-2">
              {{ notification.message }}
            </p>

            <!-- Related content preview -->
            <div v-if="hasPreview" class="notification-preview mb-2">
              <div 
                v-if="notification.notifiable?.type === 'post'"
                class="flex items-center space-x-2 text-xs text-gray-500 bg-gray-50 rounded p-2"
              >
                <DocumentTextIcon class="w-4 h-4" />
                <span class="truncate">{{ notification.notifiable.content }}</span>
              </div>
              
              <div 
                v-else-if="notification.notifiable?.type === 'comment'"
                class="flex items-center space-x-2 text-xs text-gray-500 bg-gray-50 rounded p-2"
              >
                <ChatBubbleLeftIcon class="w-4 h-4" />
                <span class="truncate">{{ notification.notifiable.content }}</span>
              </div>
            </div>

            <!-- Timestamp and actions -->
            <div class="flex items-center justify-between">
              <time 
                class="text-xs text-gray-500"
                :title="formatFullDate(notification.created_at)"
              >
                {{ notification.time_ago }}
              </time>
              
              <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <!-- Mark read/unread -->
                <button
                  v-if="notification.is_unread"
                  @click.stop="$emit('mark-read', notification)"
                  class="p-1 text-gray-400 hover:text-blue-600 rounded"
                  title="Mark as read"
                >
                  <CheckIcon class="w-4 h-4" />
                </button>
                <button
                  v-else
                  @click.stop="$emit('mark-unread', notification)"
                  class="p-1 text-gray-400 hover:text-blue-600 rounded"
                  title="Mark as unread"
                >
                  <ArrowUturnLeftIcon class="w-4 h-4" />
                </button>

                <!-- Dismiss -->
                <button
                  v-if="notification.can_be_dismissed && !notification.is_dismissed"
                  @click.stop="$emit('dismiss', notification)"
                  class="p-1 text-gray-400 hover:text-yellow-600 rounded"
                  title="Dismiss"
                >
                  <EyeSlashIcon class="w-4 h-4" />
                </button>

                <!-- Delete -->
                <button
                  @click.stop="confirmDelete"
                  class="p-1 text-gray-400 hover:text-red-600 rounded"
                  title="Delete"
                >
                  <TrashIcon class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Priority indicator -->
          <div 
            v-if="notification.priority !== 'normal'"
            class="flex-shrink-0 ml-2"
          >
            <div 
              class="w-2 h-2 rounded-full"
              :class="getPriorityColor(notification.priority)"
            ></div>
          </div>
        </div>
      </div>

      <!-- Unread indicator -->
      <div 
        v-if="notification.is_unread"
        class="flex-shrink-0 w-2 h-2 bg-blue-600 rounded-full mt-2"
      ></div>
    </div>

    <!-- Action buttons for specific notification types -->
    <div v-if="hasActionButtons" class="px-4 pb-4">
      <div class="flex space-x-2">
        <!-- Friend request actions -->
        <template v-if="notification.type === 'friend_request'">
          <button
            @click.stop="handleFriendAction('accept')"
            class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
          >
            Accept
          </button>
          <button
            @click.stop="handleFriendAction('decline')"
            class="flex-1 px-3 py-2 bg-gray-200 text-gray-700 text-sm rounded-lg hover:bg-gray-300 transition-colors"
          >
            Decline
          </button>
        </template>

        <!-- Follow back button -->
        <template v-if="notification.type === 'follow'">
          <button
            @click.stop="handleFollowBack"
            class="flex-1 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors"
          >
            Follow Back
          </button>
        </template>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  HeartIcon,
  ChatBubbleLeftIcon,
  UserPlusIcon,
  UserGroupIcon,
  ArrowTopRightOnSquareIcon,
  BellIcon,
  DocumentTextIcon,
  CheckIcon,
  ArrowUturnLeftIcon,
  EyeSlashIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import type { Notification } from '@/types/notifications'

interface Props {
  notification: Notification
}

interface Emits {
  (e: 'click', notification: Notification): void
  (e: 'mark-read', notification: Notification): void
  (e: 'mark-unread', notification: Notification): void
  (e: 'dismiss', notification: Notification): void
  (e: 'delete', notification: Notification): void
  (e: 'friend-action', action: 'accept' | 'decline', notification: Notification): void
  (e: 'follow-back', notification: Notification): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

// Computed
const hasPreview = computed(() => {
  return props.notification.notifiable && 
         ['post', 'comment'].includes(props.notification.notifiable.type)
})

const hasActionButtons = computed(() => {
  return ['friend_request', 'follow'].includes(props.notification.type)
})

// Methods
const getNotificationIcon = () => {
  const iconMap = {
    like: HeartIcon,
    comment: ChatBubbleLeftIcon,
    follow: UserPlusIcon,
    friend_request: UserGroupIcon,
    friend_accepted: UserGroupIcon,
    share: ArrowTopRightOnSquareIcon,
    mention: ChatBubbleLeftIcon,
    group_invite: UserGroupIcon,
    message: ChatBubbleLeftIcon,
    post_edited: DocumentTextIcon,
    system: BellIcon
  }
  
  return iconMap[props.notification.type as keyof typeof iconMap] || BellIcon
}

const getPriorityColor = (priority: string) => {
  const colorMap = {
    low: 'bg-gray-400',
    normal: 'bg-blue-500',
    high: 'bg-orange-500',
    urgent: 'bg-red-500'
  }
  
  return colorMap[priority as keyof typeof colorMap] || 'bg-blue-500'
}

const formatFullDate = (dateString: string) => {
  return new Date(dateString).toLocaleString()
}

const handleClick = () => {
  emit('click', props.notification)
}

const confirmDelete = () => {
  if (confirm('Are you sure you want to delete this notification?')) {
    emit('delete', props.notification)
  }
}

const handleFriendAction = (action: 'accept' | 'decline') => {
  emit('friend-action', action, props.notification)
}

const handleFollowBack = () => {
  emit('follow-back', props.notification)
}
</script>

<style scoped>
.notification-item {
  @apply transition-colors duration-200 cursor-pointer group;
}

.notification-item:hover {
  @apply bg-gray-50;
}

.notification-unread {
  @apply bg-blue-50 border-l-4 border-l-blue-500;
}

.notification-dismissed {
  @apply opacity-60;
}

.notification-preview {
  @apply max-w-md;
}
</style> 