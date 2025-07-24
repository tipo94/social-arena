<template>
  <div id="app">
    <!-- Navigation Header -->
    <header class="bg-white border-b border-neutral-200 shadow-soft">
      <div class="content-wrapper py-4">
        <nav class="flex items-center justify-between">
          <!-- Logo/Brand -->
          <div class="flex items-center gap-2">
            <div class="avatar avatar-sm bg-primary-500 text-white font-bold">
              AI
            </div>
            <RouterLink to="/" class="text-xl font-bold text-neutral-900 hover:text-primary-600 transition-colors">
              AI-Book
            </RouterLink>
          </div>

          <!-- Navigation Links -->
          <div class="nav">
            <RouterLink to="/" class="nav-link" active-class="nav-link-active">
              Home
            </RouterLink>
            <RouterLink to="/about" class="nav-link" active-class="nav-link-active">
              About
            </RouterLink>
            <RouterLink to="/dashboard" class="nav-link" active-class="nav-link-active">
              Dashboard
            </RouterLink>
            <RouterLink to="/design-system" class="nav-link" active-class="nav-link-active">
              Design System
            </RouterLink>
          </div>

          <!-- Action Buttons -->
          <div class="flex items-center gap-3">
            <RouterLink to="/login" class="btn btn-ghost btn-sm">
              Sign In
            </RouterLink>
            <RouterLink to="/register" class="btn btn-primary btn-sm">
              Get Started
            </RouterLink>
          </div>
        </nav>
      </div>
    </header>

    <!-- Main Content -->
    <main>
      <RouterView />
    </main>

    <!-- Footer -->
    <footer class="bg-neutral-50 border-t border-neutral-200 mt-16">
      <div class="content-wrapper py-8">
        <div class="text-center">
          <div class="flex items-center justify-center gap-2 mb-4">
            <div class="avatar avatar-xs bg-primary-500 text-white font-bold">
              AI
            </div>
            <span class="font-semibold text-neutral-900">AI-Book</span>
          </div>
          <p class="text-sm text-neutral-600 mb-4">
            The social networking platform for book lovers, powered by AI recommendations.
          </p>
          <div class="flex items-center justify-center gap-4 text-sm text-neutral-500">
            <a href="#" class="hover:text-primary-600 transition-colors">Privacy</a>
            <span>•</span>
            <a href="#" class="hover:text-primary-600 transition-colors">Terms</a>
            <span>•</span>
            <a href="#" class="hover:text-primary-600 transition-colors">Support</a>
          </div>
        </div>
      </div>
    </footer>
  </div>
</template>

<script setup lang="ts">
// Main App component - provides navigation and layout structure
</script>

<style>
/* Global app styles */
#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

main {
  flex: 1;
}
</style> 