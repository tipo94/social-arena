<template>
  <div class="tag-input" :class="{ 'tag-input-disabled': disabled }">
    <!-- Tags Display -->
    <div class="tags-container">
      <!-- Existing Tags -->
      <div v-if="modelValue.length" class="tags-list">
        <div
          v-for="(tag, index) in modelValue"
          :key="`${tag}-${index}`"
          class="tag-item"
        >
          <span class="tag-text">{{ tag }}</span>
          <button
            type="button"
            @click="removeTag(index)"
            :disabled="disabled"
            class="tag-remove"
            :title="`Remove ${tag}`"
          >
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Input Field -->
      <div class="tag-input-wrapper">
        <input
          ref="inputElement"
          v-model="inputValue"
          type="text"
          :placeholder="getPlaceholder()"
          :disabled="disabled || isMaxTagsReached"
          class="tag-input-field"
          @input="handleInput"
          @keydown="handleKeydown"
          @focus="handleFocus"
          @blur="handleBlur"
        />
      </div>
    </div>

    <!-- Tag Suggestions -->
    <div v-if="showSuggestions && suggestions.length" class="tag-suggestions">
      <div class="suggestion-header">
        <span class="text-xs font-medium text-neutral-600">Suggestions</span>
      </div>
      <div class="suggestion-list">
        <button
          v-for="(suggestion, index) in suggestions"
          :key="suggestion.tag"
          type="button"
          :class="['suggestion-item', { 'suggestion-active': index === selectedSuggestionIndex }]"
          @click="selectSuggestion(suggestion)"
          @mouseenter="selectedSuggestionIndex = index"
        >
          <div class="suggestion-content">
            <span class="suggestion-tag">{{ suggestion.tag }}</span>
            <span v-if="suggestion.count" class="suggestion-count">{{ suggestion.count }}</span>
          </div>
          <div v-if="suggestion.description" class="suggestion-description">
            {{ suggestion.description }}
          </div>
        </button>
      </div>
    </div>

    <!-- Popular Tags -->
    <div v-if="showPopularTags && popularTags.length && !inputValue" class="popular-tags">
      <div class="popular-tags-header">
        <span class="text-xs font-medium text-neutral-600">Popular Tags</span>
      </div>
      <div class="popular-tags-list">
        <button
          v-for="tag in popularTags"
          :key="tag.tag"
          type="button"
          class="popular-tag"
          :disabled="disabled || isTagSelected(tag.tag) || isMaxTagsReached"
          @click="addTag(tag.tag)"
        >
          <span class="popular-tag-text">{{ tag.tag }}</span>
          <span class="popular-tag-count">{{ tag.count }}</span>
        </button>
      </div>
    </div>

    <!-- Tag Info -->
    <div class="tag-info">
      <div class="flex justify-between text-xs text-neutral-500">
        <span>{{ modelValue.length }}/{{ maxTags }} tags</span>
        <span v-if="inputValue.length">Press Enter to add</span>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="error-message">
      {{ error }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'

interface TagSuggestion {
  tag: string
  count?: number
  description?: string
}

interface PopularTag {
  tag: string
  count: number
}

interface Props {
  modelValue: string[]
  placeholder?: string
  maxTags?: number
  disabled?: boolean
  error?: string
  showPopularTags?: boolean
  minTagLength?: number
  maxTagLength?: number
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'Add tags...',
  maxTags: 20,
  disabled: false,
  showPopularTags: true,
  minTagLength: 2,
  maxTagLength: 30,
})

const emit = defineEmits<{
  'update:modelValue': [value: string[]]
  'tag-added': [tag: string]
  'tag-removed': [tag: string]
  input: [value: string[]]
}>()

// Refs
const inputElement = ref<HTMLInputElement>()
const inputValue = ref('')
const suggestions = ref<TagSuggestion[]>([])
const popularTags = ref<PopularTag[]>([])
const showSuggestions = ref(false)
const selectedSuggestionIndex = ref(0)
const isFocused = ref(false)

// Computed
const isMaxTagsReached = computed(() => props.modelValue.length >= props.maxTags)

const getPlaceholder = () => {
  if (props.disabled) return 'Tags disabled'
  if (isMaxTagsReached.value) return `Maximum ${props.maxTags} tags reached`
  if (props.modelValue.length === 0) return props.placeholder
  return 'Add another tag...'
}

// Methods
const handleInput = () => {
  if (props.disabled) return
  
  const value = inputValue.value.trim()
  
  if (value.length >= 1) {
    searchTagSuggestions(value)
  } else {
    suggestions.value = []
    showSuggestions.value = false
  }
}

const handleKeydown = (event: KeyboardEvent) => {
  if (props.disabled) return

  // Handle suggestion navigation
  if (showSuggestions.value && suggestions.value.length > 0) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      selectedSuggestionIndex.value = Math.min(selectedSuggestionIndex.value + 1, suggestions.value.length - 1)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      selectedSuggestionIndex.value = Math.max(selectedSuggestionIndex.value - 1, 0)
    } else if (event.key === 'Enter') {
      event.preventDefault()
      if (suggestions.value[selectedSuggestionIndex.value]) {
        selectSuggestion(suggestions.value[selectedSuggestionIndex.value])
      } else {
        addTagFromInput()
      }
      return
    } else if (event.key === 'Escape') {
      event.preventDefault()
      closeSuggestions()
      return
    }
  }

  // Handle tag input
  if (event.key === 'Enter') {
    event.preventDefault()
    addTagFromInput()
  } else if (event.key === 'Backspace' && inputValue.value === '' && props.modelValue.length > 0) {
    // Remove last tag when backspacing on empty input
    removeTag(props.modelValue.length - 1)
  } else if (event.key === ',' || event.key === ' ') {
    // Add tag on comma or space
    event.preventDefault()
    addTagFromInput()
  }
}

const handleFocus = () => {
  isFocused.value = true
  
  if (inputValue.value.trim()) {
    searchTagSuggestions(inputValue.value.trim())
  }
}

const handleBlur = () => {
  isFocused.value = false
  
  // Close suggestions after a short delay to allow for clicks
  setTimeout(() => {
    if (!isFocused.value) {
      closeSuggestions()
    }
  }, 200)
}

const addTagFromInput = () => {
  const tag = inputValue.value.trim()
  if (tag) {
    addTag(tag)
  }
}

const addTag = (tag: string) => {
  if (props.disabled || isMaxTagsReached.value) return
  
  // Clean and validate tag
  const cleanTag = cleanTagInput(tag)
  
  if (!validateTag(cleanTag)) return
  
  if (!isTagSelected(cleanTag)) {
    const newTags = [...props.modelValue, cleanTag]
    emit('update:modelValue', newTags)
    emit('tag-added', cleanTag)
    emit('input', newTags)
  }
  
  // Clear input and close suggestions
  inputValue.value = ''
  closeSuggestions()
  
  // Focus input for continuous tagging
  nextTick(() => {
    inputElement.value?.focus()
  })
}

const removeTag = (index: number) => {
  if (props.disabled) return
  
  const removedTag = props.modelValue[index]
  const newTags = props.modelValue.filter((_, i) => i !== index)
  
  emit('update:modelValue', newTags)
  emit('tag-removed', removedTag)
  emit('input', newTags)
  
  // Focus input after removal
  nextTick(() => {
    inputElement.value?.focus()
  })
}

const isTagSelected = (tag: string): boolean => {
  return props.modelValue.some(existingTag => 
    existingTag.toLowerCase() === tag.toLowerCase()
  )
}

const cleanTagInput = (input: string): string => {
  return input
    .trim()
    .toLowerCase()
    .replace(/[^a-zA-Z0-9\s-_]/g, '') // Remove special characters
    .replace(/\s+/g, '') // Remove spaces
    .slice(0, props.maxTagLength)
}

const validateTag = (tag: string): boolean => {
  if (tag.length < props.minTagLength) return false
  if (tag.length > props.maxTagLength) return false
  return true
}

const searchTagSuggestions = async (query: string) => {
  if (query.length < 1) {
    suggestions.value = []
    showSuggestions.value = false
    return
  }

  // In a real app, this would call an API to search for tag suggestions
  // For now, we'll use mock data
  const mockSuggestions = getMockTagSuggestions()
  
  suggestions.value = mockSuggestions
    .filter(suggestion => 
      suggestion.tag.toLowerCase().includes(query.toLowerCase()) &&
      !isTagSelected(suggestion.tag)
    )
    .slice(0, 8)
  
  selectedSuggestionIndex.value = 0
  showSuggestions.value = suggestions.value.length > 0
}

const selectSuggestion = (suggestion: TagSuggestion) => {
  addTag(suggestion.tag)
}

const closeSuggestions = () => {
  showSuggestions.value = false
  suggestions.value = []
  selectedSuggestionIndex.value = 0
}

const loadPopularTags = async () => {
  if (!props.showPopularTags) return
  
  // In a real app, this would call an API to get popular tags
  // For now, we'll use mock data
  popularTags.value = getMockPopularTags().slice(0, 10)
}

const getMockTagSuggestions = (): TagSuggestion[] => {
  return [
    { tag: 'fiction', count: 1250, description: 'Fictional literature and stories' },
    { tag: 'nonfiction', count: 890, description: 'Factual and educational content' },
    { tag: 'mystery', count: 675, description: 'Mystery and detective stories' },
    { tag: 'romance', count: 1100, description: 'Romance novels and love stories' },
    { tag: 'fantasy', count: 950, description: 'Fantasy and magical worlds' },
    { tag: 'scifi', count: 720, description: 'Science fiction and futuristic themes' },
    { tag: 'thriller', count: 580, description: 'Suspenseful and thrilling stories' },
    { tag: 'horror', count: 420, description: 'Horror and scary stories' },
    { tag: 'biography', count: 380, description: 'Life stories of real people' },
    { tag: 'history', count: 465, description: 'Historical events and periods' },
    { tag: 'cooking', count: 290, description: 'Recipes and culinary arts' },
    { tag: 'travel', count: 340, description: 'Travel guides and experiences' },
    { tag: 'selfhelp', count: 510, description: 'Personal development and growth' },
    { tag: 'poetry', count: 275, description: 'Poems and verse' },
    { tag: 'children', count: 425, description: 'Books for children' },
    { tag: 'youngadult', count: 685, description: 'Young adult literature' },
    { tag: 'classic', count: 395, description: 'Classic literature' },
    { tag: 'contemporary', count: 320, description: 'Modern contemporary fiction' },
    { tag: 'adventure', count: 445, description: 'Adventure and action stories' },
    { tag: 'drama', count: 360, description: 'Dramatic and emotional stories' },
  ]
}

const getMockPopularTags = (): PopularTag[] => {
  return [
    { tag: 'fiction', count: 1250 },
    { tag: 'romance', count: 1100 },
    { tag: 'fantasy', count: 950 },
    { tag: 'nonfiction', count: 890 },
    { tag: 'scifi', count: 720 },
    { tag: 'youngadult', count: 685 },
    { tag: 'mystery', count: 675 },
    { tag: 'thriller', count: 580 },
    { tag: 'selfhelp', count: 510 },
    { tag: 'history', count: 465 },
  ]
}

// Click outside handler
const handleClickOutside = (event: Event) => {
  const target = event.target as Element
  if (!target.closest('.tag-input')) {
    closeSuggestions()
  }
}

// Watchers
watch(() => props.modelValue, () => {
  // Update suggestions when tags change
  if (inputValue.value.trim()) {
    searchTagSuggestions(inputValue.value.trim())
  }
})

// Lifecycle
onMounted(() => {
  loadPopularTags()
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.tag-input {
  @apply relative w-full;
}

.tag-input-disabled {
  @apply opacity-50;
}

.tags-container {
  @apply flex flex-wrap gap-2 p-3 border border-neutral-200 rounded-lg bg-white focus-within:ring-2 focus-within:ring-primary-500 focus-within:ring-opacity-20 focus-within:border-primary-500;
}

.tags-list {
  @apply flex flex-wrap gap-2;
}

.tag-item {
  @apply flex items-center bg-primary-100 text-primary-800 px-2 py-1 rounded-md text-sm;
}

.tag-text {
  @apply font-medium;
}

.tag-remove {
  @apply ml-1 text-primary-600 hover:text-primary-800 transition-colors;
}

.tag-remove:disabled {
  @apply cursor-not-allowed opacity-50;
}

.tag-input-wrapper {
  @apply flex-1 min-w-0;
}

.tag-input-field {
  @apply w-full border-none outline-none bg-transparent text-sm placeholder-neutral-400;
}

.tag-input-field:disabled {
  @apply cursor-not-allowed;
}

.tag-suggestions {
  @apply absolute top-full left-0 right-0 z-10 bg-white border border-neutral-200 rounded-lg shadow-lg mt-1;
}

.suggestion-header {
  @apply px-3 py-2 border-b border-neutral-200;
}

.suggestion-list {
  @apply max-h-60 overflow-y-auto;
}

.suggestion-item {
  @apply flex flex-col p-3 hover:bg-neutral-50 transition-colors w-full text-left;
}

.suggestion-active {
  @apply bg-primary-50;
}

.suggestion-content {
  @apply flex items-center justify-between;
}

.suggestion-tag {
  @apply text-sm font-medium text-neutral-900;
}

.suggestion-count {
  @apply text-xs text-neutral-500 bg-neutral-100 px-2 py-0.5 rounded-full;
}

.suggestion-description {
  @apply text-xs text-neutral-600 mt-1;
}

.popular-tags {
  @apply mt-3 p-3 bg-neutral-50 rounded-lg border border-neutral-200;
}

.popular-tags-header {
  @apply mb-2;
}

.popular-tags-list {
  @apply flex flex-wrap gap-2;
}

.popular-tag {
  @apply flex items-center space-x-1 bg-white border border-neutral-200 hover:border-primary-300 hover:bg-primary-50 px-2 py-1 rounded-md text-sm transition-colors;
}

.popular-tag:disabled {
  @apply opacity-50 cursor-not-allowed;
}

.popular-tag-text {
  @apply font-medium text-neutral-700;
}

.popular-tag-count {
  @apply text-xs text-neutral-500;
}

.tag-info {
  @apply mt-2;
}

.error-message {
  @apply mt-1 text-sm text-red-600;
}
</style> 