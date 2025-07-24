<template>
  <div class="video-preview bg-black rounded-lg overflow-hidden relative">
    <!-- Video element -->
    <video
      ref="videoElement"
      :src="video.url"
      :poster="video.thumbnail_url"
      class="w-full h-full object-contain"
      :muted="muted"
      :loop="loop"
      :autoplay="autoplay"
      @loadedmetadata="onLoadedMetadata"
      @loadstart="onLoadStart"
      @canplay="onCanPlay"
      @play="onPlay"
      @pause="onPause"
      @ended="onEnded"
      @timeupdate="onTimeUpdate"
      @volumechange="onVolumeChange"
      @error="onError"
      @click="togglePlayPause"
    >
      Your browser does not support the video tag.
    </video>

    <!-- Loading overlay -->
    <div 
      v-if="loading"
      class="absolute inset-0 flex items-center justify-center bg-black bg-opacity-50"
    >
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-white"></div>
    </div>

    <!-- Error overlay -->
    <div 
      v-if="error"
      class="absolute inset-0 flex items-center justify-center bg-black"
    >
      <div class="text-center text-white">
        <ExclamationTriangleIcon class="w-16 h-16 mx-auto mb-4 opacity-50" />
        <p class="text-lg font-medium mb-2">Failed to load video</p>
        <p class="text-gray-300">{{ error }}</p>
      </div>
    </div>

    <!-- Play/Pause overlay -->
    <div 
      v-if="!loading && !error && showPlayButton"
      class="absolute inset-0 flex items-center justify-center pointer-events-none"
    >
      <div 
        class="w-20 h-20 bg-black bg-opacity-60 rounded-full flex items-center justify-center transition-opacity duration-200"
        :class="{ 'opacity-0': isPlaying && !showControls }"
      >
        <PlayIcon v-if="!isPlaying" class="w-10 h-10 text-white ml-1" />
        <PauseIcon v-else class="w-10 h-10 text-white" />
      </div>
    </div>

    <!-- Custom controls -->
    <div 
      v-if="!loading && !error && showCustomControls"
      class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent transition-opacity duration-300"
      :class="{ 'opacity-0': !showControls && isPlaying }"
      @mouseenter="showControls = true"
    >
      <!-- Progress bar -->
      <div class="px-4 pb-2">
        <div 
          class="relative h-2 bg-white bg-opacity-30 rounded-full cursor-pointer"
          @click="seek"
          ref="progressBar"
        >
          <!-- Buffered progress -->
          <div 
            class="absolute top-0 left-0 h-full bg-white bg-opacity-50 rounded-full"
            :style="{ width: bufferedPercentage + '%' }"
          ></div>
          
          <!-- Played progress -->
          <div 
            class="absolute top-0 left-0 h-full bg-blue-500 rounded-full"
            :style="{ width: progressPercentage + '%' }"
          ></div>
          
          <!-- Progress thumb -->
          <div 
            class="absolute top-1/2 transform -translate-y-1/2 w-4 h-4 bg-blue-500 rounded-full shadow-lg opacity-0 hover:opacity-100 transition-opacity"
            :style="{ left: progressPercentage + '%', marginLeft: '-8px' }"
          ></div>
        </div>
      </div>

      <!-- Control buttons -->
      <div class="flex items-center justify-between px-4 pb-4">
        <div class="flex items-center space-x-3">
          <!-- Play/Pause -->
          <button
            @click="togglePlayPause"
            class="p-2 text-white hover:text-blue-400 transition-colors"
          >
            <PlayIcon v-if="!isPlaying" class="w-6 h-6" />
            <PauseIcon v-else class="w-6 h-6" />
          </button>

          <!-- Previous frame -->
          <button
            @click="previousFrame"
            class="p-2 text-white hover:text-blue-400 transition-colors"
            title="Previous Frame"
          >
            <BackwardIcon class="w-5 h-5" />
          </button>

          <!-- Next frame -->
          <button
            @click="nextFrame"
            class="p-2 text-white hover:text-blue-400 transition-colors"
            title="Next Frame"
          >
            <ForwardIcon class="w-5 h-5" />
          </button>

          <!-- Volume controls -->
          <div class="flex items-center space-x-2">
            <button
              @click="toggleMute"
              class="p-2 text-white hover:text-blue-400 transition-colors"
            >
              <SpeakerWaveIcon v-if="!muted && volume > 0" class="w-5 h-5" />
              <SpeakerXMarkIcon v-else class="w-5 h-5" />
            </button>
            
            <div 
              class="w-20 h-1 bg-white bg-opacity-30 rounded-full cursor-pointer"
              @click="setVolume"
              ref="volumeBar"
            >
              <div 
                class="h-full bg-white rounded-full"
                :style="{ width: (muted ? 0 : volume * 100) + '%' }"
              ></div>
            </div>
          </div>

          <!-- Time display -->
          <div class="text-white text-sm font-mono">
            {{ formatTime(currentTime) }} / {{ formatTime(duration) }}
          </div>
        </div>

        <div class="flex items-center space-x-3">
          <!-- Playback speed -->
          <select
            v-model="playbackRate"
            @change="setPlaybackRate"
            class="bg-transparent text-white text-sm border border-white border-opacity-30 rounded px-2 py-1 focus:outline-none focus:border-blue-400"
          >
            <option value="0.5" class="text-black">0.5x</option>
            <option value="0.75" class="text-black">0.75x</option>
            <option value="1" class="text-black">1x</option>
            <option value="1.25" class="text-black">1.25x</option>
            <option value="1.5" class="text-black">1.5x</option>
            <option value="2" class="text-black">2x</option>
          </select>

          <!-- Picture-in-Picture -->
          <button
            v-if="supportsPiP"
            @click="togglePictureInPicture"
            class="p-2 text-white hover:text-blue-400 transition-colors"
            title="Picture in Picture"
          >
            <RectangleStackIcon class="w-5 h-5" />
          </button>

          <!-- Fullscreen -->
          <button
            @click="toggleFullscreen"
            class="p-2 text-white hover:text-blue-400 transition-colors"
            title="Fullscreen"
          >
            <ArrowsPointingOutIcon v-if="!isFullscreen" class="w-5 h-5" />
            <ArrowsPointingInIcon v-else class="w-5 h-5" />
          </button>
        </div>
      </div>
    </div>

    <!-- Video info overlay -->
    <div 
      v-if="showInfo"
      class="absolute top-4 left-4 bg-black bg-opacity-60 text-white p-3 rounded-lg"
    >
      <div class="text-sm space-y-1">
        <div class="font-medium">{{ video.original_filename }}</div>
        <div class="text-gray-300">{{ formatFileSize(video.size) }}</div>
        <div class="text-gray-300">{{ videoWidth }}×{{ videoHeight }}</div>
        <div class="text-gray-300">{{ formatDuration(duration) }}</div>
      </div>
    </div>

    <!-- Thumbnail generator -->
    <div v-if="showThumbnailGenerator" class="absolute top-4 right-4">
      <button
        @click="generateThumbnail"
        :disabled="!canGenerateThumbnail"
        class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed text-sm"
      >
        Generate Thumbnail
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import {
  PlayIcon,
  PauseIcon,
  BackwardIcon,
  ForwardIcon,
  SpeakerWaveIcon,
  SpeakerXMarkIcon,
  ArrowsPointingOutIcon,
  ArrowsPointingInIcon,
  RectangleStackIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import type { MediaAttachment } from '@/types/media'

interface Props {
  video: MediaAttachment
  autoplay?: boolean
  loop?: boolean
  muted?: boolean
  showCustomControls?: boolean
  showInfo?: boolean
  showThumbnailGenerator?: boolean
}

interface Emits {
  (e: 'play'): void
  (e: 'pause'): void
  (e: 'ended'): void
  (e: 'timeupdate', currentTime: number): void
  (e: 'thumbnail-generated', thumbnail: string): void
}

const props = withDefaults(defineProps<Props>(), {
  autoplay: false,
  loop: false,
  muted: false,
  showCustomControls: true,
  showInfo: false,
  showThumbnailGenerator: false
})

const emit = defineEmits<Emits>()

// Refs
const videoElement = ref<HTMLVideoElement>()
const progressBar = ref<HTMLElement>()
const volumeBar = ref<HTMLElement>()

// State
const loading = ref(true)
const error = ref<string | null>(null)
const isPlaying = ref(false)
const showControls = ref(true)
const showPlayButton = ref(true)
const currentTime = ref(0)
const duration = ref(0)
const bufferedTime = ref(0)
const volume = ref(1)
const muted = ref(props.muted)
const playbackRate = ref(1)
const isFullscreen = ref(false)
const videoWidth = ref(0)
const videoHeight = ref(0)

// Computed
const progressPercentage = computed(() => {
  return duration.value > 0 ? (currentTime.value / duration.value) * 100 : 0
})

const bufferedPercentage = computed(() => {
  return duration.value > 0 ? (bufferedTime.value / duration.value) * 100 : 0
})

const supportsPiP = computed(() => {
  return 'pictureInPictureEnabled' in document
})

const canGenerateThumbnail = computed(() => {
  return !loading.value && !error.value && videoElement.value
})

// Control visibility timer
let controlsTimer: number | null = null

const hideControlsAfterDelay = () => {
  if (controlsTimer) clearTimeout(controlsTimer)
  controlsTimer = setTimeout(() => {
    if (isPlaying.value) {
      showControls.value = false
    }
  }, 3000)
}

const onLoadStart = () => {
  loading.value = true
  error.value = null
}

const onLoadedMetadata = () => {
  if (videoElement.value) {
    duration.value = videoElement.value.duration
    videoWidth.value = videoElement.value.videoWidth
    videoHeight.value = videoElement.value.videoHeight
  }
}

const onCanPlay = () => {
  loading.value = false
  error.value = null
}

const onPlay = () => {
  isPlaying.value = true
  showPlayButton.value = false
  hideControlsAfterDelay()
  emit('play')
}

const onPause = () => {
  isPlaying.value = false
  showPlayButton.value = true
  showControls.value = true
  if (controlsTimer) clearTimeout(controlsTimer)
  emit('pause')
}

const onEnded = () => {
  isPlaying.value = false
  showPlayButton.value = true
  showControls.value = true
  if (controlsTimer) clearTimeout(controlsTimer)
  emit('ended')
}

const onTimeUpdate = () => {
  if (videoElement.value) {
    currentTime.value = videoElement.value.currentTime
    
    // Update buffered time
    const buffered = videoElement.value.buffered
    if (buffered.length > 0) {
      bufferedTime.value = buffered.end(buffered.length - 1)
    }
    
    emit('timeupdate', currentTime.value)
  }
}

const onVolumeChange = () => {
  if (videoElement.value) {
    volume.value = videoElement.value.volume
    muted.value = videoElement.value.muted
  }
}

const onError = () => {
  loading.value = false
  error.value = 'Failed to load video'
}

const togglePlayPause = () => {
  if (!videoElement.value) return
  
  if (isPlaying.value) {
    videoElement.value.pause()
  } else {
    videoElement.value.play()
  }
}

const previousFrame = () => {
  if (!videoElement.value) return
  videoElement.value.currentTime = Math.max(0, currentTime.value - 1/30)
}

const nextFrame = () => {
  if (!videoElement.value) return
  videoElement.value.currentTime = Math.min(duration.value, currentTime.value + 1/30)
}

const seek = (event: MouseEvent) => {
  if (!progressBar.value || !videoElement.value) return
  
  const rect = progressBar.value.getBoundingClientRect()
  const percentage = (event.clientX - rect.left) / rect.width
  const newTime = percentage * duration.value
  
  videoElement.value.currentTime = Math.max(0, Math.min(duration.value, newTime))
}

const toggleMute = () => {
  if (videoElement.value) {
    videoElement.value.muted = !videoElement.value.muted
  }
}

const setVolume = (event: MouseEvent) => {
  if (!volumeBar.value || !videoElement.value) return
  
  const rect = volumeBar.value.getBoundingClientRect()
  const percentage = (event.clientX - rect.left) / rect.width
  const newVolume = Math.max(0, Math.min(1, percentage))
  
  videoElement.value.volume = newVolume
  videoElement.value.muted = newVolume === 0
}

const setPlaybackRate = () => {
  if (videoElement.value) {
    videoElement.value.playbackRate = playbackRate.value
  }
}

const togglePictureInPicture = async () => {
  if (!videoElement.value || !supportsPiP.value) return
  
  try {
    if (document.pictureInPictureElement) {
      await document.exitPictureInPicture()
    } else {
      await videoElement.value.requestPictureInPicture()
    }
  } catch (err) {
    console.error('Picture-in-Picture error:', err)
  }
}

const toggleFullscreen = async () => {
  try {
    if (document.fullscreenElement) {
      await document.exitFullscreen()
      isFullscreen.value = false
    } else if (videoElement.value?.parentElement) {
      await videoElement.value.parentElement.requestFullscreen()
      isFullscreen.value = true
    }
  } catch (err) {
    console.error('Fullscreen error:', err)
  }
}

const generateThumbnail = () => {
  if (!videoElement.value) return
  
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  
  if (!ctx) return
  
  canvas.width = videoElement.value.videoWidth
  canvas.height = videoElement.value.videoHeight
  
  ctx.drawImage(videoElement.value, 0, 0, canvas.width, canvas.height)
  
  const thumbnail = canvas.toDataURL('image/jpeg', 0.8)
  emit('thumbnail-generated', thumbnail)
}

const formatTime = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  const secs = Math.floor(seconds % 60)
  
  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`
  }
  
  return `${minutes}:${secs.toString().padStart(2, '0')}`
}

const formatDuration = (seconds: number): string => {
  return formatTime(seconds)
}

const formatFileSize = (bytes: number): string => {
  const units = ['B', 'KB', 'MB', 'GB']
  let size = bytes
  let unitIndex = 0
  
  while (size >= 1024 && unitIndex < units.length - 1) {
    size /= 1024
    unitIndex++
  }
  
  return `${size.toFixed(1)} ${units[unitIndex]}`
}

const handleKeydown = (event: KeyboardEvent) => {
  if (!videoElement.value) return
  
  switch (event.key) {
    case ' ':
      event.preventDefault()
      togglePlayPause()
      break
    case 'ArrowLeft':
      event.preventDefault()
      videoElement.value.currentTime = Math.max(0, currentTime.value - 10)
      break
    case 'ArrowRight':
      event.preventDefault()
      videoElement.value.currentTime = Math.min(duration.value, currentTime.value + 10)
      break
    case 'ArrowUp':
      event.preventDefault()
      videoElement.value.volume = Math.min(1, volume.value + 0.1)
      break
    case 'ArrowDown':
      event.preventDefault()
      videoElement.value.volume = Math.max(0, volume.value - 0.1)
      break
    case 'm':
    case 'M':
      toggleMute()
      break
    case 'f':
    case 'F':
      toggleFullscreen()
      break
  }
}

const handleMouseMove = () => {
  showControls.value = true
  hideControlsAfterDelay()
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
  document.addEventListener('mousemove', handleMouseMove)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  document.removeEventListener('mousemove', handleMouseMove)
  if (controlsTimer) clearTimeout(controlsTimer)
})
</script> 