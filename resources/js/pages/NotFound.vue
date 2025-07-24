<template>
  <div class="page-container">
    <div class="content-wrapper">
      <div class="max-w-md w-full mx-auto text-center">
        <div>
          <h1 class="text-9xl font-bold text-neutral-300">404</h1>
          <h2 class="mt-4 text-3xl font-bold text-neutral-900">Page not found</h2>
          <p class="mt-2 text-lg text-neutral-600">
            Sorry, we couldn't find the page you're looking for.
          </p>
        </div>
        <div class="mt-6">
          <RouterLink
            to="/"
            class="btn btn-primary"
          >
            <svg class="-ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
            Back to home
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
// 404 page logic
</script> 