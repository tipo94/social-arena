<template>
  <div 
    class="media-thumbnail relative overflow-hidden group cursor-pointer"
    :class="thumbnailClasses"
    @click="handleClick"
    @mouseenter="handleMouseEnter"
    @mouseleave="handleMouseLeave"
  >
    <!-- Image thumbnail -->
    <img
      v-if="media.type === 'image'"
      :src="thumbnailUrl"
      :alt="media.alt_text || 'Media thumbnail'"
      class="w-full h-full object-cover transition-transform duration-300"
      :class="{ 'group-hover:scale-105': hoverable && !isLoading }"
      @load="onLoad"
      @error="onError"
    />

    <!-- Video thumbnail -->
    <div v-else-if="media.type === 'video'" class="relative w-full h-full bg-black">
      <img
        v-if="media.thumbnail_url"
        :src="media.thumbnail_url"
        :alt="media.alt_text || 'Video thumbnail'"
        class="w-full h-full object-cover transition-transform duration-300"
        :class="{ 'group-hover:scale-105': hoverable && !isLoading }"
        @load="onLoad"
        @error="onError"
      />
      <div v-else class="w-full h-full bg-gray-800 flex items-center justify-center">
        <VideoCameraIcon class="w-8 h-8 text-gray-400" />
      </div>
      
      <!-- Play button overlay -->
      <div class="absolute inset-0 flex items-center justify-center">
        <div class="w-12 h-12 bg-black bg-opacity-60 rounded-full flex items-center justify-center group-hover:bg-opacity-80 transition-all duration-200">
          <PlayIcon class="w-6 h-6 text-white ml-1" />
        </div>
      </div>
      
      <!-- Duration badge -->
      <div v-if="videoDuration" class="absolute bottom-2 right-2 bg-black bg-opacity-70 text-white text-xs px-2 py-1 rounded">
        {{ formatDuration(videoDuration) }}
      </div>
    </div>

    <!-- Document thumbnail -->
    <div v-else-if="media.type === 'document'" class="w-full h-full bg-gray-100 flex flex-col items-center justify-center p-4">
      <DocumentIcon class="w-8 h-8 text-gray-400 mb-2" />
      <span class="text-xs text-gray-600 text-center truncate max-w-full">
        {{ media.original_filename }}
      </span>
      <span class="text-xs text-gray-500 mt-1">
        {{ formatFileSize(media.size) }}
      </span>
    </div>

    <!-- Loading overlay -->
    <div 
      v-if="isLoading" 
      class="absolute inset-0 bg-gray-200 flex items-center justify-center"
    >
      <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
    </div>

    <!-- Error overlay -->
    <div 
      v-if="hasError" 
      class="absolute inset-0 bg-gray-100 flex items-center justify-center"
    >
      <ExclamationTriangleIcon class="w-6 h-6 text-gray-400" />
    </div>

    <!-- Selection overlay -->
    <div 
      v-if="isSelected" 
      class="absolute inset-0 bg-blue-600 bg-opacity-30 border-2 border-blue-600"
    >
      <div class="absolute top-2 right-2 w-6 h-6 bg-blue-600 rounded-full flex items-center justify-center">
        <CheckIcon class="w-4 h-4 text-white" />
      </div>
    </div>

    <!-- Hover overlay -->
    <div 
      v-if="hoverable && !isLoading && !hasError"
      class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all duration-200"
    >
      <!-- Action buttons on hover -->
      <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200 flex space-x-1">
        <button
          v-if="showViewButton"
          @click.stop="$emit('view', media)"
          class="w-8 h-8 bg-white bg-opacity-90 rounded-full flex items-center justify-center hover:bg-opacity-100 transition-all"
        >
          <EyeIcon class="w-4 h-4 text-gray-700" />
        </button>
        
        <button
          v-if="showDeleteButton"
          @click.stop="$emit('delete', media)"
          class="w-8 h-8 bg-red-500 bg-opacity-90 rounded-full flex items-center justify-center hover:bg-opacity-100 transition-all"
        >
          <TrashIcon class="w-4 h-4 text-white" />
        </button>
      </div>

      <!-- Media info on hover -->
      <div 
        v-if="showInfo"
        class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-2 opacity-0 group-hover:opacity-100 transition-opacity duration-200"
      >
        <div class="text-white text-xs">
          <div class="font-medium truncate">{{ media.original_filename }}</div>
          <div class="text-gray-300">{{ formatFileSize(media.size) }}</div>
        </div>
      </div>
    </div>

    <!-- Badge indicators -->
    <div v-if="badges.length > 0" class="absolute top-2 left-2 flex flex-col space-y-1">
      <span
        v-for="badge in badges"
        :key="badge.type"
        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
        :class="getBadgeClasses(badge.type)"
      >
        {{ badge.label }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import {
  PlayIcon,
  VideoCameraIcon,
  DocumentIcon,
  ExclamationTriangleIcon,
  CheckIcon,
  EyeIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import type { MediaAttachment } from '@/types/media'

interface Props {
  media: MediaAttachment
  size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  aspectRatio?: 'square' | '16:9' | '4:3' | '3:2' | 'auto'
  rounded?: boolean
  hoverable?: boolean
  selectable?: boolean
  isSelected?: boolean
  showInfo?: boolean
  showViewButton?: boolean
  showDeleteButton?: boolean
  badges?: Array<{ type: string; label: string }>
  videoDuration?: number
}

interface Emits {
  (e: 'click', media: MediaAttachment): void
  (e: 'view', media: MediaAttachment): void
  (e: 'delete', media: MediaAttachment): void
  (e: 'select', media: MediaAttachment): void
  (e: 'hover', media: MediaAttachment, isHovering: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
  aspectRatio: 'square',
  rounded: false,
  hoverable: true,
  selectable: false,
  isSelected: false,
  showInfo: false,
  showViewButton: true,
  showDeleteButton: false,
  badges: () => []
})

const emit = defineEmits<Emits>()

const isLoading = ref(true)
const hasError = ref(false)

const thumbnailUrl = computed(() => {
  if (props.media.type === 'image') {
    return props.media.thumbnail_url || props.media.preview_url || props.media.url
  }
  return props.media.thumbnail_url || props.media.url
})

const thumbnailClasses = computed(() => {
  const classes = []
  
  // Size classes
  const sizeClasses = {
    xs: 'w-16 h-16',
    sm: 'w-20 h-20',
    md: 'w-24 h-24',
    lg: 'w-32 h-32',
    xl: 'w-40 h-40'
  }
  classes.push(sizeClasses[props.size])
  
  // Aspect ratio classes
  if (props.aspectRatio !== 'auto') {
    const aspectClasses = {
      square: 'aspect-square',
      '16:9': 'aspect-video',
      '4:3': 'aspect-[4/3]',
      '3:2': 'aspect-[3/2]'
    }
    classes.push(aspectClasses[props.aspectRatio])
  }
  
  // Rounded classes
  if (props.rounded) {
    classes.push('rounded-lg')
  }
  
  // Interactive classes
  if (props.hoverable || props.selectable) {
    classes.push('transition-all duration-200')
  }
  
  if (props.selectable) {
    classes.push('hover:ring-2 hover:ring-blue-500')
  }
  
  return classes.join(' ')
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

const formatDuration = (seconds: number) => {
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = Math.floor(seconds % 60)
  return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`
}

const getBadgeClasses = (type: string) => {
  const badgeClasses = {
    new: 'bg-green-100 text-green-800',
    processing: 'bg-yellow-100 text-yellow-800',
    error: 'bg-red-100 text-red-800',
    featured: 'bg-blue-100 text-blue-800'
  }
  return badgeClasses[type as keyof typeof badgeClasses] || 'bg-gray-100 text-gray-800'
}

const handleClick = () => {
  if (props.selectable) {
    emit('select', props.media)
  } else {
    emit('click', props.media)
  }
}

const handleMouseEnter = () => {
  emit('hover', props.media, true)
}

const handleMouseLeave = () => {
  emit('hover', props.media, false)
}

const onLoad = () => {
  isLoading.value = false
  hasError.value = false
}

const onError = () => {
  isLoading.value = false
  hasError.value = true
}
</script> 