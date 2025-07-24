<template>
  <div class="page-container">
    <div class="content-wrapper">
      <div class="text-center">
        <h1 class="section-header text-neutral-900 sm:text-4xl">
          About AI-Book
        </h1>
        <p class="section-subtitle text-neutral-600">
          The future of social reading and book discovery
        </p>
      </div>

      <div class="mt-12 grid grid-cols-1 gap-8 lg:grid-cols-2">
        <div>
          <h2 class="text-2xl font-bold text-neutral-900">Our Mission</h2>
          <p class="mt-4 text-neutral-600">
            AI-Book aims to revolutionize how people discover, discuss, and connect through books. 
            By leveraging artificial intelligence and social networking, we create personalized reading 
            experiences that help users find their next favorite book and connect with like-minded readers 
            around the world.
          </p>
        </div>
        <div>
          <h2 class="text-2xl font-bold text-neutral-900">Technology Stack</h2>
          <div class="mt-4 space-y-2">
            <div class="flex items-center">
              <span class="inline-block w-3 h-3 bg-accent-500 rounded-full mr-3"></span>
              <span class="text-neutral-600">Vue 3 with TypeScript</span>
            </div>
            <div class="flex items-center">
              <span class="inline-block w-3 h-3 bg-error rounded-full mr-3"></span>
              <span class="text-neutral-600">Laravel 11+ API</span>
            </div>
            <div class="flex items-center">
              <span class="inline-block w-3 h-3 bg-primary-500 rounded-full mr-3"></span>
              <span class="text-neutral-600">Pinia State Management</span>
            </div>
            <div class="flex items-center">
              <span class="inline-block w-3 h-3 bg-secondary-500 rounded-full mr-3"></span>
              <span class="text-neutral-600">Vite Build System</span>
            </div>
            <div class="flex items-center">
              <span class="inline-block w-3 h-3 bg-warning rounded-full mr-3"></span>
              <span class="text-neutral-600">Laravel Sanctum Authentication</span>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-12">
        <h2 class="text-2xl font-bold text-neutral-900 text-center">Features</h2>
        <div class="mt-8 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
          <div class="card card-hover">
            <div class="card-body">
              <h3 class="text-lg font-semibold text-neutral-900">Smart Recommendations</h3>
              <p class="mt-2 text-neutral-600">
                Our AI analyzes your reading history, preferences, and behavior to suggest books 
                you'll love, tailored specifically to your taste.
              </p>
            </div>
          </div>
          <div class="card card-hover">
            <div class="card-body">
              <h3 class="text-lg font-semibold text-neutral-900">Social Features</h3>
              <p class="mt-2 text-neutral-600">
                Connect with friends, join reading groups, share reviews, and discover what 
                others are reading in your network.
              </p>
            </div>
          </div>
          <div class="card card-hover">
            <div class="card-body">
              <h3 class="text-lg font-semibold text-neutral-900">Reading Tracking</h3>
              <p class="mt-2 text-neutral-600">
                Track your reading progress, set goals, manage your to-read list, and get 
                insights into your reading habits.
              </p>
            </div>
          </div>
        </div>
      </div>

      <div class="mt-12 text-center">
        <p class="text-neutral-600">
          Ready to transform your reading experience?
        </p>
        <div class="mt-4">
          <RouterLink
            to="/register"
            class="btn btn-primary btn-lg"
          >
            Join AI-Book Today
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// About page logic
</script> 