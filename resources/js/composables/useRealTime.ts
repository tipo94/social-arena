import { ref, onMounted, onUnmounted, computed } from 'vue'
import { realTimeService } from '@/services/realTimeService'
import { notificationService } from '@/services/notificationService'
import type { Notification } from '@/types/notifications'
import type { User } from '@/types/auth'

export function useRealTime() {
  // State
  const isConnected = ref(false)
  const connectionState = ref('initializing')
  const reconnectAttempts = ref(0)
  const notifications = ref<Notification[]>([])
  const unreadCount = ref(0)
  const onlineUsers = ref<User[]>([])
  const currentUser = ref<User | null>(null)

  // Connection management
  const initializeRealTime = async (authToken: string, user: User) => {
    currentUser.value = user
    
    try {
      await realTimeService.initialize(authToken)
      
      // Subscribe to user's notification channel
      realTimeService.subscribeToNotifications(user.id, {
        onNotificationCreated: handleNewNotification,
        onNotificationRead: handleNotificationRead,
        onBulkRead: handleBulkRead,
      })

      // Subscribe to user presence updates
      realTimeService.subscribeToPresence({
        onUserOnline: handleUserOnline,
        onUserOffline: handleUserOffline,
        onPresenceUpdate: handlePresenceUpdate,
      })

      // Update connection state
      updateConnectionState()
      
      console.log('Real-time service initialized for user:', user.username)
    } catch (error) {
      console.error('Failed to initialize real-time service:', error)
    }
  }

  const disconnect = () => {
    realTimeService.disconnect()
    updateConnectionState()
  }

  const forceReconnect = async () => {
    if (currentUser.value) {
      disconnect()
      // Note: Would need auth token again for reconnection
      console.log('Force reconnect requested - auth token needed')
    }
  }

  // Notification handlers
  const handleNewNotification = (notification: Notification) => {
    // Add to local state
    notifications.value.unshift(notification)
    unreadCount.value++

    // Show browser notification if permission granted
    if (notificationService) {
      notificationService.showBrowserNotification(notification)
    }

    // Emit custom event for other components to listen
    window.dispatchEvent(new CustomEvent('notification:received', {
      detail: notification
    }))
  }

  const handleNotificationRead = (data: { notification_id: number; read_at: string }) => {
    // Update local state
    const notification = notifications.value.find(n => n.id === data.notification_id)
    if (notification && !notification.is_read) {
      notification.is_read = true
      notification.read_at = data.read_at
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }

  const handleBulkRead = (data: { marked_count: number }) => {
    // Update all unread notifications to read
    notifications.value.forEach(notification => {
      if (!notification.is_read) {
        notification.is_read = true
        notification.read_at = new Date().toISOString()
      }
    })
    unreadCount.value = 0

    // Emit event
    window.dispatchEvent(new CustomEvent('notifications:bulk-read', {
      detail: data
    }))
  }

  // Presence handlers
  const handleUserOnline = (user: User) => {
    if (!onlineUsers.value.find(u => u.id === user.id)) {
      onlineUsers.value.push(user)
    }

    // Emit custom event
    window.dispatchEvent(new CustomEvent('user:online', {
      detail: user
    }))
  }

  const handleUserOffline = (user: User) => {
    const index = onlineUsers.value.findIndex(u => u.id === user.id)
    if (index !== -1) {
      onlineUsers.value.splice(index, 1)
    }

    // Emit custom event
    window.dispatchEvent(new CustomEvent('user:offline', {
      detail: user
    }))
  }

  const handlePresenceUpdate = (data: { user: User; is_online: boolean }) => {
    if (data.is_online) {
      handleUserOnline(data.user)
    } else {
      handleUserOffline(data.user)
    }
  }

  // Post interaction subscriptions
  const subscribeToPost = (postId: number, callbacks?: {
    onLikeUpdate?: (data: any) => void
    onCommentUpdate?: (data: any) => void
    onShareUpdate?: (data: any) => void
  }) => {
    realTimeService.subscribeToPostInteractions(postId, {
      onLikeUpdate: (data) => {
        callbacks?.onLikeUpdate?.(data)
        window.dispatchEvent(new CustomEvent('post:like-update', {
          detail: { postId, data }
        }))
      },
      onCommentUpdate: (data) => {
        callbacks?.onCommentUpdate?.(data)
        window.dispatchEvent(new CustomEvent('post:comment-update', {
          detail: { postId, data }
        }))
      },
      onShareUpdate: (data) => {
        callbacks?.onShareUpdate?.(data)
        window.dispatchEvent(new CustomEvent('post:share-update', {
          detail: { postId, data }
        }))
      },
    })
  }

  const subscribeToTyping = (postId: number, callback?: (data: { user: User; is_typing: boolean }) => void) => {
    realTimeService.subscribeToTypingIndicators(postId, {
      onUserTyping: (data) => {
        callback?.(data)
        window.dispatchEvent(new CustomEvent('post:typing', {
          detail: { postId, ...data }
        }))
      }
    })
  }

  const sendTyping = (postId: number, isTyping: boolean) => {
    realTimeService.sendTypingIndicator(postId, isTyping)
  }

  // Friend activity subscription
  const subscribeToFriendActivity = (userId: number, callback?: (data: any) => void) => {
    realTimeService.subscribeToFriendActivity(userId, {
      onFriendActivity: (data) => {
        callback?.(data)
        window.dispatchEvent(new CustomEvent('friend:activity', {
          detail: data
        }))
      }
    })
  }

  // Feed updates subscription
  const subscribeToFeedUpdates = (userId: number, callback?: (data: any) => void) => {
    realTimeService.subscribeToFeedUpdates(userId, {
      onFeedUpdate: (data) => {
        callback?.(data)
        window.dispatchEvent(new CustomEvent('feed:update', {
          detail: data
        }))
      }
    })
  }

  // Utility methods
  const updateConnectionState = () => {
    const status = realTimeService.getConnectionStatus()
    isConnected.value = status.isConnected
    connectionState.value = status.state
    reconnectAttempts.value = status.reconnectAttempts
  }

  const unsubscribeFromChannel = (channelName: string) => {
    realTimeService.unsubscribeFromChannel(channelName)
  }

  // Load initial notifications
  const loadNotifications = async () => {
    if (!currentUser.value) return

    try {
      const response = await notificationService.getNotifications({
        unread_only: false,
        per_page: 50
      })
      
      if (response.data) {
        notifications.value = response.data
        unreadCount.value = response.data.filter(n => !n.is_read).length
      }
    } catch (error) {
      console.error('Failed to load notifications:', error)
    }
  }

  // Computed properties
  const connectionStatus = computed(() => ({
    isConnected: isConnected.value,
    state: connectionState.value,
    reconnectAttempts: reconnectAttempts.value,
    canReconnect: reconnectAttempts.value < 5
  }))

  const latestNotifications = computed(() => 
    notifications.value.slice(0, 10)
  )

  const hasUnreadNotifications = computed(() => 
    unreadCount.value > 0
  )

  const onlineFriendsCount = computed(() => 
    onlineUsers.value.length
  )

  // Lifecycle
  onMounted(() => {
    // Update connection state periodically
    const interval = setInterval(updateConnectionState, 5000)
    
    onUnmounted(() => {
      clearInterval(interval)
      disconnect()
    })
  })

  // Request browser notification permission
  const requestNotificationPermission = async () => {
    if (notificationService) {
      return await notificationService.requestNotificationPermission()
    }
    return 'denied'
  }

  return {
    // State
    isConnected: computed(() => isConnected.value),
    connectionState: computed(() => connectionState.value),
    notifications: computed(() => notifications.value),
    unreadCount: computed(() => unreadCount.value),
    onlineUsers: computed(() => onlineUsers.value),
    
    // Computed
    connectionStatus,
    latestNotifications,
    hasUnreadNotifications,
    onlineFriendsCount,
    
    // Methods
    initializeRealTime,
    disconnect,
    forceReconnect,
    subscribeToPost,
    subscribeToTyping,
    sendTyping,
    subscribeToFriendActivity,
    subscribeToFeedUpdates,
    unsubscribeFromChannel,
    loadNotifications,
    requestNotificationPermission,
    
    // Event handlers (for external use)
    handleNewNotification,
    handleNotificationRead,
    handleBulkRead,
    handleUserOnline,
    handleUserOffline,
  }
}

// Global event listener types for TypeScript
declare global {
  interface WindowEventMap {
    'notification:received': CustomEvent<Notification>
    'notifications:bulk-read': CustomEvent<{ marked_count: number }>
    'user:online': CustomEvent<User>
    'user:offline': CustomEvent<User>
    'post:like-update': CustomEvent<{ postId: number; data: any }>
    'post:comment-update': CustomEvent<{ postId: number; data: any }>
    'post:share-update': CustomEvent<{ postId: number; data: any }>
    'post:typing': CustomEvent<{ postId: number; user: User; is_typing: boolean }>
    'friend:activity': CustomEvent<any>
    'feed:update': CustomEvent<any>
  }
} 