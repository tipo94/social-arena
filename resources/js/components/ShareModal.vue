<template>
  <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
      <!-- Header -->
      <div class="flex justify-between items-center mb-6">
        <h3 class="text-lg font-semibold text-gray-900">Share Post</h3>
        <button
          @click="$emit('close')"
          class="text-gray-400 hover:text-gray-600"
        >
          <XMarkIcon class="w-6 h-6" />
        </button>
      </div>

      <!-- Share options -->
      <div class="space-y-4">
        <!-- Copy link -->
        <button
          @click="copyLink"
          class="w-full flex items-center space-x-3 p-3 text-left hover:bg-gray-50 rounded-lg transition-colors"
        >
          <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
            <LinkIcon class="w-5 h-5 text-blue-600" />
          </div>
          <div class="flex-1">
            <div class="font-medium text-gray-900">Copy Link</div>
            <div class="text-sm text-gray-500">Share via link</div>
          </div>
          <div v-if="linkCopied" class="text-green-600 text-sm">Copied!</div>
        </button>

        <!-- Email -->
        <button
          @click="shareViaEmail"
          class="w-full flex items-center space-x-3 p-3 text-left hover:bg-gray-50 rounded-lg transition-colors"
        >
          <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
            <EnvelopeIcon class="w-5 h-5 text-gray-600" />
          </div>
          <div class="flex-1">
            <div class="font-medium text-gray-900">Email</div>
            <div class="text-sm text-gray-500">Share via email</div>
          </div>
        </button>

        <!-- Social media options -->
        <div class="grid grid-cols-2 gap-3 pt-2">
          <button
            @click="shareToTwitter"
            class="flex flex-col items-center p-4 hover:bg-gray-50 rounded-lg transition-colors"
          >
            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center mb-2">
              <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
              </svg>
            </div>
            <span class="text-sm font-medium text-gray-900">Twitter</span>
          </button>

          <button
            @click="shareToFacebook"
            class="flex flex-col items-center p-4 hover:bg-gray-50 rounded-lg transition-colors"
          >
            <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center mb-2">
              <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="currentColor">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
            </div>
            <span class="text-sm font-medium text-gray-900">Facebook</span>
          </button>
        </div>
      </div>

      <!-- Internal sharing (if within the platform) -->
      <div class="mt-6 pt-4 border-t border-gray-200">
        <div class="text-sm font-medium text-gray-900 mb-3">Share internally</div>
        <button
          @click="handleInternalShare"
          class="w-full flex items-center justify-center space-x-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          <ArrowTopRightOnSquareIcon class="w-4 h-4" />
          <span>Share as Post</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import {
  XMarkIcon,
  LinkIcon,
  EnvelopeIcon,
  ArrowTopRightOnSquareIcon
} from '@heroicons/vue/24/outline'
import type { Post } from '@/types/posts'

interface Props {
  post: Post
}

interface Emits {
  (e: 'close'): void
  (e: 'shared'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const linkCopied = ref(false)

const getPostUrl = () => {
  return `${window.location.origin}/posts/${props.post.id}`
}

const getShareText = () => {
  const content = props.post.content || ''
  const preview = content.length > 100 ? content.substring(0, 100) + '...' : content
  return `Check out this post: ${preview}`
}

const copyLink = async () => {
  try {
    await navigator.clipboard.writeText(getPostUrl())
    linkCopied.value = true
    setTimeout(() => {
      linkCopied.value = false
    }, 2000)
  } catch (err) {
    console.error('Failed to copy link:', err)
  }
}

const shareViaEmail = () => {
  const subject = encodeURIComponent('Check out this post')
  const body = encodeURIComponent(`${getShareText()}\n\n${getPostUrl()}`)
  window.open(`mailto:?subject=${subject}&body=${body}`)
}

const shareToTwitter = () => {
  const text = encodeURIComponent(getShareText())
  const url = encodeURIComponent(getPostUrl())
  window.open(`https://twitter.com/intent/tweet?text=${text}&url=${url}`, '_blank')
}

const shareToFacebook = () => {
  const url = encodeURIComponent(getPostUrl())
  window.open(`https://www.facebook.com/sharer/sharer.php?u=${url}`, '_blank')
}

const handleInternalShare = () => {
  emit('shared')
  // This would trigger creating a new post that shares/reposts the original
  // The parent component would handle the actual sharing logic
}
</script> 