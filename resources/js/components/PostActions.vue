<template>
  <div class="flex items-center justify-between pt-4 border-t border-gray-200">
    <!-- Left side actions -->
    <div class="flex items-center space-x-4 sm:space-x-6">
      <!-- Like button -->
      <button
        @click="handleLike"
        :disabled="actionsLoading.like"
        class="flex items-center space-x-2 text-gray-600 hover:text-red-600 transition-colors duration-200 group"
        :class="{ 'text-red-600': post.liked }"
      >
        <HeartIcon 
          class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"
          :class="{ 'fill-current': post.liked }"
        />
        <span class="text-sm font-medium hidden sm:inline">
          {{ post.likes_count || 0 }}
        </span>
      </button>

      <!-- Comment button -->
      <button
        @click="handleComment"
        class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition-colors duration-200 group"
      >
        <ChatBubbleLeftIcon class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" />
        <span class="text-sm font-medium hidden sm:inline">
          {{ post.comments_count || 0 }}
        </span>
      </button>

      <!-- Share button -->
      <button
        @click="handleShare"
        :disabled="actionsLoading.share"
        class="flex items-center space-x-2 text-gray-600 hover:text-green-600 transition-colors duration-200 group"
      >
        <ArrowTopRightOnSquareIcon class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" />
        <span class="text-sm font-medium hidden sm:inline">
          {{ post.shares_count || 0 }}
        </span>
      </button>
    </div>

    <!-- Right side actions -->
    <div class="flex items-center space-x-2">
      <!-- Bookmark button -->
      <button
        @click="handleBookmark"
        :disabled="actionsLoading.bookmark"
        class="p-2 text-gray-600 hover:text-yellow-600 transition-colors duration-200 group"
        :class="{ 'text-yellow-600': post.bookmarked }"
      >
        <BookmarkIcon 
          class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"
          :class="{ 'fill-current': post.bookmarked }"
        />
      </button>

      <!-- More options -->
      <div class="relative" ref="dropdownRef">
        <button
          @click="showDropdown = !showDropdown"
          class="p-2 text-gray-600 hover:text-gray-800 transition-colors duration-200"
        >
          <EllipsisHorizontalIcon class="w-5 h-5" />
        </button>

        <!-- Dropdown menu -->
        <div
          v-if="showDropdown"
          class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
        >
          <button
            v-if="canEdit"
            @click="handleEdit"
            class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-2"
          >
            <PencilIcon class="w-4 h-4" />
            <span>Edit Post</span>
          </button>
          
          <button
            @click="handleCopyLink"
            class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-2"
          >
            <LinkIcon class="w-4 h-4" />
            <span>Copy Link</span>
          </button>
          
          <button
            @click="handleReport"
            class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center space-x-2"
          >
            <FlagIcon class="w-4 h-4" />
            <span>Report Post</span>
          </button>
          
          <button
            v-if="canDelete"
            @click="handleDelete"
            class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center space-x-2"
          >
            <TrashIcon class="w-4 h-4" />
            <span>Delete Post</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Share Modal -->
  <ShareModal
    v-if="showShareModal"
    :post="post"
    @close="showShareModal = false"
    @shared="onShared"
  />
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import {
  HeartIcon,
  ChatBubbleLeftIcon,
  ArrowTopRightOnSquareIcon,
  BookmarkIcon,
  EllipsisHorizontalIcon,
  PencilIcon,
  LinkIcon,
  FlagIcon,
  TrashIcon
} from '@heroicons/vue/24/outline'
import type { Post } from '@/types/posts'
import { useAuthStore } from '@/stores/auth'
import ShareModal from './ShareModal.vue'

interface Props {
  post: Post
}

interface Emits {
  (e: 'like', postId: number): void
  (e: 'comment', postId: number): void
  (e: 'share', postId: number): void
  (e: 'bookmark', postId: number): void
  (e: 'edit', post: Post): void
  (e: 'delete', postId: number): void
  (e: 'report', postId: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const authStore = useAuthStore()
const dropdownRef = ref<HTMLElement>()
const showDropdown = ref(false)
const showShareModal = ref(false)

const actionsLoading = ref({
  like: false,
  share: false,
  bookmark: false
})

const canEdit = computed(() => {
  return authStore.user?.id === props.post.user_id
})

const canDelete = computed(() => {
  return authStore.user?.id === props.post.user_id || authStore.user?.role === 'admin'
})

const handleLike = async () => {
  if (actionsLoading.value.like) return
  
  actionsLoading.value.like = true
  try {
    emit('like', props.post.id)
  } finally {
    actionsLoading.value.like = false
  }
}

const handleComment = () => {
  emit('comment', props.post.id)
}

const handleShare = async () => {
  if (actionsLoading.value.share) return
  
  showShareModal.value = true
}

const handleBookmark = async () => {
  if (actionsLoading.value.bookmark) return
  
  actionsLoading.value.bookmark = true
  try {
    emit('bookmark', props.post.id)
  } finally {
    actionsLoading.value.bookmark = false
  }
}

const handleEdit = () => {
  showDropdown.value = false
  emit('edit', props.post)
}

const handleCopyLink = async () => {
  showDropdown.value = false
  const url = `${window.location.origin}/posts/${props.post.id}`
  
  try {
    await navigator.clipboard.writeText(url)
    // Show success notification (you can implement this)
    console.log('Link copied to clipboard')
  } catch (err) {
    console.error('Failed to copy link:', err)
  }
}

const handleReport = () => {
  showDropdown.value = false
  emit('report', props.post.id)
}

const handleDelete = () => {
  showDropdown.value = false
  if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
    emit('delete', props.post.id)
  }
}

const onShared = () => {
  showShareModal.value = false
  emit('share', props.post.id)
}

const handleClickOutside = (event: Event) => {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    showDropdown.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script> 