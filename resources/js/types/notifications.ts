export interface Notification {
  id: number
  type: NotificationType
  title: string
  message: string
  action_url?: string
  data?: Record<string, any>
  priority: NotificationPriority
  
  // Status flags
  is_read: boolean
  is_unread: boolean
  is_dismissed: boolean
  can_be_dismissed: boolean
  
  // Delivery status
  is_sent_email: boolean
  is_sent_push: boolean
  
  // Actor information (who triggered the notification)
  actor?: {
    id: number
    name: string
    username: string
    avatar_url?: string
  }
  
  // Related entity information
  notifiable?: {
    type: string
    id: number
    content?: string
    post_id?: number
    user_id?: number
    created_at?: string
  }
  
  // Timestamps
  read_at?: string
  created_at: string
  updated_at: string
  
  // Human-readable time
  time_ago: string
  
  // Additional computed properties
  age_in_hours?: number
  is_recent: boolean
}

export type NotificationType = 
  | 'like'
  | 'comment'
  | 'follow'
  | 'friend_request'
  | 'friend_accepted'
  | 'share'
  | 'mention'
  | 'group_invite'
  | 'message'
  | 'post_edited'
  | 'system'

export type NotificationPriority = 'low' | 'normal' | 'high' | 'urgent'

export interface NotificationSettings {
  email_notifications: boolean
  push_notifications: boolean
  notification_likes: boolean
  notification_comments: boolean
  notification_friend_requests: boolean
  notification_group_invites: boolean
  notification_book_recommendations: boolean
  notification_reading_reminders: boolean
}

export interface NotificationStats {
  total: number
  unread: number
  today: number
  this_week: number
  by_type: Record<NotificationType, number>
  high_priority: number
}

export interface NotificationTypeCount {
  type: NotificationType
  total: number
  unread: number
}

export interface CreateNotificationData {
  type: NotificationType
  title: string
  message: string
  actor_id?: number
  notifiable_type?: string
  notifiable_id?: number
  data?: Record<string, any>
  priority?: NotificationPriority
  action_url?: string
}

export interface UpdateNotificationData {
  read_at?: string | null
  is_dismissed?: boolean
}

export interface NotificationFilters {
  type?: NotificationType | 'all' | 'unread'
  unread_only?: boolean
  priority?: NotificationPriority
  date_from?: string
  date_to?: string
}

export interface NotificationApiResponse {
  success: boolean
  data: Notification[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    has_more: boolean
  }
}

export interface NotificationStatsResponse {
  success: boolean
  data: NotificationStats
}

export interface NotificationUnreadCountResponse {
  success: boolean
  data: {
    unread_count: number
  }
}

export interface NotificationTypesResponse {
  success: boolean
  data: NotificationTypeCount[]
}

export interface BulkNotificationAction {
  notification_ids: number[]
  action: 'mark_read' | 'dismiss' | 'delete'
}

export interface BulkActionResponse {
  success: boolean
  data: {
    marked_count?: number
    dismissed_count?: number
    deleted_count?: number
    requested_count: number
  }
  message: string
} 