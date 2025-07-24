import axios from 'axios'
import type { AxiosResponse } from 'axios'
import type {
  Friendship,
  FriendshipApiResponse,
  FriendshipListResponse,
  FriendshipStatsResponse,
  FriendSuggestionsResponse,
  SendFriendRequestData,
  FriendshipFilters,
  FriendshipStatus,
  SocialApiResult,
  SocialApiError
} from '@/types/social'
import type { User } from '@/types/auth'

class FriendshipService {
  private baseURL = '/api/friendships'

  // Friend request operations
  async sendFriendRequest(data: SendFriendRequestData): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.post(
        `${this.baseURL}/send-request`,
        data
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async acceptFriendRequest(friendshipId: number): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.patch(
        `${this.baseURL}/${friendshipId}/accept`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async declineFriendRequest(friendshipId: number): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.patch(
        `${this.baseURL}/${friendshipId}/decline`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async cancelFriendRequest(friendshipId: number): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.delete(
        `${this.baseURL}/${friendshipId}/cancel`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async unfriend(friendshipId: number): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.delete(
        `${this.baseURL}/${friendshipId}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async blockUser(userId: number): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.post(
        `${this.baseURL}/block`,
        { user_id: userId }
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async unblockUser(friendshipId: number): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.patch(
        `${this.baseURL}/${friendshipId}/unblock`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Get friendship lists
  async getFriends(filters?: FriendshipFilters): Promise<SocialApiResult<FriendshipListResponse>> {
    try {
      const params = new URLSearchParams()
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<FriendshipListResponse> = await axios.get(
        `${this.baseURL}/friends?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getPendingRequests(type: 'sent' | 'received' = 'received', filters?: FriendshipFilters): Promise<SocialApiResult<FriendshipListResponse>> {
    try {
      const params = new URLSearchParams()
      params.append('type', type)
      
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<FriendshipListResponse> = await axios.get(
        `${this.baseURL}/pending?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async getBlockedUsers(filters?: FriendshipFilters): Promise<SocialApiResult<FriendshipListResponse>> {
    try {
      const params = new URLSearchParams()
      if (filters) {
        Object.entries(filters).forEach(([key, value]) => {
          if (value !== undefined && value !== null) {
            params.append(key, String(value))
          }
        })
      }

      const response: AxiosResponse<FriendshipListResponse> = await axios.get(
        `${this.baseURL}/blocked?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Mutual friends
  async getMutualFriends(userId: number, filters?: FriendshipFilters): Promise<SocialApiResult<FriendshipListResponse>> {
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

      const response: AxiosResponse<FriendshipListResponse> = await axios.get(
        `${this.baseURL}/mutual?${params.toString()}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Friend suggestions
  async getFriendSuggestions(limit: number = 10): Promise<SocialApiResult<FriendSuggestionsResponse>> {
    try {
      const response: AxiosResponse<FriendSuggestionsResponse> = await axios.get(
        `${this.baseURL}/suggestions?limit=${limit}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Friendship statistics
  async getFriendshipStats(): Promise<SocialApiResult<FriendshipStatsResponse>> {
    try {
      const response: AxiosResponse<FriendshipStatsResponse> = await axios.get(
        `${this.baseURL}/statistics`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Get specific friendship
  async getFriendship(friendshipId: number): Promise<SocialApiResult<FriendshipApiResponse>> {
    try {
      const response: AxiosResponse<FriendshipApiResponse> = await axios.get(
        `${this.baseURL}/${friendshipId}`
      )
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Check friendship status with a user
  async getFriendshipStatus(userId: number): Promise<FriendshipStatus | null> {
    try {
      const response = await axios.get(`${this.baseURL}/status/${userId}`)
      return response.data.data?.status || null
    } catch (error) {
      console.error('Error checking friendship status:', error)
      return null
    }
  }

  // Convenience methods
  async isFriend(userId: number): Promise<boolean> {
    const status = await this.getFriendshipStatus(userId)
    return status === 'accepted'
  }

  async hasPendingRequest(userId: number): Promise<boolean> {
    const status = await this.getFriendshipStatus(userId)
    return status === 'pending'
  }

  async isBlocked(userId: number): Promise<boolean> {
    const status = await this.getFriendshipStatus(userId)
    return status === 'blocked'
  }

  // Bulk operations
  async bulkAcceptRequests(friendshipIds: number[]): Promise<SocialApiResult<{ success: boolean; accepted_count: number; message: string }>> {
    try {
      const response = await axios.post(`${this.baseURL}/bulk/accept`, {
        friendship_ids: friendshipIds
      })
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async bulkDeclineRequests(friendshipIds: number[]): Promise<SocialApiResult<{ success: boolean; declined_count: number; message: string }>> {
    try {
      const response = await axios.post(`${this.baseURL}/bulk/decline`, {
        friendship_ids: friendshipIds
      })
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  async bulkUnfriend(friendshipIds: number[]): Promise<SocialApiResult<{ success: boolean; unfriended_count: number; message: string }>> {
    try {
      const response = await axios.post(`${this.baseURL}/bulk/unfriend`, {
        friendship_ids: friendshipIds
      })
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Search users (for sending friend requests)
  async searchUsers(query: string, page: number = 1, limit: number = 20): Promise<SocialApiResult<{ success: boolean; data: User[]; meta: any }>> {
    try {
      const params = new URLSearchParams()
      params.append('q', query)
      params.append('page', String(page))
      params.append('limit', String(limit))

      const response = await axios.get(`${this.baseURL}/search-users?${params.toString()}`)
      return response.data
    } catch (error: any) {
      return this.handleError(error)
    }
  }

  // Get friendship history/timeline
  async getFriendshipHistory(userId: number): Promise<SocialApiResult<{ success: boolean; data: any[] }>> {
    try {
      const response = await axios.get(`${this.baseURL}/history/${userId}`)
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

export const friendshipService = new FriendshipService()
export default friendshipService 