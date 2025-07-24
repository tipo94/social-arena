import axios from 'axios';

// Set up Axios defaults
window.axios = axios;

// Global Axios configuration for Laravel Sanctum
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['Content-Type'] = 'application/json';

// Enable credentials for CSRF cookies
axios.defaults.withCredentials = true;

// Set base URL
axios.defaults.baseURL = window.location.origin;

// Helper function to get cookie value
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}

// Request interceptor to handle CSRF
axios.interceptors.request.use(
    (config) => {
        // Get CSRF token from cookie
        const csrfToken = getCookie('XSRF-TOKEN');
        if (csrfToken) {
            config.headers['X-XSRF-TOKEN'] = decodeURIComponent(csrfToken);
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// Response interceptor for error handling
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            // 401 on /api/auth/me is expected when checking for existing session
            if (!error.config?.url?.includes('/api/auth/me')) {
                console.warn('Authentication failed');
            }
        }
        
        if (error.response?.status === 419) {
            // CSRF token mismatch - refresh page or get new token
            console.warn('CSRF token mismatch');
        }
        
        return Promise.reject(error);
    }
);
