<template>
  <div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Poll Details</h3>
    
    <!-- Poll Question -->
    <div>
      <label for="poll-question" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Poll Question *
      </label>
      <input
        id="poll-question"
        type="text"
        :value="metadata.poll_question"
        @input="updateField('poll_question', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="What's your question?"
        required
      />
    </div>

    <!-- Poll Options -->
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Options *
      </label>
      <div class="space-y-2">
        <div
          v-for="(option, index) in pollOptions"
          :key="index"
          class="flex items-center space-x-2"
        >
          <input
            :value="option.text"
            @input="updateOption(index, ($event.target as HTMLInputElement).value)"
            class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
            :placeholder="`Option ${index + 1}`"
            required
          />
          <button
            v-if="pollOptions.length > 2"
            @click="removeOption(index)"
            class="p-2 text-gray-400 hover:text-red-500 transition-colors"
            type="button"
          >
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
      </div>
      
      <button
        v-if="pollOptions.length < 10"
        @click="addOption"
        class="mt-2 inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-600"
        type="button"
      >
        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd" />
        </svg>
        Add Option
      </button>
    </div>

    <!-- Poll Type -->
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Poll Type *
      </label>
      <div class="space-y-2">
        <label class="flex items-center">
          <input
            type="radio"
            value="single"
            :checked="metadata.poll_type === 'single'"
            @change="updateField('poll_type', 'single')"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
          />
          <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Single choice (users can select only one option)</span>
        </label>
        <label class="flex items-center">
          <input
            type="radio"
            value="multiple"
            :checked="metadata.poll_type === 'multiple'"
            @change="updateField('poll_type', 'multiple')"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
          />
          <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Multiple choice (users can select multiple options)</span>
        </label>
      </div>
    </div>

    <!-- Poll Duration -->
    <div>
      <label for="poll-duration" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Poll Duration
      </label>
      <select
        id="poll-duration"
        :value="pollDuration"
        @change="updateDuration(($event.target as HTMLSelectElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
      >
        <option value="">Never (poll stays open indefinitely)</option>
        <option value="1h">1 hour</option>
        <option value="6h">6 hours</option>
        <option value="1d">1 day</option>
        <option value="3d">3 days</option>
        <option value="1w">1 week</option>
        <option value="custom">Custom date/time</option>
      </select>
    </div>

    <!-- Custom End Date -->
    <div v-if="pollDuration === 'custom'">
      <label for="poll-end-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Poll End Date & Time
      </label>
      <input
        id="poll-end-date"
        type="datetime-local"
        :value="formatDateTimeLocal(metadata.poll_ends_at)"
        @input="updateField('poll_ends_at', ($event.target as HTMLInputElement).value)"
        :min="minDateTime"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
      />
    </div>

    <!-- Poll Settings -->
    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
      <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Poll Settings</h4>
      
      <div class="space-y-3">
        <label class="flex items-center">
          <input
            type="checkbox"
            :checked="metadata.allow_add_options"
            @change="updateField('allow_add_options', ($event.target as HTMLInputElement).checked)"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
          />
          <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Allow users to add their own options</span>
        </label>
        
        <label class="flex items-center">
          <input
            type="checkbox"
            :checked="metadata.show_results_before_voting"
            @change="updateField('show_results_before_voting', ($event.target as HTMLInputElement).checked)"
            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
          />
          <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Show results before voting</span>
        </label>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PollMetadata } from '@/types/posts'

interface Props {
  modelValue: Partial<PollMetadata>
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => ({
    poll_question: '',
    poll_options: [{ text: '' }, { text: '' }],
    poll_type: 'single'
  })
})

const emit = defineEmits<{
  'update:modelValue': [value: Partial<PollMetadata>]
}>()

const metadata = computed(() => props.modelValue)
const pollDuration = ref('')

const pollOptions = computed(() => {
  return metadata.value.poll_options || [{ text: '' }, { text: '' }]
})

const minDateTime = computed(() => {
  const now = new Date()
  now.setMinutes(now.getMinutes() + 5) // Minimum 5 minutes from now
  return now.toISOString().slice(0, 16)
})

const updateField = (field: keyof PollMetadata, value: any) => {
  const updated = { ...metadata.value, [field]: value }
  if (value === '' || value === undefined || value === false) {
    delete updated[field]
  }
  emit('update:modelValue', updated)
}

const updateOption = (index: number, text: string) => {
  const options = [...pollOptions.value]
  options[index] = { ...options[index], text }
  updateField('poll_options', options)
}

const addOption = () => {
  const options = [...pollOptions.value, { text: '' }]
  updateField('poll_options', options)
}

const removeOption = (index: number) => {
  const options = pollOptions.value.filter((_, i) => i !== index)
  updateField('poll_options', options)
}

const updateDuration = (duration: string) => {
  pollDuration.value = duration
  
  if (duration === '' || duration === 'custom') {
    if (duration === '') {
      updateField('poll_ends_at', undefined)
    }
    return
  }
  
  const now = new Date()
  let endDate: Date
  
  switch (duration) {
    case '1h':
      endDate = new Date(now.getTime() + 60 * 60 * 1000)
      break
    case '6h':
      endDate = new Date(now.getTime() + 6 * 60 * 60 * 1000)
      break
    case '1d':
      endDate = new Date(now.getTime() + 24 * 60 * 60 * 1000)
      break
    case '3d':
      endDate = new Date(now.getTime() + 3 * 24 * 60 * 60 * 1000)
      break
    case '1w':
      endDate = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000)
      break
    default:
      return
  }
  
  updateField('poll_ends_at', endDate.toISOString())
}

const formatDateTimeLocal = (isoString?: string) => {
  if (!isoString) return ''
  return new Date(isoString).toISOString().slice(0, 16)
}

// Initialize poll duration based on existing end date
onMounted(() => {
  if (metadata.value.poll_ends_at) {
    pollDuration.value = 'custom'
  }
})
</script> 