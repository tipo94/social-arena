import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import type { Post, FeedResponse, PostSearchParams } from '@/types/posts'
import { postService } from '@/services/postService'

export interface FeedState {
  posts: Post[]
  loading: boolean
  hasMore: boolean
  cursor: string | null
  error: string | null
  feedType: string
  refreshing: boolean
  initialLoad: boolean
}

export const useFeedStore = defineStore('feed', () => {
  // State
  const posts = ref<Post[]>([])
  const loading = ref(false)
  const hasMore = ref(true)
  const cursor = ref<string | null>(null)
  const error = ref<string | null>(null)
  const feedType = ref('chronological')
  const refreshing = ref(false)
  const initialLoad = ref(true)

  // Getters
  const isEmpty = computed(() => posts.value.length === 0)
  const isLoadingInitial = computed(() => loading.value && initialLoad.value)
  const isLoadingMore = computed(() => loading.value && !initialLoad.value)

  // Actions
  const loadFeed = async (type: string = 'chronological', params: PostSearchParams = {}) => {
    try {
      loading.value = true
      error.value = null
      
      const searchParams: PostSearchParams = {
        type,
        per_page: 10,
        cursor: cursor.value || undefined,
        ...params
      }

      const response = await postService.getFeed(searchParams)
      
      if (response.success && response.data) {
        const feedData = response.data
        
        if (initialLoad.value || refreshing.value) {
          posts.value = feedData.posts
        } else {
          posts.value = [...posts.value, ...feedData.posts]
        }
        
        cursor.value = feedData.next_cursor
        hasMore.value = feedData.has_more
        feedType.value = type
        initialLoad.value = false
        refreshing.value = false
      } else {
        throw new Error(response.error || 'Failed to load feed')
      }
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'An error occurred'
      console.error('Feed loading error:', err)
    } finally {
      loading.value = false
    }
  }

  const loadMore = async () => {
    if (loading.value || !hasMore.value) return
    await loadFeed(feedType.value)
  }

  const refresh = async () => {
    refreshing.value = true
    cursor.value = null
    hasMore.value = true
    initialLoad.value = true
    await loadFeed(feedType.value)
  }

  const switchFeedType = async (type: string) => {
    if (type === feedType.value) return
    
    posts.value = []
    cursor.value = null
    hasMore.value = true
    initialLoad.value = true
    await loadFeed(type)
  }

  const addPost = (post: Post) => {
    posts.value.unshift(post)
  }

  const updatePost = (postId: number, updates: Partial<Post>) => {
    const index = posts.value.findIndex(p => p.id === postId)
    if (index !== -1) {
      posts.value[index] = { ...posts.value[index], ...updates }
    }
  }

  const removePost = (postId: number) => {
    const index = posts.value.findIndex(p => p.id === postId)
    if (index !== -1) {
      posts.value.splice(index, 1)
    }
  }

  const toggleLike = async (postId: number) => {
    try {
      const post = posts.value.find(p => p.id === postId)
      if (!post) return

      const response = await postService.toggleLike(postId)
      if (response.success && response.data) {
        updatePost(postId, {
          liked: response.data.liked,
          likes_count: response.data.likes_count
        })
      }
    } catch (err) {
      console.error('Toggle like error:', err)
    }
  }

  const toggleBookmark = async (postId: number) => {
    try {
      const post = posts.value.find(p => p.id === postId)
      if (!post) return

      const response = await postService.toggleBookmark(postId)
      if (response.success && response.data) {
        updatePost(postId, {
          bookmarked: response.data.bookmarked
        })
      }
    } catch (err) {
      console.error('Toggle bookmark error:', err)
    }
  }

  const sharePost = async (postId: number) => {
    try {
      const response = await postService.sharePost(postId)
      if (response.success && response.data) {
        updatePost(postId, {
          shares_count: response.data.shares_count
        })
      }
    } catch (err) {
      console.error('Share post error:', err)
    }
  }

  const clear = () => {
    posts.value = []
    cursor.value = null
    hasMore.value = true
    loading.value = false
    error.value = null
    refreshing.value = false
    initialLoad.value = true
  }

  return {
    // State
    posts,
    loading,
    hasMore,
    cursor,
    error,
    feedType,
    refreshing,
    initialLoad,
    
    // Getters
    isEmpty,
    isLoadingInitial,
    isLoadingMore,
    
    // Actions
    loadFeed,
    loadMore,
    refresh,
    switchFeedType,
    addPost,
    updatePost,
    removePost,
    toggleLike,
    toggleBookmark,
    sharePost,
    clear
  }
}) 