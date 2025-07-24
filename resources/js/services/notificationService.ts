import axios from 'axios'
import type { AxiosResponse } from 'axios'
import type {
  Notification,
  NotificationApiResponse,
  NotificationStatsResponse,
  NotificationUnreadCountResponse,
  NotificationTypesResponse,
  NotificationFilters,
  CreateNotificationData,
  UpdateNotificationData,
  BulkNotificationAction,
  BulkActionResponse,
  NotificationType,
  NotificationPriority
} from '@/types/notifications'

class NotificationService {
  private baseURL = '/api/notifications'

  // Get notifications
  async getNotifications(filters?: NotificationFilters): Promise<NotificationApiResponse> {
    try {
      const params = new URLSearchParams()
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<NotificationApiResponse> = await axios.get(
        `${this.baseURL}?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Get notification statistics
  async getNotificationStats(): Promise<NotificationStatsResponse> {
    try {
      const response: AxiosResponse<NotificationStatsResponse> = await axios.get(
        `${this.baseURL}/statistics`
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Get unread count
  async getUnreadCount(): Promise<NotificationUnreadCountResponse> {
    try {
      const response: AxiosResponse<NotificationUnreadCountResponse> = await axios.get(
        `${this.baseURL}/unread-count`
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Get notification types with counts
  async getNotificationTypes(): Promise<NotificationTypesResponse> {
    try {
      const response: AxiosResponse<NotificationTypesResponse> = await axios.get(
        `${this.baseURL}/types`
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Get single notification
  async getNotification(id: number): Promise<{ success: boolean; data: Notification }> {
    try {
      const response = await axios.get(`${this.baseURL}/${id}`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Mark as read/unread
  async markAsRead(id: number): Promise<{ success: boolean; data: Notification; message: string }> {
    try {
      const response = await axios.patch(`${this.baseURL}/${id}/read`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async markAsUnread(id: number): Promise<{ success: boolean; data: Notification; message: string }> {
    try {
      const response = await axios.patch(`${this.baseURL}/${id}/unread`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Mark all as read
  async markAllAsRead(): Promise<{ success: boolean; marked_count: number; message: string }> {
    try {
      const response = await axios.patch(`${this.baseURL}/mark-all-read`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Dismiss notification
  async dismissNotification(id: number): Promise<{ success: boolean; data: Notification; message: string }> {
    try {
      const response = await axios.patch(`${this.baseURL}/${id}/dismiss`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Delete notification
  async deleteNotification(id: number): Promise<{ success: boolean; message: string }> {
    try {
      const response = await axios.delete(`${this.baseURL}/${id}`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Bulk operations
  async bulkMarkAsRead(notificationIds: number[]): Promise<BulkActionResponse> {
    try {
      const action: BulkNotificationAction = {
        notification_ids: notificationIds,
        action: 'mark_read'
      }
      const response: AxiosResponse<BulkActionResponse> = await axios.post(
        `${this.baseURL}/bulk/mark-read`,
        action
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async bulkDismiss(notificationIds: number[]): Promise<BulkActionResponse> {
    try {
      const action: BulkNotificationAction = {
        notification_ids: notificationIds,
        action: 'dismiss'
      }
      const response: AxiosResponse<BulkActionResponse> = await axios.post(
        `${this.baseURL}/bulk/dismiss`,
        action
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async bulkDelete(notificationIds: number[]): Promise<BulkActionResponse> {
    try {
      const action: BulkNotificationAction = {
        notification_ids: notificationIds,
        action: 'delete'
      }
      const response: AxiosResponse<BulkActionResponse> = await axios.post(
        `${this.baseURL}/bulk/delete`,
        action
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Cleanup old notifications
  async cleanupOldNotifications(daysOld: number = 30): Promise<{ success: boolean; deleted_count: number; message: string }> {
    try {
      const response = await axios.delete(`${this.baseURL}/cleanup?days=${daysOld}`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Test notification (for development)
  async sendTestNotification(type: NotificationType = 'system'): Promise<{ success: boolean; data: Notification; message: string }> {
    try {
      const response = await axios.post(`${this.baseURL}/test`, { type })
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Utility methods
  async getUnreadNotifications(): Promise<Notification[]> {
    try {
      const response = await this.getNotifications({ unread_only: true })
      return response.data || []
    } catch (error) {
      console.error('Error fetching unread notifications:', error)
      return []
    }
  }

  async getNotificationsByType(type: NotificationType): Promise<Notification[]> {
    try {
      const response = await this.getNotifications({ type })
      return response.data || []
    } catch (error) {
      console.error(`Error fetching ${type} notifications:`, error)
      return []
    }
  }

  async getHighPriorityNotifications(): Promise<Notification[]> {
    try {
      const response = await this.getNotifications({ priority: 'high' })
      return response.data || []
    } catch (error) {
      console.error('Error fetching high priority notifications:', error)
      return []
    }
  }

  // Real-time functionality (WebSocket/Server-Sent Events)
  private eventSource: EventSource | null = null

  startRealTimeNotifications(onNotification: (notification: Notification) => void): void {
    if (this.eventSource) {
      this.stopRealTimeNotifications()
    }

    try {
      this.eventSource = new EventSource('/api/notifications/stream')
      
      this.eventSource.onmessage = (event) => {
        try {
          const notification: Notification = JSON.parse(event.data)
          onNotification(notification)
        } catch (error) {
          console.error('Error parsing notification:', error)
        }
      }

      this.eventSource.onerror = (error) => {
        console.error('Notification stream error:', error)
        this.stopRealTimeNotifications()
      }
    } catch (error) {
      console.error('Error starting real-time notifications:', error)
    }
  }

  stopRealTimeNotifications(): void {
    if (this.eventSource) {
      this.eventSource.close()
      this.eventSource = null
    }
  }

  // Local notification handling
  async requestNotificationPermission(): Promise<NotificationPermission> {
    if (!('Notification' in window)) {
      console.warn('This browser does not support notifications')
      return 'denied'
    }

    if (Notification.permission === 'granted') {
      return 'granted'
    }

    if (Notification.permission !== 'denied') {
      const permission = await Notification.requestPermission()
      return permission
    }

    return Notification.permission
  }

  showBrowserNotification(notification: Notification): void {
    if (Notification.permission === 'granted') {
      const browserNotification = new window.Notification(notification.title, {
        body: notification.message,
        icon: notification.actor?.avatar_url || '/favicon.ico',
        tag: `notification-${notification.id}`,
        requireInteraction: notification.priority === 'high' || notification.priority === 'urgent'
      })

      browserNotification.onclick = () => {
        window.focus()
        if (notification.action_url) {
          window.location.href = notification.action_url
        }
        browserNotification.close()
      }

      // Auto close after 5 seconds for normal priority
      if (notification.priority === 'normal' || notification.priority === 'low') {
        setTimeout(() => {
          browserNotification.close()
        }, 5000)
      }
    }
  }

  // Error handling
  private handleError(error: any): Error {
    if (error.response?.data) {
      return new Error(error.response.data.message || 'An error occurred')
    }
    return new Error(error.message || 'Network error occurred')
  }
}

export const notificationService = new NotificationService()
export default notificationService 