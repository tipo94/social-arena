<template>
  <div class="post-content">
    <!-- Text content -->
    <div 
      v-if="post.content"
      class="mb-4 text-gray-900 leading-relaxed"
      v-html="formattedContent"
    ></div>

    <!-- Media content based on post type -->
    <div v-if="hasMedia" class="mb-4">
      <!-- Image posts -->
      <div v-if="post.type === 'image'" class="space-y-3">
        <div 
          v-for="media in imageAttachments" 
          :key="media.id"
          class="relative group cursor-pointer rounded-lg overflow-hidden"
          @click="openLightbox(media)"
        >
          <img 
            :src="media.preview_url || media.url" 
            :alt="media.alt_text || 'Post image'"
            class="w-full h-auto max-h-96 object-cover transition-transform duration-200 group-hover:scale-105"
            loading="lazy"
          />
          <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity duration-200"></div>
        </div>
      </div>

      <!-- Video posts -->
      <div v-if="post.type === 'video'" class="space-y-3">
        <div 
          v-for="media in videoAttachments" 
          :key="media.id"
          class="relative rounded-lg overflow-hidden bg-black"
        >
          <video 
            :src="media.url"
            :poster="media.thumbnail_url"
            class="w-full h-auto max-h-96 object-contain"
            controls
            preload="metadata"
          >
            Your browser does not support the video tag.
          </video>
        </div>
      </div>

      <!-- Mixed media for text posts -->
      <div v-if="post.type === 'text' && hasMedia" class="grid gap-3" :class="mediaGridClass">
        <div 
          v-for="media in post.media_attachments" 
          :key="media.id"
          class="relative group cursor-pointer rounded-lg overflow-hidden"
          @click="openLightbox(media)"
        >
          <img 
            v-if="media.type === 'image'"
            :src="media.preview_url || media.url" 
            :alt="media.alt_text || 'Post media'"
            class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105"
            loading="lazy"
          />
          <div 
            v-else-if="media.type === 'video'"
            class="relative bg-black"
          >
            <video 
              :src="media.url"
              :poster="media.thumbnail_url"
              class="w-full h-full object-cover"
              muted
              loop
              @mouseenter="$event.target.play()"
              @mouseleave="$event.target.pause()"
            >
            </video>
            <div class="absolute inset-0 flex items-center justify-center">
              <PlayIcon class="w-12 h-12 text-white opacity-80" />
            </div>
          </div>
          <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-opacity duration-200"></div>
        </div>
      </div>
    </div>

    <!-- Link preview -->
    <div v-if="post.type === 'link' && linkMetadata" class="border border-gray-200 rounded-lg overflow-hidden mb-4">
      <a 
        :href="linkMetadata.url" 
        target="_blank" 
        rel="noopener noreferrer"
        class="block hover:bg-gray-50 transition-colors"
      >
        <img 
          v-if="linkMetadata.image"
          :src="linkMetadata.image" 
          :alt="linkMetadata.title"
          class="w-full h-48 object-cover"
        />
        <div class="p-4">
          <div class="text-sm text-gray-500 mb-1">{{ linkMetadata.domain }}</div>
          <div class="font-semibold text-gray-900 mb-2">{{ linkMetadata.title }}</div>
          <div class="text-sm text-gray-600">{{ linkMetadata.description }}</div>
        </div>
      </a>
    </div>

    <!-- Book review content -->
    <div v-if="post.type === 'book_review' && bookReviewData" class="border border-gray-200 rounded-lg p-4 mb-4 bg-gray-50">
      <div class="flex space-x-4">
        <img 
          v-if="bookReviewData.cover_image"
          :src="bookReviewData.cover_image" 
          :alt="bookReviewData.title"
          class="w-20 h-28 object-cover rounded shadow-sm"
        />
        <div class="flex-1">
          <h4 class="font-semibold text-gray-900 mb-1">{{ bookReviewData.title }}</h4>
          <p class="text-gray-600 mb-2">by {{ bookReviewData.author }}</p>
          <div class="flex items-center space-x-2 mb-2">
            <div class="flex items-center">
              <StarIcon 
                v-for="i in 5" 
                :key="i"
                class="w-4 h-4"
                :class="i <= (bookReviewData.rating || 0) ? 'text-yellow-400 fill-current' : 'text-gray-300'"
              />
            </div>
            <span class="text-sm text-gray-600">{{ bookReviewData.rating }}/5</span>
          </div>
          <p class="text-sm text-gray-600">{{ bookReviewData.genre }}</p>
        </div>
      </div>
      <div v-if="bookReviewData.review" class="mt-4 pt-4 border-t border-gray-200">
        <p class="text-gray-900">{{ bookReviewData.review }}</p>
      </div>
    </div>

    <!-- Poll content -->
    <div v-if="post.type === 'poll' && pollData" class="border border-gray-200 rounded-lg p-4 mb-4">
      <h4 class="font-semibold text-gray-900 mb-4">{{ pollData.question }}</h4>
      <div class="space-y-3">
        <div 
          v-for="(option, index) in pollData.options" 
          :key="index"
          class="relative"
        >
          <button
            @click="votePoll(index)"
            :disabled="hasVoted || pollExpired"
            class="w-full text-left p-3 rounded-lg border-2 transition-colors"
            :class="getOptionClasses(option, index)"
          >
            <div class="flex justify-between items-center">
              <span>{{ option.text }}</span>
              <span v-if="showResults" class="text-sm font-medium">
                {{ option.votes }} ({{ getPercentage(option.votes) }}%)
              </span>
            </div>
            <div 
              v-if="showResults"
              class="absolute bottom-0 left-0 h-1 bg-blue-500 rounded-full transition-all duration-500"
              :style="{ width: `${getPercentage(option.votes)}%` }"
            ></div>
          </button>
        </div>
      </div>
      <div class="flex justify-between items-center mt-4 text-sm text-gray-500">
        <span>{{ totalVotes }} votes</span>
        <span v-if="pollData.expires_at">
          {{ pollExpired ? 'Poll ended' : `Ends ${formatDate(pollData.expires_at)}` }}
        </span>
      </div>
    </div>

    <!-- Tags -->
    <div v-if="post.tags && post.tags.length" class="flex flex-wrap gap-2 mb-3">
      <span 
        v-for="tag in post.tags" 
        :key="tag"
        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 hover:bg-blue-200 cursor-pointer transition-colors"
        @click="$emit('tag-click', tag)"
      >
        #{{ tag }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { PlayIcon, StarIcon } from '@heroicons/vue/24/solid'
import type { Post, MediaAttachment } from '@/types/posts'

interface Props {
  post: Post
}

interface Emits {
  (e: 'media-click', media: MediaAttachment): void
  (e: 'tag-click', tag: string): void
  (e: 'poll-vote', optionIndex: number): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const hasMedia = computed(() => {
  return props.post.media_attachments && props.post.media_attachments.length > 0
})

const imageAttachments = computed(() => {
  return props.post.media_attachments?.filter(m => m.type === 'image') || []
})

const videoAttachments = computed(() => {
  return props.post.media_attachments?.filter(m => m.type === 'video') || []
})

const mediaGridClass = computed(() => {
  const count = props.post.media_attachments?.length || 0
  if (count === 1) return 'grid-cols-1'
  if (count === 2) return 'grid-cols-2'
  if (count === 3) return 'grid-cols-3'
  return 'grid-cols-2'
})

const formattedContent = computed(() => {
  if (!props.post.content) return ''
  
  let content = props.post.content
  
  // Convert mentions @username to links
  content = content.replace(/@(\w+)/g, '<a href="/users/$1" class="text-blue-600 hover:underline">@$1</a>')
  
  // Convert hashtags #tag to links
  content = content.replace(/#(\w+)/g, '<a href="/tags/$1" class="text-blue-600 hover:underline">#$1</a>')
  
  // Convert URLs to links
  const urlRegex = /(https?:\/\/[^\s]+)/g
  content = content.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">$1</a>')
  
  // Convert line breaks
  content = content.replace(/\n/g, '<br>')
  
  return content
})

const linkMetadata = computed(() => {
  return props.post.metadata?.link_preview
})

const bookReviewData = computed(() => {
  return props.post.metadata?.book_review
})

const pollData = computed(() => {
  return props.post.metadata?.poll
})

const hasVoted = computed(() => {
  return pollData.value?.user_voted || false
})

const pollExpired = computed(() => {
  if (!pollData.value?.expires_at) return false
  return new Date(pollData.value.expires_at) < new Date()
})

const showResults = computed(() => {
  return hasVoted.value || pollExpired.value
})

const totalVotes = computed(() => {
  return pollData.value?.options?.reduce((sum, option) => sum + (option.votes || 0), 0) || 0
})

const openLightbox = (media: MediaAttachment) => {
  emit('media-click', media)
}

const votePoll = (optionIndex: number) => {
  if (hasVoted.value || pollExpired.value) return
  emit('poll-vote', optionIndex)
}

const getPercentage = (votes: number) => {
  if (totalVotes.value === 0) return 0
  return Math.round((votes / totalVotes.value) * 100)
}

const getOptionClasses = (option: any, index: number) => {
  const baseClasses = 'hover:border-blue-300'
  
  if (showResults.value) {
    if (option.user_selected) {
      return `${baseClasses} border-blue-500 bg-blue-50`
    }
    return `${baseClasses} border-gray-200 bg-gray-50`
  }
  
  return `${baseClasses} border-gray-300 hover:bg-gray-50`
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString()
}
</script> 