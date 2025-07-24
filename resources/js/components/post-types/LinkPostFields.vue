<template>
  <div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Link Post Details</h3>
    
    <!-- Link URL -->
    <div>
      <label for="link-url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        URL *
      </label>
      <input
        id="link-url"
        type="url"
        :value="metadata.link_url"
        @input="updateField('link_url', ($event.target as HTMLInputElement).value)"
        @blur="extractMetadata"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="https://example.com/article"
        required
      />
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
        Paste a URL and we'll try to extract the title and description automatically
      </p>
    </div>

    <!-- Loading indicator -->
    <div v-if="isExtracting" class="flex items-center space-x-2 text-sm text-gray-600 dark:text-gray-400">
      <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500"></div>
      <span>Extracting link metadata...</span>
    </div>

    <!-- Link Title -->
    <div>
      <label for="link-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Title
      </label>
      <input
        id="link-title"
        type="text"
        :value="metadata.link_title"
        @input="updateField('link_title', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="Link title"
      />
    </div>

    <!-- Link Description -->
    <div>
      <label for="link-description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Description
      </label>
      <textarea
        id="link-description"
        :value="metadata.link_description"
        @input="updateField('link_description', ($event.target as HTMLTextAreaElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        rows="3"
        placeholder="Link description"
      ></textarea>
    </div>

    <!-- Link Type -->
    <div>
      <label for="link-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Link Type
      </label>
      <select
        id="link-type"
        :value="metadata.link_type"
        @change="updateField('link_type', ($event.target as HTMLSelectElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
      >
        <option value="">Auto-detect</option>
        <option value="article">Article</option>
        <option value="video">Video</option>
        <option value="image">Image</option>
        <option value="website">Website</option>
      </select>
    </div>

    <!-- Link Image -->
    <div>
      <label for="link-image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Preview Image URL
      </label>
      <input
        id="link-image"
        type="url"
        :value="metadata.link_image_url"
        @input="updateField('link_image_url', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="https://example.com/image.jpg"
      />
    </div>

    <!-- Preview -->
    <div v-if="hasPreviewData" class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 bg-gray-50 dark:bg-gray-800">
      <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Link Preview</h4>
      <div class="flex space-x-4">
        <img
          v-if="metadata.link_image_url"
          :src="metadata.link_image_url"
          :alt="metadata.link_title"
          class="w-20 h-20 object-cover rounded-md flex-shrink-0"
          @error="onImageError"
        />
        <div class="flex-1">
          <h5 v-if="metadata.link_title" class="font-medium text-gray-900 dark:text-white line-clamp-2">
            {{ metadata.link_title }}
          </h5>
          <p v-if="metadata.link_description" class="text-sm text-gray-600 dark:text-gray-400 mt-1 line-clamp-3">
            {{ metadata.link_description }}
          </p>
          <p v-if="metadata.link_domain" class="text-xs text-gray-500 dark:text-gray-500 mt-2">
            {{ metadata.link_domain }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { LinkMetadata } from '@/types/posts'

interface Props {
  modelValue: Partial<LinkMetadata>
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => ({
    link_url: ''
  })
})

const emit = defineEmits<{
  'update:modelValue': [value: Partial<LinkMetadata>]
}>()

const metadata = computed(() => props.modelValue)
const isExtracting = ref(false)

const hasPreviewData = computed(() => {
  return metadata.value.link_title || metadata.value.link_description || metadata.value.link_image_url
})

const updateField = (field: keyof LinkMetadata, value: any) => {
  const updated = { ...metadata.value, [field]: value }
  if (value === '' || value === undefined) {
    delete updated[field]
  }
  emit('update:modelValue', updated)
}

const extractMetadata = async () => {
  const url = metadata.value.link_url
  if (!url || !isValidUrl(url)) return

  isExtracting.value = true
  
  try {
    // Extract domain
    const domain = new URL(url).hostname
    updateField('link_domain', domain)
    
    // In a real implementation, you would call an API to extract metadata
    // For now, we'll just set the domain
    // TODO: Implement actual metadata extraction via backend API
    
  } catch (error) {
    console.error('Error extracting link metadata:', error)
  } finally {
    isExtracting.value = false
  }
}

const isValidUrl = (string: string) => {
  try {
    new URL(string)
    return true
  } catch (_) {
    return false
  }
}

const onImageError = (event: Event) => {
  // Hide broken images
  const img = event.target as HTMLImageElement
  img.style.display = 'none'
}
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.line-clamp-3 {
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style> 