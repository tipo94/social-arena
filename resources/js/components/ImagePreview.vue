<template>
  <div class="image-preview bg-white rounded-lg shadow-lg overflow-hidden">
    <!-- Header -->
    <div class="flex items-center justify-between p-4 border-b border-gray-200">
      <div class="flex items-center space-x-3">
        <img
          :src="image.thumbnail_url || image.preview_url"
          :alt="image.alt_text"
          class="w-10 h-10 rounded object-cover"
        />
        <div>
          <h3 class="font-semibold text-gray-900 truncate max-w-xs">{{ image.original_filename }}</h3>
          <p class="text-sm text-gray-500">
            {{ formatFileSize(image.size) }} • {{ imageWidth }}x{{ imageHeight }}
          </p>
        </div>
      </div>
      
      <div class="flex items-center space-x-2">
        <button
          v-if="allowEdit"
          @click="toggleEditMode"
          class="p-2 text-gray-600 hover:text-blue-600 transition-colors"
          :class="{ 'text-blue-600 bg-blue-50': editMode }"
        >
          <PencilIcon class="w-4 h-4" />
        </button>
        
        <button
          @click="downloadImage"
          class="p-2 text-gray-600 hover:text-green-600 transition-colors"
        >
          <ArrowDownTrayIcon class="w-4 h-4" />
        </button>
        
        <button
          @click="$emit('close')"
          class="p-2 text-gray-600 hover:text-red-600 transition-colors"
        >
          <XMarkIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex items-center justify-between p-4 bg-gray-50 border-b border-gray-200">
      <!-- Zoom controls -->
      <div class="flex items-center space-x-2">
        <button
          @click="zoomOut"
          :disabled="zoomLevel <= minZoom"
          class="p-2 text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <MinusIcon class="w-4 h-4" />
        </button>
        
        <span class="text-sm text-gray-600 min-w-[4rem] text-center">
          {{ Math.round(zoomLevel * 100) }}%
        </span>
        
        <button
          @click="zoomIn"
          :disabled="zoomLevel >= maxZoom"
          class="p-2 text-gray-600 hover:text-gray-900 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <PlusIcon class="w-4 h-4" />
        </button>
        
        <button
          @click="resetZoom"
          class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded"
        >
          Fit
        </button>
      </div>

      <!-- View controls -->
      <div class="flex items-center space-x-2">
        <button
          @click="rotateLeft"
          class="p-2 text-gray-600 hover:text-gray-900"
          title="Rotate Left"
        >
          <ArrowUturnLeftIcon class="w-4 h-4" />
        </button>
        
        <button
          @click="rotateRight"
          class="p-2 text-gray-600 hover:text-gray-900"
          title="Rotate Right"
        >
          <ArrowUturnRightIcon class="w-4 h-4" />
        </button>
        
        <button
          @click="toggleFullscreen"
          class="p-2 text-gray-600 hover:text-gray-900"
          title="Fullscreen"
        >
          <ArrowsPointingOutIcon class="w-4 h-4" />
        </button>
      </div>

      <!-- Edit controls -->
      <div v-if="editMode" class="flex items-center space-x-2">
        <button
          @click="adjustBrightness(-10)"
          class="p-2 text-gray-600 hover:text-gray-900"
          title="Decrease Brightness"
        >
          <SunIcon class="w-4 h-4 opacity-60" />
        </button>
        
        <button
          @click="adjustBrightness(10)"
          class="p-2 text-gray-600 hover:text-gray-900"
          title="Increase Brightness"
        >
          <SunIcon class="w-4 h-4" />
        </button>
        
        <button
          @click="adjustContrast(-10)"
          class="p-2 text-gray-600 hover:text-gray-900"
          title="Decrease Contrast"
        >
          <AdjustmentsHorizontalIcon class="w-4 h-4 opacity-60" />
        </button>
        
        <button
          @click="adjustContrast(10)"
          class="p-2 text-gray-600 hover:text-gray-900"
          title="Increase Contrast"
        >
          <AdjustmentsHorizontalIcon class="w-4 h-4" />
        </button>
        
        <button
          @click="resetFilters"
          class="px-3 py-1 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 rounded"
        >
          Reset
        </button>
      </div>
    </div>

    <!-- Image container -->
    <div 
      ref="imageContainer"
      class="relative overflow-hidden bg-gray-100"
      :style="{ height: containerHeight + 'px' }"
      @mousedown="startPan"
      @mousemove="handlePan"
      @mouseup="endPan"
      @mouseleave="endPan"
      @wheel="handleWheel"
    >
      <img
        ref="imageElement"
        :src="image.url"
        :alt="image.alt_text"
        class="absolute transition-transform duration-200 cursor-grab select-none"
        :class="{ 'cursor-grabbing': isPanning }"
        :style="imageStyle"
        @load="onImageLoad"
        @error="onImageError"
        @dragstart.prevent
      />
      
      <!-- Loading overlay -->
      <div 
        v-if="loading"
        class="absolute inset-0 flex items-center justify-center bg-gray-200"
      >
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
      
      <!-- Error overlay -->
      <div 
        v-if="error"
        class="absolute inset-0 flex items-center justify-center bg-gray-100"
      >
        <div class="text-center">
          <ExclamationTriangleIcon class="w-16 h-16 text-gray-400 mx-auto mb-4" />
          <p class="text-gray-600">Failed to load image</p>
        </div>
      </div>
    </div>

    <!-- Edit panel -->
    <div v-if="editMode" class="p-4 bg-gray-50 border-t border-gray-200">
      <div class="grid grid-cols-2 gap-4">
        <!-- Brightness -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Brightness: {{ brightness }}%
          </label>
          <input
            v-model="brightness"
            type="range"
            min="0"
            max="200"
            class="w-full"
          />
        </div>
        
        <!-- Contrast -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Contrast: {{ contrast }}%
          </label>
          <input
            v-model="contrast"
            type="range"
            min="0"
            max="200"
            class="w-full"
          />
        </div>
        
        <!-- Saturation -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Saturation: {{ saturation }}%
          </label>
          <input
            v-model="saturation"
            type="range"
            min="0"
            max="200"
            class="w-full"
          />
        </div>
        
        <!-- Blur -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">
            Blur: {{ blur }}px
          </label>
          <input
            v-model="blur"
            type="range"
            min="0"
            max="10"
            step="0.1"
            class="w-full"
          />
        </div>
      </div>
      
      <div class="flex items-center justify-between mt-4">
        <button
          @click="resetFilters"
          class="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50"
        >
          Reset All
        </button>
        
        <div class="flex items-center space-x-2">
          <button
            @click="cancelEdit"
            class="px-4 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          
          <button
            @click="saveEdit"
            class="px-4 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700"
          >
            Save Changes
          </button>
        </div>
      </div>
    </div>

    <!-- Image info -->
    <div v-if="showInfo" class="p-4 border-t border-gray-200 bg-gray-50">
      <div class="grid grid-cols-2 gap-4 text-sm">
        <div>
          <span class="font-medium text-gray-700">Format:</span>
          <span class="text-gray-600 ml-2">{{ image.mime_type }}</span>
        </div>
        
        <div>
          <span class="font-medium text-gray-700">Dimensions:</span>
          <span class="text-gray-600 ml-2">{{ imageWidth }}×{{ imageHeight }}</span>
        </div>
        
        <div>
          <span class="font-medium text-gray-700">File Size:</span>
          <span class="text-gray-600 ml-2">{{ formatFileSize(image.size) }}</span>
        </div>
        
        <div>
          <span class="font-medium text-gray-700">Created:</span>
          <span class="text-gray-600 ml-2">{{ formatDate(image.created_at) }}</span>
        </div>
      </div>
      
      <div v-if="image.alt_text" class="mt-3">
        <span class="font-medium text-gray-700">Alt Text:</span>
        <p class="text-gray-600 mt-1">{{ image.alt_text }}</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue'
import {
  XMarkIcon,
  PencilIcon,
  ArrowDownTrayIcon,
  MinusIcon,
  PlusIcon,
  ArrowUturnLeftIcon,
  ArrowUturnRightIcon,
  ArrowsPointingOutIcon,
  SunIcon,
  AdjustmentsHorizontalIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import type { MediaAttachment } from '@/types/media'

interface Props {
  image: MediaAttachment
  allowEdit?: boolean
  showInfo?: boolean
  containerHeight?: number
}

interface Emits {
  (e: 'close'): void
  (e: 'save', editedImage: MediaAttachment): void
}

const props = withDefaults(defineProps<Props>(), {
  allowEdit: false,
  showInfo: true,
  containerHeight: 500
})

const emit = defineEmits<Emits>()

// Refs
const imageContainer = ref<HTMLElement>()
const imageElement = ref<HTMLImageElement>()

// State
const loading = ref(true)
const error = ref(false)
const editMode = ref(false)
const zoomLevel = ref(1)
const rotation = ref(0)
const panX = ref(0)
const panY = ref(0)
const isPanning = ref(false)
const lastPanPoint = ref({ x: 0, y: 0 })

// Image dimensions
const imageWidth = ref(0)
const imageHeight = ref(0)

// Edit controls
const brightness = ref(100)
const contrast = ref(100)
const saturation = ref(100)
const blur = ref(0)

// Original values for reset
const originalFilters = {
  brightness: 100,
  contrast: 100,
  saturation: 100,
  blur: 0
}

// Constants
const minZoom = 0.1
const maxZoom = 5
const zoomStep = 0.2

const imageStyle = computed(() => {
  const transform = [
    `translate(${panX.value}px, ${panY.value}px)`,
    `scale(${zoomLevel.value})`,
    `rotate(${rotation.value}deg)`
  ].join(' ')

  const filter = [
    `brightness(${brightness.value}%)`,
    `contrast(${contrast.value}%)`,
    `saturate(${saturation.value}%)`,
    `blur(${blur.value}px)`
  ].join(' ')

  return {
    transform,
    filter,
    transformOrigin: 'center center'
  }
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

const onImageLoad = () => {
  loading.value = false
  error.value = false
  
  if (imageElement.value) {
    imageWidth.value = imageElement.value.naturalWidth
    imageHeight.value = imageElement.value.naturalHeight
    centerImage()
  }
}

const onImageError = () => {
  loading.value = false
  error.value = true
}

const centerImage = () => {
  if (!imageContainer.value || !imageElement.value) return
  
  const containerRect = imageContainer.value.getBoundingClientRect()
  const imageRect = imageElement.value.getBoundingClientRect()
  
  panX.value = (containerRect.width - imageRect.width) / 2
  panY.value = (containerRect.height - imageRect.height) / 2
}

const zoomIn = () => {
  if (zoomLevel.value < maxZoom) {
    zoomLevel.value = Math.min(maxZoom, zoomLevel.value + zoomStep)
  }
}

const zoomOut = () => {
  if (zoomLevel.value > minZoom) {
    zoomLevel.value = Math.max(minZoom, zoomLevel.value - zoomStep)
  }
}

const resetZoom = () => {
  zoomLevel.value = 1
  panX.value = 0
  panY.value = 0
  nextTick(() => centerImage())
}

const rotateLeft = () => {
  rotation.value = (rotation.value - 90) % 360
}

const rotateRight = () => {
  rotation.value = (rotation.value + 90) % 360
}

const toggleFullscreen = () => {
  if (document.fullscreenElement) {
    document.exitFullscreen()
  } else if (imageContainer.value) {
    imageContainer.value.requestFullscreen()
  }
}

const startPan = (event: MouseEvent) => {
  isPanning.value = true
  lastPanPoint.value = { x: event.clientX, y: event.clientY }
}

const handlePan = (event: MouseEvent) => {
  if (!isPanning.value) return
  
  const deltaX = event.clientX - lastPanPoint.value.x
  const deltaY = event.clientY - lastPanPoint.value.y
  
  panX.value += deltaX
  panY.value += deltaY
  
  lastPanPoint.value = { x: event.clientX, y: event.clientY }
}

const endPan = () => {
  isPanning.value = false
}

const handleWheel = (event: WheelEvent) => {
  event.preventDefault()
  
  const delta = event.deltaY > 0 ? -zoomStep : zoomStep
  const newZoom = Math.max(minZoom, Math.min(maxZoom, zoomLevel.value + delta))
  
  if (newZoom !== zoomLevel.value) {
    zoomLevel.value = newZoom
  }
}

const toggleEditMode = () => {
  editMode.value = !editMode.value
}

const adjustBrightness = (delta: number) => {
  brightness.value = Math.max(0, Math.min(200, brightness.value + delta))
}

const adjustContrast = (delta: number) => {
  contrast.value = Math.max(0, Math.min(200, contrast.value + delta))
}

const resetFilters = () => {
  brightness.value = originalFilters.brightness
  contrast.value = originalFilters.contrast
  saturation.value = originalFilters.saturation
  blur.value = originalFilters.blur
}

const cancelEdit = () => {
  resetFilters()
  editMode.value = false
}

const saveEdit = () => {
  // Emit save event with edited filters
  const editedImage = {
    ...props.image,
    filters: {
      brightness: brightness.value,
      contrast: contrast.value,
      saturation: saturation.value,
      blur: blur.value
    }
  }
  
  emit('save', editedImage)
  editMode.value = false
}

const downloadImage = () => {
  const link = document.createElement('a')
  link.href = props.image.url
  link.download = props.image.original_filename
  link.click()
}

const handleKeydown = (event: KeyboardEvent) => {
  switch (event.key) {
    case 'Escape':
      emit('close')
      break
    case '=':
    case '+':
      zoomIn()
      break
    case '-':
      zoomOut()
      break
    case '0':
      resetZoom()
      break
    case 'r':
    case 'R':
      rotateRight()
      break
    case 'f':
    case 'F':
      toggleFullscreen()
      break
  }
}

onMounted(() => {
  document.addEventListener('keydown', handleKeydown)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
})
</script> 