import type { User } from './auth'

// Follow System Types
export interface Follow {
  id: number
  follower_id: number
  following_id: number
  followed_at: string
  is_muted: boolean
  show_notifications: boolean
  is_close_friend: boolean
  interaction_preferences?: Record<string, any>
  can_modify: boolean
  is_active: boolean
  follow_duration_days: number
  created_at: string
  updated_at: string
  
  // Relationships
  follower?: User
  following?: User
}

export interface FollowStats {
  followers_count: number
  following_count: number
  mutual_follows_count?: number
}

export interface FollowSettings {
  is_muted?: boolean
  show_notifications?: boolean
  is_close_friend?: boolean
  interaction_preferences?: Record<string, any>
}

export interface CreateFollowData {
  user_id: number
  options?: FollowSettings
}

// Friendship System Types
export interface Friendship {
  id: number
  user_id: number
  friend_id: number
  status: FriendshipStatus
  initiated_by: number
  last_interaction_at?: string
  interaction_count: number
  relationship_strength: number
  created_at: string
  updated_at: string
  
  // Relationships
  user?: User
  friend?: User
  initiator?: User
}

export type FriendshipStatus = 'pending' | 'accepted' | 'declined' | 'blocked'

export interface FriendRequest {
  id: number
  user_id: number
  friend_id: number
  status: FriendshipStatus
  message?: string
  created_at: string
  updated_at: string
  
  // Relationships
  user?: User
  friend?: User
}

export interface SendFriendRequestData {
  user_id: number
  message?: string
}

export interface FriendSuggestion {
  user: User
  mutual_friends_count: number
  mutual_friends: User[]
  score: number
  reasons: string[]
}

export interface FriendshipStats {
  friends_count: number
  pending_sent_count: number
  pending_received_count: number
  mutual_friends_count?: number
}

// API Response Types
export interface FollowApiResponse {
  success: boolean
  data: Follow
  message?: string
}

export interface FollowListResponse {
  success: boolean
  data: Follow[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    has_more: boolean
  }
}

export interface FollowStatsResponse {
  success: boolean
  data: FollowStats
}

export interface FriendshipApiResponse {
  success: boolean
  data: Friendship
  message?: string
}

export interface FriendshipListResponse {
  success: boolean
  data: Friendship[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    has_more: boolean
  }
}

export interface FriendSuggestionsResponse {
  success: boolean
  data: FriendSuggestion[]
  meta: {
    algorithm: string
    computed_at: string
  }
}

export interface FriendshipStatsResponse {
  success: boolean
  data: FriendshipStats
}

// Filter and Search Types
export interface FollowFilters {
  search?: string
  is_muted?: boolean
  is_close_friend?: boolean
  sort_by?: 'newest' | 'oldest' | 'name'
  per_page?: number
  page?: number
}

export interface FriendshipFilters {
  search?: string
  status?: FriendshipStatus
  sort_by?: 'newest' | 'oldest' | 'name' | 'interaction'
  per_page?: number
  page?: number
}

// Error Types
export interface SocialApiError {
  success: false
  message: string
  errors?: Record<string, string[]>
}

export type SocialApiResult<T> = T | SocialApiError 