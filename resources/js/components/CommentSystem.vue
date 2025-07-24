<template>
  <div class="comment-system">
    <!-- Comments header -->
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold text-gray-900">
        Comments ({{ totalComments }})
      </h3>
      
      <div class="flex items-center space-x-2">
        <!-- Sort options -->
        <select
          v-model="sortBy"
          @change="loadComments"
          class="text-sm border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
        >
          <option value="newest">Newest</option>
          <option value="oldest">Oldest</option>
          <option value="popular">Most Popular</option>
        </select>
      </div>
    </div>

    <!-- Comment form -->
    <div v-if="canComment" class="mb-6">
      <CommentForm
        :post-id="postId"
        :parent-id="null"
        :placeholder="'Write a comment...'"
        @submit="handleCommentSubmit"
        @cancel="() => {}"
      />
    </div>

    <!-- Comments list -->
    <div v-if="isLoading && comments.length === 0" class="space-y-4">
      <div v-for="i in 3" :key="i" class="animate-pulse">
        <div class="flex space-x-3">
          <div class="w-8 h-8 bg-gray-200 rounded-full"></div>
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
            <div class="h-3 bg-gray-200 rounded w-3/4"></div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="comments.length === 0" class="text-center py-8">
      <ChatBubbleLeftIcon class="w-12 h-12 mx-auto text-gray-300 mb-4" />
      <p class="text-gray-500">No comments yet. Be the first to comment!</p>
    </div>

    <div v-else class="space-y-4">
      <CommentItem
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :post-id="postId"
        :can-reply="canComment"
        :max-depth="maxDepth"
        @like="handleCommentLike"
        @reply="handleCommentReply"
        @edit="handleCommentEdit"
        @delete="handleCommentDelete"
        @load-replies="handleLoadReplies"
      />
    </div>

    <!-- Load more -->
    <div v-if="hasMore" class="mt-6 text-center">
      <button
        @click="loadMore"
        :disabled="isLoadingMore"
        class="px-4 py-2 text-blue-600 hover:text-blue-800 disabled:opacity-50"
      >
        <span v-if="isLoadingMore">Loading...</span>
        <span v-else>Load more comments</span>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { ChatBubbleLeftIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '@/stores/auth'
import { commentService } from '@/services/commentService'
import type { Comment, CreateCommentData } from '@/types/comments'
import CommentItem from './CommentItem.vue'
import CommentForm from './CommentForm.vue'

interface Props {
  postId: number
  allowComments?: boolean
  maxDepth?: number
}

interface Emits {
  (e: 'comment-added', comment: Comment): void
  (e: 'comment-deleted', commentId: number): void
  (e: 'comments-loaded', count: number): void
}

const props = withDefaults(defineProps<Props>(), {
  allowComments: true,
  maxDepth: 3
})

const emit = defineEmits<Emits>()

const authStore = useAuthStore()

// State
const comments = ref<Comment[]>([])
const isLoading = ref(false)
const isLoadingMore = ref(false)
const hasMore = ref(true)
const totalComments = ref(0)
const sortBy = ref('newest')
const currentPage = ref(1)

// Computed
const canComment = computed(() => {
  return authStore.isAuthenticated && props.allowComments
})

// Methods
const loadComments = async (reset = true) => {
  if (reset) {
    isLoading.value = true
    currentPage.value = 1
    comments.value = []
  } else {
    isLoadingMore.value = true
  }

  try {
    const response = await commentService.getComments(props.postId, {
      sort: sortBy.value,
      page: currentPage.value,
      include_replies: true,
      depth: props.maxDepth
    })

    if (reset) {
      comments.value = response.data
    } else {
      comments.value.push(...response.data)
    }

    totalComments.value = response.meta.total
    hasMore.value = response.meta.has_more
    
    emit('comments-loaded', totalComments.value)
  } catch (error) {
    console.error('Failed to load comments:', error)
  } finally {
    isLoading.value = false
    isLoadingMore.value = false
  }
}

const loadMore = async () => {
  if (hasMore.value && !isLoadingMore.value) {
    currentPage.value++
    await loadComments(false)
  }
}

const handleCommentSubmit = async (commentData: CreateCommentData) => {
  try {
    const newComment = await commentService.createComment(props.postId, commentData)
    
    // Add to the beginning of the list if sorting by newest
    if (sortBy.value === 'newest') {
      comments.value.unshift(newComment)
    } else {
      // Reload to maintain sort order
      await loadComments()
    }
    
    totalComments.value++
    emit('comment-added', newComment)
  } catch (error) {
    console.error('Failed to create comment:', error)
    throw error
  }
}

const handleCommentLike = async (comment: Comment) => {
  try {
    await commentService.toggleLike(props.postId, comment.id)
    
    // Update comment in the list
    const index = findCommentIndex(comment.id)
    if (index !== -1) {
      const updatedComment = { ...comments.value[index] }
      updatedComment.liked = !updatedComment.liked
      updatedComment.likes_count += updatedComment.liked ? 1 : -1
      comments.value[index] = updatedComment
    }
  } catch (error) {
    console.error('Failed to toggle comment like:', error)
  }
}

const handleCommentReply = (parentComment: Comment, replyData: CreateCommentData) => {
  // This is handled by the CommentItem component
  // The reply will be added to the parent comment's replies
}

const handleCommentEdit = async (comment: Comment, newContent: string) => {
  try {
    const updatedComment = await commentService.updateComment(props.postId, comment.id, {
      content: newContent
    })
    
    // Update comment in the list
    const index = findCommentIndex(comment.id)
    if (index !== -1) {
      comments.value[index] = updatedComment
    }
  } catch (error) {
    console.error('Failed to edit comment:', error)
    throw error
  }
}

const handleCommentDelete = async (comment: Comment) => {
  if (!confirm('Are you sure you want to delete this comment?')) {
    return
  }

  try {
    await commentService.deleteComment(props.postId, comment.id)
    
    // Remove comment from the list
    removeCommentFromList(comment.id)
    totalComments.value--
    
    emit('comment-deleted', comment.id)
  } catch (error) {
    console.error('Failed to delete comment:', error)
  }
}

const handleLoadReplies = async (comment: Comment) => {
  try {
    const response = await commentService.getReplies(props.postId, comment.id)
    
    // Update comment with replies
    const index = findCommentIndex(comment.id)
    if (index !== -1) {
      comments.value[index] = {
        ...comments.value[index],
        replies: response.data,
        replies_loaded: true
      }
    }
  } catch (error) {
    console.error('Failed to load replies:', error)
  }
}

// Helper functions
const findCommentIndex = (commentId: number): number => {
  return comments.value.findIndex(c => c.id === commentId)
}

const removeCommentFromList = (commentId: number) => {
  const index = findCommentIndex(commentId)
  if (index !== -1) {
    comments.value.splice(index, 1)
  }
}

// Lifecycle
onMounted(() => {
  loadComments()
})

// Watch for sort changes
watch(sortBy, () => {
  loadComments()
})
</script>

<style scoped>
.comment-system {
  @apply max-w-none;
}
</style> 