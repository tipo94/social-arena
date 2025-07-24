import axios from 'axios'
import type { AxiosResponse } from 'axios'
import type {
  Follow,
  FollowApiResponse,
  FollowListResponse,
  FollowStatsResponse,
  CreateFollowData,
  FollowSettings,
  FollowFilters,
  SocialApiResult,
  SocialApiError
} from '@/types/social'
import type { User } from '@/types/auth'

class FollowService {
  private baseURL = '/api/follow'

  // Follow operations
  async followUser(userId: number, options?: FollowSettings): Promise<SocialApiResult<FollowApiResponse>> {
    try {
      const data: CreateFollowData = {
        user_id: userId,
        options
      }
      
      const response: AxiosResponse<FollowApiResponse> = await axios.post(
        `${this.baseURL}/user`,
        data
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async unfollowUser(userId: number): Promise<SocialApiResult<FollowApiResponse>> {
    try {
      const response: AxiosResponse<FollowApiResponse> = await axios.delete(
        `${this.baseURL}/user`,
        { data: { user_id: userId } }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async toggleFollow(userId: number): Promise<SocialApiResult<FollowApiResponse>> {
    try {
      const response: AxiosResponse<FollowApiResponse> = await axios.post(
        `${this.baseURL}/toggle`,
        { user_id: userId }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Get follow lists
  async getFollowing(filters?: FollowFilters): Promise<SocialApiResult<FollowListResponse>> {
    try {
      const params = new URLSearchParams()
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<FollowListResponse> = await axios.get(
        `${this.baseURL}/following?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getFollowers(filters?: FollowFilters): Promise<SocialApiResult<FollowListResponse>> {
    try {
      const params = new URLSearchParams()
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<FollowListResponse> = await axios.get(
        `${this.baseURL}/followers?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Follow statistics
  async getFollowStats(): Promise<SocialApiResult<FollowStatsResponse>> {
    try {
      const response: AxiosResponse<FollowStatsResponse> = await axios.get(
        `${this.baseURL}/statistics`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Update follow settings
  async updateFollowSettings(followId: number, settings: FollowSettings): Promise<SocialApiResult<FollowApiResponse>> {
    try {
      const response: AxiosResponse<FollowApiResponse> = await axios.patch(
        `${this.baseURL}/${followId}/settings`,
        settings
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Convenience methods
  async muteUser(followId: number): Promise<SocialApiResult<FollowApiResponse>> {
    return this.updateFollowSettings(followId, { is_muted: true })
  }

  async unmuteUser(followId: number): Promise<SocialApiResult<FollowApiResponse>> {
    return this.updateFollowSettings(followId, { is_muted: false })
  }

  async addToCloseFriends(followId: number): Promise<SocialApiResult<FollowApiResponse>> {
    return this.updateFollowSettings(followId, { is_close_friend: true })
  }

  async removeFromCloseFriends(followId: number): Promise<SocialApiResult<FollowApiResponse>> {
    return this.updateFollowSettings(followId, { is_close_friend: false })
  }

  async toggleNotifications(followId: number, enabled: boolean): Promise<SocialApiResult<FollowApiResponse>> {
    return this.updateFollowSettings(followId, { show_notifications: enabled })
  }

  // Check follow status
  async isFollowing(userId: number): Promise<boolean> {
    try {
      const following = await this.getFollowing({ per_page: 1000 })
      if ('success' in following && following.success) {
        return following.data.some(follow => follow.following_id === userId)
      }
      return false
    } catch (error) {
      console.error('Error checking follow status:', error)
      return false
    }
  }

  // Get users that follow a specific user
  async getUserFollowers(userId: number, filters?: FollowFilters): Promise<SocialApiResult<FollowListResponse>> {
    try {
      const params = new URLSearchParams()
      params.append('user_id', String(userId))
      
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<FollowListResponse> = await axios.get(
        `${this.baseURL}/user-followers?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Get users that a specific user follows
  async getUserFollowing(userId: number, filters?: FollowFilters): Promise<SocialApiResult<FollowListResponse>> {
    try {
      const params = new URLSearchParams()
      params.append('user_id', String(userId))
      
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<FollowListResponse> = await axios.get(
        `${this.baseURL}/user-following?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Bulk operations
  async bulkUnfollow(userIds: number[]): Promise<SocialApiResult<{ success: boolean; unfollowed_count: number; message: string }>> {
    try {
      const response = await axios.post(`${this.baseURL}/bulk/unfollow`, {
        user_ids: userIds
      })
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async bulkMute(followIds: number[]): Promise<SocialApiResult<{ success: boolean; muted_count: number; message: string }>> {
    try {
      const response = await axios.post(`${this.baseURL}/bulk/mute`, {
        follow_ids: followIds
      })
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Error handling
  private handleError(error: any): SocialApiError {
    if (error.response?.data) {
      return {
        success: false,
        message: error.response.data.message || 'An error occurred',
        errors: error.response.data.errors
      }
    }
    
    return {
      success: false,
      message: error.message || 'Network error occurred'
    }
  }
}

export const followService = new FollowService()
export default followService 