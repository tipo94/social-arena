<template>
  <div class="media-upload-component">
    <!-- Upload Area -->
    <div
      ref="dropZone"
      class="upload-zone"
      :class="{
        'upload-zone-active': isDragOver,
        'upload-zone-disabled': disabled,
        'upload-zone-error': error
      }"
      @click="triggerFileInput"
      @drop="handleDrop"
      @dragover="handleDragOver"
      @dragenter="handleDragEnter"
      @dragleave="handleDragLeave"
    >
      <!-- Upload Icon & Text -->
      <div v-if="!uploadedFiles.length" class="upload-prompt">
        <div class="upload-icon">
          <svg v-if="type === 'images'" class="w-8 h-8 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
          </svg>
          <svg v-else-if="type === 'videos'" class="w-8 h-8 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
          </svg>
          <svg v-else class="w-8 h-8 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
          </svg>
        </div>
        
        <div class="upload-text">
          <p class="upload-title">
            {{ getUploadTitle() }}
          </p>
          <p class="upload-subtitle">
            {{ getUploadSubtitle() }}
          </p>
        </div>
      </div>

      <!-- File Preview Grid -->
      <div v-else class="file-grid">
        <div
          v-for="file in uploadedFiles"
          :key="file.id"
          class="file-item"
          :class="{ 'file-item-processing': file.status === 'processing' }"
        >
          <!-- Image Preview -->
          <div v-if="file.type === 'image'" class="file-preview">
            <img 
              :src="file.preview_url || file.url" 
              :alt="file.filename"
              class="file-image"
              @load="handleImageLoad"
            />
            
            <!-- Processing Overlay -->
            <div v-if="file.status === 'processing'" class="processing-overlay">
              <div class="processing-spinner"></div>
              <span class="processing-text">Processing...</span>
            </div>
          </div>

          <!-- Video Preview -->
          <div v-else-if="file.type === 'video'" class="file-preview">
            <video 
              v-if="file.preview_url || file.url"
              :src="file.preview_url || file.url"
              class="file-video"
              controls
              preload="metadata"
            ></video>
            
            <div v-else class="video-placeholder">
              <svg class="w-8 h-8 text-neutral-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z" />
              </svg>
            </div>

            <!-- Processing Overlay -->
            <div v-if="file.status === 'processing'" class="processing-overlay">
              <div class="processing-spinner"></div>
              <span class="processing-text">Processing...</span>
              <div v-if="file.progress !== undefined" class="progress-bar">
                <div class="progress-fill" :style="{ width: `${file.progress}%` }"></div>
              </div>
            </div>
          </div>

          <!-- File Info -->
          <div class="file-info">
            <span class="file-name">{{ file.filename }}</span>
            <span class="file-size">{{ formatFileSize(file.size) }}</span>
          </div>

          <!-- Remove Button -->
          <button
            @click.stop="removeFile(file.id)"
            :disabled="disabled || file.status === 'processing'"
            class="file-remove"
            title="Remove file"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Add More Button -->
        <button
          v-if="uploadedFiles.length < maxFiles"
          @click.stop="triggerFileInput"
          :disabled="disabled"
          class="add-more-btn"
          title="Add more files"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
          </svg>
        </button>
      </div>
    </div>

    <!-- File Input -->
    <input
      ref="fileInput"
      type="file"
      :multiple="maxFiles > 1"
      :accept="acceptedTypes"
      :disabled="disabled"
      class="hidden"
      @change="handleFileSelect"
    />

    <!-- Upload Progress -->
    <div v-if="uploadProgress.length" class="upload-progress mt-4">
      <div v-for="progress in uploadProgress" :key="progress.fileName" class="progress-item">
        <div class="flex justify-between text-sm text-neutral-600 mb-1">
          <span>{{ progress.fileName }}</span>
          <span>{{ Math.round(progress.progress) }}%</span>
        </div>
        <div class="progress-bar">
          <div 
            class="progress-fill bg-primary-500" 
            :style="{ width: `${progress.progress}%` }"
          ></div>
        </div>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="mt-2 text-sm text-red-600">
      {{ error }}
    </div>

    <!-- File Info -->
    <div v-if="uploadedFiles.length" class="file-summary mt-3">
      <div class="text-sm text-neutral-600">
        {{ uploadedFiles.length }} of {{ maxFiles }} files uploaded
        <span v-if="totalSize"> • Total size: {{ formatFileSize(totalSize) }}</span>
      </div>
    </div>

    <!-- Upload Guidelines -->
    <div class="upload-guidelines mt-3 text-xs text-neutral-500">
      <div class="flex flex-wrap gap-4">
        <span>Max {{ maxFiles }} file{{ maxFiles > 1 ? 's' : '' }}</span>
        <span>Max {{ formatFileSize(getMaxFileSize()) }} per file</span>
        <span>{{ getAcceptedFormats() }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import { mediaService } from '@/services/mediaService'
import type { MediaAttachment } from '@/types/media'

interface UploadProgress {
  fileName: string
  progress: number
  id?: string
}

interface UploadedFile extends MediaAttachment {
  preview_url?: string
  progress?: number
  status: 'uploading' | 'processing' | 'ready' | 'error'
}

interface Props {
  modelValue?: number[]
  type: 'images' | 'videos' | 'mixed'
  maxFiles?: number
  disabled?: boolean
  error?: string
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => [],
  maxFiles: 5,
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: number[]]
  upload: [files: UploadedFile[]]
  remove: [fileId: number]
  error: [error: string]
}>()

// Refs
const dropZone = ref<HTMLElement>()
const fileInput = ref<HTMLInputElement>()

// State
const uploadedFiles = ref<UploadedFile[]>([])
const uploadProgress = ref<UploadProgress[]>([])
const isDragOver = ref(false)
const dragCounter = ref(0)

// Computed
const acceptedTypes = computed(() => {
  switch (props.type) {
    case 'images':
      return 'image/jpeg,image/jpg,image/png,image/gif,image/webp'
    case 'videos':
      return 'video/mp4,video/webm,video/ogg,video/avi,video/mov'
    case 'mixed':
      return 'image/jpeg,image/jpg,image/png,image/gif,image/webp,video/mp4,video/webm,video/ogg'
    default:
      return '*/*'
  }
})

const totalSize = computed(() => {
  return uploadedFiles.value.reduce((total, file) => total + (file.size || 0), 0)
})

// Methods
const getMaxFileSize = () => {
  switch (props.type) {
    case 'images':
      return 10 * 1024 * 1024 // 10MB
    case 'videos':
      return 100 * 1024 * 1024 // 100MB
    case 'mixed':
      return 50 * 1024 * 1024 // 50MB
    default:
      return 10 * 1024 * 1024 // 10MB
  }
}

const getUploadTitle = () => {
  if (props.type === 'images') return 'Upload Images'
  if (props.type === 'videos') return 'Upload Videos'
  return 'Upload Files'
}

const getUploadSubtitle = () => {
  if (props.type === 'images') {
    return 'Click to select or drag and drop images here'
  }
  if (props.type === 'videos') {
    return 'Click to select or drag and drop videos here'
  }
  return 'Click to select or drag and drop files here'
}

const getAcceptedFormats = () => {
  switch (props.type) {
    case 'images':
      return 'JPG, PNG, GIF, WebP'
    case 'videos':
      return 'MP4, WebM, OGG, AVI, MOV'
    case 'mixed':
      return 'Images & Videos'
    default:
      return 'All formats'
  }
}

const formatFileSize = (bytes: number) => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const validateFile = (file: File): string | null => {
  // Check file size
  const maxSize = getMaxFileSize()
  if (file.size > maxSize) {
    return `File size exceeds ${formatFileSize(maxSize)} limit`
  }

  // Check file type
  const allowedTypes = acceptedTypes.value.split(',')
  if (!allowedTypes.includes(file.type) && acceptedTypes.value !== '*/*') {
    return `File type ${file.type} is not supported`
  }

  // Check max files
  if (uploadedFiles.value.length >= props.maxFiles) {
    return `Maximum ${props.maxFiles} files allowed`
  }

  return null
}

const createFilePreview = (file: File): Promise<string> => {
  return new Promise((resolve) => {
    if (file.type.startsWith('image/')) {
      const reader = new FileReader()
      reader.onload = (e) => resolve(e.target?.result as string)
      reader.readAsDataURL(file)
    } else if (file.type.startsWith('video/')) {
      const video = document.createElement('video')
      const canvas = document.createElement('canvas')
      const ctx = canvas.getContext('2d')
      
      video.onloadedmetadata = () => {
        canvas.width = video.videoWidth
        canvas.height = video.videoHeight
        ctx?.drawImage(video, 0, 0)
        resolve(canvas.toDataURL())
      }
      
      video.src = URL.createObjectURL(file)
    } else {
      resolve('')
    }
  })
}

const uploadFile = async (file: File) => {
  const tempId = `temp_${Date.now()}_${Math.random()}`
  
  try {
    // Create preview
    const previewUrl = await createFilePreview(file)
    
    // Add to uploaded files with temp status
    const tempFile: UploadedFile = {
      id: tempId as any,
      filename: file.name,
      type: file.type.startsWith('image/') ? 'image' : 'video',
      size: file.size,
      preview_url: previewUrl,
      status: 'uploading',
      progress: 0,
      url: '',
      created_at: new Date().toISOString(),
    }
    
    uploadedFiles.value.push(tempFile)
    
    // Add to upload progress
    const progressItem: UploadProgress = {
      fileName: file.name,
      progress: 0,
      id: tempId,
    }
    uploadProgress.value.push(progressItem)
    
    // Upload file
    const uploadType = props.type === 'images' ? 'posts' : 'videos'
    const result = await mediaService.uploadFile(file, uploadType, {
      onProgress: (progress: number) => {
        const progressIndex = uploadProgress.value.findIndex(p => p.id === tempId)
        if (progressIndex >= 0) {
          uploadProgress.value[progressIndex].progress = progress
        }
        
        const fileIndex = uploadedFiles.value.findIndex(f => f.id === tempId)
        if (fileIndex >= 0) {
          uploadedFiles.value[fileIndex].progress = progress
        }
      }
    })
    
    if (result.success) {
      // Update with real file data
      const fileIndex = uploadedFiles.value.findIndex(f => f.id === tempId)
      if (fileIndex >= 0) {
        uploadedFiles.value[fileIndex] = {
          ...result.data,
          preview_url: previewUrl,
          status: result.data.status === 'ready' ? 'ready' : 'processing',
        }
      }
      
      // Remove from progress
      const progressIndex = uploadProgress.value.findIndex(p => p.id === tempId)
      if (progressIndex >= 0) {
        uploadProgress.value.splice(progressIndex, 1)
      }
      
      // Emit upload event
      emit('upload', [result.data])
      
      // Update model value
      updateModelValue()
      
    } else {
      throw new Error(result.message || 'Upload failed')
    }
    
  } catch (error: any) {
    console.error('Upload error:', error)
    
    // Remove failed upload
    const fileIndex = uploadedFiles.value.findIndex(f => f.id === tempId)
    if (fileIndex >= 0) {
      uploadedFiles.value.splice(fileIndex, 1)
    }
    
    const progressIndex = uploadProgress.value.findIndex(p => p.id === tempId)
    if (progressIndex >= 0) {
      uploadProgress.value.splice(progressIndex, 1)
    }
    
    // Emit error
    emit('error', error.message || 'Upload failed')
  }
}

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const files = Array.from(target.files || [])
  processFiles(files)
  target.value = '' // Reset input
}

const handleDrop = (event: DragEvent) => {
  event.preventDefault()
  isDragOver.value = false
  dragCounter.value = 0
  
  if (props.disabled) return
  
  const files = Array.from(event.dataTransfer?.files || [])
  processFiles(files)
}

const handleDragOver = (event: DragEvent) => {
  event.preventDefault()
}

const handleDragEnter = (event: DragEvent) => {
  event.preventDefault()
  dragCounter.value++
  isDragOver.value = true
}

const handleDragLeave = (event: DragEvent) => {
  event.preventDefault()
  dragCounter.value--
  if (dragCounter.value === 0) {
    isDragOver.value = false
  }
}

const processFiles = (files: File[]) => {
  if (props.disabled) return
  
  for (const file of files) {
    const error = validateFile(file)
    if (error) {
      emit('error', error)
      continue
    }
    
    uploadFile(file)
  }
}

const triggerFileInput = () => {
  if (!props.disabled && uploadedFiles.value.length < props.maxFiles) {
    fileInput.value?.click()
  }
}

const removeFile = (fileId: number | string) => {
  const index = uploadedFiles.value.findIndex(f => f.id === fileId)
  if (index >= 0) {
    const file = uploadedFiles.value[index]
    uploadedFiles.value.splice(index, 1)
    
    // Emit remove event if it's a real file
    if (typeof fileId === 'number') {
      emit('remove', fileId)
    }
    
    updateModelValue()
  }
}

const updateModelValue = () => {
  const fileIds = uploadedFiles.value
    .filter(f => typeof f.id === 'number' && f.status === 'ready')
    .map(f => f.id as number)
  
  emit('update:modelValue', fileIds)
}

const handleImageLoad = () => {
  // Optional: Handle image load events
}

// Watch for external changes to model value
watch(() => props.modelValue, async (newIds) => {
  if (!newIds || newIds.length === 0) {
    uploadedFiles.value = []
    return
  }
  
  // Load file data for provided IDs
  try {
    const filePromises = newIds.map(id => mediaService.getFile(id))
    const files = await Promise.all(filePromises)
    
    uploadedFiles.value = files.map(file => ({
      ...file,
      status: 'ready' as const,
    }))
  } catch (error) {
    console.error('Failed to load files:', error)
  }
}, { immediate: true })

// Cleanup on unmount
onUnmounted(() => {
  // Clean up any object URLs
  uploadedFiles.value.forEach(file => {
    if (file.preview_url && file.preview_url.startsWith('blob:')) {
      URL.revokeObjectURL(file.preview_url)
    }
  })
})
</script>

<style scoped>
.media-upload-component {
  @apply w-full;
}

.upload-zone {
  @apply relative border-2 border-dashed border-neutral-300 rounded-lg p-6 text-center cursor-pointer transition-colors;
  min-height: 120px;
}

.upload-zone:hover {
  @apply border-primary-400 bg-primary-50;
}

.upload-zone-active {
  @apply border-primary-500 bg-primary-100;
}

.upload-zone-disabled {
  @apply cursor-not-allowed opacity-50;
}

.upload-zone-error {
  @apply border-red-400 bg-red-50;
}

.upload-prompt {
  @apply flex flex-col items-center justify-center space-y-3;
}

.upload-icon {
  @apply flex items-center justify-center w-12 h-12 mx-auto mb-2;
}

.upload-title {
  @apply text-sm font-medium text-neutral-900;
}

.upload-subtitle {
  @apply text-xs text-neutral-500;
}

.file-grid {
  @apply grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4;
}

.file-item {
  @apply relative bg-white border border-neutral-200 rounded-lg overflow-hidden;
}

.file-item-processing {
  @apply opacity-75;
}

.file-preview {
  @apply relative aspect-square bg-neutral-100;
}

.file-image,
.file-video {
  @apply w-full h-full object-cover;
}

.video-placeholder {
  @apply flex items-center justify-center w-full h-full;
}

.processing-overlay {
  @apply absolute inset-0 bg-black bg-opacity-50 flex flex-col items-center justify-center text-white text-xs;
}

.processing-spinner {
  @apply animate-spin w-6 h-6 border-2 border-white border-t-transparent rounded-full mb-2;
}

.processing-text {
  @apply mb-2;
}

.file-info {
  @apply p-2 text-xs;
}

.file-name {
  @apply block font-medium text-neutral-900 truncate;
}

.file-size {
  @apply block text-neutral-500 mt-1;
}

.file-remove {
  @apply absolute top-2 right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors;
}

.add-more-btn {
  @apply aspect-square bg-neutral-100 border-2 border-dashed border-neutral-300 rounded-lg flex items-center justify-center text-neutral-500 hover:border-primary-400 hover:text-primary-600 transition-colors;
}

.upload-progress {
  @apply space-y-2;
}

.progress-item {
  @apply text-sm;
}

.progress-bar {
  @apply w-full bg-neutral-200 rounded-full h-2 overflow-hidden;
}

.progress-fill {
  @apply h-full bg-primary-500 transition-all duration-300 ease-out;
}

.file-summary {
  @apply text-sm text-neutral-600;
}

.upload-guidelines {
  @apply text-xs text-neutral-500;
}
</style> 