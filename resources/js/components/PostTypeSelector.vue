<template>
  <div class="post-type-selector">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
      <button
        v-for="type in postTypes"
        :key="type.value"
        type="button"
        :disabled="disabled"
        :class="[
          'post-type-option',
          {
            'post-type-selected': modelValue === type.value,
            'post-type-disabled': disabled
          }
        ]"
        @click="selectType(type.value)"
      >
        <div class="post-type-icon">
          <component :is="type.icon" />
        </div>
        <div class="post-type-content">
          <div class="post-type-name">{{ type.label }}</div>
          <div class="post-type-description">{{ type.description }}</div>
        </div>
        
        <!-- Selection Indicator -->
        <div v-if="modelValue === type.value" class="post-type-indicator">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
          </svg>
        </div>
      </button>
    </div>
    
    <!-- Selected Type Info -->
    <div v-if="selectedType" class="selected-type-info mt-4">
      <div class="flex items-center space-x-2 text-sm text-neutral-600">
        <component :is="selectedType.icon" class="w-4 h-4" />
        <span>{{ selectedType.label }}:</span>
        <span>{{ selectedType.helpText }}</span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, defineComponent, h } from 'vue'

interface PostType {
  value: string
  label: string
  description: string
  helpText: string
  icon: any
}

interface Props {
  modelValue: string
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  change: [value: string]
}>()

// Icon components as render functions
const TextIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M4 6h16M4 12h16M4 18h7'
  })
]))

const ImageIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'
  })
]))

const VideoIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z'
  })
]))

const LinkIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'
  })
]))

const BookIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253'
  })
]))

const PollIcon = defineComponent(() => () => h('svg', {
  class: 'w-5 h-5',
  fill: 'none',
  stroke: 'currentColor',
  viewBox: '0 0 24 24'
}, [
  h('path', {
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    'stroke-width': '2',
    d: 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'
  })
]))

// Post type definitions
const postTypes: PostType[] = [
  {
    value: 'text',
    label: 'Text Post',
    description: 'Share your thoughts',
    helpText: 'Write a text-based post with optional media attachments',
    icon: TextIcon,
  },
  {
    value: 'image',
    label: 'Photo',
    description: 'Share images',
    helpText: 'Upload and share photos with captions',
    icon: ImageIcon,
  },
  {
    value: 'video',
    label: 'Video',
    description: 'Share videos',
    helpText: 'Upload and share videos with descriptions',
    icon: VideoIcon,
  },
  {
    value: 'link',
    label: 'Link',
    description: 'Share a link',
    helpText: 'Share an external link with preview and comments',
    icon: LinkIcon,
  },
  {
    value: 'book_review',
    label: 'Book Review',
    description: 'Review a book',
    helpText: 'Write a detailed review about a book you\'ve read',
    icon: BookIcon,
  },
  {
    value: 'poll',
    label: 'Poll',
    description: 'Create a poll',
    helpText: 'Ask a question with multiple choice answers',
    icon: PollIcon,
  },
]

// Computed
const selectedType = computed(() => {
  return postTypes.find(type => type.value === props.modelValue)
})

// Methods
const selectType = (value: string) => {
  if (props.disabled) return
  
  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<style scoped>
.post-type-selector {
  @apply w-full;
}

.post-type-option {
  @apply relative flex items-center p-4 border-2 border-neutral-200 rounded-lg bg-white hover:border-primary-300 hover:bg-primary-50 transition-all duration-200 text-left;
}

.post-type-option:focus {
  @apply outline-none ring-2 ring-primary-500 ring-opacity-50;
}

.post-type-selected {
  @apply border-primary-500 bg-primary-50 ring-2 ring-primary-500 ring-opacity-20;
}

.post-type-disabled {
  @apply opacity-50 cursor-not-allowed;
}

.post-type-icon {
  @apply flex-shrink-0 w-8 h-8 flex items-center justify-center text-neutral-600 mr-3;
}

.post-type-selected .post-type-icon {
  @apply text-primary-600;
}

.post-type-content {
  @apply flex-1 min-w-0;
}

.post-type-name {
  @apply text-sm font-medium text-neutral-900 mb-1;
}

.post-type-description {
  @apply text-xs text-neutral-500;
}

.post-type-indicator {
  @apply absolute top-2 right-2 w-5 h-5 bg-primary-500 text-white rounded-full flex items-center justify-center;
}

.selected-type-info {
  @apply p-3 bg-neutral-50 rounded-lg border border-neutral-200;
}
</style> 