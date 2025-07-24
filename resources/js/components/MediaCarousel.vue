<template>
  <div class="media-carousel relative bg-black rounded-lg overflow-hidden">
    <!-- Main display area -->
    <div class="relative" :style="{ height: height + 'px' }">
      <!-- Media items -->
      <div 
        class="flex transition-transform duration-300 ease-in-out h-full"
        :style="{ transform: `translateX(-${currentIndex * 100}%)` }"
      >
        <div
          v-for="(media, index) in mediaItems"
          :key="media.id"
          class="flex-shrink-0 w-full h-full flex items-center justify-center"
        >
          <!-- Image -->
          <img
            v-if="media.type === 'image'"
            :src="media.url"
            :alt="media.alt_text"
            class="max-w-full max-h-full object-contain"
            @load="onMediaLoad(index)"
            @error="onMediaError(index)"
          />
          
          <!-- Video -->
          <VideoPreview
            v-else-if="media.type === 'video'"
            :video="media"
            :show-custom-controls="true"
            :show-info="false"
            class="w-full h-full"
          />
          
          <!-- Document -->
          <div
            v-else-if="media.type === 'document'"
            class="bg-gray-800 rounded-lg p-8 text-center max-w-sm"
          >
            <DocumentIcon class="w-24 h-24 text-gray-400 mx-auto mb-4" />
            <h3 class="text-white text-lg font-semibold mb-2">{{ media.original_filename }}</h3>
            <p class="text-gray-400 mb-4">{{ formatFileSize(media.size) }}</p>
            <a
              :href="media.url"
              download
              class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
              Download
            </a>
          </div>
        </div>
      </div>

      <!-- Loading overlay -->
      <div 
        v-if="loading[currentIndex]"
        class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50"
      >
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
      </div>

      <!-- Error overlay -->
      <div 
        v-if="errors[currentIndex]"
        class="absolute inset-0 flex items-center justify-center bg-black"
      >
        <div class="text-center text-white">
          <ExclamationTriangleIcon class="w-16 h-16 mx-auto mb-4 opacity-50" />
          <p class="text-lg font-medium">Failed to load media</p>
        </div>
      </div>

      <!-- Navigation arrows -->
      <button
        v-if="showNavigation && canGoPrevious"
        @click="previous"
        class="absolute left-4 top-1/2 transform -translate-y-1/2 w-12 h-12 bg-black bg-opacity-60 rounded-full flex items-center justify-center text-white hover:bg-opacity-80 transition-all z-10"
      >
        <ChevronLeftIcon class="w-8 h-8" />
      </button>

      <button
        v-if="showNavigation && canGoNext"
        @click="next"
        class="absolute right-4 top-1/2 transform -translate-y-1/2 w-12 h-12 bg-black bg-opacity-60 rounded-full flex items-center justify-center text-white hover:bg-opacity-80 transition-all z-10"
      >
        <ChevronRightIcon class="w-8 h-8" />
      </button>

      <!-- Close button -->
      <button
        v-if="showCloseButton"
        @click="$emit('close')"
        class="absolute top-4 right-4 w-10 h-10 bg-black bg-opacity-60 rounded-full flex items-center justify-center text-white hover:bg-opacity-80 transition-all z-10"
      >
        <XMarkIcon class="w-6 h-6" />
      </button>

      <!-- Media counter -->
      <div 
        v-if="showCounter"
        class="absolute top-4 left-4 bg-black bg-opacity-60 text-white px-3 py-2 rounded-lg text-sm z-10"
      >
        {{ currentIndex + 1 }} / {{ mediaItems.length }}
      </div>

      <!-- Media info -->
      <div 
        v-if="showInfo && currentMedia"
        class="absolute bottom-4 left-4 right-4 bg-black bg-opacity-60 text-white p-4 rounded-lg z-10"
      >
        <h3 class="font-semibold mb-2 truncate">{{ currentMedia.original_filename }}</h3>
        <div class="text-sm text-gray-300 space-y-1">
          <div>{{ getMediaTypeLabel(currentMedia.type) }} • {{ formatFileSize(currentMedia.size) }}</div>
          <div v-if="currentMedia.alt_text">{{ currentMedia.alt_text }}</div>
          <div>{{ formatDate(currentMedia.created_at) }}</div>
        </div>
      </div>
    </div>

    <!-- Thumbnails strip -->
    <div 
      v-if="showThumbnails && mediaItems.length > 1"
      class="bg-gray-900 p-4"
    >
      <div class="flex space-x-2 overflow-x-auto scrollbar-hide">
        <div
          v-for="(media, index) in mediaItems"
          :key="`thumb-${media.id}`"
          class="flex-shrink-0 cursor-pointer transition-all duration-200"
          @click="goToIndex(index)"
        >
          <MediaThumbnail
            :media="media"
            size="sm"
            :class="[
              'border-2 transition-all duration-200',
              index === currentIndex 
                ? 'border-blue-500 opacity-100' 
                : 'border-transparent opacity-60 hover:opacity-80'
            ]"
          />
        </div>
      </div>
    </div>

    <!-- Indicators -->
    <div 
      v-if="showIndicators && mediaItems.length > 1 && !showThumbnails"
      class="absolute bottom-4 left-1/2 transform -translate-x-1/2 flex space-x-2 z-10"
    >
      <button
        v-for="(_, index) in mediaItems"
        :key="`indicator-${index}`"
        @click="goToIndex(index)"
        class="w-3 h-3 rounded-full transition-all duration-200"
        :class="index === currentIndex 
          ? 'bg-white' 
          : 'bg-white bg-opacity-50 hover:bg-opacity-75'"
      />
    </div>

    <!-- Slideshow controls -->
    <div 
      v-if="allowSlideshow"
      class="absolute top-4 right-20 flex items-center space-x-2 z-10"
    >
      <button
        @click="toggleSlideshow"
        class="w-10 h-10 bg-black bg-opacity-60 rounded-full flex items-center justify-center text-white hover:bg-opacity-80 transition-all"
        :title="isPlaying ? 'Pause Slideshow' : 'Start Slideshow'"
      >
        <PlayIcon v-if="!isPlaying" class="w-5 h-5 ml-0.5" />
        <PauseIcon v-else class="w-5 h-5" />
      </button>
      
      <select
        v-model="slideshowInterval"
        @change="resetSlideshowTimer"
        class="bg-black bg-opacity-60 text-white text-sm rounded px-2 py-1 focus:outline-none"
      >
        <option value="2000">2s</option>
        <option value="3000">3s</option>
        <option value="5000">5s</option>
        <option value="10000">10s</option>
      </select>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import {
  ChevronLeftIcon,
  ChevronRightIcon,
  XMarkIcon,
  DocumentIcon,
  ArrowDownTrayIcon,
  ExclamationTriangleIcon,
  PlayIcon,
  PauseIcon
} from '@heroicons/vue/24/outline'
import type { MediaAttachment } from '@/types/media'
import MediaThumbnail from './MediaThumbnail.vue'
import VideoPreview from './VideoPreview.vue'

interface Props {
  mediaItems: MediaAttachment[]
  initialIndex?: number
  height?: number
  showNavigation?: boolean
  showThumbnails?: boolean
  showIndicators?: boolean
  showCounter?: boolean
  showInfo?: boolean
  showCloseButton?: boolean
  allowSlideshow?: boolean
  autoPlay?: boolean
  loop?: boolean
  slideshowSpeed?: number
}

interface Emits {
  (e: 'close'): void
  (e: 'change', index: number, media: MediaAttachment): void
  (e: 'slideshow-start'): void
  (e: 'slideshow-pause'): void
}

const props = withDefaults(defineProps<Props>(), {
  initialIndex: 0,
  height: 600,
  showNavigation: true,
  showThumbnails: false,
  showIndicators: true,
  showCounter: true,
  showInfo: true,
  showCloseButton: false,
  allowSlideshow: false,
  autoPlay: false,
  loop: true,
  slideshowSpeed: 3000
})

const emit = defineEmits<Emits>()

// State
const currentIndex = ref(props.initialIndex)
const loading = ref<boolean[]>(new Array(props.mediaItems.length).fill(true))
const errors = ref<boolean[]>(new Array(props.mediaItems.length).fill(false))
const isPlaying = ref(props.autoPlay)
const slideshowInterval = ref(props.slideshowSpeed)

// Slideshow timer
let slideshowTimer: number | null = null

// Computed
const currentMedia = computed(() => {
  return props.mediaItems[currentIndex.value]
})

const canGoPrevious = computed(() => {
  return currentIndex.value > 0
})

const canGoNext = computed(() => {
  return currentIndex.value < props.mediaItems.length - 1
})

// Methods
const goToIndex = (index: number) => {
  if (index >= 0 && index < props.mediaItems.length) {
    currentIndex.value = index
    emit('change', index, props.mediaItems[index])
  }
}

const previous = () => {
  if (canGoPrevious.value) {
    goToIndex(currentIndex.value - 1)
  } else if (props.loop) {
    goToIndex(props.mediaItems.length - 1)
  }
}

const next = () => {
  if (canGoNext.value) {
    goToIndex(currentIndex.value + 1)
  } else if (props.loop) {
    goToIndex(0)
  }
}

const toggleSlideshow = () => {
  isPlaying.value = !isPlaying.value
  
  if (isPlaying.value) {
    startSlideshow()
    emit('slideshow-start')
  } else {
    stopSlideshow()
    emit('slideshow-pause')
  }
}

const startSlideshow = () => {
  if (slideshowTimer) clearInterval(slideshowTimer)
  
  slideshowTimer = setInterval(() => {
    if (canGoNext.value) {
      next()
    } else if (props.loop) {
      goToIndex(0)
    } else {
      stopSlideshow()
    }
  }, slideshowInterval.value)
}

const stopSlideshow = () => {
  if (slideshowTimer) {
    clearInterval(slideshowTimer)
    slideshowTimer = null
  }
  isPlaying.value = false
}

const resetSlideshowTimer = () => {
  if (isPlaying.value) {
    stopSlideshow()
    startSlideshow()
  }
}

const onMediaLoad = (index: number) => {
  loading.value[index] = false
  errors.value[index] = false
}

const onMediaError = (index: number) => {
  loading.value[index] = false
  errors.value[index] = true
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

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}

const getMediaTypeLabel = (type: string) => {
  const labels = {
    image: 'Image',
    video: 'Video',
    document: 'Document'
  }
  return labels[type as keyof typeof labels] || type
}

// Keyboard navigation
const handleKeydown = (event: KeyboardEvent) => {
  switch (event.key) {
    case 'ArrowLeft':
      event.preventDefault()
      previous()
      break
    case 'ArrowRight':
      event.preventDefault()
      next()
      break
    case 'Escape':
      emit('close')
      break
    case ' ':
      if (props.allowSlideshow) {
        event.preventDefault()
        toggleSlideshow()
      }
      break
    case 'Home':
      event.preventDefault()
      goToIndex(0)
      break
    case 'End':
      event.preventDefault()
      goToIndex(props.mediaItems.length - 1)
      break
  }
}

// Touch/swipe support
let touchStartX = 0
let touchEndX = 0

const handleTouchStart = (event: TouchEvent) => {
  touchStartX = event.changedTouches[0].screenX
}

const handleTouchEnd = (event: TouchEvent) => {
  touchEndX = event.changedTouches[0].screenX
  handleSwipe()
}

const handleSwipe = () => {
  const swipeThreshold = 50
  const diff = touchStartX - touchEndX
  
  if (Math.abs(diff) > swipeThreshold) {
    if (diff > 0) {
      // Swipe left - next
      next()
    } else {
      // Swipe right - previous
      previous()
    }
  }
}

// Watch for changes in media items
watch(() => props.mediaItems.length, (newLength) => {
  loading.value = new Array(newLength).fill(true)
  errors.value = new Array(newLength).fill(false)
  
  if (currentIndex.value >= newLength) {
    currentIndex.value = Math.max(0, newLength - 1)
  }
})

// Auto-start slideshow
watch(() => props.autoPlay, (autoPlay) => {
  if (autoPlay && props.allowSlideshow) {
    startSlideshow()
  }
}, { immediate: true })

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('touchstart', handleTouchStart)
  document.addEventListener('touchend', handleTouchEnd)
  
  // Preload first few images
  if (props.mediaItems.length > 0) {
    loading.value[0] = false // Current image loads immediately
  }
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('touchstart', handleTouchStart)
  document.removeEventListener('touchend', handleTouchEnd)
  stopSlideshow()
})
</script>

<style scoped>
.scrollbar-hide {
  -ms-overflow-style: none;
  scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
  display: none;
}
</style> 