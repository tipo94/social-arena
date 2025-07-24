<template>
  <div class="social-interaction-widget">
    <!-- Post/Content Actions Bar -->
    <div v-if="mode === 'post'" class="post-actions">
      <div class="flex items-center justify-between pt-4 border-t border-gray-200">
        <!-- Left side actions -->
        <div class="flex items-center space-x-4 sm:space-x-6">
          <!-- Like button -->
          <button
            @click="handleLike"
            :disabled="actionsLoading.like"
            class="flex items-center space-x-2 text-gray-600 hover:text-red-600 transition-colors duration-200 group"
            :class="{ 'text-red-600': isLiked }"
          >
            <HeartIcon 
              class="w-5 h-5 transition-transform duration-200 group-hover:scale-110"
              :class="{ 'fill-current': isLiked }"
            />
            <span class="text-sm font-medium hidden sm:inline">
              {{ likesCount }}
            </span>
          </button>

          <!-- Comment button -->
          <button
            @click="toggleComments"
            class="flex items-center space-x-2 text-gray-600 hover:text-blue-600 transition-colors duration-200 group"
          >
            <ChatBubbleLeftIcon class="w-5 h-5 transition-transform duration-200 group-hover:scale-110" />
            <span class="text-sm font-medium hidden sm:inline">
              {{ commentsCount }}
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
              {{ sharesCount }}
            </span>
          </button>
        </div>

        <!-- Right side actions -->
        <div class="flex items-center space-x-2">
          <!-- User-specific actions -->
          <div v-if="postUser && showUserActions">
            <FollowButton
              v-if="!isCurrentUser"
              :user="postUser"
              :compact="true"
              :initial-follow-status="followStatus"
              :initial-follow="followData"
              @follow="handleFollow"
              @unfollow="handleUnfollow"
              @settings-updated="handleFollowSettingsUpdated"
            />
            
            <FriendRequestButton
              v-if="!isCurrentUser && showFriendActions"
              :user="postUser"
              :initial-status="friendshipStatus"
              :initial-friendship="friendshipData"
              @request-sent="handleFriendRequestSent"
              @request-accepted="handleFriendRequestAccepted"
              @unfriended="handleUnfriended"
              @blocked="handleBlocked"
            />
          </div>

          <!-- More actions dropdown -->
          <div class="relative">
            <button
              @click="showMoreActions = !showMoreActions"
              class="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
            >
              <EllipsisHorizontalIcon class="w-5 h-5" />
            </button>

            <div
              v-if="showMoreActions"
              v-click-outside="() => showMoreActions = false"
              class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
            >
              <button
                @click="copyLink"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center"
              >
                <LinkIcon class="w-4 h-4 mr-2" />
                Copy link
              </button>
              
              <button
                v-if="isCurrentUser"
                @click="editPost"
                class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center"
              >
                <PencilIcon class="w-4 h-4 mr-2" />
                Edit post
              </button>
              
              <button
                v-if="!isCurrentUser"
                @click="reportContent"
                class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center"
              >
                <FlagIcon class="w-4 h-4 mr-2" />
                Report
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Comments Section -->
      <div v-if="showComments && allowComments" class="mt-4">
        <CommentSystem
          :post-id="postId"
          :initial-comments="comments"
          @comment-added="handleCommentAdded"
          @comment-updated="handleCommentUpdated"
          @comment-deleted="handleCommentDeleted"
        />
      </div>
    </div>

    <!-- User Profile Actions -->
    <div v-else-if="mode === 'profile'" class="profile-actions">
      <div class="flex items-center space-x-3">
        <FollowButton
          v-if="profileUser && !isCurrentUser"
          :user="profileUser"
          :initial-follow-status="followStatus"
          :initial-follow="followData"
          @follow="handleFollow"
          @unfollow="handleUnfollow"
          @settings-updated="handleFollowSettingsUpdated"
        />
        
        <FriendRequestButton
          v-if="profileUser && !isCurrentUser"
          :user="profileUser"
          :initial-status="friendshipStatus"
          :initial-friendship="friendshipData"
          @request-sent="handleFriendRequestSent"
          @request-accepted="handleFriendRequestAccepted"
          @unfriended="handleUnfriended"
          @blocked="handleBlocked"
        />

        <!-- Message button -->
        <button
          v-if="!isCurrentUser && canMessage"
          @click="startMessage"
          class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
        >
          <ChatBubbleLeftEllipsisIcon class="w-4 h-4 mr-2" />
          Message
        </button>

        <!-- More options -->
        <div class="relative">
          <button
            @click="showMoreActions = !showMoreActions"
            class="p-2 text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100"
          >
            <EllipsisHorizontalIcon class="w-5 h-5" />
          </button>

          <div
            v-if="showMoreActions"
            v-click-outside="() => showMoreActions = false"
            class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 z-10"
          >
            <button
              @click="shareProfile"
              class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 flex items-center"
            >
              <ShareIcon class="w-4 h-4 mr-2" />
              Share profile
            </button>
            
            <button
              v-if="!isCurrentUser"
              @click="reportUser"
              class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50 flex items-center"
            >
              <FlagIcon class="w-4 h-4 mr-2" />
              Report user
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Compact Actions (for cards, lists, etc.) -->
    <div v-else-if="mode === 'compact'" class="compact-actions">
      <div class="flex items-center space-x-2">
        <!-- Like -->
        <button
          @click="handleLike"
          :disabled="actionsLoading.like"
          class="p-2 text-gray-400 hover:text-red-500 rounded-full hover:bg-red-50 transition-colors duration-200"
          :class="{ 'text-red-500 bg-red-50': isLiked }"
        >
          <HeartIcon 
            class="w-4 h-4"
            :class="{ 'fill-current': isLiked }"
          />
        </button>

        <!-- Comment -->
        <button
          @click="toggleComments"
          class="p-2 text-gray-400 hover:text-blue-500 rounded-full hover:bg-blue-50 transition-colors duration-200"
        >
          <ChatBubbleLeftIcon class="w-4 h-4" />
        </button>

        <!-- Share -->
        <button
          @click="handleShare"
          :disabled="actionsLoading.share"
          class="p-2 text-gray-400 hover:text-green-500 rounded-full hover:bg-green-50 transition-colors duration-200"
        >
          <ArrowTopRightOnSquareIcon class="w-4 h-4" />
        </button>

        <!-- Follow/Friend actions for users -->
        <div v-if="postUser && showUserActions" class="flex items-center space-x-1">
          <FollowButton
            v-if="!isCurrentUser"
            :user="postUser"
            :compact="true"
            :initial-follow-status="followStatus"
            :initial-follow="followData"
          />
        </div>
      </div>
    </div>

    <!-- Action feedback -->
    <div v-if="actionFeedback" class="mt-2">
      <div 
        class="inline-flex items-center px-3 py-1 rounded-full text-sm"
        :class="{
          'bg-green-100 text-green-800': actionFeedback.type === 'success',
          'bg-red-100 text-red-800': actionFeedback.type === 'error',
          'bg-blue-100 text-blue-800': actionFeedback.type === 'info'
        }"
      >
        <CheckCircleIcon v-if="actionFeedback.type === 'success'" class="w-4 h-4 mr-1" />
        <ExclamationCircleIcon v-if="actionFeedback.type === 'error'" class="w-4 h-4 mr-1" />
        <InformationCircleIcon v-if="actionFeedback.type === 'info'" class="w-4 h-4 mr-1" />
        {{ actionFeedback.message }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import {
  HeartIcon,
  ChatBubbleLeftIcon,
  ArrowTopRightOnSquareIcon,
  EllipsisHorizontalIcon,
  LinkIcon,
  PencilIcon,
  FlagIcon,
  ShareIcon,
  ChatBubbleLeftEllipsisIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  InformationCircleIcon
} from '@heroicons/vue/24/outline'

import FollowButton from './FollowButton.vue'
import FriendRequestButton from './FriendRequestButton.vue'
import CommentSystem from './CommentSystem.vue'

import { postService } from '@/services/postService'
import { followService } from '@/services/followService'
import { friendshipService } from '@/services/friendshipService'

import type { User } from '@/types/auth'
import type { Post, Comment } from '@/types/posts'
import type { Follow, Friendship, FriendshipStatus } from '@/types/social'

interface Props {
  mode: 'post' | 'profile' | 'compact'
  
  // Post-related props
  postId?: number
  postUser?: User
  initialLikesCount?: number
  initialCommentsCount?: number
  initialSharesCount?: number
  initialIsLiked?: boolean
  allowComments?: boolean
  comments?: Comment[]
  
  // Profile-related props
  profileUser?: User
  
  // Social interaction props
  showUserActions?: boolean
  showFriendActions?: boolean
  canMessage?: boolean
  
  // Initial social states
  followStatus?: 'following' | 'not_following'
  followData?: Follow
  friendshipStatus?: FriendshipStatus | 'none'
  friendshipData?: Friendship
}

interface Emits {
  (e: 'like-toggled', data: { isLiked: boolean; likesCount: number }): void
  (e: 'share-created', shareData: any): void
  (e: 'comment-toggled', show: boolean): void
  (e: 'follow-changed', data: { isFollowing: boolean; follow?: Follow }): void
  (e: 'friendship-changed', data: { status: FriendshipStatus | 'none'; friendship?: Friendship }): void
  (e: 'action-performed', action: string): void
}

const props = withDefaults(defineProps<Props>(), {
  mode: 'post',
  allowComments: true,
  showUserActions: true,
  showFriendActions: true,
  canMessage: true,
  initialLikesCount: 0,
  initialCommentsCount: 0,
  initialSharesCount: 0,
  initialIsLiked: false,
  followStatus: 'not_following',
  friendshipStatus: 'none'
})

const emit = defineEmits<Emits>()

// State
const likesCount = ref(props.initialLikesCount)
const commentsCount = ref(props.initialCommentsCount)
const sharesCount = ref(props.initialSharesCount)
const isLiked = ref(props.initialIsLiked)
const showComments = ref(false)
const showMoreActions = ref(false)

const actionsLoading = ref({
  like: false,
  share: false,
  follow: false,
  friend: false
})

const actionFeedback = ref<{
  type: 'success' | 'error' | 'info'
  message: string
} | null>(null)

// Computed
const isCurrentUser = computed(() => {
  // TODO: Get current user from auth store and compare
  return false
})

// Methods
const handleLike = async () => {
  if (!props.postId || actionsLoading.value.like) return

  actionsLoading.value.like = true
  
  try {
    const result = await postService.toggleLike(props.postId)
    
    if (result.success) {
      isLiked.value = result.data.liked
      likesCount.value = result.data.likes_count
      
      emit('like-toggled', {
        isLiked: isLiked.value,
        likesCount: likesCount.value
      })
      
      showActionFeedback('success', isLiked.value ? 'Post liked!' : 'Like removed')
    }
  } catch (error: any) {
    showActionFeedback('error', 'Failed to update like')
  } finally {
    actionsLoading.value.like = false
  }
}

const handleShare = async () => {
  if (!props.postId || actionsLoading.value.share) return

  actionsLoading.value.share = true
  
  try {
    const result = await postService.sharePost(props.postId)
    
    if (result.success) {
      sharesCount.value = result.data.shares_count
      
      emit('share-created', result.data)
      showActionFeedback('success', 'Post shared!')
    }
  } catch (error: any) {
    showActionFeedback('error', 'Failed to share post')
  } finally {
    actionsLoading.value.share = false
  }
}

const toggleComments = () => {
  showComments.value = !showComments.value
  emit('comment-toggled', showComments.value)
}

const handleCommentAdded = (comment: Comment) => {
  commentsCount.value++
  showActionFeedback('success', 'Comment added!')
}

const handleCommentUpdated = (comment: Comment) => {
  showActionFeedback('success', 'Comment updated!')
}

const handleCommentDeleted = (commentId: number) => {
  commentsCount.value--
  showActionFeedback('success', 'Comment deleted!')
}

const handleFollow = (follow: Follow) => {
  emit('follow-changed', { isFollowing: true, follow })
  showActionFeedback('success', `Now following ${props.postUser?.name || props.profileUser?.name}!`)
}

const handleUnfollow = (userId: number) => {
  emit('follow-changed', { isFollowing: false })
  showActionFeedback('success', `Unfollowed ${props.postUser?.name || props.profileUser?.name}`)
}

const handleFollowSettingsUpdated = (follow: Follow) => {
  emit('follow-changed', { isFollowing: true, follow })
  showActionFeedback('success', 'Follow settings updated!')
}

const handleFriendRequestSent = (friendship: Friendship) => {
  emit('friendship-changed', { status: 'pending', friendship })
  showActionFeedback('success', 'Friend request sent!')
}

const handleFriendRequestAccepted = (friendship: Friendship) => {
  emit('friendship-changed', { status: 'accepted', friendship })
  showActionFeedback('success', 'Friend request accepted!')
}

const handleUnfriended = (friendshipId: number) => {
  emit('friendship-changed', { status: 'none' })
  showActionFeedback('success', 'Removed from friends')
}

const handleBlocked = (friendship: Friendship) => {
  emit('friendship-changed', { status: 'blocked', friendship })
  showActionFeedback('success', 'User blocked')
}

const copyLink = async () => {
  try {
    const url = window.location.href
    await navigator.clipboard.writeText(url)
    showActionFeedback('success', 'Link copied to clipboard!')
  } catch (error) {
    showActionFeedback('error', 'Failed to copy link')
  }
  showMoreActions.value = false
}

const editPost = () => {
  emit('action-performed', 'edit-post')
  showMoreActions.value = false
}

const reportContent = () => {
  emit('action-performed', 'report-content')
  showMoreActions.value = false
  showActionFeedback('success', 'Content reported')
}

const startMessage = () => {
  emit('action-performed', 'start-message')
  showActionFeedback('info', 'Opening message...')
}

const shareProfile = async () => {
  try {
    const url = window.location.href
    await navigator.clipboard.writeText(url)
    showActionFeedback('success', 'Profile link copied!')
  } catch (error) {
    showActionFeedback('error', 'Failed to copy profile link')
  }
  showMoreActions.value = false
}

const reportUser = () => {
  emit('action-performed', 'report-user')
  showMoreActions.value = false
  showActionFeedback('success', 'User reported')
}

const showActionFeedback = (type: 'success' | 'error' | 'info', message: string) => {
  actionFeedback.value = { type, message }
  setTimeout(() => {
    actionFeedback.value = null
  }, 3000)
}

// Directives
const vClickOutside = {
  mounted(el: HTMLElement, binding: any) {
    el.clickOutsideEvent = (event: Event) => {
      if (!(el === event.target || el.contains(event.target as Node))) {
        binding.value()
      }
    }
    document.addEventListener('click', el.clickOutsideEvent)
  },
  unmounted(el: HTMLElement) {
    document.removeEventListener('click', el.clickOutsideEvent)
  }
}
</script>

<style scoped>
.social-interaction-widget {
  width: 100%;
}

/* Smooth transitions for all interactive elements */
.social-interaction-widget button {
  transition: all 0.2s ease-in-out;
}

/* Hover effects for action buttons */
.social-interaction-widget button:hover {
  transform: translateY(-1px);
}

/* Animation for feedback messages */
.social-interaction-widget > div:last-child {
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateY(-10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive design */
@media (max-width: 640px) {
  .social-interaction-widget .hidden.sm\\:inline {
    display: none !important;
  }
  
  .social-interaction-widget .space-x-4 {
    gap: 0.75rem;
  }
}
</style> 