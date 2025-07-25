<template>
  <div class="fixed inset-0 z-50 overflow-y-auto">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
      <!-- Background overlay -->
      <div 
        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
        @click="$emit('close')"
      ></div>

      <!-- Modal -->
      <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
        <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg leading-6 font-medium text-gray-900">
              Upload Cover Image
            </h3>
            <button
              @click="$emit('close')"
              class="text-gray-400 hover:text-gray-500"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div v-if="!selectedFile" class="mt-2">
            <!-- Upload Area -->
            <div
              @drop="handleDrop"
              @dragover.prevent
              @dragenter.prevent
              class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-gray-400 transition-colors cursor-pointer"
              :class="{ 'border-indigo-500 bg-indigo-50': isDragging }"
              @click="triggerFileInput"
            >
              <svg class="mx-auto h-16 w-16 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                <path d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L32 26.414M4 16v20a2 2 0 002 2h16m0-26l8 8m0 0v20a2 2 0 002 2H20m12-10l4-4m0 0l-4-4m4 4H20" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <div class="mt-4">
                <p class="text-lg text-gray-600">
                  <span class="font-medium text-indigo-600 hover:text-indigo-500">
                    Click to upload
                  </span>
                  or drag and drop
                </p>
                <p class="text-sm text-gray-500 mt-2">
                  PNG, JPG, GIF up to 10MB
                </p>
                <p class="text-xs text-gray-400 mt-1">
                  Recommended size: 1200x400 pixels
                </p>
              </div>
            </div>

            <input
              ref="fileInput"
              type="file"
              accept="image/*"
              @change="handleFileSelect"
              class="hidden"
            />
          </div>

          <!-- Preview -->
          <div v-else class="mt-2">
            <div class="text-center">
              <div class="relative inline-block">
                <img
                  :src="previewUrl"
                  alt="Cover image preview"
                  class="w-full max-w-lg h-40 object-cover rounded-lg border shadow-lg"
                />
                <div class="absolute inset-0 bg-black bg-opacity-25 rounded-lg"></div>
                <div class="absolute bottom-2 left-2 text-white text-sm">
                  <p class="font-medium">{{ selectedFile.name }}</p>
                  <p class="text-xs opacity-90">{{ formatFileSize(selectedFile.size) }}</p>
                </div>
              </div>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="errorMessage" class="mt-4 rounded-md bg-red-50 p-4">
            <div class="flex">
              <div class="flex-shrink-0">
                <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
              </div>
              <div class="ml-3">
                <h3 class="text-sm font-medium text-red-800">{{ errorMessage }}</h3>
              </div>
            </div>
          </div>
        </div>

        <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
          <button
            v-if="selectedFile"
            type="button"
            @click="handleUpload"
            :disabled="isUploading"
            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm disabled:opacity-50"
          >
            <svg 
              v-if="isUploading"
              class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" 
              fill="none" 
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ isUploading ? 'Uploading...' : 'Upload Cover Image' }}
          </button>
          
          <button
            v-if="selectedFile"
            type="button"
            @click="clearSelection"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Choose Different
          </button>
          
          <button
            type="button"
            @click="$emit('close')"
            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm"
          >
            Cancel
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import authService from '@/services/authService'

const emit = defineEmits<{
  close: []
  uploaded: [coverImageUrl: string]
}>()

const fileInput = ref<HTMLInputElement>()
const selectedFile = ref<File | null>(null)
const isDragging = ref(false)
const isUploading = ref(false)
const errorMessage = ref('')

const previewUrl = computed(() => {
  if (selectedFile.value) {
    return URL.createObjectURL(selectedFile.value)
  }
  return ''
})

const triggerFileInput = () => {
  fileInput.value?.click()
}

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    selectFile(file)
  }
}

const handleDrop = (event: DragEvent) => {
  event.preventDefault()
  isDragging.value = false
  
  const files = event.dataTransfer?.files
  if (files && files.length > 0) {
    selectFile(files[0])
  }
}

const selectFile = (file: File) => {
  errorMessage.value = ''
  
  // Validate file type
  if (!file.type.startsWith('image/')) {
    errorMessage.value = 'Please select an image file.'
    return
  }
  
  // Validate file size (10MB for cover images)
  if (file.size > 10 * 1024 * 1024) {
    errorMessage.value = 'File size must be less than 10MB.'
    return
  }
  
  selectedFile.value = file
}

const clearSelection = () => {
  selectedFile.value = null
  errorMessage.value = ''
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

const handleUpload = async () => {
  if (!selectedFile.value) return
  
  isUploading.value = true
  errorMessage.value = ''
  
  try {
    const response = await authService.updateCoverImage(selectedFile.value)
    
    if (response.success && response.data?.cover_image_url) {
      emit('uploaded', response.data.cover_image_url)
    } else {
      errorMessage.value = response.message || 'Failed to upload cover image. Please try again.'
    }
  } catch (error) {
    console.error('Cover image upload error:', error)
    errorMessage.value = 'An unexpected error occurred. Please try again.'
  } finally {
    isUploading.value = false
  }
}
</script> 