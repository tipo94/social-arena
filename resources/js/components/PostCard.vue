<template>
  <article class="bg-white rounded-lg shadow-sm border border-gray-200 mb-4 overflow-hidden">
    <!-- Post header -->
    <header class="p-6 pb-0">
      <div class="flex items-center space-x-3">
        <!-- User avatar -->
        <div class="flex-shrink-0">
          <img 
            :src="post.user?.avatar || '/default-avatar.png'" 
            :alt="post.user?.name || 'User'"
            class="w-10 h-10 rounded-full object-cover ring-2 ring-gray-100"
          />
        </div>

        <!-- User info and timestamp -->
        <div class="flex-1 min-w-0">
          <div class="flex items-center space-x-2">
            <h3 class="text-sm font-semibold text-gray-900 truncate">
              {{ post.user?.name || 'Unknown User' }}
            </h3>
            <span 
              v-if="post.user?.verified"
              class="inline-flex items-center text-blue-500"
              title="Verified"
            >
              <CheckBadgeIcon class="w-4 h-4" />
            </span>
          </div>
          <div class="flex items-center space-x-2 text-sm text-gray-500">
            <span>{{ post.user?.username ? `@${post.user.username}` : '' }}</span>
            <span v-if="post.user?.username">•</span>
            <time :datetime="post.created_at" :title="formatFullDate(post.created_at)">
              {{ formatRelativeDate(post.created_at) }}
            </time>
            <span v-if="post.edited_at" class="text-xs text-gray-400" :title="`Edited ${formatFullDate(post.edited_at)}`">
              (edited)
            </span>
          </div>
        </div>

        <!-- Post type indicator -->
        <div class="flex items-center space-x-2">
          <span 
            v-if="post.type !== 'text'"
            class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium"
            :class="getPostTypeClass(post.type)"
          >
            <component :is="getPostTypeIcon(post.type)" class="w-3 h-3 mr-1" />
            {{ getPostTypeLabel(post.type) }}
          </span>
          
          <!-- Visibility indicator -->
          <span 
            v-if="post.visibility !== 'public'"
            class="inline-flex items-center text-gray-400"
            :title="`Visible to: ${getVisibilityLabel(post.visibility)}`"
          >
            <component :is="getVisibilityIcon(post.visibility)" class="w-4 h-4" />
          </span>
        </div>
      </div>
    </header>

    <!-- Post content -->
    <main class="px-6 py-4">
      <PostContent 
        :post="post"
        @media-click="handleMediaClick"
        @tag-click="handleTagClick"
        @poll-vote="handlePollVote"
      />
    </main>

    <!-- Post actions -->
    <footer class="px-6 pb-6">
      <PostActions 
        :post="post"
        @like="handleLike"
        @comment="handleComment"
        @share="handleShare"
        @bookmark="handleBookmark"
        @edit="handleEdit"
        @delete="handleDelete"
        @report="handleReport"
      />
    </footer>

    <!-- Comments preview (if enabled) -->
    <div v-if="showCommentsPreview && post.comments_count > 0" class="border-t border-gray-200 px-6 py-4">
      <button 
        @click="handleViewComments"
        class="text-sm text-blue-600 hover:text-blue-800 font-medium"
      >
        View {{ post.comments_count === 1 ? '1 comment' : `${post.comments_count} comments` }}
      </button>
    </div>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  CheckBadgeIcon,
  PhotoIcon,
  VideoCameraIcon,
  LinkIcon,
  BookOpenIcon,
  ChartBarIcon,
  LockClosedIcon,
  UserGroupIcon,
  EyeSlashIcon,
  UsersIcon
} from '@heroicons/vue/24/outline'
import type { Post, MediaAttachment } from '@/types/posts'
import PostContent from './PostContent.vue'
import PostActions from './PostActions.vue'

interface Props {
  post: Post
  showCommentsPreview?: boolean
}

interface Emits {
  (e: 'like', postId: number): void
  (e: 'comment', postId: number): void
  (e: 'share', postId: number): void
  (e: 'bookmark', postId: number): void
  (e: 'edit', post: Post): void
  (e: 'delete', postId: number): void
  (e: 'report', postId: number): void
  (e: 'media-click', media: MediaAttachment): void
  (e: 'tag-click', tag: string): void
  (e: 'poll-vote', postId: number, optionIndex: number): void
  (e: 'view-comments', postId: number): void
}

const props = withDefaults(defineProps<Props>(), {
  showCommentsPreview: true
})

const emit = defineEmits<Emits>()

const getPostTypeIcon = (type: string) => {
  const icons = {
    image: PhotoIcon,
    video: VideoCameraIcon,
    link: LinkIcon,
    book_review: BookOpenIcon,
    poll: ChartBarIcon
  }
  return icons[type as keyof typeof icons] || PhotoIcon
}

const getPostTypeClass = (type: string) => {
  const classes = {
    image: 'bg-green-100 text-green-800',
    video: 'bg-purple-100 text-purple-800',
    link: 'bg-blue-100 text-blue-800',
    book_review: 'bg-yellow-100 text-yellow-800',
    poll: 'bg-indigo-100 text-indigo-800'
  }
  return classes[type as keyof typeof classes] || 'bg-gray-100 text-gray-800'
}

const getPostTypeLabel = (type: string) => {
  const labels = {
    image: 'Photo',
    video: 'Video',
    link: 'Link',
    book_review: 'Book Review',
    poll: 'Poll'
  }
  return labels[type as keyof typeof labels] || type
}

const getVisibilityIcon = (visibility: string) => {
  const icons = {
    friends: UserGroupIcon,
    close_friends: UsersIcon,
    private: LockClosedIcon,
    custom: EyeSlashIcon,
    group: UserGroupIcon
  }
  return icons[visibility as keyof typeof icons] || LockClosedIcon
}

const getVisibilityLabel = (visibility: string) => {
  const labels = {
    public: 'Everyone',
    friends: 'Friends',
    close_friends: 'Close Friends',
    friends_of_friends: 'Friends of Friends',
    private: 'Only Me',
    custom: 'Custom',
    group: 'Group Members'
  }
  return labels[visibility as keyof typeof labels] || visibility
}

const formatRelativeDate = (dateString: string) => {
  const date = new Date(dateString)
  const now = new Date()
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)
  
  if (diffInSeconds < 60) return 'Just now'
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m`
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h`
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d`
  
  return date.toLocaleDateString()
}

const formatFullDate = (dateString: string) => {
  return new Date(dateString).toLocaleString()
}

// Event handlers
const handleLike = () => {
  emit('like', props.post.id)
}

const handleComment = () => {
  emit('comment', props.post.id)
}

const handleShare = () => {
  emit('share', props.post.id)
}

const handleBookmark = () => {
  emit('bookmark', props.post.id)
}

const handleEdit = () => {
  emit('edit', props.post)
}

const handleDelete = () => {
  emit('delete', props.post.id)
}

const handleReport = () => {
  emit('report', props.post.id)
}

const handleMediaClick = (media: MediaAttachment) => {
  emit('media-click', media)
}

const handleTagClick = (tag: string) => {
  emit('tag-click', tag)
}

const handlePollVote = (optionIndex: number) => {
  emit('poll-vote', props.post.id, optionIndex)
}

const handleViewComments = () => {
  emit('view-comments', props.post.id)
}
</script> 