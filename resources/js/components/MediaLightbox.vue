<template>
  <div class="fixed inset-0 bg-black bg-opacity-90 flex items-center justify-center z-50">
    <!-- Close button -->
    <button
      @click="$emit('close')"
      class="absolute top-4 right-4 text-white hover:text-gray-300 z-10"
    >
      <XMarkIcon class="w-8 h-8" />
    </button>

    <!-- Navigation buttons -->
    <button
      v-if="canNavigate && hasPrevious"
      @click="$emit('previous')"
      class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 z-10"
    >
      <ChevronLeftIcon class="w-8 h-8" />
    </button>

    <button
      v-if="canNavigate && hasNext"
      @click="$emit('next')"
      class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white hover:text-gray-300 z-10"
    >
      <ChevronRightIcon class="w-8 h-8" />
    </button>

    <!-- Media content -->
    <div class="relative max-w-full max-h-full p-4 flex items-center justify-center">
      <!-- Image -->
      <img
        v-if="media.type === 'image'"
        :src="media.url"
        :alt="media.alt_text || 'Image'"
        class="max-w-full max-h-full object-contain"
        @load="onMediaLoad"
        @error="onMediaError"
      />

      <!-- Video -->
      <video
        v-else-if="media.type === 'video'"
        :src="media.url"
        :poster="media.thumbnail_url"
        class="max-w-full max-h-full object-contain"
        controls
        autoplay
        @load="onMediaLoad"
        @error="onMediaError"
      >
        Your browser does not support the video tag.
      </video>

      <!-- Document (PDF, etc.) -->
      <div
        v-else-if="media.type === 'document'"
        class="bg-white rounded-lg p-8 max-w-sm text-center"
      >
        <DocumentIcon class="w-16 h-16 text-gray-400 mx-auto mb-4" />
        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ media.original_filename }}</h3>
        <p class="text-gray-600 mb-4">{{ formatFileSize(media.size) }}</p>
        <a
          :href="media.url"
          download
          class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
          Download
        </a>
      </div>

      <!-- Loading state -->
      <div v-if="loading" class="text-white text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white mx-auto mb-4"></div>
        <p>Loading...</p>
      </div>

      <!-- Error state -->
      <div v-if="error" class="text-white text-center">
        <ExclamationTriangleIcon class="w-16 h-16 mx-auto mb-4 text-red-400" />
        <p class="text-lg font-semibold mb-2">Failed to load media</p>
        <p class="text-gray-300">{{ error }}</p>
      </div>
    </div>

    <!-- Media info overlay -->
    <div 
      v-if="showInfo && !loading && !error"
      class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-6"
    >
      <div class="text-white">
        <div class="flex items-center justify-between mb-2">
          <div>
            <h3 class="font-semibold">{{ media.original_filename }}</h3>
            <p class="text-sm text-gray-300">{{ formatFileSize(media.size) }} • {{ media.mime_type }}</p>
          </div>
          <button
            @click="toggleInfo"
            class="text-gray-300 hover:text-white"
          >
            <InformationCircleIcon class="w-6 h-6" />
          </button>
        </div>
        
        <div v-if="media.alt_text" class="text-sm text-gray-300 mb-2">
          {{ media.alt_text }}
        </div>

        <div v-if="post" class="text-sm text-gray-300">
          Posted by {{ post.user?.name }} • {{ formatDate(post.created_at) }}
        </div>
      </div>
    </div>

    <!-- Info toggle button (when info is hidden) -->
    <button
      v-if="!showInfo && !loading && !error"
      @click="toggleInfo"
      class="absolute bottom-4 left-4 text-white hover:text-gray-300"
    >
      <InformationCircleIcon class="w-6 h-6" />
    </button>

    <!-- Keyboard shortcuts hint -->
    <div class="absolute top-4 left-4 text-white text-sm opacity-60">
      <div v-if="canNavigate">← → Navigate</div>
      <div>ESC Close</div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  XMarkIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  DocumentIcon,
  ArrowDownTrayIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon
} from '@heroicons/vue/24/outline'
import type { MediaAttachment, Post } from '@/types/posts'

interface Props {
  media: MediaAttachment
  post?: Post | null
}

interface Emits {
  (e: 'close'): void
  (e: 'previous'): void
  (e: 'next'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const loading = ref(true)
const error = ref<string | null>(null)
const showInfo = ref(false)

const canNavigate = computed(() => {
  return props.post?.media_attachments && props.post.media_attachments.length > 1
})

const hasPrevious = computed(() => {
  if (!canNavigate.value) return false
  const currentIndex = props.post!.media_attachments!.findIndex(m => m.id === props.media.id)
  return currentIndex > 0
})

const hasNext = computed(() => {
  if (!canNavigate.value) return false
  const currentIndex = props.post!.media_attachments!.findIndex(m => m.id === props.media.id)
  return currentIndex < props.post!.media_attachments!.length - 1
})

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

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const onMediaLoad = () => {
  loading.value = false
  error.value = null
}

const onMediaError = () => {
  loading.value = false
  error.value = 'Failed to load media file'
}

const toggleInfo = () => {
  showInfo.value = !showInfo.value
}

const handleKeydown = (event: KeyboardEvent) => {
  switch (event.key) {
    case 'Escape':
      emit('close')
      break
    case 'ArrowLeft':
      if (hasPrevious.value) {
        emit('previous')
      }
      break
    case 'ArrowRight':
      if (hasNext.value) {
        emit('next')
      }
      break
    case 'i':
    case 'I':
      toggleInfo()
      break
  }
}

const handleClickOutside = (event: MouseEvent) => {
  // Close lightbox when clicking on the background (not on the media)
  if (event.target === event.currentTarget) {
    emit('close')
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.body.style.overflow = 'hidden' // Prevent scrolling
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.body.style.overflow = '' // Restore scrolling
})
</script> 