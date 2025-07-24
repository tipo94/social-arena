import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

// Import page components
import Home from '@/pages/Home.vue'
import About from '@/pages/About.vue'
import Login from '@/pages/auth/Login.vue'
import Register from '@/pages/auth/Register.vue'
import Profile from '@/pages/auth/Profile.vue'
import Dashboard from '@/pages/Dashboard.vue'
import People from '@/pages/People.vue'
import NotFound from '@/pages/NotFound.vue'
import DesignSystem from '@/pages/DesignSystem.vue'

// Define routes
const routes: Array<RouteRecordRaw> = [
  {
    path: '/',
    name: 'Home',
    component: Home,
    meta: {
      title: 'Home - AI-Book Social Network'
    }
  },
  {
    path: '/about',
    name: 'About', 
    component: About,
    meta: {
      title: 'About - AI-Book Social Network'
    }
  },
  // Authentication routes
  {
    path: '/auth/login',
    name: 'Login',
    component: Login,
    meta: {
      title: 'Login - AI-Book Social Network',
      guest: true
    }
  },
  {
    path: '/auth/register',
    name: 'Register',
    component: Register,
    meta: {
      title: 'Register - AI-Book Social Network', 
      guest: true
    }
  },
  {
    path: '/auth/forgot-password',
    name: 'ForgotPassword',
    component: () => import('@/pages/auth/ForgotPassword.vue'),
    meta: {
      title: 'Forgot Password - AI-Book Social Network',
      guest: true
    }
  },
  {
    path: '/auth/reset-password',
    name: 'ResetPassword',
    component: () => import('@/pages/auth/ResetPassword.vue'),
    meta: {
      title: 'Reset Password - AI-Book Social Network',
      guest: true
    }
  },
  // Profile routes
  {
    path: '/profile',
    name: 'MyProfile',
    component: Profile,
    meta: {
      title: 'My Profile - AI-Book Social Network',
      requiresAuth: true
    }
  },
  {
    path: '/profile/:userId',
    name: 'UserProfile',
    component: Profile,
    meta: {
      title: 'Profile - AI-Book Social Network',
      requiresAuth: true
    }
  },
  // Dashboard
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: Dashboard,
    meta: {
      title: 'Dashboard - AI-Book Social Network',
      requiresAuth: true
    }
  },
  // People
  {
    path: '/people',
    name: 'People',
    component: People,
    meta: {
      title: 'People - AI-Book Social Network',
      requiresAuth: true
    }
  },
  // Design system
  {
    path: '/design-system',
    name: 'DesignSystem',
    component: DesignSystem,
    meta: {
      title: 'Design System - AI-Book Social Network'
    }
  },
  // Legacy redirects for backward compatibility
  {
    path: '/login',
    redirect: '/auth/login'
  },
  {
    path: '/register',
    redirect: '/auth/register'
  },
  // 404 page
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: NotFound,
    meta: {
      title: 'Page Not Found - AI-Book Social Network'
    }
  }
]

// Create router instance
const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior(to, from, savedPosition) {
    if (savedPosition) {
      return savedPosition
    } else {
      return { top: 0 }
    }
  }
})

// Navigation guards
router.beforeEach(async (to, from, next) => {
  // Set page title
  if (to.meta.title) {
    document.title = to.meta.title as string
  }

  const authStore = useAuthStore()

  // Wait for session check if not already done
  if (!authStore.sessionChecked) {
    await authStore.checkSession()
  }

  // Check authentication requirements
  if (to.meta.requiresAuth && !authStore.isLoggedIn) {
    // User needs to be authenticated but isn't
    next({
      name: 'Login',
      query: { redirect: to.fullPath }
    })
    return
  }

  if (to.meta.guest && authStore.isLoggedIn) {
    // User is authenticated but trying to access guest-only pages
    const redirectPath = to.query.redirect as string || '/dashboard'
    next(redirectPath)
    return
  }

  next()
})

export default router 