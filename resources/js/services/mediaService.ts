import axios from 'axios'
import type { AxiosResponse, AxiosProgressEvent } from 'axios'
import type { 
  MediaAttachment, 
  MediaUploadOptions, 
  MediaUploadResult, 
  MediaUploadError,
  MediaValidationRules,
  MediaValidationResult,
  MediaAnalytics,
  MediaGallery,
  ApiResponse
} from '@/types/media'

class MediaService {
  private baseURL = '/api'
  private uploadQueue: Map<string, AbortController> = new Map()

  // Validation rules (these would typically come from the backend)
  private validationRules: MediaValidationRules = {
    image: {
      max_size: 10 * 1024 * 1024, // 10MB
      max_width: 4096,
      max_height: 4096,
      min_width: 100,
      min_height: 100,
      allowed_types: [
        'image/jpeg',
        'image/jpg', 
        'image/png',
        'image/gif',
        'image/webp'
      ],
      quality_min: 10,
      quality_max: 100
    },
    video: {
      max_size: 100 * 1024 * 1024, // 100MB
      max_duration: 600, // 10 minutes
      max_width: 1920,
      max_height: 1080,
      allowed_types: [
        'video/mp4',
        'video/webm',
        'video/ogg',
        'video/avi',
        'video/mov',
        'video/quicktime'
      ],
      max_bitrate: 5000000 // 5Mbps
    },
    document: {
      max_size: 25 * 1024 * 1024, // 25MB
      allowed_types: [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
      ]
    }
  }

  // File upload operations
  async uploadFile(
    file: File, 
    type: 'posts' | 'avatars' | 'covers' | 'messages' | 'temp' = 'posts', 
    options: MediaUploadOptions = {}
  ): Promise<MediaUploadResult> {
    try {
      // Validate file before upload
      const validation = this.validateFile(file)
      if (!validation.valid) {
        return {
          success: false,
          message: 'File validation failed',
          error: validation.errors[0]
        }
      }

      // Create upload identifier
      const uploadId = `${Date.now()}_${Math.random()}`
      const abortController = new AbortController()
      this.uploadQueue.set(uploadId, abortController)

      // Prepare form data
      const formData = new FormData()
      formData.append('file', file)
      formData.append('type', type)
      
      if (options.folder) {
        formData.append('folder', options.folder)
      }
      
      if (options.alt_text) {
        formData.append('alt_text', options.alt_text)
      }

      if (options.quality) {
        formData.append('quality', options.quality.toString())
      }

      if (options.resize) {
        formData.append('resize', JSON.stringify(options.resize))
      }

      if (options.watermark) {
        formData.append('watermark', JSON.stringify(options.watermark))
      }

      // Upload file with progress tracking
      const response: AxiosResponse<ApiResponse<MediaAttachment>> = await axios.post(
        `${this.baseURL}/media/upload`,
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data'
          },
          signal: abortController.signal,
          onUploadProgress: (progressEvent: AxiosProgressEvent) => {
            if (progressEvent.total) {
              const progress = Math.round((progressEvent.loaded * 100) / progressEvent.total)
              options.onProgress?.(progress)
            }
          }
        }
      )

      // Remove from upload queue
      this.uploadQueue.delete(uploadId)

      const result: MediaUploadResult = {
        success: response.data.success,
        message: response.data.message,
        data: response.data.data,
        processing: response.data.data?.status === 'processing',
      }

      // Call completion callback
      if (result.success && result.data) {
        options.onComplete?.(result.data)
      }

      return result

    } catch (error: any) {
      if (error.name === 'AbortError') {
        return {
          success: false,
          message: 'Upload cancelled',
          error: {
            code: 'UPLOAD_CANCELLED',
            message: 'Upload was cancelled by user'
          }
        }
      }

      const uploadError = this.handleUploadError(error)
      options.onError?.(uploadError.message)
      
      return {
        success: false,
        message: uploadError.message,
        error: uploadError
      }
    }
  }

  async uploadMultipleFiles(
    files: File[], 
    type: 'posts' | 'avatars' | 'covers' | 'messages' | 'temp' = 'posts',
    options: MediaUploadOptions = {}
  ): Promise<{ 
    successful: MediaAttachment[], 
    failed: Array<{ file: File, error: MediaUploadError }> 
  }> {
    const successful: MediaAttachment[] = []
    const failed: Array<{ file: File, error: MediaUploadError }> = []

    const uploadPromises = files.map(async (file) => {
      try {
        const result = await this.uploadFile(file, type, {
          ...options,
          onProgress: (progress) => {
            // Could aggregate progress here for multiple files
            options.onProgress?.(progress)
          }
        })

        if (result.success && result.data) {
          successful.push(result.data)
        } else {
          failed.push({ 
            file, 
            error: result.error || {
              code: 'UPLOAD_FAILED',
              message: result.message
            }
          })
        }
      } catch (error: any) {
        failed.push({ 
          file, 
          error: this.handleUploadError(error)
        })
      }
    })

    await Promise.allSettled(uploadPromises)

    return { successful, failed }
  }

  // File management operations
  async getFile(id: number): Promise<MediaAttachment> {
    try {
      const response: AxiosResponse<ApiResponse<MediaAttachment>> = await axios.get(
        `${this.baseURL}/media/${id}`
      )
      return response.data.data!
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async updateFile(id: number, data: {
    alt_text?: string
    tags?: string[]
    description?: string
  }): Promise<MediaAttachment> {
    try {
      const response: AxiosResponse<ApiResponse<MediaAttachment>> = await axios.put(
        `${this.baseURL}/media/${id}`,
        data
      )
      return response.data.data!
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async deleteFile(id: number): Promise<void> {
    try {
      await axios.delete(`${this.baseURL}/media/${id}`)
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async getFileUrl(id: number, variant?: 'original' | 'thumbnail' | 'preview' | 'compressed'): Promise<string> {
    try {
      const response: AxiosResponse<{ url: string }> = await axios.get(
        `${this.baseURL}/media/${id}/url`,
        { params: { variant } }
      )
      return response.data.url
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Processing operations
  async processImage(id: number, options: {
    resize?: { width?: number; height?: number; maintain_aspect_ratio?: boolean }
    quality?: number
    format?: 'jpeg' | 'png' | 'webp' | 'avif'
    watermark?: { 
      text?: string
      position?: 'top-left' | 'top-right' | 'bottom-left' | 'bottom-right' | 'center'
      opacity?: number
    }
  }): Promise<{ job_id: string }> {
    try {
      const response: AxiosResponse<{ job_id: string }> = await axios.post(
        `${this.baseURL}/media/${id}/process/image`,
        options
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async processVideo(id: number, options: {
    resize?: { width?: number; height?: number }
    quality?: 'low' | 'medium' | 'high' | 'ultra'
    format?: 'mp4' | 'webm' | 'ogg'
    trim?: { start: number; end: number }
    generate_thumbnail?: { at: number }
  }): Promise<{ job_id: string }> {
    try {
      const response: AxiosResponse<{ job_id: string }> = await axios.post(
        `${this.baseURL}/media/${id}/process/video`,
        options
      )
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async getProcessingStatus(jobId: string): Promise<{
    status: 'pending' | 'processing' | 'completed' | 'failed'
    progress: number
    error?: string
    result?: MediaAttachment
  }> {
    try {
      const response = await axios.get(`${this.baseURL}/media/processing/${jobId}`)
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Gallery operations
  async createGallery(data: {
    name: string
    description?: string
    is_public?: boolean
  }): Promise<MediaGallery> {
    try {
      const response: AxiosResponse<ApiResponse<MediaGallery>> = await axios.post(
        `${this.baseURL}/media/galleries`,
        data
      )
      return response.data.data!
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async getGalleries(): Promise<MediaGallery[]> {
    try {
      const response: AxiosResponse<ApiResponse<MediaGallery[]>> = await axios.get(
        `${this.baseURL}/media/galleries`
      )
      return response.data.data!
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async addToGallery(galleryId: number, mediaIds: number[]): Promise<void> {
    try {
      await axios.post(`${this.baseURL}/media/galleries/${galleryId}/media`, {
        media_ids: mediaIds
      })
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async removeFromGallery(galleryId: number, mediaIds: number[]): Promise<void> {
    try {
      await axios.delete(`${this.baseURL}/media/galleries/${galleryId}/media`, {
        data: { media_ids: mediaIds }
      })
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Search and filtering
  async searchMedia(params: {
    query?: string
    type?: 'image' | 'video' | 'document'
    tags?: string[]
    date_from?: string
    date_to?: string
    sort?: 'newest' | 'oldest' | 'largest' | 'smallest' | 'name'
    per_page?: number
    page?: number
  }): Promise<{
    data: MediaAttachment[]
    pagination: any
  }> {
    try {
      const response = await axios.get(`${this.baseURL}/media/search`, { params })
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async getUserMedia(userId?: number, params: {
    type?: 'image' | 'video' | 'document'
    per_page?: number
    page?: number
  } = {}): Promise<{
    data: MediaAttachment[]
    pagination: any
  }> {
    try {
      const endpoint = userId ? `/users/${userId}/media` : '/my/media'
      const response = await axios.get(`${this.baseURL}${endpoint}`, { params })
      return response.data
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  // Analytics
  async getMediaAnalytics(id: number): Promise<MediaAnalytics> {
    try {
      const response: AxiosResponse<ApiResponse<MediaAnalytics>> = await axios.get(
        `${this.baseURL}/media/${id}/analytics`
      )
      return response.data.data!
    } catch (error: any) {
      throw this.handleError(error)
    }
  }

  async incrementView(id: number): Promise<void> {
    try {
      await axios.post(`${this.baseURL}/media/${id}/view`)
    } catch (error: any) {
      // Fail silently for view tracking
      console.warn('Failed to track media view:', error)
    }
  }

  async incrementDownload(id: number): Promise<void> {
    try {
      await axios.post(`${this.baseURL}/media/${id}/download`)
    } catch (error: any) {
      // Fail silently for download tracking
      console.warn('Failed to track media download:', error)
    }
  }

  // Utility methods
  cancelUpload(uploadId: string): void {
    const controller = this.uploadQueue.get(uploadId)
    if (controller) {
      controller.abort()
      this.uploadQueue.delete(uploadId)
    }
  }

  cancelAllUploads(): void {
    this.uploadQueue.forEach((controller, id) => {
      controller.abort()
    })
    this.uploadQueue.clear()
  }

  validateFile(file: File): MediaValidationResult {
    const errors: MediaUploadError[] = []
    const warnings: string[] = []

    // Determine file type
    const fileType = file.type.split('/')[0] as 'image' | 'video'
    const rules = this.validationRules[fileType]

    if (!rules) {
      errors.push({
        code: 'UNSUPPORTED_TYPE',
        message: `File type ${file.type} is not supported`,
        fileName: file.name,
        details: { file_type: file.type }
      })
      return { valid: false, errors, warnings }
    }

    // Check file size
    if (file.size > rules.max_size) {
      errors.push({
        code: 'FILE_TOO_LARGE',
        message: `File size ${this.formatFileSize(file.size)} exceeds limit of ${this.formatFileSize(rules.max_size)}`,
        fileName: file.name,
        details: { 
          file_size: file.size, 
          max_size: rules.max_size 
        }
      })
    }

    // Check MIME type
    if (!rules.allowed_types.includes(file.type)) {
      errors.push({
        code: 'INVALID_MIME_TYPE',
        message: `File type ${file.type} is not allowed`,
        fileName: file.name,
        details: { 
          file_type: file.type, 
          allowed_types: rules.allowed_types 
        }
      })
    }

    // Warn about large files
    if (file.size > rules.max_size * 0.8) {
      warnings.push(`File is quite large (${this.formatFileSize(file.size)}). Consider compressing it for faster upload.`)
    }

    return {
      valid: errors.length === 0,
      errors,
      warnings
    }
  }

  formatFileSize(bytes: number): string {
    if (bytes === 0) return '0 Bytes'
    const k = 1024
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB']
    const i = Math.floor(Math.log(bytes) / Math.log(k))
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
  }

  getFileTypeIcon(mimeType: string): string {
    if (mimeType.startsWith('image/')) return '🖼️'
    if (mimeType.startsWith('video/')) return '🎥'
    if (mimeType.startsWith('audio/')) return '🎵'
    if (mimeType === 'application/pdf') return '📄'
    if (mimeType.includes('word') || mimeType.includes('document')) return '📝'
    if (mimeType.includes('spreadsheet') || mimeType.includes('excel')) return '📊'
    if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return '📈'
    return '📎'
  }

  isImage(file: File | MediaAttachment): boolean {
    const mimeType = 'type' in file ? file.mime_type : file.type
    return mimeType.startsWith('image/')
  }

  isVideo(file: File | MediaAttachment): boolean {
    const mimeType = 'type' in file ? file.mime_type : file.type
    return mimeType.startsWith('video/')
  }

  getImageDimensions(file: File): Promise<{ width: number; height: number }> {
    return new Promise((resolve, reject) => {
      if (!this.isImage(file)) {
        reject(new Error('File is not an image'))
        return
      }

      const img = new Image()
      img.onload = () => {
        resolve({ width: img.naturalWidth, height: img.naturalHeight })
      }
      img.onerror = () => {
        reject(new Error('Failed to load image'))
      }
      img.src = URL.createObjectURL(file)
    })
  }

  getVideoDuration(file: File): Promise<number> {
    return new Promise((resolve, reject) => {
      if (!this.isVideo(file)) {
        reject(new Error('File is not a video'))
        return
      }

      const video = document.createElement('video')
      video.onloadedmetadata = () => {
        resolve(video.duration)
      }
      video.onerror = () => {
        reject(new Error('Failed to load video'))
      }
      video.src = URL.createObjectURL(file)
    })
  }

  createThumbnail(file: File, maxWidth = 200, maxHeight = 200, quality = 0.8): Promise<Blob> {
    return new Promise((resolve, reject) => {
      if (!this.isImage(file)) {
        reject(new Error('File is not an image'))
        return
      }

      const canvas = document.createElement('canvas')
      const ctx = canvas.getContext('2d')
      const img = new Image()

      img.onload = () => {
        // Calculate new dimensions
        let { width, height } = img
        
        if (width > height) {
          if (width > maxWidth) {
            height = (height * maxWidth) / width
            width = maxWidth
          }
        } else {
          if (height > maxHeight) {
            width = (width * maxHeight) / height
            height = maxHeight
          }
        }

        canvas.width = width
        canvas.height = height

        // Draw and compress
        ctx?.drawImage(img, 0, 0, width, height)
        canvas.toBlob((blob) => {
          if (blob) {
            resolve(blob)
          } else {
            reject(new Error('Failed to create thumbnail'))
          }
        }, 'image/jpeg', quality)
      }

      img.onerror = () => {
        reject(new Error('Failed to load image for thumbnail'))
      }

      img.src = URL.createObjectURL(file)
    })
  }

  // Error handling
  private handleError(error: any): Error {
    if (error.response?.data?.message) {
      return new Error(error.response.data.message)
    }
    return new Error(error.message || 'An unexpected error occurred')
  }

  private handleUploadError(error: any): MediaUploadError {
    if (error.response?.data?.errors) {
      return {
        code: 'VALIDATION_ERROR',
        message: error.response.data.message || 'Validation failed',
        details: error.response.data.errors
      }
    }

    if (error.response?.status === 413) {
      return {
        code: 'FILE_TOO_LARGE',
        message: 'File is too large to upload'
      }
    }

    if (error.response?.status === 415) {
      return {
        code: 'UNSUPPORTED_TYPE',
        message: 'File type is not supported'
      }
    }

    return {
      code: 'UPLOAD_ERROR',
      message: error.message || 'Upload failed'
    }
  }
}

export const mediaService = new MediaService()
export default mediaService 