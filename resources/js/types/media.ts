// Media attachment interfaces
export interface MediaAttachment {
  id: number
  user_id: number
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
  duration?: number // For videos
  status: 'uploading' | 'processing' | 'ready' | 'error'
  metadata?: MediaMetadata
  attachable_type?: string
  attachable_id?: number
  storage_path: string
  created_at: string
  updated_at: string
}

export interface MediaMetadata {
  // Image metadata
  exif?: {
    camera?: string
    lens?: string
    aperture?: string
    iso?: number
    shutter_speed?: string
    focal_length?: string
    taken_at?: string
    location?: {
      latitude: number
      longitude: number
      altitude?: number
    }
  }
  
  // Video metadata
  codec?: string
  bitrate?: number
  frame_rate?: number
  resolution?: {
    width: number
    height: number
  }
  
  // Processing metadata
  variants?: MediaVariant[]
  compression?: {
    original_size: number
    compressed_size: number
    compression_ratio: number
    quality: number
  }
  
  // Security metadata
  virus_scan?: {
    status: 'pending' | 'clean' | 'infected' | 'error'
    scanned_at?: string
    engine?: string
  }
  
  // Additional metadata
  tags?: string[]
  description?: string
  copyright?: string
  source?: string
}

export interface MediaVariant {
  id: string
  type: 'thumbnail' | 'preview' | 'compressed' | 'watermarked'
  url: string
  width?: number
  height?: number
  size: number
  format: string
  quality?: number
}

// Upload interfaces
export interface MediaUploadOptions {
  type?: 'posts' | 'avatars' | 'covers' | 'messages' | 'temp'
  folder?: string
  quality?: number
  resize?: {
    width?: number
    height?: number
    maintain_aspect_ratio?: boolean
  }
  watermark?: {
    enabled: boolean
    position?: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right' | 'center'
    opacity?: number
  }
  alt_text?: string
  onProgress?: (progress: number) => void
  onComplete?: (media: MediaAttachment) => void
  onError?: (error: MediaUploadError) => void
}

export interface MediaUploadProgress {
  fileName: string
  progress: number
  status: 'uploading' | 'processing' | 'complete' | 'error'
  fileSize: number
  uploadedSize: number
  timeRemaining?: number
  speed?: number // bytes per second
}

export interface MediaUploadError {
  code: string
  message: string
  fileName?: string
  details?: {
    file_size?: number
    max_size?: number
    file_type?: string
    allowed_types?: string[]
    [key: string]: any
  }
}

export interface MediaUploadResult {
  success: boolean
  message: string
  data?: MediaAttachment
  error?: MediaUploadError
  processing?: boolean
  estimated_time?: number
}

// Validation interfaces
export interface MediaValidationRules {
  image?: {
    max_size: number // bytes
    max_width?: number
    max_height?: number
    min_width?: number
    min_height?: number
    allowed_types: string[]
    quality_min?: number
    quality_max?: number
  }
  video?: {
    max_size: number
    max_duration?: number // seconds
    max_width?: number
    max_height?: number
    allowed_types: string[]
    max_bitrate?: number
  }
  document?: {
    max_size: number
    allowed_types: string[]
  }
}

export interface MediaValidationResult {
  valid: boolean
  errors: MediaUploadError[]
  warnings?: string[]
}

// Processing interfaces
export interface MediaProcessingJob {
  id: string
  media_id: number
  type: 'compress' | 'resize' | 'watermark' | 'generate_thumbnail' | 'convert_format'
  status: 'pending' | 'processing' | 'completed' | 'failed'
  progress: number
  options: Record<string, any>
  error?: string
  started_at?: string
  completed_at?: string
  estimated_completion?: string
}

export interface ImageProcessingOptions {
  resize?: {
    width?: number
    height?: number
    maintain_aspect_ratio?: boolean
    upscale?: boolean
  }
  quality?: number // 1-100
  format?: 'jpeg' | 'png' | 'webp' | 'avif'
  optimize?: boolean
  progressive?: boolean
  watermark?: {
    image_path?: string
    text?: string
    position: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right' | 'center'
    opacity: number
    size?: number
  }
  effects?: {
    blur?: number
    brightness?: number
    contrast?: number
    saturation?: number
    grayscale?: boolean
    sepia?: boolean
  }
}

export interface VideoProcessingOptions {
  resize?: {
    width?: number
    height?: number
    maintain_aspect_ratio?: boolean
  }
  quality?: 'low' | 'medium' | 'high' | 'ultra'
  format?: 'mp4' | 'webm' | 'ogg'
  codec?: 'h264' | 'h265' | 'vp8' | 'vp9' | 'av1'
  bitrate?: number
  frame_rate?: number
  audio?: {
    codec?: 'aac' | 'mp3' | 'opus'
    bitrate?: number
    remove?: boolean
  }
  trim?: {
    start: number // seconds
    end: number // seconds
  }
  thumbnail?: {
    at: number // seconds
    width?: number
    height?: number
  }
}

// Storage interfaces
export interface MediaStorageConfig {
  driver: 'local' | 's3' | 'gcs' | 'azure'
  bucket?: string
  region?: string
  path_prefix?: string
  public_url_base?: string
  cdn_url?: string
}

export interface MediaStorageStats {
  total_files: number
  total_size: number
  storage_used: number
  storage_limit: number
  files_by_type: {
    images: number
    videos: number
    documents: number
  }
  size_by_type: {
    images: number
    videos: number
    documents: number
  }
}

// Gallery and organization interfaces
export interface MediaGallery {
  id: number
  user_id: number
  name: string
  description?: string
  is_public: boolean
  media_count: number
  cover_media_id?: number
  created_at: string
  updated_at: string
  media?: MediaAttachment[]
}

export interface MediaTag {
  id: number
  name: string
  slug: string
  usage_count: number
  created_at: string
}

export interface MediaFilter {
  type?: 'image' | 'video' | 'document'
  date_from?: string
  date_to?: string
  size_min?: number
  size_max?: number
  tags?: string[]
  search?: string
  gallery_id?: number
  sort?: 'newest' | 'oldest' | 'largest' | 'smallest' | 'name'
}

// EXIF and metadata extraction
export interface ExifData {
  make?: string
  model?: string
  lens_model?: string
  aperture?: string
  shutter_speed?: string
  iso?: number
  focal_length?: string
  flash?: boolean
  white_balance?: string
  exposure_mode?: string
  metering_mode?: string
  date_time?: string
  gps?: {
    latitude: number
    longitude: number
    altitude?: number
    accuracy?: number
  }
  dimensions?: {
    width: number
    height: number
  }
  color_space?: string
  orientation?: number
}

// Security and content moderation
export interface ContentModerationResult {
  status: 'pending' | 'approved' | 'rejected' | 'requires_review'
  confidence: number
  categories: Array<{
    name: string
    confidence: number
    severity: 'low' | 'medium' | 'high'
  }>
  flags: string[]
  reviewed_by?: number
  reviewed_at?: string
  notes?: string
}

export interface MediaSecurityScan {
  virus_scan: {
    status: 'pending' | 'clean' | 'infected' | 'error'
    engine: string
    signature_version?: string
    scanned_at: string
    threat_found?: string
  }
  content_analysis: ContentModerationResult
  duplicate_check?: {
    is_duplicate: boolean
    original_media_id?: number
    similarity_score?: number
  }
}

// Analytics and insights
export interface MediaAnalytics {
  media_id: number
  views: number
  downloads: number
  shares: number
  likes: number
  comments: number
  engagement_rate: number
  top_referrers: Array<{
    source: string
    views: number
  }>
  geographic_distribution: Array<{
    country: string
    views: number
  }>
  device_breakdown: Array<{
    device_type: string
    views: number
  }>
  time_series: Array<{
    date: string
    views: number
    downloads: number
  }>
}

// Bulk operations
export interface BulkMediaOperation {
  id: string
  type: 'delete' | 'move' | 'tag' | 'process'
  media_ids: number[]
  status: 'pending' | 'processing' | 'completed' | 'failed'
  progress: number
  total_items: number
  processed_items: number
  failed_items: number
  options: Record<string, any>
  errors: Array<{
    media_id: number
    error: string
  }>
  started_at: string
  completed_at?: string
}

// Export utility types
export type MediaType = MediaAttachment['type']
export type MediaStatus = MediaAttachment['status']
export type ProcessingStatus = MediaProcessingJob['status']
export type ModerationStatus = ContentModerationResult['status']

// Type guards and utilities
export const isImage = (media: MediaAttachment): boolean => {
  return media.type === 'image'
}

export const isVideo = (media: MediaAttachment): boolean => {
  return media.type === 'video'
}

export const isProcessed = (media: MediaAttachment): boolean => {
  return media.status === 'ready'
}

export const getFileExtension = (filename: string): string => {
  return filename.split('.').pop()?.toLowerCase() || ''
}

export const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

export const getMediaDimensions = (media: MediaAttachment): { width: number; height: number } | null => {
  if (media.width && media.height) {
    return { width: media.width, height: media.height }
  }
  return null
}

export const getMediaAspectRatio = (media: MediaAttachment): number | null => {
  const dimensions = getMediaDimensions(media)
  if (dimensions) {
    return dimensions.width / dimensions.height
  }
  return null
}

export const isMediaSupported = (file: File, rules: MediaValidationRules): boolean => {
  const type = file.type.split('/')[0] as 'image' | 'video'
  const typeRules = rules[type]
  
  if (!typeRules) return false
  
  return typeRules.allowed_types.includes(file.type) && 
         file.size <= typeRules.max_size
} 