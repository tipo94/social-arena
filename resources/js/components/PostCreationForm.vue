<template>
  <div class="post-creation-form">
    <!-- Form Header -->
    <div class="flex items-center justify-between mb-6">
      <h2 class="text-xl font-semibold text-neutral-900">
        {{ editMode ? 'Edit Post' : 'Create Post' }}
      </h2>
      <button 
        v-if="onClose"
        @click="onClose"
        class="btn btn-ghost btn-sm"
      >
        ✕
      </button>
    </div>

    <!-- Error Display -->
    <div v-if="errorMessage" class="alert alert-error mb-4">
      <div class="alert-icon">⚠️</div>
      <div class="alert-content">
        <div class="alert-title">Error</div>
        <div class="alert-description">{{ errorMessage }}</div>
      </div>
    </div>

    <!-- Success Display -->
    <div v-if="successMessage" class="alert alert-success mb-4">
      <div class="alert-icon">✅</div>
      <div class="alert-content">
        <div class="alert-title">Success</div>
        <div class="alert-description">{{ successMessage }}</div>
      </div>
    </div>

    <form @submit.prevent="handleSubmit" class="space-y-6">
      <!-- Post Type Selection -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 mb-2">
          Post Type
        </label>
        <PostTypeSelector
          v-model="formData.type"
          :disabled="isSubmitting"
          @change="handlePostTypeChange"
        />
      </div>

      <!-- Content Area -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 mb-2">
          Content
          <span v-if="formData.type !== 'image' && formData.type !== 'video'" class="text-red-500">*</span>
        </label>
        <RichTextEditor
          v-model="formData.content"
          :placeholder="getContentPlaceholder()"
          :max-length="5000"
          :disabled="isSubmitting"
          :error="errors.content?.[0]"
          @input="clearError('content')"
        />
        <div class="flex justify-between mt-2 text-xs text-neutral-500">
          <span v-if="errors.content" class="text-red-500">{{ errors.content[0] }}</span>
          <span class="ml-auto">{{ contentLength }}/5000</span>
        </div>
      </div>

      <!-- Media Upload Section -->
      <div v-if="showMediaUpload">
        <label class="block text-sm font-medium text-neutral-700 mb-2">
          Media
          <span v-if="formData.type === 'image' || formData.type === 'video'" class="text-red-500">*</span>
        </label>
        <MediaUploadComponent
          v-model="formData.media_ids"
          :type="getMediaType()"
          :max-files="getMaxFiles()"
          :disabled="isSubmitting"
          :error="errors.media_ids?.[0]"
          @upload="handleMediaUpload"
          @remove="handleMediaRemove"
        />
        <span v-if="errors.media_ids" class="text-xs text-red-500 mt-1">{{ errors.media_ids[0] }}</span>
      </div>

      <!-- Post Type Specific Fields -->
      <component
        :is="getTypeComponent()"
        v-if="getTypeComponent()"
        v-model="formData.metadata"
        :disabled="isSubmitting"
        :errors="errors"
        @input="clearMetadataErrors"
      />

      <!-- Visibility Settings -->
      <div>
        <label class="block text-sm font-medium text-neutral-700 mb-2">
          Visibility
        </label>
        <VisibilitySelector
          v-model="formData.visibility"
          v-model:custom-audience="formData.custom_audience"
          :disabled="isSubmitting"
          :group-id="formData.group_id"
          @change="clearError('visibility')"
        />
        <span v-if="errors.visibility" class="text-xs text-red-500 mt-1">{{ errors.visibility[0] }}</span>
      </div>

      <!-- Advanced Options -->
      <div class="border-t border-neutral-200 pt-4">
        <button
          type="button"
          @click="showAdvancedOptions = !showAdvancedOptions"
          class="flex items-center text-sm font-medium text-neutral-700 hover:text-neutral-900 transition-colors"
        >
          <svg 
            class="w-4 h-4 mr-2 transform transition-transform"
            :class="{ 'rotate-90': showAdvancedOptions }"
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
          Advanced Options
        </button>

        <div v-show="showAdvancedOptions" class="mt-4 space-y-4">
          <!-- Interaction Settings -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <label class="flex items-center">
              <input
                v-model="formData.allow_comments"
                type="checkbox"
                class="sr-only"
                :disabled="isSubmitting"
              />
              <div class="toggle toggle-sm mr-2" :class="{ 'toggle-checked': formData.allow_comments }"></div>
              <span class="text-sm text-neutral-700">Allow Comments</span>
            </label>

            <label class="flex items-center">
              <input
                v-model="formData.allow_reactions"
                type="checkbox"
                class="sr-only"
                :disabled="isSubmitting"
              />
              <div class="toggle toggle-sm mr-2" :class="{ 'toggle-checked': formData.allow_reactions }"></div>
              <span class="text-sm text-neutral-700">Allow Reactions</span>
            </label>

            <label class="flex items-center">
              <input
                v-model="formData.allow_resharing"
                type="checkbox"
                class="sr-only"
                :disabled="isSubmitting"
              />
              <div class="toggle toggle-sm mr-2" :class="{ 'toggle-checked': formData.allow_resharing }"></div>
              <span class="text-sm text-neutral-700">Allow Sharing</span>
            </label>
          </div>

          <!-- Scheduled Publishing -->
          <div v-if="!editMode">
            <label class="flex items-center mb-2">
              <input
                v-model="enableScheduling"
                type="checkbox"
                class="sr-only"
                :disabled="isSubmitting"
              />
              <div class="toggle toggle-sm mr-2" :class="{ 'toggle-checked': enableScheduling }"></div>
              <span class="text-sm font-medium text-neutral-700">Schedule Post</span>
            </label>

            <div v-show="enableScheduling" class="mt-2">
              <input
                v-model="formData.scheduled_at"
                type="datetime-local"
                :min="minScheduleTime"
                :max="maxScheduleTime"
                class="input input-sm w-full max-w-xs"
                :disabled="isSubmitting"
              />
              <span v-if="errors.scheduled_at" class="text-xs text-red-500 mt-1 block">{{ errors.scheduled_at[0] }}</span>
            </div>
          </div>

          <!-- Tags -->
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-2">
              Tags
            </label>
            <TagInput
              v-model="formData.tags"
              :max-tags="20"
              :disabled="isSubmitting"
              placeholder="Add tags..."
            />
            <span v-if="errors.tags" class="text-xs text-red-500 mt-1">{{ errors.tags[0] }}</span>
          </div>

          <!-- Edit Reason (Edit Mode Only) -->
          <div v-if="editMode">
            <label class="block text-sm font-medium text-neutral-700 mb-2">
              Edit Reason (Optional)
            </label>
            <input
              v-model="editReason"
              type="text"
              maxlength="500"
              placeholder="Why are you editing this post?"
              class="input w-full"
              :disabled="isSubmitting"
            />
          </div>
        </div>
      </div>

      <!-- Form Actions -->
      <div class="flex items-center justify-between pt-4 border-t border-neutral-200">
        <div class="flex items-center space-x-2">
          <!-- Character Counter -->
          <span class="text-xs text-neutral-500">
            {{ contentLength }} characters
          </span>
          
          <!-- Draft Save Status -->
          <span v-if="draftSaved" class="text-xs text-green-600 flex items-center">
            <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
            </svg>
            Draft saved
          </span>
        </div>

        <div class="flex items-center space-x-3">
          <!-- Save Draft Button -->
          <button
            v-if="!editMode"
            type="button"
            @click="saveDraft"
            :disabled="isSubmitting || !hasContent"
            class="btn btn-ghost btn-sm"
          >
            Save Draft
          </button>

          <!-- Cancel Button -->
          <button
            v-if="onCancel"
            type="button"
            @click="onCancel"
            :disabled="isSubmitting"
            class="btn btn-secondary btn-sm"
          >
            Cancel
          </button>

          <!-- Submit Button -->
          <button
            type="submit"
            :disabled="isSubmitting || !isFormValid"
            class="btn btn-primary btn-sm"
          >
            <svg 
              v-if="isSubmitting"
              class="animate-spin -ml-1 mr-2 h-4 w-4" 
              fill="none" 
              viewBox="0 0 24 24"
            >
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            {{ getSubmitButtonText() }}
          </button>
        </div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { postService } from '@/services/postService'
import type { Post, CreatePostData, UpdatePostData, ValidationErrors } from '@/types/posts'

// Component imports
import PostTypeSelector from './PostTypeSelector.vue'
import RichTextEditor from './RichTextEditor.vue'
import MediaUploadComponent from './MediaUploadComponent.vue'
import VisibilitySelector from './VisibilitySelector.vue'
import TagInput from './TagInput.vue'
import BookReviewFields from './post-types/BookReviewFields.vue'
import LinkPostFields from './post-types/LinkPostFields.vue'
import PollFields from './post-types/PollFields.vue'

interface Props {
  editMode?: boolean
  post?: Post | null
  groupId?: number | null
  initialType?: string
  onClose?: () => void
  onCancel?: () => void
  onSuccess?: (post: Post) => void
}

const props = withDefaults(defineProps<Props>(), {
  editMode: false,
  post: null,
  groupId: null,
  initialType: 'text',
})

const emit = defineEmits<{
  success: [post: Post]
  cancel: []
  close: []
}>()

const authStore = useAuthStore()

// Form state
const formData = reactive<CreatePostData | UpdatePostData>({
  content: '',
  type: props.initialType || 'text',
  visibility: 'friends',
  metadata: {},
  media_ids: [],
  tags: [],
  allow_comments: true,
  allow_reactions: true,
  allow_resharing: true,
  custom_audience: [],
  group_id: props.groupId,
})

// UI state
const isSubmitting = ref(false)
const errorMessage = ref('')
const successMessage = ref('')
const errors = reactive<ValidationErrors>({})
const showAdvancedOptions = ref(false)
const enableScheduling = ref(false)
const editReason = ref('')
const draftSaved = ref(false)
const draftTimer = ref<NodeJS.Timeout | null>(null)

// Computed properties
const contentLength = computed(() => formData.content?.length || 0)

const hasContent = computed(() => {
  return formData.content?.trim() || 
         formData.media_ids?.length > 0 || 
         (formData.metadata && Object.keys(formData.metadata).length > 0)
})

const isFormValid = computed(() => {
  if (formData.type === 'text' && !formData.content?.trim()) return false
  if ((formData.type === 'image' || formData.type === 'video') && !formData.media_ids?.length) return false
  if (formData.type === 'link' && !formData.metadata?.link_url) return false
  if (formData.type === 'book_review' && !formData.metadata?.book_title) return false
  if (formData.type === 'poll' && (!formData.metadata?.poll_question || !formData.metadata?.poll_options?.length)) return false
  return true
})

const showMediaUpload = computed(() => {
  return ['image', 'video', 'text'].includes(formData.type)
})

const minScheduleTime = computed(() => {
  const now = new Date()
  now.setMinutes(now.getMinutes() + 5) // Minimum 5 minutes from now
  return now.toISOString().slice(0, 16)
})

const maxScheduleTime = computed(() => {
  const maxDate = new Date()
  maxDate.setFullYear(maxDate.getFullYear() + 1) // Maximum 1 year from now
  return maxDate.toISOString().slice(0, 16)
})

// Methods
const clearError = (field: string) => {
  if (errors[field]) {
    delete errors[field]
  }
  errorMessage.value = ''
}

const clearMetadataErrors = () => {
  Object.keys(errors).forEach(key => {
    if (key.startsWith('metadata.')) {
      delete errors[key]
    }
  })
}

const getContentPlaceholder = () => {
  switch (formData.type) {
    case 'book_review':
      return "Share your thoughts about this book..."
    case 'link':
      return "What do you think about this link?"
    case 'poll':
      return "Add additional context for your poll..."
    case 'image':
    case 'video':
      return "Add a caption for your media..."
    default:
      return "What's on your mind?"
  }
}

const getMediaType = () => {
  switch (formData.type) {
    case 'image':
      return 'images'
    case 'video':
      return 'videos'
    default:
      return 'mixed'
  }
}

const getMaxFiles = () => {
  switch (formData.type) {
    case 'image':
      return 10
    case 'video':
      return 1
    default:
      return 5
  }
}

const getTypeComponent = () => {
  switch (formData.type) {
    case 'book_review':
      return BookReviewFields
    case 'link':
      return LinkPostFields
    case 'poll':
      return PollFields
    default:
      return null
  }
}

const getSubmitButtonText = () => {
  if (isSubmitting.value) {
    return props.editMode ? 'Updating...' : 'Publishing...'
  }
  
  if (enableScheduling.value && formData.scheduled_at) {
    return 'Schedule Post'
  }
  
  return props.editMode ? 'Update Post' : 'Publish Post'
}

const handlePostTypeChange = (newType: string) => {
  // Clear metadata when type changes
  formData.metadata = {}
  
  // Clear media if not applicable to new type
  if (!['image', 'video', 'text'].includes(newType)) {
    formData.media_ids = []
  }
  
  // Set default visibility for group posts
  if (newType === 'group' && props.groupId) {
    formData.visibility = 'group'
    formData.group_id = props.groupId
  }
}

const handleMediaUpload = (uploadedMedia: any[]) => {
  const newMediaIds = uploadedMedia.map(media => media.id)
  formData.media_ids = [...(formData.media_ids || []), ...newMediaIds]
}

const handleMediaRemove = (mediaId: number) => {
  formData.media_ids = formData.media_ids?.filter(id => id !== mediaId) || []
}

const saveDraft = async () => {
  if (!hasContent.value) return
  
  try {
    // In a real implementation, this would save to localStorage or API
    localStorage.setItem('post_draft', JSON.stringify({
      ...formData,
      saved_at: new Date().toISOString()
    }))
    
    draftSaved.value = true
    setTimeout(() => {
      draftSaved.value = false
    }, 2000)
  } catch (error) {
    console.error('Failed to save draft:', error)
  }
}

const loadDraft = () => {
  try {
    const draft = localStorage.getItem('post_draft')
    if (draft && !props.editMode) {
      const draftData = JSON.parse(draft)
      Object.assign(formData, draftData)
    }
  } catch (error) {
    console.error('Failed to load draft:', error)
  }
}

const clearDraft = () => {
  localStorage.removeItem('post_draft')
}

const startAutoDraft = () => {
  if (draftTimer.value) {
    clearInterval(draftTimer.value)
  }
  
  draftTimer.value = setInterval(() => {
    if (hasContent.value && !isSubmitting.value) {
      saveDraft()
    }
  }, 30000) // Auto-save every 30 seconds
}

const stopAutoDraft = () => {
  if (draftTimer.value) {
    clearInterval(draftTimer.value)
    draftTimer.value = null
  }
}

const handleSubmit = async () => {
  clearError('general')
  Object.keys(errors).forEach(key => delete errors[key])
  
  if (!isFormValid.value) {
    errorMessage.value = 'Please fill in all required fields'
    return
  }
  
  isSubmitting.value = true
  
  try {
    let result
    
    if (props.editMode && props.post) {
      // Update existing post
      const updateData: UpdatePostData & { edit_reason?: string } = { ...formData }
      if (editReason.value) {
        updateData.edit_reason = editReason.value
      }
      
      result = await postService.updatePost(props.post.id, updateData)
    } else {
      // Create new post
      result = await postService.createPost(formData as CreatePostData)
    }
    
    if (result.success) {
      successMessage.value = result.message
      clearDraft()
      
      // Emit success event
      emit('success', result.data)
      
      if (props.onSuccess) {
        props.onSuccess(result.data)
      }
      
      // Reset form if not in edit mode
      if (!props.editMode) {
        resetForm()
      }
    } else {
      errorMessage.value = result.message || 'Failed to save post'
      if (result.errors) {
        Object.assign(errors, result.errors)
      }
    }
  } catch (error: any) {
    errorMessage.value = error.message || 'An unexpected error occurred'
    console.error('Post submission error:', error)
  } finally {
    isSubmitting.value = false
  }
}

const resetForm = () => {
  Object.assign(formData, {
    content: '',
    type: 'text',
    visibility: 'friends',
    metadata: {},
    media_ids: [],
    tags: [],
    allow_comments: true,
    allow_reactions: true,
    allow_resharing: true,
    custom_audience: [],
    group_id: props.groupId,
    scheduled_at: undefined,
  })
  
  enableScheduling.value = false
  editReason.value = ''
  showAdvancedOptions.value = false
  clearError('general')
  Object.keys(errors).forEach(key => delete errors[key])
}

const loadPostData = () => {
  if (props.editMode && props.post) {
    Object.assign(formData, {
      content: props.post.content || '',
      type: props.post.type || 'text',
      visibility: props.post.visibility || 'friends',
      metadata: props.post.metadata || {},
      media_ids: props.post.media_attachments?.map(m => m.id) || [],
      tags: props.post.tags || [],
      allow_comments: props.post.allow_comments ?? true,
      allow_reactions: props.post.allow_reactions ?? true,
      allow_resharing: props.post.allow_resharing ?? true,
      custom_audience: props.post.custom_audience || [],
      group_id: props.post.group_id,
    })
  }
}

// Watchers
watch(() => formData.content, () => {
  if (draftTimer.value === null && !props.editMode) {
    startAutoDraft()
  }
})

watch(() => props.post, () => {
  if (props.editMode && props.post) {
    loadPostData()
  }
}, { immediate: true })

// Lifecycle
onMounted(() => {
  if (!props.editMode) {
    loadDraft()
  } else {
    loadPostData()
  }
})

onUnmounted(() => {
  stopAutoDraft()
})
</script>

<style scoped>
.post-creation-form {
  @apply bg-white rounded-lg border border-neutral-200 p-6;
}

.toggle {
  @apply relative inline-block w-8 h-4 bg-neutral-300 rounded-full transition-colors cursor-pointer;
}

.toggle::after {
  @apply absolute top-0.5 left-0.5 w-3 h-3 bg-white rounded-full transition-transform;
  content: '';
}

.toggle-checked {
  @apply bg-primary-500;
}

.toggle-checked::after {
  @apply transform translate-x-4;
}

.toggle-sm {
  @apply w-6 h-3;
}

.toggle-sm::after {
  @apply w-2 h-2;
}

.toggle-sm.toggle-checked::after {
  @apply transform translate-x-3;
}
</style> 