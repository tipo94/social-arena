<template>
  <div class="feed-display">
    <!-- Feed type selector -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-semibold text-gray-900">Feed</h2>
        <button
          @click="handleRefresh"
          :disabled="feedStore.refreshing"
          class="p-2 text-gray-600 hover:text-gray-800 transition-colors rounded-lg hover:bg-gray-100"
          :class="{ 'animate-spin': feedStore.refreshing }"
        >
          <ArrowPathIcon class="w-5 h-5" />
        </button>
      </div>
      
      <!-- Feed type tabs -->
      <div class="flex space-x-1 bg-gray-100 rounded-lg p-1">
        <button
          v-for="type in feedTypes"
          :key="type.value"
          @click="switchFeedType(type.value)"
          class="flex-1 px-3 py-2 text-sm font-medium rounded-md transition-colors"
          :class="feedStore.feedType === type.value 
            ? 'bg-white text-blue-600 shadow-sm' 
            : 'text-gray-600 hover:text-gray-900'"
        >
          {{ type.label }}
        </button>
      </div>
    </div>

    <!-- Error state -->
    <div v-if="feedStore.error" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
      <div class="flex items-center space-x-2">
        <ExclamationTriangleIcon class="w-5 h-5 text-red-600" />
        <span class="text-red-800 font-medium">Error loading feed</span>
      </div>
      <p class="text-red-700 mt-1">{{ feedStore.error }}</p>
      <button
        @click="handleRefresh"
        class="mt-3 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors"
      >
        Try Again
      </button>
    </div>

    <!-- Empty state -->
    <div v-else-if="feedStore.isEmpty && !feedStore.isLoadingInitial" class="text-center py-12">
      <div class="max-w-sm mx-auto">
        <DocumentTextIcon class="w-16 h-16 text-gray-300 mx-auto mb-4" />
        <h3 class="text-lg font-medium text-gray-900 mb-2">No posts yet</h3>
        <p class="text-gray-600 mb-6">
          {{ getEmptyStateMessage() }}
        </p>
        <button
          @click="handleRefresh"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          Refresh Feed
        </button>
      </div>
    </div>

    <!-- Posts -->
    <div v-else class="space-y-0">
      <!-- Loading skeletons for initial load -->
      <template v-if="feedStore.isLoadingInitial">
        <PostSkeleton 
          v-for="i in 5" 
          :key="`skeleton-${i}`"
          :variant="i % 2 === 0 ? 'detailed' : 'simple'"
          :show-media="i % 3 === 0"
        />
      </template>

      <!-- Actual posts -->
      <template v-else>
        <LazyLoader
          v-for="post in feedStore.posts"
          :key="post.id"
          :threshold="0.1"
          :root-margin="200"
          @intersect="onPostVisible(post.id)"
        >
          <PostCard
            :post="post"
            @like="handleLike"
            @comment="handleComment"
            @share="handleShare"
            @bookmark="handleBookmark"
            @edit="handleEdit"
            @delete="handleDelete"
            @report="handleReport"
            @media-click="handleMediaClick"
            @tag-click="handleTagClick"
            @poll-vote="handlePollVote"
            @view-comments="handleViewComments"
          />
        </LazyLoader>

        <!-- Load more trigger -->
        <LazyLoader
          v-if="feedStore.hasMore && !feedStore.isLoadingMore"
          :threshold="0.5"
          :root-margin="400"
          @intersect="loadMore"
          target-class="py-8"
        >
          <template #placeholder>
            <div class="text-center py-8">
              <button
                @click="loadMore"
                class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                Load More Posts
              </button>
            </div>
          </template>
        </LazyLoader>

        <!-- Loading more indicator -->
        <div v-if="feedStore.isLoadingMore" class="space-y-0">
          <PostSkeleton 
            v-for="i in 3" 
            :key="`loading-${i}`"
            variant="simple"
          />
        </div>

        <!-- End of feed -->
        <div v-if="!feedStore.hasMore && feedStore.posts.length > 0" class="text-center py-8">
          <div class="text-gray-500">
            <CheckIcon class="w-6 h-6 mx-auto mb-2" />
            <p>You're all caught up!</p>
          </div>
        </div>
      </template>
    </div>

    <!-- Lightbox Modal -->
    <MediaLightbox
      v-if="lightboxMedia"
      :media="lightboxMedia"
      :post="lightboxPost"
      @close="closeLightbox"
      @previous="previousMedia"
      @next="nextMedia"
    />

    <!-- Post Creation Modal -->
    <PostCreationForm
      v-if="showPostCreation"
      @close="showPostCreation = false"
      @success="onPostCreated"
    />

    <!-- Floating Action Button -->
    <button
      @click="showPostCreation = true"
      class="fixed bottom-6 right-6 w-14 h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center z-40"
    >
      <PlusIcon class="w-6 h-6" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'
import {
  ArrowPathIcon,
  ExclamationTriangleIcon,
  DocumentTextIcon,
  CheckIcon,
  PlusIcon
} from '@heroicons/vue/24/outline'
import { useFeedStore } from '@/stores/feed'
import { useAuthStore } from '@/stores/auth'
import type { Post, MediaAttachment } from '@/types/posts'
import PostCard from './PostCard.vue'
import PostSkeleton from './PostSkeleton.vue'
import LazyLoader from './LazyLoader.vue'
import PostCreationForm from './PostCreationForm.vue'
import MediaLightbox from './MediaLightbox.vue'

interface Props {
  initialFeedType?: string
}

const props = withDefaults(defineProps<Props>(), {
  initialFeedType: 'chronological'
})

const feedStore = useFeedStore()
const authStore = useAuthStore()

const feedTypes = [
  { value: 'chronological', label: 'Latest' },
  { value: 'following', label: 'Following' },
  { value: 'trending', label: 'Trending' },
  { value: 'discover', label: 'Discover' }
]

const lightboxMedia = ref<MediaAttachment | null>(null)
const lightboxPost = ref<Post | null>(null)
const showPostCreation = ref(false)
const visiblePosts = ref(new Set<number>())

const getEmptyStateMessage = () => {
  switch (feedStore.feedType) {
    case 'following':
      return "Follow some users to see their posts here."
    case 'trending':
      return "No trending posts right now. Check back later!"
    case 'discover':
      return "No new posts to discover. Try following more users."
    default:
      return "Be the first to share something!"
  }
}

const handleRefresh = async () => {
  await feedStore.refresh()
}

const switchFeedType = async (type: string) => {
  await feedStore.switchFeedType(type)
}

const loadMore = async () => {
  await feedStore.loadMore()
}

const onPostVisible = (postId: number) => {
  visiblePosts.value.add(postId)
  // Could implement analytics tracking here
}

// Post interaction handlers
const handleLike = async (postId: number) => {
  await feedStore.toggleLike(postId)
}

const handleComment = (postId: number) => {
  // Navigate to post detail or open comment modal
  console.log('Comment on post:', postId)
}

const handleShare = async (postId: number) => {
  await feedStore.sharePost(postId)
}

const handleBookmark = async (postId: number) => {
  await feedStore.toggleBookmark(postId)
}

const handleEdit = (post: Post) => {
  // Open edit modal with post data
  console.log('Edit post:', post)
}

const handleDelete = async (postId: number) => {
  feedStore.removePost(postId)
  // Could call API to delete post
}

const handleReport = (postId: number) => {
  // Open report modal
  console.log('Report post:', postId)
}

const handleMediaClick = (media: MediaAttachment) => {
  lightboxMedia.value = media
  lightboxPost.value = feedStore.posts.find(p => 
    p.media_attachments?.some(m => m.id === media.id)
  ) || null
}

const handleTagClick = (tag: string) => {
  // Navigate to tag page or filter posts
  console.log('Tag clicked:', tag)
}

const handlePollVote = (postId: number, optionIndex: number) => {
  // Handle poll voting
  console.log('Poll vote:', postId, optionIndex)
}

const handleViewComments = (postId: number) => {
  // Navigate to post detail or open comments
  console.log('View comments:', postId)
}

const closeLightbox = () => {
  lightboxMedia.value = null
  lightboxPost.value = null
}

const previousMedia = () => {
  if (!lightboxPost.value?.media_attachments || !lightboxMedia.value) return
  
  const currentIndex = lightboxPost.value.media_attachments.findIndex(
    m => m.id === lightboxMedia.value!.id
  )
  
  if (currentIndex > 0) {
    lightboxMedia.value = lightboxPost.value.media_attachments[currentIndex - 1]
  }
}

const nextMedia = () => {
  if (!lightboxPost.value?.media_attachments || !lightboxMedia.value) return
  
  const currentIndex = lightboxPost.value.media_attachments.findIndex(
    m => m.id === lightboxMedia.value!.id
  )
  
  if (currentIndex < lightboxPost.value.media_attachments.length - 1) {
    lightboxMedia.value = lightboxPost.value.media_attachments[currentIndex + 1]
  }
}

const onPostCreated = (post: Post) => {
  feedStore.addPost(post)
  showPostCreation.value = false
}

// Keyboard shortcuts
const handleKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    if (lightboxMedia.value) {
      closeLightbox()
    } else if (showPostCreation.value) {
      showPostCreation.value = false
    }
  }
  
  if (lightboxMedia.value) {
    if (event.key === 'ArrowLeft') {
      previousMedia()
    } else if (event.key === 'ArrowRight') {
      nextMedia()
    }
  }
}

onMounted(async () => {
  document.addEventListener('keydown', handleKeydown)
  await feedStore.loadFeed(props.initialFeedType)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeydown)
  feedStore.clear()
})
</script>

<style scoped>
.feed-display {
  max-width: 42rem;
  margin: 0 auto;
  padding: 0 1rem;
}

@media (min-width: 768px) {
  .feed-display {
    padding: 0 1.5rem;
  }
}
</style> 