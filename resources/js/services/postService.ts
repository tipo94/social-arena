import axios from 'axios'
import type { AxiosResponse } from 'axios'
import type { 
  Post, 
  CreatePostData, 
  UpdatePostData, 
  ApiResponse, 
  FeedResponse,
  PostSearchParams,
  PostAnalytics,
  PostRevision,
  PostDeletionLog,
  Comment,
  CreateCommentData,
  UpdateCommentData
} from '@/types/posts'

class PostService {
  private baseURL = '/api'

  // Post CRUD operations
  async createPost(data: CreatePostData): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.post(
        `${this.baseURL}/posts`,
        data
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async updatePost(id: number, data: UpdatePostData): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.put(
        `${this.baseURL}/posts/${id}`,
        data
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getPost(id: number): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.get(
        `${this.baseURL}/posts/${id}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async deletePost(id: number, reason?: string, permanent = false): Promise<ApiResponse<void>> {
    try {
      const response: AxiosResponse<ApiResponse<void>> = await axios.delete(
        `${this.baseURL}/posts/${id}`,
        {
          data: { reason, permanent }
        }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async restorePost(id: number): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.post(
        `${this.baseURL}/posts/${id}/restore`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Feed operations
  async getFeed(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/feed`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getChronologicalFeed(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/feed/chronological`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getAlgorithmicFeed(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/feed/algorithmic`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getFollowingFeed(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/feed/following`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getTrendingFeed(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/feed/trending`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getDiscoverFeed(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/feed/discover`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getBookmarksFeed(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/feed/bookmarks`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Search operations
  async searchPosts(params: PostSearchParams): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/posts/search`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Interaction operations
  async likePost(id: number): Promise<ApiResponse<{ liked: boolean; likes_count: number }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ liked: boolean; likes_count: number }>> = await axios.post(
        `${this.baseURL}/posts/${id}/like`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async unlikePost(id: number): Promise<ApiResponse<{ liked: boolean; likes_count: number }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ liked: boolean; likes_count: number }>> = await axios.delete(
        `${this.baseURL}/posts/${id}/like`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async bookmarkPost(id: number): Promise<ApiResponse<{ bookmarked: boolean }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ bookmarked: boolean }>> = await axios.post(
        `${this.baseURL}/posts/${id}/bookmark`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async unbookmarkPost(id: number): Promise<ApiResponse<{ bookmarked: boolean }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ bookmarked: boolean }>> = await axios.delete(
        `${this.baseURL}/posts/${id}/bookmark`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async sharePost(id: number, platform?: string): Promise<ApiResponse<{ shared: boolean; shares_count: number }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ shared: boolean; shares_count: number }>> = await axios.post(
        `${this.baseURL}/posts/${id}/share`,
        { platform }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async reportPost(id: number, reason: string, details?: string): Promise<ApiResponse<void>> {
    try {
      const response: AxiosResponse<ApiResponse<void>> = await axios.post(
        `${this.baseURL}/posts/${id}/report`,
        { reason, details }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Pin/Unpin operations
  async pinPost(id: number): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.post(
        `${this.baseURL}/posts/${id}/pin`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async unpinPost(id: number): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.delete(
        `${this.baseURL}/posts/${id}/pin`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Visibility operations
  async updateVisibility(id: number, visibility: string, customAudience?: number[]): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.patch(
        `${this.baseURL}/posts/${id}/visibility`,
        { visibility, custom_audience: customAudience }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getVisibilityHistory(id: number): Promise<ApiResponse<Array<any>>> {
    try {
      const response: AxiosResponse<ApiResponse<Array<any>>> = await axios.get(
        `${this.baseURL}/posts/${id}/visibility/history`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Revision operations
  async getRevisions(id: number): Promise<ApiResponse<PostRevision[]>> {
    try {
      const response: AxiosResponse<ApiResponse<PostRevision[]>> = await axios.get(
        `${this.baseURL}/posts/${id}/revisions`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getRevision(postId: number, revisionId: number): Promise<ApiResponse<PostRevision>> {
    try {
      const response: AxiosResponse<ApiResponse<PostRevision>> = await axios.get(
        `${this.baseURL}/posts/${postId}/revisions/${revisionId}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async revertToRevision(postId: number, revisionId: number, reason?: string): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.post(
        `${this.baseURL}/posts/${postId}/revisions/${revisionId}/revert`,
        { reason }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Deletion log operations
  async getDeletionLogs(id: number): Promise<ApiResponse<PostDeletionLog[]>> {
    try {
      const response: AxiosResponse<ApiResponse<PostDeletionLog[]>> = await axios.get(
        `${this.baseURL}/posts/${id}/deletion-logs`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Comment operations
  async getComments(
    postId: number, 
    params: { sort?: 'newest' | 'oldest' | 'popular'; per_page?: number; cursor?: string } = {}
  ): Promise<ApiResponse<{ comments: Comment[]; pagination: any }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ comments: Comment[]; pagination: any }>> = await axios.get(
        `${this.baseURL}/posts/${postId}/comments`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async createComment(postId: number, data: CreateCommentData): Promise<ApiResponse<Comment>> {
    try {
      const response: AxiosResponse<ApiResponse<Comment>> = await axios.post(
        `${this.baseURL}/posts/${postId}/comments`,
        data
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async updateComment(postId: number, commentId: number, data: UpdateCommentData): Promise<ApiResponse<Comment>> {
    try {
      const response: AxiosResponse<ApiResponse<Comment>> = await axios.put(
        `${this.baseURL}/posts/${postId}/comments/${commentId}`,
        data
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async deleteComment(postId: number, commentId: number, reason?: string): Promise<ApiResponse<void>> {
    try {
      const response: AxiosResponse<ApiResponse<void>> = await axios.delete(
        `${this.baseURL}/posts/${postId}/comments/${commentId}`,
        { data: { reason } }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async likeComment(postId: number, commentId: number): Promise<ApiResponse<{ liked: boolean; likes_count: number }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ liked: boolean; likes_count: number }>> = await axios.post(
        `${this.baseURL}/posts/${postId}/comments/${commentId}/like`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async unlikeComment(postId: number, commentId: number): Promise<ApiResponse<{ liked: boolean; likes_count: number }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ liked: boolean; likes_count: number }>> = await axios.delete(
        `${this.baseURL}/posts/${postId}/comments/${commentId}/like`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Analytics operations
  async getPostAnalytics(id: number): Promise<ApiResponse<PostAnalytics>> {
    try {
      const response: AxiosResponse<ApiResponse<PostAnalytics>> = await axios.get(
        `${this.baseURL}/posts/${id}/analytics`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async incrementView(id: number): Promise<ApiResponse<{ views_count: number }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ views_count: number }>> = await axios.post(
        `${this.baseURL}/posts/${id}/view`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Draft operations
  async saveDraft(data: CreatePostData): Promise<ApiResponse<{ id: string }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ id: string }>> = await axios.post(
        `${this.baseURL}/posts/drafts`,
        data
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getDrafts(): Promise<ApiResponse<Array<any>>> {
    try {
      const response: AxiosResponse<ApiResponse<Array<any>>> = await axios.get(
        `${this.baseURL}/posts/drafts`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async deleteDraft(id: string): Promise<ApiResponse<void>> {
    try {
      const response: AxiosResponse<ApiResponse<void>> = await axios.delete(
        `${this.baseURL}/posts/drafts/${id}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Scheduled posts operations
  async getScheduledPosts(): Promise<ApiResponse<Post[]>> {
    try {
      const response: AxiosResponse<ApiResponse<Post[]>> = await axios.get(
        `${this.baseURL}/posts/scheduled`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async updateScheduledPost(id: number, scheduledAt: string): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.patch(
        `${this.baseURL}/posts/${id}/schedule`,
        { scheduled_at: scheduledAt }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async publishNow(id: number): Promise<ApiResponse<Post>> {
    try {
      const response: AxiosResponse<ApiResponse<Post>> = await axios.post(
        `${this.baseURL}/posts/${id}/publish`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Bulk operations
  async bulkDelete(ids: number[], reason?: string): Promise<ApiResponse<{ deleted_count: number; errors: any[] }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ deleted_count: number; errors: any[] }>> = await axios.delete(
        `${this.baseURL}/posts/bulk`,
        { 
          data: { ids, reason }
        }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async bulkUpdateVisibility(
    ids: number[], 
    visibility: string, 
    customAudience?: number[]
  ): Promise<ApiResponse<{ updated_count: number; errors: any[] }>> {
    try {
      const response: AxiosResponse<ApiResponse<{ updated_count: number; errors: any[] }>> = await axios.patch(
        `${this.baseURL}/posts/bulk/visibility`,
        { ids, visibility, custom_audience: customAudience }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // User posts operations
  async getUserPosts(
    userId: number, 
    params: PostSearchParams = {}
  ): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/users/${userId}/posts`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getMyPosts(params: PostSearchParams = {}): Promise<ApiResponse<FeedResponse>> {
    try {
      const response: AxiosResponse<ApiResponse<FeedResponse>> = await axios.get(
        `${this.baseURL}/my/posts`,
        { params }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Link preview operations
  async getLinkPreview(url: string): Promise<ApiResponse<{
    title?: string
    description?: string
    image_url?: string
    domain?: string
    type?: string
  }>> {
    try {
      const response = await axios.get(
        `${this.baseURL}/posts/link-preview`,
        { params: { url } }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Hashtag operations
  async getHashtagSuggestions(query: string): Promise<ApiResponse<Array<{ tag: string; count: number }>>> {
    try {
      const response: AxiosResponse<ApiResponse<Array<{ tag: string; count: number }>>> = await axios.get(
        `${this.baseURL}/hashtags/suggestions`,
        { params: { q: query } }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getPopularHashtags(): Promise<ApiResponse<Array<{ tag: string; count: number }>>> {
    try {
      const response: AxiosResponse<ApiResponse<Array<{ tag: string; count: number }>>> = await axios.get(
        `${this.baseURL}/hashtags/popular`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Mention operations
  async searchMentions(query: string): Promise<ApiResponse<Array<{ id: number; name: string; username: string; avatar_url?: string }>>> {
    try {
      const response = await axios.get(
        `${this.baseURL}/users/search`,
        { params: { q: query, limit: 10 } }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Error handling
  private handleError(error: any): ApiResponse<any> {
    if (error.response?.data) {
      return error.response.data
    }
    
    return {
      success: false,
      message: error.message || 'An unexpected error occurred',
      errors: {
        general: [error.message || 'An unexpected error occurred']
      }
    }
  }

  // Utility methods
  getPostUrl(post: Post): string {
    return `${window.location.origin}/posts/${post.id}`
  }

  getShareUrl(post: Post, platform?: string): string {
    const url = encodeURIComponent(this.getPostUrl(post))
    const title = encodeURIComponent(post.content?.slice(0, 100) + '...' || 'Check out this post')
    
    switch (platform) {
      case 'twitter':
        return `https://twitter.com/intent/tweet?url=${url}&text=${title}`
      case 'facebook':
        return `https://www.facebook.com/sharer/sharer.php?u=${url}`
      case 'linkedin':
        return `https://www.linkedin.com/sharing/share-offsite/?url=${url}`
      case 'reddit':
        return `https://reddit.com/submit?url=${url}&title=${title}`
      default:
        return this.getPostUrl(post)
    }
  }

  formatEngagementCount(count: number): string {
    if (count >= 1000000) {
      return Math.floor(count / 1000000) + 'M'
    } else if (count >= 1000) {
      return Math.floor(count / 1000) + 'K'
    }
    return count.toString()
  }

  calculateEngagementRate(post: Post): number {
    const totalEngagement = post.likes_count + post.comments_count + post.shares_count
    if (post.views_count === 0) return 0
    return Math.round((totalEngagement / post.views_count) * 100 * 100) / 100
  }

  isPostExpired(post: Post): boolean {
    if (!post.visibility_expires_at) return false
    return new Date(post.visibility_expires_at) < new Date()
  }

  canEditPost(post: Post, currentUserId?: number): boolean {
    if (!currentUserId || post.user_id !== currentUserId) return false
    if (post.is_deleted) return false
    if (post.edit_deadline && new Date(post.edit_deadline) < new Date()) return false
    return true
  }

  getTimeUntilEditDeadline(post: Post): number | null {
    if (!post.edit_deadline) return null
    const deadline = new Date(post.edit_deadline)
    const now = new Date()
    return deadline.getTime() - now.getTime()
  }
}

export const postService = new PostService()
export default postService 