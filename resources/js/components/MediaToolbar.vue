<template>
  <div class="media-toolbar bg-white border border-gray-200 rounded-lg p-4">
    <!-- Main toolbar row -->
    <div class="flex items-center justify-between flex-wrap gap-4">
      <!-- Left side: Search and filters -->
      <div class="flex items-center space-x-4 flex-1 min-w-0">
        <!-- Search -->
        <div class="relative min-w-0 flex-1 max-w-md">
          <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" />
          </div>
          <input
            v-model="searchQuery"
            @input="handleSearch"
            type="text"
            :placeholder="searchPlaceholder"
            class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
          />
          <div v-if="searchQuery" class="absolute inset-y-0 right-0 pr-3 flex items-center">
            <button
              @click="clearSearch"
              class="text-gray-400 hover:text-gray-600"
            >
              <XMarkIcon class="h-4 w-4" />
            </button>
          </div>
        </div>

        <!-- Filter dropdown -->
        <div class="relative" ref="filterDropdown">
          <button
            @click="showFilters = !showFilters"
            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500"
            :class="{ 'bg-blue-50 border-blue-300 text-blue-700': hasActiveFilters }"
          >
            <FunnelIcon class="w-4 h-4 mr-2" />
            Filter
            <div v-if="activeFiltersCount > 0" class="ml-1 px-2 py-0.5 bg-blue-600 text-white text-xs rounded-full">
              {{ activeFiltersCount }}
            </div>
          </button>

          <!-- Filter dropdown menu -->
          <div
            v-if="showFilters"
            class="absolute left-0 top-full mt-2 w-72 bg-white rounded-lg shadow-lg border border-gray-200 p-4 z-20"
          >
            <!-- Media type filter -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Media Type</label>
              <div class="space-y-2">
                <label v-for="type in mediaTypes" :key="type.value" class="flex items-center">
                  <input
                    v-model="filters.types"
                    :value="type.value"
                    type="checkbox"
                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                  />
                  <span class="ml-2 text-sm text-gray-700">{{ type.label }}</span>
                </label>
              </div>
            </div>

            <!-- Date range filter -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">Date Range</label>
              <select
                v-model="filters.dateRange"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">All time</option>
                <option value="today">Today</option>
                <option value="week">This week</option>
                <option value="month">This month</option>
                <option value="year">This year</option>
              </select>
            </div>

            <!-- Size filter -->
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 mb-2">File Size</label>
              <select
                v-model="filters.sizeRange"
                class="block w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
              >
                <option value="">Any size</option>
                <option value="small">Small (&lt; 1MB)</option>
                <option value="medium">Medium (1-10MB)</option>
                <option value="large">Large (&gt; 10MB)</option>
              </select>
            </div>

            <!-- Filter actions -->
            <div class="flex items-center justify-between pt-3 border-t border-gray-200">
              <button
                @click="clearFilters"
                class="text-sm text-gray-600 hover:text-gray-800"
              >
                Clear all
              </button>
              <button
                @click="applyFilters"
                class="px-3 py-2 bg-blue-600 text-white text-sm rounded-md hover:bg-blue-700"
              >
                Apply
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right side: View controls and actions -->
      <div class="flex items-center space-x-4">
        <!-- Sort dropdown -->
        <select
          v-model="sortBy"
          @change="handleSort"
          class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="created_at">Latest</option>
          <option value="name">Name</option>
          <option value="size">Size</option>
          <option value="type">Type</option>
        </select>

        <!-- Sort order -->
        <button
          @click="toggleSortOrder"
          class="p-2 border border-gray-300 rounded-md text-gray-600 hover:text-gray-800 hover:bg-gray-50"
          :title="sortOrder === 'desc' ? 'Sort Ascending' : 'Sort Descending'"
        >
          <ArrowUpIcon v-if="sortOrder === 'asc'" class="w-4 h-4" />
          <ArrowDownIcon v-else class="w-4 h-4" />
        </button>

        <!-- View mode toggle -->
        <div class="flex bg-gray-100 rounded-lg p-1">
          <button
            v-for="mode in viewModes"
            :key="mode.value"
            @click="setViewMode(mode.value)"
            class="p-2 rounded-md transition-colors"
            :class="viewMode === mode.value 
              ? 'bg-white shadow-sm text-blue-600' 
              : 'text-gray-600 hover:text-gray-900'"
            :title="mode.label"
          >
            <component :is="mode.icon" class="w-4 h-4" />
          </button>
        </div>

        <!-- Selection mode toggle -->
        <button
          @click="toggleSelectionMode"
          class="p-2 border border-gray-300 rounded-md transition-colors"
          :class="selectionMode 
            ? 'bg-blue-50 border-blue-300 text-blue-700' 
            : 'text-gray-600 hover:text-gray-800 hover:bg-gray-50'"
          title="Selection Mode"
        >
          <CheckCircleIcon class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Selection toolbar (shown when items are selected) -->
    <div v-if="selectedCount > 0" class="mt-4 pt-4 border-t border-gray-200">
      <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
          <span class="text-sm text-gray-700 font-medium">
            {{ selectedCount }} item{{ selectedCount !== 1 ? 's' : '' }} selected
          </span>
          
          <button
            @click="selectAll"
            v-if="!allSelected"
            class="text-sm text-blue-600 hover:text-blue-800"
          >
            Select all
          </button>
          
          <button
            @click="clearSelection"
            class="text-sm text-gray-600 hover:text-gray-800"
          >
            Clear selection
          </button>
        </div>

        <!-- Bulk actions -->
        <div class="flex items-center space-x-2">
          <button
            @click="downloadSelected"
            :disabled="bulkActionsDisabled"
            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
            Download
          </button>
          
          <button
            @click="moveSelected"
            :disabled="bulkActionsDisabled"
            class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <FolderIcon class="w-4 h-4 mr-2" />
            Move
          </button>
          
          <button
            @click="deleteSelected"
            :disabled="bulkActionsDisabled"
            class="inline-flex items-center px-3 py-2 border border-red-300 rounded-md text-sm font-medium text-red-700 bg-white hover:bg-red-50 disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <TrashIcon class="w-4 h-4 mr-2" />
            Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Active filters display -->
    <div v-if="hasActiveFilters && !showFilters" class="mt-4 pt-4 border-t border-gray-200">
      <div class="flex items-center space-x-2 flex-wrap">
        <span class="text-sm text-gray-600">Active filters:</span>
        
        <div
          v-for="filter in activeFilterTags"
          :key="filter.key"
          class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"
        >
          {{ filter.label }}
          <button
            @click="removeFilter(filter.key)"
            class="ml-1 text-blue-600 hover:text-blue-800"
          >
            <XMarkIcon class="w-3 h-3" />
          </button>
        </div>
        
        <button
          @click="clearFilters"
          class="text-xs text-gray-600 hover:text-gray-800 underline"
        >
          Clear all
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import {
  MagnifyingGlassIcon,
  XMarkIcon,
  FunnelIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  CheckCircleIcon,
  ArrowDownTrayIcon,
  FolderIcon,
  TrashIcon,
  Squares2X2Icon,
  ListBulletIcon,
  ViewColumnsIcon
} from '@heroicons/vue/24/outline'

interface Props {
  selectedCount?: number
  totalCount?: number
  viewMode?: string
  selectionMode?: boolean
  searchPlaceholder?: string
}

interface Emits {
  (e: 'search', query: string): void
  (e: 'filter', filters: any): void
  (e: 'sort', sortBy: string, order: string): void
  (e: 'view-mode-change', mode: string): void
  (e: 'selection-mode-toggle', enabled: boolean): void
  (e: 'select-all'): void
  (e: 'clear-selection'): void
  (e: 'bulk-download', selectedIds: number[]): void
  (e: 'bulk-move', selectedIds: number[]): void
  (e: 'bulk-delete', selectedIds: number[]): void
}

const props = withDefaults(defineProps<Props>(), {
  selectedCount: 0,
  totalCount: 0,
  viewMode: 'grid',
  selectionMode: false,
  searchPlaceholder: 'Search media files...'
})

const emit = defineEmits<Emits>()

// Refs
const filterDropdown = ref<HTMLElement>()

// State
const searchQuery = ref('')
const showFilters = ref(false)
const sortBy = ref('created_at')
const sortOrder = ref<'asc' | 'desc'>('desc')

const filters = ref({
  types: [] as string[],
  dateRange: '',
  sizeRange: ''
})

// Constants
const mediaTypes = [
  { value: 'image', label: 'Images' },
  { value: 'video', label: 'Videos' },
  { value: 'document', label: 'Documents' }
]

const viewModes = [
  { value: 'grid', label: 'Grid View', icon: Squares2X2Icon },
  { value: 'list', label: 'List View', icon: ListBulletIcon },
  { value: 'masonry', label: 'Masonry View', icon: ViewColumnsIcon }
]

// Computed
const hasActiveFilters = computed(() => {
  return filters.value.types.length > 0 || 
         filters.value.dateRange !== '' || 
         filters.value.sizeRange !== ''
})

const activeFiltersCount = computed(() => {
  let count = 0
  if (filters.value.types.length > 0) count++
  if (filters.value.dateRange) count++
  if (filters.value.sizeRange) count++
  return count
})

const activeFilterTags = computed(() => {
  const tags = []
  
  if (filters.value.types.length > 0) {
    const typeLabels = filters.value.types.map(type => 
      mediaTypes.find(t => t.value === type)?.label
    ).join(', ')
    tags.push({ key: 'types', label: `Type: ${typeLabels}` })
  }
  
  if (filters.value.dateRange) {
    const dateLabels: Record<string, string> = {
      today: 'Today',
      week: 'This week',
      month: 'This month',
      year: 'This year'
    }
    tags.push({ key: 'dateRange', label: `Date: ${dateLabels[filters.value.dateRange]}` })
  }
  
  if (filters.value.sizeRange) {
    const sizeLabels: Record<string, string> = {
      small: 'Small files',
      medium: 'Medium files',
      large: 'Large files'
    }
    tags.push({ key: 'sizeRange', label: `Size: ${sizeLabels[filters.value.sizeRange]}` })
  }
  
  return tags
})

const allSelected = computed(() => {
  return props.selectedCount === props.totalCount && props.totalCount > 0
})

const bulkActionsDisabled = computed(() => {
  return props.selectedCount === 0
})

// Methods
const handleSearch = () => {
  emit('search', searchQuery.value)
}

const clearSearch = () => {
  searchQuery.value = ''
  emit('search', '')
}

const handleSort = () => {
  emit('sort', sortBy.value, sortOrder.value)
}

const toggleSortOrder = () => {
  sortOrder.value = sortOrder.value === 'asc' ? 'desc' : 'asc'
  handleSort()
}

const setViewMode = (mode: string) => {
  emit('view-mode-change', mode)
}

const toggleSelectionMode = () => {
  emit('selection-mode-toggle', !props.selectionMode)
}

const selectAll = () => {
  emit('select-all')
}

const clearSelection = () => {
  emit('clear-selection')
}

const applyFilters = () => {
  showFilters.value = false
  emit('filter', { ...filters.value })
}

const clearFilters = () => {
  filters.value = {
    types: [],
    dateRange: '',
    sizeRange: ''
  }
  showFilters.value = false
  emit('filter', { ...filters.value })
}

const removeFilter = (filterKey: string) => {
  switch (filterKey) {
    case 'types':
      filters.value.types = []
      break
    case 'dateRange':
      filters.value.dateRange = ''
      break
    case 'sizeRange':
      filters.value.sizeRange = ''
      break
  }
  emit('filter', { ...filters.value })
}

const downloadSelected = () => {
  emit('bulk-download', []) // Parent should provide selected IDs
}

const moveSelected = () => {
  emit('bulk-move', []) // Parent should provide selected IDs
}

const deleteSelected = () => {
  if (confirm(`Are you sure you want to delete ${props.selectedCount} selected items?`)) {
    emit('bulk-delete', []) // Parent should provide selected IDs
  }
}

const handleClickOutside = (event: Event) => {
  if (filterDropdown.value && !filterDropdown.value.contains(event.target as Node)) {
    showFilters.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script> 