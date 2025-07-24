<template>
  <div class="media-gallery">
    <!-- Gallery header -->
    <div v-if="showHeader" class="flex items-center justify-between mb-4">
      <div class="flex items-center space-x-4">
        <h3 v-if="title" class="text-lg font-semibold text-gray-900">{{ title }}</h3>
        <span v-if="showCount" class="text-sm text-gray-500">{{ mediaItems.length }} items</span>
      </div>
      
      <div class="flex items-center space-x-2">
        <!-- View mode toggle -->
        <div v-if="allowViewModeChange" class="flex bg-gray-100 rounded-lg p-1">
          <button
            @click="setViewMode('grid')"
            class="p-2 rounded-md transition-colors"
            :class="viewMode === 'grid' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900'"
          >
            <Squares2X2Icon class="w-4 h-4" />
          </button>
          <button
            @click="setViewMode('list')"
            class="p-2 rounded-md transition-colors"
            :class="viewMode === 'list' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900'"
          >
            <ListBulletIcon class="w-4 h-4" />
          </button>
          <button
            @click="setViewMode('masonry')"
            class="p-2 rounded-md transition-colors"
            :class="viewMode === 'masonry' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-600 hover:text-gray-900'"
          >
            <ViewColumnsIcon class="w-4 h-4" />
          </button>
        </div>
        
        <!-- Sort options -->
        <select
          v-if="allowSorting"
          v-model="sortBy"
          @change="handleSort"
          class="text-sm border border-gray-300 rounded-md px-3 py-1 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="created_at">Date</option>
          <option value="name">Name</option>
          <option value="size">Size</option>
          <option value="type">Type</option>
        </select>
      </div>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="grid gap-4" :class="gridClasses">
      <div
        v-for="i in 12"
        :key="`skeleton-${i}`"
        class="bg-gray-200 animate-pulse rounded-lg aspect-square"
      ></div>
    </div>

    <!-- Empty state -->
    <div v-else-if="sortedMedia.length === 0" class="text-center py-12">
      <PhotoIcon class="w-16 h-16 text-gray-300 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">No media files</h3>
      <p class="text-gray-600">{{ emptyMessage }}</p>
    </div>

    <!-- Gallery content -->
    <div v-else>
      <!-- Grid view -->
      <div v-if="viewMode === 'grid'" class="grid gap-4" :class="gridClasses">
        <MediaThumbnail
          v-for="media in paginatedMedia"
          :key="media.id"
          :media="media"
          :size="thumbnailSize"
          :hoverable="true"
          :selectable="selectable"
          :is-selected="selectedMedia.includes(media.id)"
          :show-info="showThumbnailInfo"
          :show-view-button="true"
          :show-delete-button="allowDelete"
          @click="handleMediaClick"
          @view="handleMediaView"
          @delete="handleMediaDelete"
          @select="handleMediaSelect"
        />
      </div>

      <!-- List view -->
      <div v-else-if="viewMode === 'list'" class="space-y-2">
        <div
          v-for="media in paginatedMedia"
          :key="media.id"
          class="flex items-center space-x-4 p-3 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors"
          :class="{ 'ring-2 ring-blue-500': selectedMedia.includes(media.id) }"
        >
          <MediaThumbnail
            :media="media"
            size="sm"
            :hoverable="false"
            :selectable="false"
            @click="handleMediaClick"
          />
          
          <div class="flex-1 min-w-0">
            <h4 class="text-sm font-medium text-gray-900 truncate">{{ media.original_filename }}</h4>
            <div class="flex items-center space-x-4 text-xs text-gray-500 mt-1">
              <span>{{ getMediaTypeLabel(media.type) }}</span>
              <span>{{ formatFileSize(media.size) }}</span>
              <span>{{ formatDate(media.created_at) }}</span>
            </div>
            <div v-if="media.alt_text" class="text-xs text-gray-600 mt-1 truncate">
              {{ media.alt_text }}
            </div>
          </div>
          
          <div class="flex items-center space-x-2">
            <button
              v-if="selectable"
              @click="handleMediaSelect(media)"
              class="p-2 text-gray-400 hover:text-blue-600 transition-colors"
            >
              <CheckIcon v-if="selectedMedia.includes(media.id)" class="w-4 h-4 text-blue-600" />
              <div v-else class="w-4 h-4 border border-gray-300 rounded"></div>
            </button>
            
            <button
              @click="handleMediaView(media)"
              class="p-2 text-gray-400 hover:text-blue-600 transition-colors"
            >
              <EyeIcon class="w-4 h-4" />
            </button>
            
            <button
              v-if="allowDelete"
              @click="handleMediaDelete(media)"
              class="p-2 text-gray-400 hover:text-red-600 transition-colors"
            >
              <TrashIcon class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Masonry view -->
      <div v-else-if="viewMode === 'masonry'" class="columns-1 sm:columns-2 md:columns-3 lg:columns-4 gap-4 space-y-4">
        <div
          v-for="media in paginatedMedia"
          :key="media.id"
          class="break-inside-avoid mb-4"
        >
          <MediaThumbnail
            :media="media"
            aspect-ratio="auto"
            :hoverable="true"
            :selectable="selectable"
            :is-selected="selectedMedia.includes(media.id)"
            :show-info="showThumbnailInfo"
            :show-view-button="true"
            :show-delete-button="allowDelete"
            @click="handleMediaClick"
            @view="handleMediaView"
            @delete="handleMediaDelete"
            @select="handleMediaSelect"
          />
        </div>
      </div>

      <!-- Pagination -->
      <div v-if="showPagination && totalPages > 1" class="flex items-center justify-between mt-8">
        <div class="text-sm text-gray-600">
          Showing {{ startItem }}-{{ endItem }} of {{ sortedMedia.length }} items
        </div>
        
        <div class="flex items-center space-x-2">
          <button
            @click="previousPage"
            :disabled="currentPage === 1"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Previous
          </button>
          
          <div class="flex items-center space-x-1">
            <button
              v-for="page in visiblePages"
              :key="page"
              @click="setCurrentPage(page)"
              class="px-3 py-2 text-sm rounded-md transition-colors"
              :class="page === currentPage 
                ? 'bg-blue-600 text-white' 
                : 'text-gray-600 hover:bg-gray-100'"
            >
              {{ page }}
            </button>
          </div>
          
          <button
            @click="nextPage"
            :disabled="currentPage === totalPages"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Selection actions -->
    <div v-if="selectable && selectedMedia.length > 0" class="fixed bottom-6 left-1/2 transform -translate-x-1/2 bg-white border border-gray-200 rounded-lg shadow-lg p-4 z-40">
      <div class="flex items-center space-x-4">
        <span class="text-sm text-gray-600">{{ selectedMedia.length }} selected</span>
        
        <div class="flex items-center space-x-2">
          <button
            @click="handleBulkDelete"
            class="px-3 py-2 text-sm bg-red-600 text-white rounded-md hover:bg-red-700 transition-colors"
          >
            Delete
          </button>
          
          <button
            @click="clearSelection"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md hover:bg-gray-50 transition-colors"
          >
            Clear
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import {
  Squares2X2Icon,
  ListBulletIcon,
  ViewColumnsIcon,
  PhotoIcon,
  EyeIcon,
  TrashIcon,
  CheckIcon
} from '@heroicons/vue/24/outline'
import type { MediaAttachment } from '@/types/media'
import MediaThumbnail from './MediaThumbnail.vue'

interface Props {
  mediaItems: MediaAttachment[]
  title?: string
  loading?: boolean
  emptyMessage?: string
  showHeader?: boolean
  showCount?: boolean
  allowViewModeChange?: boolean
  allowSorting?: boolean
  allowDelete?: boolean
  selectable?: boolean
  showPagination?: boolean
  itemsPerPage?: number
  thumbnailSize?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'
  showThumbnailInfo?: boolean
  initialViewMode?: 'grid' | 'list' | 'masonry'
  columns?: number
}

interface Emits {
  (e: 'media-click', media: MediaAttachment): void
  (e: 'media-view', media: MediaAttachment): void
  (e: 'media-delete', media: MediaAttachment): void
  (e: 'media-select', selectedIds: number[]): void
  (e: 'bulk-delete', selectedIds: number[]): void
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  loading: false,
  emptyMessage: 'No media files to display.',
  showHeader: true,
  showCount: true,
  allowViewModeChange: true,
  allowSorting: true,
  allowDelete: false,
  selectable: false,
  showPagination: true,
  itemsPerPage: 24,
  thumbnailSize: 'md',
  showThumbnailInfo: false,
  initialViewMode: 'grid',
  columns: 4
})

const emit = defineEmits<Emits>()

const viewMode = ref(props.initialViewMode)
const sortBy = ref('created_at')
const currentPage = ref(1)
const selectedMedia = ref<number[]>([])

const gridClasses = computed(() => {
  const baseClasses = 'grid-cols-2 sm:grid-cols-3'
  
  switch (props.columns) {
    case 2: return 'grid-cols-1 sm:grid-cols-2'
    case 3: return 'grid-cols-2 sm:grid-cols-3'
    case 4: return 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4'
    case 5: return 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5'
    case 6: return 'grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6'
    default: return baseClasses
  }
})

const sortedMedia = computed(() => {
  const sorted = [...props.mediaItems]
  
  sorted.sort((a, b) => {
    switch (sortBy.value) {
      case 'name':
        return a.original_filename.localeCompare(b.original_filename)
      case 'size':
        return b.size - a.size
      case 'type':
        return a.type.localeCompare(b.type)
      case 'created_at':
      default:
        return new Date(b.created_at).getTime() - new Date(a.created_at).getTime()
    }
  })
  
  return sorted
})

const totalPages = computed(() => {
  return Math.ceil(sortedMedia.value.length / props.itemsPerPage)
})

const paginatedMedia = computed(() => {
  if (!props.showPagination) return sortedMedia.value
  
  const start = (currentPage.value - 1) * props.itemsPerPage
  const end = start + props.itemsPerPage
  return sortedMedia.value.slice(start, end)
})

const startItem = computed(() => {
  return (currentPage.value - 1) * props.itemsPerPage + 1
})

const endItem = computed(() => {
  return Math.min(currentPage.value * props.itemsPerPage, sortedMedia.value.length)
})

const visiblePages = computed(() => {
  const pages = []
  const start = Math.max(1, currentPage.value - 2)
  const end = Math.min(totalPages.value, currentPage.value + 2)
  
  for (let i = start; i <= end; i++) {
    pages.push(i)
  }
  
  return pages
})

const setViewMode = (mode: 'grid' | 'list' | 'masonry') => {
  viewMode.value = mode
}

const handleSort = () => {
  currentPage.value = 1
}

const setCurrentPage = (page: number) => {
  currentPage.value = page
}

const previousPage = () => {
  if (currentPage.value > 1) {
    currentPage.value--
  }
}

const nextPage = () => {
  if (currentPage.value < totalPages.value) {
    currentPage.value++
  }
}

const handleMediaClick = (media: MediaAttachment) => {
  emit('media-click', media)
}

const handleMediaView = (media: MediaAttachment) => {
  emit('media-view', media)
}

const handleMediaDelete = (media: MediaAttachment) => {
  emit('media-delete', media)
}

const handleMediaSelect = (media: MediaAttachment) => {
  const index = selectedMedia.value.indexOf(media.id)
  if (index > -1) {
    selectedMedia.value.splice(index, 1)
  } else {
    selectedMedia.value.push(media.id)
  }
  emit('media-select', selectedMedia.value)
}

const handleBulkDelete = () => {
  emit('bulk-delete', selectedMedia.value)
  selectedMedia.value = []
}

const clearSelection = () => {
  selectedMedia.value = []
  emit('media-select', [])
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

// Reset page when media items change
watch(() => props.mediaItems, () => {
  currentPage.value = 1
})
</script> 