export interface User {
  id: number
  name: string
  email: string
  username: string
  first_name: string | null
  last_name: string | null
  full_name: string
  display_name: string
  avatar_url: string | null
  is_online: boolean | null
  last_activity_at: string | null
  created_at: string
  email_verified_at: string | null
  profile?: UserProfile
  privacy_context?: PrivacyContext
}

export interface UserProfile {
  bio: string | null
  location: string | null
  website: string | null
  birth_date: string | null
  gender: string | null
  occupation: string | null
  education: string | null
  phone: string | null
  avatar_url: string | null
  cover_image_url: string | null
  is_private_profile: boolean
  is_verified: boolean
  friends_count: number | null
  posts_count: number
  profile_completion_percentage: number | null
  reading_preferences: ReadingPreferences | null
  social_links: Record<string, string> | null
  mutual_friends_count: number | null
}

export interface ReadingPreferences {
  genres: string[]
  authors: string[]
  goals: string[]
  speed: string | null
  languages: string[]
}

export interface PrivacyContext {
  relationship: 'friends' | 'friends_of_friends' | 'strangers'
  can_send_friend_request: boolean
  can_send_message: boolean
  can_see_reading_activity: boolean
  can_see_friends_list: boolean
}

export interface LoginCredentials {
  email: string
  password: string
  remember?: boolean
}

export interface RegisterData {
  name: string
  email: string
  username: string
  password: string
  password_confirmation: string
  first_name?: string
  last_name?: string
  privacy_accepted: boolean
  terms_accepted: boolean
}

export interface ResetPasswordData {
  email: string
  token: string
  password: string
  password_confirmation: string
}

export interface AuthResponse {
  success: boolean
  message?: string
  data?: {
    user: User
    token?: string
    expires_at?: string
  }
  errors?: Record<string, string[]>
}

export interface ProfileUpdateData {
  name?: string
  first_name?: string
  last_name?: string
  username?: string
  timezone?: string
  locale?: string
  theme?: string
  bio?: string
  location?: string
  website?: string
  birth_date?: string
  gender?: string
  occupation?: string
  education?: string
  phone?: string
  social_links?: Record<string, string>
}

export interface PasswordChangeData {
  current_password: string
  password: string
  password_confirmation: string
}

export interface PrivacySettings {
  profile_privacy: {
    is_private_profile: boolean
    profile_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    contact_info_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    location_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    birth_date_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    search_visibility: 'everyone' | 'friends_of_friends' | 'friends' | 'nobody'
  }
  activity_privacy: {
    show_reading_activity: boolean
    show_online_status: boolean
    show_last_activity: boolean
    reading_activity_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    post_visibility_default: 'public' | 'friends' | 'close_friends' | 'private'
  }
  social_privacy: {
    show_friends_list: boolean
    show_mutual_friends: boolean
    friends_list_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    who_can_see_posts: 'public' | 'friends' | 'close_friends' | 'private'
    who_can_tag_me: 'everyone' | 'friends' | 'friends_of_friends' | 'nobody'
  }
  interaction_privacy: {
    allow_friend_requests: boolean
    allow_group_invites: boolean
    allow_book_recommendations: boolean
    allow_messages_from: 'everyone' | 'friends' | 'friends_of_friends' | 'nobody'
    friend_request_visibility: 'everyone' | 'friends_of_friends' | 'friends' | 'nobody'
    who_can_find_me: 'everyone' | 'friends_of_friends' | 'friends' | 'nobody'
  }
  content_privacy: {
    book_lists_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    reviews_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    reading_goals_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
    reading_history_visibility: 'public' | 'friends' | 'friends_of_friends' | 'private'
  }
}

export interface ApiResponse<T = any> {
  success: boolean
  message?: string
  data?: T
  errors?: Record<string, string[]>
}

export interface ValidationErrors {
  [key: string]: string[]
} 