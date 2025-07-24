import Pusher from 'pusher-js'
import Echo from 'laravel-echo'
import type { Notification } from '@/types/notifications'
import type { User } from '@/types/auth'

// Declare global variables for Laravel Echo integration
declare global {
  interface Window {
    Pusher: typeof Pusher
    Echo: Echo
  }
}

class RealTimeService {
  private echo: Echo | null = null
  private isConnected = false
  private reconnectAttempts = 0
  private maxReconnectAttempts = 5
  private reconnectDelay = 1000

  /**
   * Initialize the real-time service with authentication
   */
  async initialize(authToken?: string): Promise<void> {
    try {
      // Set up Pusher
      window.Pusher = Pusher
      
      // Configure Laravel Echo
      this.echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
        forceTLS: true,
        auth: {
          headers: {
            Authorization: `Bearer ${authToken}`,
            Accept: 'application/json',
          },
        },
        authEndpoint: '/api/broadcasting/auth',
        enabledTransports: ['ws', 'wss'],
      })

      // Set up connection event listeners
      this.setupConnectionListeners()
      
      // Make Echo globally available
      window.Echo = this.echo
      
      this.isConnected = true
      console.log('Real-time service initialized successfully')
    } catch (error) {
      console.error('Failed to initialize real-time service:', error)
      this.handleConnectionError()
    }
  }

  /**
   * Set up connection event listeners
   */
  private setupConnectionListeners(): void {
    if (!this.echo) return

    this.echo.connector.pusher.connection.bind('connected', () => {
      console.log('WebSocket connected')
      this.isConnected = true
      this.reconnectAttempts = 0
    })

    this.echo.connector.pusher.connection.bind('disconnected', () => {
      console.log('WebSocket disconnected')
      this.isConnected = false
      this.handleReconnection()
    })

    this.echo.connector.pusher.connection.bind('error', (error: any) => {
      console.error('WebSocket error:', error)
      this.handleConnectionError()
    })
  }

  /**
   * Handle connection errors with exponential backoff
   */
  private handleConnectionError(): void {
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++
      const delay = this.reconnectDelay * Math.pow(2, this.reconnectAttempts - 1)
      
      console.log(`Attempting to reconnect (${this.reconnectAttempts}/${this.maxReconnectAttempts}) in ${delay}ms`)
      
      setTimeout(() => {
        this.handleReconnection()
      }, delay)
    } else {
      console.error('Max reconnection attempts reached. Please refresh the page.')
    }
  }

  /**
   * Handle reconnection logic
   */
  private handleReconnection(): void {
    if (this.echo && !this.isConnected) {
      try {
        this.echo.connector.pusher.connect()
      } catch (error) {
        console.error('Reconnection failed:', error)
        this.handleConnectionError()
      }
    }
  }

  /**
   * Subscribe to user's notification channel
   */
  subscribeToNotifications(
    userId: number,
    callbacks: {
      onNotificationCreated?: (notification: Notification) => void
      onNotificationRead?: (data: { notification_id: number; read_at: string }) => void
      onBulkRead?: (data: { marked_count: number }) => void
    }
  ): void {
    if (!this.echo) {
      console.error('Echo not initialized')
      return
    }

    const channelName = `user.${userId}.notifications`
    
    this.echo.private(channelName)
      .listen('.notification.created', (data: { notification: Notification }) => {
        console.log('New notification received:', data.notification)
        callbacks.onNotificationCreated?.(data.notification)
      })
      .listen('.notification.read', (data: { notification_id: number; read_at: string }) => {
        console.log('Notification marked as read:', data)
        callbacks.onNotificationRead?.(data)
      })
      .listen('.notification.bulk-read', (data: { marked_count: number }) => {
        console.log('Bulk notifications marked as read:', data)
        callbacks.onBulkRead?.(data)
      })
  }

  /**
   * Subscribe to user presence channel
   */
  subscribeToPresence(
    callbacks: {
      onUserOnline?: (user: User) => void
      onUserOffline?: (user: User) => void
      onPresenceUpdate?: (data: { user: User; is_online: boolean }) => void
    }
  ): void {
    if (!this.echo) {
      console.error('Echo not initialized')
      return
    }

    this.echo.join('user-presence')
      .here((users: User[]) => {
        console.log('Users currently online:', users)
      })
      .joining((user: User) => {
        console.log('User came online:', user)
        callbacks.onUserOnline?.(user)
      })
      .leaving((user: User) => {
        console.log('User went offline:', user)
        callbacks.onUserOffline?.(user)
      })
      .listen('.user.presence.changed', (data: { user: User; is_online: boolean }) => {
        console.log('User presence changed:', data)
        callbacks.onPresenceUpdate?.(data)
      })
  }

  /**
   * Subscribe to post interaction updates
   */
  subscribeToPostInteractions(
    postId: number,
    callbacks: {
      onLikeUpdate?: (data: any) => void
      onCommentUpdate?: (data: any) => void
      onShareUpdate?: (data: any) => void
    }
  ): void {
    if (!this.echo) {
      console.error('Echo not initialized')
      return
    }

    const channelName = `post.${postId}.interactions`
    
    this.echo.private(channelName)
      .listen('.post.liked', (data: any) => {
        console.log('Post like update:', data)
        callbacks.onLikeUpdate?.(data)
      })
      .listen('.post.commented', (data: any) => {
        console.log('Post comment update:', data)
        callbacks.onCommentUpdate?.(data)
      })
      .listen('.post.shared', (data: any) => {
        console.log('Post share update:', data)
        callbacks.onShareUpdate?.(data)
      })
  }

  /**
   * Subscribe to typing indicators for comments
   */
  subscribeToTypingIndicators(
    postId: number,
    callbacks: {
      onUserTyping?: (data: { user: User; is_typing: boolean }) => void
    }
  ): void {
    if (!this.echo) {
      console.error('Echo not initialized')
      return
    }

    const channelName = `post.${postId}.typing`
    
    this.echo.private(channelName)
      .listen('.user.typing', (data: { user: User; is_typing: boolean }) => {
        console.log('User typing indicator:', data)
        callbacks.onUserTyping?.(data)
      })
  }

  /**
   * Subscribe to friend activity updates
   */
  subscribeToFriendActivity(
    userId: number,
    callbacks: {
      onFriendActivity?: (data: any) => void
    }
  ): void {
    if (!this.echo) {
      console.error('Echo not initialized')
      return
    }

    const channelName = `user.${userId}.friends-activity`
    
    this.echo.private(channelName)
      .listen('.friend.activity', (data: any) => {
        console.log('Friend activity update:', data)
        callbacks.onFriendActivity?.(data)
      })
  }

  /**
   * Subscribe to live feed updates
   */
  subscribeToFeedUpdates(
    userId: number,
    callbacks: {
      onFeedUpdate?: (data: any) => void
    }
  ): void {
    if (!this.echo) {
      console.error('Echo not initialized')
      return
    }

    const channelName = `user.${userId}.feed-updates`
    
    this.echo.private(channelName)
      .listen('.feed.updated', (data: any) => {
        console.log('Feed update:', data)
        callbacks.onFeedUpdate?.(data)
      })
  }

  /**
   * Unsubscribe from a channel
   */
  unsubscribeFromChannel(channelName: string): void {
    if (!this.echo) return

    try {
      this.echo.leave(channelName)
      console.log(`Unsubscribed from channel: ${channelName}`)
    } catch (error) {
      console.error(`Failed to unsubscribe from channel ${channelName}:`, error)
    }
  }

  /**
   * Send typing indicator
   */
  sendTypingIndicator(postId: number, isTyping: boolean): void {
    if (!this.echo) return

    const channelName = `post.${postId}.typing`
    
    try {
      this.echo.private(channelName)
        .whisper('typing', {
          is_typing: isTyping,
          timestamp: Date.now(),
        })
    } catch (error) {
      console.error('Failed to send typing indicator:', error)
    }
  }

  /**
   * Get connection status
   */
  getConnectionStatus(): {
    isConnected: boolean
    reconnectAttempts: number
    state: string
  } {
    return {
      isConnected: this.isConnected,
      reconnectAttempts: this.reconnectAttempts,
      state: this.echo?.connector?.pusher?.connection?.state || 'unknown',
    }
  }

  /**
   * Disconnect and cleanup
   */
  disconnect(): void {
    if (this.echo) {
      this.echo.disconnect()
      this.echo = null
      this.isConnected = false
      console.log('Real-time service disconnected')
    }
  }

  /**
   * Check if service is properly initialized
   */
  isInitialized(): boolean {
    return this.echo !== null
  }

  /**
   * Get current user presence info
   */
  getCurrentPresence(): any {
    if (!this.echo) return null
    
    return this.echo.connector?.pusher?.channels?.channels?.['presence-user-presence']?.members
  }

  /**
   * Force reconnection
   */
  forceReconnect(): void {
    if (this.echo) {
      this.disconnect()
      this.reconnectAttempts = 0
      // Reinitialize needs to be called externally with auth token
    }
  }
}

// Create and export singleton instance
export const realTimeService = new RealTimeService()
export default realTimeService 