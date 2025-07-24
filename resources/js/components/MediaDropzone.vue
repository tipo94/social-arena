<template>
  <div 
    class="media-dropzone relative"
    :class="dropzoneClasses"
    @dragover.prevent="handleDragOver"
    @dragleave.prevent="handleDragLeave"
    @drop.prevent="handleDrop"
    @click="openFileDialog"
  >
    <!-- Hidden file input -->
    <input
      ref="fileInput"
      type="file"
      :multiple="multiple"
      :accept="acceptedTypes"
      class="hidden"
      @change="handleFileSelect"
    />

    <!-- Dropzone content -->
    <div class="text-center p-8">
      <!-- Drag active state -->
      <div v-if="isDragActive" class="space-y-4">
        <CloudArrowUpIcon class="w-16 h-16 mx-auto text-blue-500 animate-bounce" />
        <div>
          <p class="text-lg font-semibold text-blue-600">Drop files here</p>
          <p class="text-sm text-gray-500">Release to upload</p>
        </div>
      </div>

      <!-- Default state -->
      <div v-else-if="!hasFiles" class="space-y-4">
        <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center">
          <PhotoIcon v-if="mediaType === 'images'" class="w-8 h-8 text-gray-400" />
          <VideoCameraIcon v-else-if="mediaType === 'videos'" class="w-8 h-8 text-gray-400" />
          <DocumentIcon v-else class="w-8 h-8 text-gray-400" />
        </div>
        
        <div>
          <p class="text-lg font-semibold text-gray-900">
            {{ dropzoneTitle }}
          </p>
          <p class="text-sm text-gray-500 mt-1">
            {{ dropzoneSubtitle }}
          </p>
        </div>

        <div class="flex items-center justify-center space-x-2 text-sm text-gray-500">
          <span>{{ getAcceptedFormats() }}</span>
          <span>•</span>
          <span>Max {{ formatFileSize(maxFileSize) }}</span>
        </div>

        <button
          type="button"
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          <PlusIcon class="w-4 h-4 mr-2" />
          Choose Files
        </button>
      </div>

      <!-- Files selected state -->
      <div v-else class="space-y-4">
        <div class="flex items-center justify-center space-x-2">
          <CheckCircleIcon class="w-6 h-6 text-green-500" />
          <span class="text-lg font-semibold text-gray-900">
            {{ files.length }} file{{ files.length !== 1 ? 's' : '' }} selected
          </span>
        </div>

        <button
          type="button"
          @click.stop="clearFiles"
          class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
        >
          Clear and select different files
        </button>
      </div>
    </div>

    <!-- File previews -->
    <div v-if="hasFiles && showPreviews" class="border-t border-gray-200 p-4">
      <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
        <div
          v-for="(file, index) in files"
          :key="`file-${index}`"
          class="relative group"
        >
          <!-- Image preview -->
          <div
            v-if="file.type.startsWith('image/')"
            class="aspect-square bg-gray-100 rounded-lg overflow-hidden"
          >
            <img
              :src="file.preview"
              :alt="file.file.name"
              class="w-full h-full object-cover"
            />
          </div>

          <!-- Video preview -->
          <div
            v-else-if="file.type.startsWith('video/')"
            class="aspect-square bg-black rounded-lg overflow-hidden relative"
          >
            <video
              v-if="file.preview"
              :src="file.preview"
              class="w-full h-full object-cover"
              muted
            />
            <div class="absolute inset-0 flex items-center justify-center">
              <PlayIcon class="w-8 h-8 text-white opacity-80" />
            </div>
          </div>

          <!-- Document preview -->
          <div
            v-else
            class="aspect-square bg-gray-100 rounded-lg flex flex-col items-center justify-center p-4"
          >
            <DocumentIcon class="w-8 h-8 text-gray-400 mb-2" />
            <span class="text-xs text-gray-600 text-center truncate w-full">
              {{ file.file.name }}
            </span>
          </div>

          <!-- File info overlay -->
          <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 transition-opacity rounded-lg flex items-center justify-center">
            <div class="opacity-0 group-hover:opacity-100 transition-opacity text-center">
              <p class="text-white text-sm font-medium truncate px-2">
                {{ file.file.name }}
              </p>
              <p class="text-white text-xs mt-1">
                {{ formatFileSize(file.file.size) }}
              </p>
            </div>
          </div>

          <!-- Remove button -->
          <button
            @click.stop="removeFile(index)"
            class="absolute -top-2 -right-2 w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity hover:bg-red-600"
          >
            <XMarkIcon class="w-4 h-4" />
          </button>

          <!-- Upload progress -->
          <div
            v-if="file.uploading"
            class="absolute inset-0 bg-black bg-opacity-50 rounded-lg flex items-center justify-center"
          >
            <div class="text-center text-white">
              <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-white mx-auto mb-2"></div>
              <div class="text-sm">{{ Math.round(file.progress || 0) }}%</div>
            </div>
          </div>

          <!-- Upload success -->
          <div
            v-if="file.uploaded"
            class="absolute inset-0 bg-green-500 bg-opacity-50 rounded-lg flex items-center justify-center"
          >
            <CheckCircleIcon class="w-8 h-8 text-white" />
          </div>

          <!-- Upload error -->
          <div
            v-if="file.error"
            class="absolute inset-0 bg-red-500 bg-opacity-50 rounded-lg flex items-center justify-center"
          >
            <ExclamationTriangleIcon class="w-8 h-8 text-white" />
          </div>
        </div>
      </div>

      <!-- Upload actions -->
      <div v-if="!allUploaded" class="mt-4 flex items-center justify-between">
        <div class="text-sm text-gray-600">
          {{ uploadedCount }} of {{ files.length }} uploaded
        </div>
        
        <div class="flex items-center space-x-2">
          <button
            @click="clearFiles"
            :disabled="uploading"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Clear
          </button>
          
          <button
            @click="uploadFiles"
            :disabled="uploading || files.length === 0"
            class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ uploading ? 'Uploading...' : 'Upload Files' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Error messages -->
    <div v-if="errors.length > 0" class="border-t border-red-200 bg-red-50 p-4">
      <div class="flex items-start space-x-2">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" />
        <div class="flex-1">
          <h4 class="text-sm font-medium text-red-800">Upload Errors</h4>
          <ul class="text-sm text-red-700 mt-1 space-y-1">
            <li v-for="(error, index) in errors" :key="`error-${index}`">
              {{ error }}
            </li>
          </ul>
        </div>
        
        <button
          @click="clearErrors"
          class="text-red-500 hover:text-red-700"
        >
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  CloudArrowUpIcon,
  PhotoIcon,
  VideoCameraIcon,
  DocumentIcon,
  PlusIcon,
  PlayIcon,
  XMarkIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { mediaService } from '@/services/mediaService'

interface FilePreview {
  file: File
  preview?: string
  type: string
  uploading: boolean
  uploaded: boolean
  progress?: number
  error?: string
  result?: any
}

interface Props {
  mediaType?: 'images' | 'videos' | 'documents' | 'mixed'
  multiple?: boolean
  maxFiles?: number
  maxFileSize?: number
  showPreviews?: boolean
  autoUpload?: boolean
  disabled?: boolean
}

interface Emits {
  (e: 'files-selected', files: File[]): void
  (e: 'upload-progress', progress: number): void
  (e: 'upload-complete', results: any[]): void
  (e: 'upload-error', errors: string[]): void
}

const props = withDefaults(defineProps<Props>(), {
  mediaType: 'mixed',
  multiple: true,
  maxFiles: 10,
  maxFileSize: 10 * 1024 * 1024, // 10MB
  showPreviews: true,
  autoUpload: false,
  disabled: false
})

const emit = defineEmits<Emits>()

// Refs
const fileInput = ref<HTMLInputElement>()

// State
const isDragActive = ref(false)
const files = ref<FilePreview[]>([])
const errors = ref<string[]>([])

// Computed
const hasFiles = computed(() => files.value.length > 0)

const uploading = computed(() => 
  files.value.some(f => f.uploading)
)

const uploadedCount = computed(() => 
  files.value.filter(f => f.uploaded).length
)

const allUploaded = computed(() => 
  hasFiles.value && files.value.every(f => f.uploaded || f.error)
)

const acceptedTypes = computed(() => {
  switch (props.mediaType) {
    case 'images':
      return 'image/*'
    case 'videos':
      return 'video/*'
    case 'documents':
      return '.pdf,.doc,.docx,.txt'
    default:
      return 'image/*,video/*,.pdf,.doc,.docx,.txt'
  }
})

const dropzoneClasses = computed(() => [
  'border-2 border-dashed rounded-lg cursor-pointer transition-all duration-200',
  {
    'border-blue-300 bg-blue-50': isDragActive.value,
    'border-gray-300 hover:border-gray-400': !isDragActive.value && !props.disabled,
    'border-gray-200 cursor-not-allowed opacity-50': props.disabled
  }
])

const dropzoneTitle = computed(() => {
  switch (props.mediaType) {
    case 'images':
      return 'Drop images here'
    case 'videos':
      return 'Drop videos here'
    case 'documents':
      return 'Drop documents here'
    default:
      return 'Drop files here'
  }
})

const dropzoneSubtitle = computed(() => {
  const action = props.multiple ? 'select files' : 'select a file'
  return `or click to ${action}`
})

// Methods
const getAcceptedFormats = () => {
  switch (props.mediaType) {
    case 'images':
      return 'JPG, PNG, GIF, WebP'
    case 'videos':
      return 'MP4, WebM, AVI, MOV'
    case 'documents':
      return 'PDF, DOC, DOCX, TXT'
    default:
      return 'Images, Videos, Documents'
  }
}

const formatFileSize = (bytes: number) => {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

const validateFile = (file: File): string | null => {
  // Size check
  if (file.size > props.maxFileSize) {
    return `${file.name}: File size exceeds ${formatFileSize(props.maxFileSize)}`
  }

  // Type check
  const validation = mediaService.validateFile(file)
  if (!validation.valid) {
    return `${file.name}: ${validation.error}`
  }

  return null
}

const createPreview = async (file: File): Promise<string | undefined> => {
  if (file.type.startsWith('image/')) {
    return new Promise((resolve) => {
      const reader = new FileReader()
      reader.onload = (e) => resolve(e.target?.result as string)
      reader.readAsDataURL(file)
    })
  }
  
  if (file.type.startsWith('video/')) {
    return new Promise((resolve) => {
      const video = document.createElement('video')
      video.preload = 'metadata'
      video.onloadedmetadata = () => {
        video.currentTime = 1
      }
      video.onseeked = () => {
        const canvas = document.createElement('canvas')
        canvas.width = video.videoWidth
        canvas.height = video.videoHeight
        const ctx = canvas.getContext('2d')
        ctx?.drawImage(video, 0, 0, canvas.width, canvas.height)
        resolve(canvas.toDataURL())
      }
      video.src = URL.createObjectURL(file)
    })
  }
  
  return undefined
}

const processFiles = async (fileList: FileList) => {
  const newFiles: FilePreview[] = []
  const newErrors: string[] = []

  for (let i = 0; i < fileList.length; i++) {
    const file = fileList[i]
    
    // Check file limit
    if (files.value.length + newFiles.length >= props.maxFiles) {
      newErrors.push(`Maximum ${props.maxFiles} files allowed`)
      break
    }
    
    // Validate file
    const error = validateFile(file)
    if (error) {
      newErrors.push(error)
      continue
    }
    
    // Create preview
    const preview = await createPreview(file)
    
    newFiles.push({
      file,
      preview,
      type: file.type,
      uploading: false,
      uploaded: false
    })
  }

  files.value.push(...newFiles)
  errors.value.push(...newErrors)
  
  if (newFiles.length > 0) {
    emit('files-selected', newFiles.map(f => f.file))
    
    if (props.autoUpload) {
      uploadFiles()
    }
  }
}

const uploadFiles = async () => {
  const results: any[] = []
  
  for (const filePreview of files.value) {
    if (filePreview.uploaded || filePreview.error) continue
    
    filePreview.uploading = true
    filePreview.progress = 0
    
    try {
      const result = await mediaService.uploadFile(
        filePreview.file,
        'posts',
        {
          onProgress: (progress) => {
            filePreview.progress = progress.percentage
            emit('upload-progress', progress.percentage)
          }
        }
      )
      
      filePreview.uploaded = true
      filePreview.uploading = false
      filePreview.result = result
      results.push(result)
    } catch (error) {
      filePreview.uploading = false
      filePreview.error = error instanceof Error ? error.message : 'Upload failed'
      errors.value.push(`${filePreview.file.name}: Upload failed`)
    }
  }
  
  if (results.length > 0) {
    emit('upload-complete', results)
  }
  
  if (errors.value.length > 0) {
    emit('upload-error', errors.value)
  }
}

const removeFile = (index: number) => {
  files.value.splice(index, 1)
}

const clearFiles = () => {
  files.value = []
}

const clearErrors = () => {
  errors.value = []
}

const openFileDialog = () => {
  if (props.disabled) return
  fileInput.value?.click()
}

// Event handlers
const handleDragOver = () => {
  if (props.disabled) return
  isDragActive.value = true
}

const handleDragLeave = () => {
  isDragActive.value = false
}

const handleDrop = (event: DragEvent) => {
  if (props.disabled) return
  
  isDragActive.value = false
  
  const files = event.dataTransfer?.files
  if (files) {
    processFiles(files)
  }
}

const handleFileSelect = (event: Event) => {
  const input = event.target as HTMLInputElement
  const files = input.files
  
  if (files) {
    processFiles(files)
  }
  
  // Reset input
  input.value = ''
}
</script> 