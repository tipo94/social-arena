import type { 
  User, 
  LoginCredentials, 
  RegisterData, 
  AuthResponse, 
  ProfileUpdateData, 
  PasswordChangeData,
  PrivacySettings,
  ApiResponse 
} from '@/types/auth'

class AuthService {
  private baseURL = '/api'

  // Get CSRF token for Laravel Sanctum
  async getCsrfCookie(): Promise<void> {
    await fetch('/sanctum/csrf-cookie', {
      method: 'GET',
      credentials: 'include',
    })
  }

  // Authentication methods
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    await this.getCsrfCookie()
    
    const response = await fetch(`${this.baseURL}/auth/login`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(credentials),
    })

    return await response.json()
  }

  async register(data: RegisterData): Promise<AuthResponse> {
    await this.getCsrfCookie()
    
    const response = await fetch(`${this.baseURL}/auth/register`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(data),
    })

    return await response.json()
  }

  async logout(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/auth/logout`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async logoutAll(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/auth/logout-all`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async getCurrentUser(): Promise<AuthResponse> {
    const response = await fetch(`${this.baseURL}/auth/me`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  // Password management
  async changePassword(data: PasswordChangeData): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/auth/change-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(data),
    })

    return await response.json()
  }

  async forgotPassword(email: string): Promise<ApiResponse> {
    await this.getCsrfCookie()
    
    const response = await fetch(`${this.baseURL}/auth/forgot-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ email }),
    })

    return await response.json()
  }

  async resetPassword(data: {
    email: string
    token: string
    password: string
    password_confirmation: string
  }): Promise<ApiResponse> {
    await this.getCsrfCookie()
    
    const response = await fetch(`${this.baseURL}/auth/reset-password`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(data),
    })

    return await response.json()
  }

  // Email verification
  async resendVerification(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/auth/resend-verification`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  // Profile management
  async getProfile(userId?: number): Promise<ApiResponse<{ user: User }>> {
    const url = userId 
      ? `${this.baseURL}/users/profile/${userId}`
      : `${this.baseURL}/users/profile`
    
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async updateProfile(data: ProfileUpdateData): Promise<ApiResponse<{ user: User }>> {
    const response = await fetch(`${this.baseURL}/users/profile`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(data),
    })

    return await response.json()
  }

  async updateAvatar(file: File): Promise<ApiResponse> {
    const formData = new FormData()
    formData.append('avatar', file)

    const response = await fetch(`${this.baseURL}/users/profile/avatar`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: formData,
    })

    return await response.json()
  }

  async deleteAvatar(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/profile/avatar`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async updateCoverImage(file: File): Promise<ApiResponse> {
    const formData = new FormData()
    formData.append('cover_image', file)

    const response = await fetch(`${this.baseURL}/users/profile/cover-image`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: formData,
    })

    return await response.json()
  }

  async deleteCoverImage(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/profile/cover-image`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  // Privacy settings
  async getPrivacySettings(): Promise<ApiResponse<PrivacySettings>> {
    const response = await fetch(`${this.baseURL}/users/privacy`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async updatePrivacySettings(settings: Partial<PrivacySettings>): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/privacy`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify(settings),
    })

    return await response.json()
  }

  // Username/email availability check
  async checkUsername(username: string): Promise<ApiResponse<{ available: boolean }>> {
    const response = await fetch(`${this.baseURL}/auth/check-username`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ username }),
    })

    return await response.json()
  }

  async checkEmail(email: string): Promise<ApiResponse<{ available: boolean }>> {
    const response = await fetch(`${this.baseURL}/auth/check-email`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ email }),
    })

    return await response.json()
  }

  // Social authentication
  getSocialLoginUrl(provider: 'google' | 'github'): string {
    return `${this.baseURL}/auth/social/redirect/${provider}`
  }

  async getSocialAccount(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/auth/social/account`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async unlinkSocialAccount(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/auth/social/unlink`, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  // Account deletion
  async getDeletionInfo(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/account/deletion/info`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async getDeletionStatus(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/account/deletion/status`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async requestDeletion(password: string, reason?: string): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/account/deletion/request`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({
        password,
        reason,
        confirmation: true,
      }),
    })

    return await response.json()
  }

  async cancelDeletion(): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/account/deletion/cancel`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
      },
      credentials: 'include',
    })

    return await response.json()
  }

  async exportData(password: string): Promise<ApiResponse> {
    const response = await fetch(`${this.baseURL}/users/account/export-data`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      credentials: 'include',
      body: JSON.stringify({ password }),
    })

    return await response.json()
  }
}

export const authService = new AuthService()
export default authService 