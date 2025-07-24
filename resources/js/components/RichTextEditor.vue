<template>
  <div class="rich-text-editor" :class="{ 'rich-text-editor-error': error }">
    <!-- Toolbar -->
    <div v-if="showToolbar" class="editor-toolbar">
      <div class="toolbar-group">
        <!-- Basic Formatting -->
        <button
          v-for="action in basicActions"
          :key="action.command"
          type="button"
          :class="['toolbar-btn', { 'toolbar-btn-active': isFormatActive(action.command) }]"
          :title="action.title"
          @click="execCommand(action.command, action.value)"
          :disabled="disabled"
        >
          <component :is="action.icon" />
        </button>
      </div>

      <div class="toolbar-divider"></div>

      <div class="toolbar-group">
        <!-- Lists -->
        <button
          v-for="list in listActions"
          :key="list.command"
          type="button"
          :class="['toolbar-btn', { 'toolbar-btn-active': isFormatActive(list.command) }]"
          :title="list.title"
          @click="execCommand(list.command)"
          :disabled="disabled"
        >
          <component :is="list.icon" />
        </button>
      </div>

      <div class="toolbar-divider"></div>

      <div class="toolbar-group">
        <!-- Special Actions -->
        <button
          type="button"
          class="toolbar-btn"
          title="Add emoji"
          @click="toggleEmojiPicker"
          :disabled="disabled"
        >
          😀
        </button>
        
        <button
          type="button"
          class="toolbar-btn"
          title="Add mention"
          @click="insertMention"
          :disabled="disabled"
        >
          @
        </button>
        
        <button
          type="button"
          class="toolbar-btn"
          title="Add hashtag"
          @click="insertHashtag"
          :disabled="disabled"
        >
          #
        </button>
      </div>
    </div>

    <!-- Editor Content -->
    <div class="editor-container">
      <div
        ref="editorElement"
        class="editor-content"
        :class="{ 'editor-disabled': disabled }"
        contenteditable="true"
        :data-placeholder="placeholder"
        @input="handleInput"
        @keydown="handleKeydown"
        @paste="handlePaste"
        @focus="handleFocus"
        @blur="handleBlur"
        v-html="formattedContent"
      ></div>
      
      <!-- Character Count -->
      <div v-if="maxLength" class="character-count">
        <span :class="{ 'text-red-500': modelValue.length > maxLength }">
          {{ modelValue.length }}
        </span>
        <span class="text-neutral-400">/ {{ maxLength }}</span>
      </div>
    </div>

    <!-- Emoji Picker -->
    <div v-if="showEmojiPicker" class="emoji-picker">
      <div class="emoji-grid">
        <button
          v-for="emoji in commonEmojis"
          :key="emoji"
          type="button"
          class="emoji-btn"
          @click="insertEmoji(emoji)"
        >
          {{ emoji }}
        </button>
      </div>
    </div>

    <!-- Mention Suggestions -->
    <div v-if="showMentionSuggestions" class="mention-suggestions">
      <div class="suggestion-header">
        <span class="text-xs font-medium text-neutral-600">Mention someone</span>
      </div>
      <div class="suggestion-list">
        <button
          v-for="(user, index) in mentionSuggestions"
          :key="user.id"
          type="button"
          :class="['suggestion-item', { 'suggestion-active': index === selectedMentionIndex }]"
          @click="selectMention(user)"
        >
          <img :src="user.avatar_url" :alt="user.name" class="suggestion-avatar" />
          <div class="suggestion-info">
            <div class="suggestion-name">{{ user.name }}</div>
            <div class="suggestion-username">@{{ user.username }}</div>
          </div>
        </button>
      </div>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="error-message">
      {{ error }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted, defineComponent, h } from 'vue'
import { useAuthStore } from '@/stores/auth'

interface Props {
  modelValue: string
  placeholder?: string
  maxLength?: number
  disabled?: boolean
  error?: string
  showToolbar?: boolean
}

interface User {
  id: number
  name: string
  username: string
  avatar_url: string
}

const props = withDefaults(defineProps<Props>(), {
  placeholder: 'What\'s on your mind?',
  disabled: false,
  showToolbar: true,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  input: [value: string]
  focus: [event: FocusEvent]
  blur: [event: FocusEvent]
}>()

const authStore = useAuthStore()

// Refs
const editorElement = ref<HTMLElement>()
const showEmojiPicker = ref(false)
const showMentionSuggestions = ref(false)
const mentionSuggestions = ref<User[]>([])
const selectedMentionIndex = ref(0)
const currentMentionText = ref('')
const isFocused = ref(false)

// Icon components
const BoldIcon = defineComponent(() => () => h('svg', { class: 'w-4 h-4', viewBox: '0 0 24 24', fill: 'currentColor' }, [
  h('path', { d: 'M15.6 10.79c.97-.67 1.65-1.77 1.65-2.79 0-2.26-1.75-4-4-4H7v14h7.04c2.09 0 3.71-1.7 3.71-3.79 0-1.52-.86-2.82-2.15-3.42zM10 6.5h3c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-3v-3zm3.5 9H10v-3h3.5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5z' })
]))

const ItalicIcon = defineComponent(() => () => h('svg', { class: 'w-4 h-4', viewBox: '0 0 24 24', fill: 'currentColor' }, [
  h('path', { d: 'M10 4v3h2.21l-3.42 8H6v3h8v-3h-2.21l3.42-8H18V4h-8z' })
]))

const UnderlineIcon = defineComponent(() => () => h('svg', { class: 'w-4 h-4', viewBox: '0 0 24 24', fill: 'currentColor' }, [
  h('path', { d: 'M12 17c3.31 0 6-2.69 6-6V3h-2.5v8c0 1.93-1.57 3.5-3.5 3.5S8.5 12.93 8.5 11V3H6v8c0 3.31 2.69 6 6 6zm-7 2v2h14v-2H5z' })
]))

const ListIcon = defineComponent(() => () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M4 6h16M4 10h16M4 14h16M4 18h16' })
]))

const OrderedListIcon = defineComponent(() => () => h('svg', { class: 'w-4 h-4', fill: 'none', stroke: 'currentColor', viewBox: '0 0 24 24' }, [
  h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M7 7h10M7 12h4m6 0h-6m6 5H7m0-5h10' })
]))

// Toolbar actions
const basicActions = [
  { command: 'bold', title: 'Bold', icon: BoldIcon },
  { command: 'italic', title: 'Italic', icon: ItalicIcon },
  { command: 'underline', title: 'Underline', icon: UnderlineIcon },
]

const listActions = [
  { command: 'insertUnorderedList', title: 'Bullet List', icon: ListIcon },
  { command: 'insertOrderedList', title: 'Numbered List', icon: OrderedListIcon },
]

// Common emojis
const commonEmojis = [
  '😀', '😃', '😄', '😁', '😆', '😅', '😂', '🤣',
  '😊', '😇', '🙂', '🙃', '😉', '😌', '😍', '🥰',
  '😘', '😗', '😙', '😚', '😋', '😛', '😝', '😜',
  '🤪', '🤨', '🧐', '🤓', '😎', '🤩', '🥳', '😏',
  '❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍',
  '👍', '👎', '👏', '🙌', '👌', '🤞', '✌️', '🤟',
  '📚', '📖', '📝', '✍️', '📔', '📕', '📗', '📘',
]

// Computed
const formattedContent = computed(() => {
  let content = props.modelValue
  
  // Format mentions
  content = content.replace(/@(\w+)/g, '<span class="mention">@$1</span>')
  
  // Format hashtags
  content = content.replace(/#(\w+)/g, '<span class="hashtag">#$1</span>')
  
  return content
})

// Methods
const handleInput = (event: Event) => {
  const target = event.target as HTMLElement
  const text = target.innerText || ''
  
  emit('update:modelValue', text)
  emit('input', text)
  
  checkForMentions(text)
  checkForHashtags(text)
}

const handleKeydown = (event: KeyboardEvent) => {
  if (props.disabled) {
    event.preventDefault()
    return
  }

  // Handle max length
  if (props.maxLength && props.modelValue.length >= props.maxLength) {
    if (event.key !== 'Backspace' && event.key !== 'Delete' && !event.ctrlKey && !event.metaKey) {
      event.preventDefault()
      return
    }
  }

  // Handle mention suggestions navigation
  if (showMentionSuggestions.value) {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      selectedMentionIndex.value = Math.min(selectedMentionIndex.value + 1, mentionSuggestions.value.length - 1)
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      selectedMentionIndex.value = Math.max(selectedMentionIndex.value - 1, 0)
    } else if (event.key === 'Enter') {
      event.preventDefault()
      if (mentionSuggestions.value[selectedMentionIndex.value]) {
        selectMention(mentionSuggestions.value[selectedMentionIndex.value])
      }
    } else if (event.key === 'Escape') {
      event.preventDefault()
      closeMentionSuggestions()
    }
  }

  // Handle toolbar shortcuts
  if (event.ctrlKey || event.metaKey) {
    switch (event.key) {
      case 'b':
        event.preventDefault()
        execCommand('bold')
        break
      case 'i':
        event.preventDefault()
        execCommand('italic')
        break
      case 'u':
        event.preventDefault()
        execCommand('underline')
        break
    }
  }
}

const handlePaste = (event: ClipboardEvent) => {
  if (props.disabled) {
    event.preventDefault()
    return
  }

  // Get plain text from clipboard
  event.preventDefault()
  const text = event.clipboardData?.getData('text/plain') || ''
  
  // Check max length
  if (props.maxLength && (props.modelValue.length + text.length) > props.maxLength) {
    const remainingChars = props.maxLength - props.modelValue.length
    const truncatedText = text.slice(0, remainingChars)
    document.execCommand('insertText', false, truncatedText)
  } else {
    document.execCommand('insertText', false, text)
  }
}

const handleFocus = (event: FocusEvent) => {
  isFocused.value = true
  emit('focus', event)
}

const handleBlur = (event: FocusEvent) => {
  isFocused.value = false
  emit('blur', event)
  
  // Close pickers when losing focus
  setTimeout(() => {
    if (!isFocused.value) {
      showEmojiPicker.value = false
      closeMentionSuggestions()
    }
  }, 200)
}

const execCommand = (command: string, value?: string) => {
  if (props.disabled) return
  
  editorElement.value?.focus()
  document.execCommand(command, false, value)
  
  // Update model value after command
  const text = editorElement.value?.innerText || ''
  emit('update:modelValue', text)
  emit('input', text)
}

const isFormatActive = (command: string): boolean => {
  if (!isFocused.value) return false
  return document.queryCommandState(command)
}

const toggleEmojiPicker = () => {
  if (props.disabled) return
  showEmojiPicker.value = !showEmojiPicker.value
}

const insertEmoji = (emoji: string) => {
  if (props.disabled) return
  
  execCommand('insertText', emoji)
  showEmojiPicker.value = false
  editorElement.value?.focus()
}

const insertMention = () => {
  if (props.disabled) return
  
  execCommand('insertText', '@')
  editorElement.value?.focus()
}

const insertHashtag = () => {
  if (props.disabled) return
  
  execCommand('insertText', '#')
  editorElement.value?.focus()
}

const checkForMentions = async (text: string) => {
  const mentionMatch = text.match(/@(\w*)$/)
  
  if (mentionMatch) {
    const searchTerm = mentionMatch[1]
    currentMentionText.value = searchTerm
    
    if (searchTerm.length >= 1) {
      // In a real app, this would call an API to search users
      // For now, we'll use mock data
      mentionSuggestions.value = getMockUsers().filter(user => 
        user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        user.username.toLowerCase().includes(searchTerm.toLowerCase())
      ).slice(0, 5)
      
      if (mentionSuggestions.value.length > 0) {
        showMentionSuggestions.value = true
        selectedMentionIndex.value = 0
      } else {
        closeMentionSuggestions()
      }
    } else {
      closeMentionSuggestions()
    }
  } else {
    closeMentionSuggestions()
  }
}

const checkForHashtags = (text: string) => {
  // Future: Could implement hashtag suggestions here
}

const selectMention = (user: User) => {
  if (props.disabled) return
  
  const currentText = props.modelValue
  const mentionPattern = new RegExp(`@${currentMentionText.value}$`)
  const newText = currentText.replace(mentionPattern, `@${user.username} `)
  
  emit('update:modelValue', newText)
  emit('input', newText)
  
  closeMentionSuggestions()
  editorElement.value?.focus()
  
  // Move cursor to end
  nextTick(() => {
    if (editorElement.value) {
      const range = document.createRange()
      const selection = window.getSelection()
      range.selectNodeContents(editorElement.value)
      range.collapse(false)
      selection?.removeAllRanges()
      selection?.addRange(range)
    }
  })
}

const closeMentionSuggestions = () => {
  showMentionSuggestions.value = false
  mentionSuggestions.value = []
  currentMentionText.value = ''
  selectedMentionIndex.value = 0
}

const getMockUsers = (): User[] => {
  // Mock user data for development
  return [
    { id: 1, name: 'John Doe', username: 'johndoe', avatar_url: '/api/placeholder/32/32' },
    { id: 2, name: 'Jane Smith', username: 'janesmith', avatar_url: '/api/placeholder/32/32' },
    { id: 3, name: 'Book Lover', username: 'booklover', avatar_url: '/api/placeholder/32/32' },
    { id: 4, name: 'Reading Fan', username: 'readingfan', avatar_url: '/api/placeholder/32/32' },
    { id: 5, name: 'Story Teller', username: 'storyteller', avatar_url: '/api/placeholder/32/32' },
  ]
}

// Watchers
watch(() => props.modelValue, (newValue) => {
  if (editorElement.value && editorElement.value.innerText !== newValue) {
    editorElement.value.innerText = newValue
  }
})

// Lifecycle
onMounted(() => {
  if (editorElement.value) {
    editorElement.value.innerText = props.modelValue
  }
})

// Click outside handler
const handleClickOutside = (event: Event) => {
  const target = event.target as Element
  if (!target.closest('.rich-text-editor')) {
    showEmojiPicker.value = false
    closeMentionSuggestions()
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<style scoped>
.rich-text-editor {
  @apply relative;
}

.rich-text-editor-error {
  @apply ring-2 ring-red-500 ring-opacity-20 rounded-lg;
}

.editor-toolbar {
  @apply flex items-center gap-1 p-2 bg-neutral-50 border border-neutral-200 border-b-0 rounded-t-lg;
}

.toolbar-group {
  @apply flex items-center gap-1;
}

.toolbar-divider {
  @apply w-px h-6 bg-neutral-300 mx-2;
}

.toolbar-btn {
  @apply p-2 text-neutral-600 hover:text-neutral-900 hover:bg-white rounded transition-colors;
}

.toolbar-btn:disabled {
  @apply opacity-50 cursor-not-allowed;
}

.toolbar-btn-active {
  @apply text-primary-600 bg-primary-100;
}

.editor-container {
  @apply relative;
}

.editor-content {
  @apply min-h-[120px] p-4 border border-neutral-200 rounded-b-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-opacity-20 focus:border-primary-500 resize-none;
}

.editor-content[data-placeholder]:empty::before {
  content: attr(data-placeholder);
  @apply text-neutral-400;
}

.editor-disabled {
  @apply bg-neutral-50 cursor-not-allowed;
}

.character-count {
  @apply absolute bottom-2 right-2 text-xs text-neutral-500;
}

.emoji-picker {
  @apply absolute top-full left-0 z-10 bg-white border border-neutral-200 rounded-lg shadow-lg p-3 mt-1;
  width: 280px;
}

.emoji-grid {
  @apply grid grid-cols-8 gap-1;
}

.emoji-btn {
  @apply p-2 hover:bg-neutral-100 rounded text-lg transition-colors;
}

.mention-suggestions {
  @apply absolute top-full left-0 z-10 bg-white border border-neutral-200 rounded-lg shadow-lg mt-1 w-64;
}

.suggestion-header {
  @apply px-3 py-2 border-b border-neutral-200;
}

.suggestion-list {
  @apply max-h-48 overflow-y-auto;
}

.suggestion-item {
  @apply flex items-center p-3 hover:bg-neutral-50 transition-colors w-full text-left;
}

.suggestion-active {
  @apply bg-primary-50;
}

.suggestion-avatar {
  @apply w-8 h-8 rounded-full mr-3;
}

.suggestion-info {
  @apply flex-1 min-w-0;
}

.suggestion-name {
  @apply text-sm font-medium text-neutral-900 truncate;
}

.suggestion-username {
  @apply text-xs text-neutral-500;
}

.error-message {
  @apply mt-1 text-sm text-red-600;
}

/* Content formatting styles */
:deep(.mention) {
  @apply text-primary-600 font-medium;
}

:deep(.hashtag) {
  @apply text-blue-600 font-medium;
}
</style> 