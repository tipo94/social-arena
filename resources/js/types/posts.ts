export interface User {
  id: number
  name: string
  username: string
  email?: string
  avatar_url?: string
  created_at: string
  updated_at: string
}

export interface MediaAttachment {
  id: number
  filename: string
  original_filename: string
  type: 'image' | 'video' | 'document'
  mime_type: string
  size: number
  url: string
  thumbnail_url?: string
  preview_url?: string
  alt_text?: string
  width?: number
  height?: number
  duration?: number
  status: 'uploading' | 'processing' | 'ready' | 'error'
  metadata?: Record<string, any>
  created_at: string
  updated_at: string
}

export interface Comment {
  id: number
  post_id: number
  user_id: number
  parent_id?: number
  content: string
  likes_count: number
  replies_count: number
  is_edited: boolean
  is_deleted: boolean
  created_at: string
  updated_at: string
  user: User
  replies?: Comment[]
  liked_by_user?: boolean
}

export interface PostRevision {
  id: number
  post_id: number
  user_id: number
  content: string
  metadata?: Record<string, any>
  edit_reason?: string
  created_at: string
  user: User
}

export interface PostDeletionLog {
  id: number
  post_id: number
  user_id: number
  deletion_reason?: string
  is_soft_delete: boolean
  can_restore: boolean
  expires_at?: string
  created_at: string
  user: User
}

export interface Post {
  id: number
  user_id: number
  group_id?: number
  type: 'text' | 'image' | 'video' | 'link' | 'book_review' | 'poll'
  content?: string
  visibility: 'public' | 'friends' | 'close_friends' | 'friends_of_friends' | 'private' | 'group' | 'custom'
  custom_audience?: number[]
  metadata?: Record<string, any>
  tags?: string[]
  
  // Interaction settings
  allow_comments: boolean
  allow_reactions: boolean
  allow_resharing: boolean
  
  // Scheduling
  is_scheduled: boolean
  scheduled_at?: string
  published_at?: string
  
  // Visibility controls
  visibility_expires_at?: string
  visibility_changed_at?: string
  visibility_history?: Array<{
    visibility: string
    changed_by: number
    changed_at: string
    reason?: string
  }>
  
  // Status flags
  is_draft: boolean
  is_published: boolean
  is_hidden: boolean
  is_reported: boolean
  is_pinned: boolean
  is_edited: boolean
  is_deleted: boolean
  
  // Engagement metrics
  likes_count: number
  comments_count: number
  shares_count: number
  views_count: number
  
  // Edit tracking
  edit_count: number
  last_edited_at?: string
  edit_deadline?: string
  
  // Timestamps
  created_at: string
  updated_at: string
  
  // Related data
  user: User
  group?: Group
  media_attachments?: MediaAttachment[]
  comments?: Comment[]
  revisions?: PostRevision[]
  deletion_logs?: PostDeletionLog[]
  
  // User-specific data (when authenticated)
  liked_by_user?: boolean
  bookmarked_by_user?: boolean
  can_edit?: boolean
  can_delete?: boolean
  audience_summary?: {
    type: string
    count: number
    description: string
  }
}

export interface Group {
  id: number
  name: string
  description?: string
  type: 'public' | 'private' | 'secret'
  members_count: number
  avatar_url?: string
  cover_url?: string
  created_at: string
  updated_at: string
}

// Form data interfaces
export interface CreatePostData {
  type: string
  content?: string
  visibility: string
  custom_audience?: number[]
  group_id?: number
  metadata?: Record<string, any>
  tags?: string[]
  media_ids?: number[]
  allow_comments?: boolean
  allow_reactions?: boolean
  allow_resharing?: boolean
  scheduled_at?: string
  visibility_expires_at?: string
}

export interface UpdatePostData extends Partial<CreatePostData> {
  edit_reason?: string
}

// API response interfaces
export interface ApiResponse<T = any> {
  success: boolean
  message: string
  data?: T
  errors?: ValidationErrors
  meta?: {
    pagination?: PaginationMeta
    [key: string]: any
  }
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
  from: number
  to: number
  has_more?: boolean
  next_cursor?: string
  prev_cursor?: string
}

export interface ValidationErrors {
  [key: string]: string[]
}

// Media upload interfaces
export interface MediaUploadOptions {
  onProgress?: (progress: number) => void
  onComplete?: (file: MediaAttachment) => void
  onError?: (error: string) => void
}

export interface MediaUploadResult {
  success: boolean
  message: string
  data?: MediaAttachment
  error?: string
}

// Post type specific metadata interfaces
export interface BookReviewMetadata {
  book_title: string
  book_author: string
  book_isbn?: string
  book_cover_url?: string
  rating?: number
  reading_status: 'want_to_read' | 'currently_reading' | 'finished' | 'dnf'
  reading_started_at?: string
  reading_finished_at?: string
  genres?: string[]
  publisher?: string
  publication_date?: string
}

export interface LinkMetadata {
  link_url: string
  link_title?: string
  link_description?: string
  link_image_url?: string
  link_domain?: string
  link_type?: 'article' | 'video' | 'image' | 'website'
}

export interface PollMetadata {
  poll_question: string
  poll_options: Array<{
    id?: number
    text: string
    votes_count?: number
    voted_by_user?: boolean
  }>
  poll_type: 'single' | 'multiple'
  poll_ends_at?: string
  allow_add_options?: boolean
  show_results_before_voting?: boolean
  total_votes?: number
  user_voted?: boolean
}

// Filter and search interfaces
export interface PostFilters {
  content_types?: string[]
  has_media?: boolean
  date_from?: string
  date_to?: string
  min_engagement?: number
  hashtags?: string[]
  exclude_authors?: number[]
  visibility?: string[]
  tags?: string[]
}

export interface PostSearchParams {
  query?: string
  type?: 'all' | 'friends' | 'groups' | 'following'
  period?: 'today' | 'week' | 'month' | 'year'
  sort?: 'newest' | 'popular' | 'trending'
  per_page?: number
  cursor?: string
  filters?: PostFilters
}

// Feed interfaces
export interface FeedResponse {
  posts: Post[]
  pagination: {
    has_more: boolean
    next_cursor?: string
    count: number
    total_available: number
  }
  feed_info?: {
    type: string
    applied_filters: string[]
    generated_at: string
  }
}

// Comment interfaces
export interface CreateCommentData {
  content: string
  parent_id?: number
}

export interface UpdateCommentData {
  content: string
  edit_reason?: string
}

// Analytics interfaces
export interface PostAnalytics {
  views: number
  likes: number
  comments: number
  shares: number
  engagement_rate: number
  reach: {
    estimated: number
    actual: number
  }
  top_commenters: Array<{
    user_id: number
    comment_count: number
    user: User
  }>
  performance_vs_average: {
    likes: number
    comments: number
    shares: number
  }
}

// Visibility interfaces
export interface VisibilityOption {
  value: string
  label: string
  description: string
  available: boolean
}

export interface AudienceSummary {
  type: string
  count: number
  description: string
  includes_close_friends?: boolean
  custom_users?: Array<{
    id: number
    name: string
    username: string
  }>
}

// Draft interfaces
export interface PostDraft {
  content?: string
  type: string
  visibility: string
  metadata?: Record<string, any>
  tags?: string[]
  media_ids?: number[]
  custom_audience?: number[]
  saved_at: string
  expires_at?: string
}

// Error interfaces
export interface PostError {
  code: string
  message: string
  field?: string
  details?: Record<string, any>
}

// State interfaces for components
export interface PostFormState {
  isSubmitting: boolean
  isDirty: boolean
  hasErrors: boolean
  errors: ValidationErrors
  lastSaved?: string
  autoSaveEnabled: boolean
}

export interface EditorState {
  isFocused: boolean
  selection?: {
    start: number
    end: number
  }
  history: Array<{
    content: string
    timestamp: string
  }>
  historyIndex: number
}

// Event interfaces
export interface PostEvent {
  type: 'created' | 'updated' | 'deleted' | 'published' | 'scheduled'
  post: Post
  user: User
  timestamp: string
  metadata?: Record<string, any>
}

// Permission interfaces
export interface PostPermissions {
  can_view: boolean
  can_edit: boolean
  can_delete: boolean
  can_comment: boolean
  can_react: boolean
  can_share: boolean
  can_report: boolean
  edit_deadline?: string
}

// Notification interfaces
export interface PostNotification {
  id: number
  type: 'like' | 'comment' | 'share' | 'mention'
  post_id: number
  user_id: number
  data: Record<string, any>
  read_at?: string
  created_at: string
}

// Export utility types
export type PostType = Post['type']
export type PostVisibility = Post['visibility']
export type PostStatus = 'draft' | 'scheduled' | 'published' | 'archived'
export type MediaType = MediaAttachment['type']
export type CommentSort = 'newest' | 'oldest' | 'popular'

// Type guards
export const isValidPostType = (type: string): type is PostType => {
  return ['text', 'image', 'video', 'link', 'book_review', 'poll'].includes(type)
}

export const isValidVisibility = (visibility: string): visibility is PostVisibility => {
  return ['public', 'friends', 'close_friends', 'friends_of_friends', 'private', 'group', 'custom'].includes(visibility)
}

export const hasMediaAttachments = (post: Post): boolean => {
  return post.media_attachments !== undefined && post.media_attachments.length > 0
}

export const isEditablePost = (post: Post, currentUserId?: number): boolean => {
  if (!currentUserId || post.user_id !== currentUserId) return false
  if (post.is_deleted) return false
  if (post.edit_deadline && new Date(post.edit_deadline) < new Date()) return false
  return true
}

export const getPostAudienceSize = (post: Post): number => {
  switch (post.visibility) {
    case 'public':
      return -1 // Unlimited
    case 'friends':
      return post.user?.profile?.friends_count || 0
    case 'custom':
      return post.custom_audience?.length || 0
    case 'private':
      return 1
    default:
      return 0
  }
} 