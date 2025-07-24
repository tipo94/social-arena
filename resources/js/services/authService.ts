import type { 
  LoginCredentials, 
  RegisterData, 
  AuthResponse, 
  ApiResponse, 
  PasswordChangeData, 
  ResetPasswordData,
  PrivacySettings
} from '../types/auth'

class AuthService {
  private baseURL = '/api'

  // Get CSRF token for Laravel Sanctum
  async getCsrfCookie(): Promise<void> {
    await window.axios.get('/sanctum/csrf-cookie')
  }

  // Authentication methods
  async login(credentials: LoginCredentials): Promise<AuthResponse> {
    // Get CSRF cookie first
    await this.getCsrfCookie()
    
    const response = await window.axios.post(`${this.baseURL}/auth/login`, credentials)
    return response.data
  }

  async register(data: RegisterData): Promise<AuthResponse> {
    // Get CSRF cookie first
    await this.getCsrfCookie()
    
    const response = await window.axios.post(`${this.baseURL}/auth/register`, data)
    return response.data
  }

  async logout(): Promise<ApiResponse> {
    const response = await window.axios.post(`${this.baseURL}/auth/logout`)
    return response.data
  }

  async logoutAll(): Promise<ApiResponse> {
    const response = await window.axios.post(`${this.baseURL}/auth/logout-all`)
    return response.data
  }

  async getCurrentUser(): Promise<AuthResponse> {
    if (!window.axios) {
      throw new Error('Axios not initialized. Make sure bootstrap.js is imported before using authService.')
    }
    
    const response = await window.axios.get(`${this.baseURL}/auth/me`)
    return response.data
  }

  // Password management
  async changePassword(data: PasswordChangeData): Promise<ApiResponse> {
    const response = await window.axios.post(`${this.baseURL}/auth/change-password`, data)
    return response.data
  }

  // Password reset
  async forgotPassword(email: string): Promise<ApiResponse> {
    await this.getCsrfCookie()
    
    const response = await window.axios.post(`${this.baseURL}/auth/forgot-password`, { email })
    return response.data
  }

  async resetPassword(data: ResetPasswordData): Promise<ApiResponse> {
    await this.getCsrfCookie()
    
    const response = await window.axios.post(`${this.baseURL}/auth/reset-password`, data)
    return response.data
  }

  // Email verification
  async resendVerification(): Promise<ApiResponse> {
    const response = await window.axios.post(`${this.baseURL}/auth/resend-verification`)
    return response.data
  }

  // Username/email availability check
  async checkUsername(username: string): Promise<ApiResponse<{ available: boolean }>> {
    const response = await window.axios.post(`${this.baseURL}/auth/check-username`, { username })
    return response.data
  }

  async checkEmail(email: string): Promise<ApiResponse<{ available: boolean }>> {
    const response = await window.axios.post(`${this.baseURL}/auth/check-email`, { email })
    return response.data
  }

  // Profile refresh
  async refreshProfile(): Promise<AuthResponse> {
    const response = await window.axios.post(`${this.baseURL}/auth/refresh`)
    return response.data
  }

  // Social authentication helpers
  getSocialLoginUrl(provider: string, redirectUrl?: string): string {
    const url = new URL(`${window.location.origin}${this.baseURL}/auth/social/redirect/${provider}`)
    if (redirectUrl) {
      url.searchParams.set('redirect_url', redirectUrl)
    }
    return url.toString()
  }

  // Utility methods
  isTokenExpired(token: string): boolean {
    try {
      const payload = JSON.parse(atob(token.split('.')[1]))
      return Date.now() >= payload.exp * 1000
    } catch {
      return true
    }
  }

  // Session management
  async validateSession(): Promise<boolean> {
    try {
      const response = await this.getCurrentUser()
      return response.success && !!response.data?.user
    } catch {
      return false
    }
  }

  // Helper to format user data for storage
  formatUserForStorage(user: any): any {
    return {
      id: user.id,
      name: user.name,
      email: user.email,
      username: user.username,
      avatar_url: user.avatar_url,
      email_verified_at: user.email_verified_at,
      role: user.role,
      is_premium: user.is_premium,
      profile: user.profile,
    }
  }

  // Error handling helper
  handleAuthError(error: any): string {
    if (error.response?.status === 401) {
      return 'Invalid credentials or session expired'
    }
    
    if (error.response?.status === 422) {
      const errors = error.response.data?.errors
      if (errors) {
        return Object.values(errors).flat().join(', ')
      }
    }
    
    if (error.response?.status === 419) {
      return 'Security token expired. Please refresh the page and try again.'
    }
    
    return error.response?.data?.message || error.message || 'An unexpected error occurred'
  }

  // Token validation
  async validateToken(token: string): Promise<boolean> {
    try {
      const response = await window.axios.get(`${this.baseURL}/auth/me`, {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      })
      return response.data.success
    } catch {
      return false
    }
  }

  // Check if user needs to verify email
  async checkVerificationStatus(): Promise<{ verified: boolean, message?: string }> {
    try {
      const response = await this.getCurrentUser()
      if (response.success && response.data?.user) {
        return {
          verified: !!response.data.user.email_verified_at,
          message: response.data.user.email_verified_at 
            ? undefined 
            : 'Please verify your email address to access all features.'
        }
      }
      return { verified: false, message: 'Unable to check verification status' }
    } catch {
      return { verified: false, message: 'Unable to check verification status' }
    }
  }

  // Get user permissions
  async getUserPermissions(): Promise<string[]> {
    try {
      const response = await this.getCurrentUser()
      if (response.success && response.data?.user) {
        return response.data.user.permissions || []
      }
      return []
    } catch {
      return []
    }
  }

  // Check if user has permission
  async hasPermission(permission: string): Promise<boolean> {
    const permissions = await this.getUserPermissions()
    return permissions.includes(permission) || permissions.includes('*')
  }

  // Get user role
  async getUserRole(): Promise<string | null> {
    try {
      const response = await this.getCurrentUser()
      if (response.success && response.data?.user) {
        return response.data.user.role
      }
      return null
    } catch {
      return null
    }
  }

  // Session timeout handling
  setupSessionTimeout(minutes: number = 30): void {
    const timeoutMs = minutes * 60 * 1000
    
    let timeoutId: number
    
    const resetTimeout = () => {
      clearTimeout(timeoutId)
      timeoutId = window.setTimeout(() => {
        this.logout().then(() => {
          window.location.href = '/login?reason=timeout'
        })
      }, timeoutMs)
    }
    
    // Reset timeout on user activity
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart']
    events.forEach(event => {
      document.addEventListener(event, resetTimeout, { passive: true })
    })
    
    // Initial timeout setup
    resetTimeout()
  }

  // Privacy settings methods
  async getPrivacySettings(): Promise<ApiResponse> {
    const response = await window.axios.get(`${this.baseURL}/users/privacy`)
    return response.data
  }

  async updatePrivacySettings(settings: PrivacySettings): Promise<ApiResponse> {
    const response = await window.axios.put(`${this.baseURL}/users/privacy`, settings)
    return response.data
  }
}

export default new AuthService() 