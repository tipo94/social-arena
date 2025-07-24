<template>
  <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-4 animate-pulse">
    <!-- Header -->
    <div class="flex items-center space-x-3 mb-4">
      <div class="w-10 h-10 bg-gray-300 rounded-full"></div>
      <div class="flex-1">
        <div class="h-4 bg-gray-300 rounded w-32 mb-2"></div>
        <div class="h-3 bg-gray-300 rounded w-24"></div>
      </div>
      <div class="w-6 h-6 bg-gray-300 rounded"></div>
    </div>

    <!-- Content -->
    <div class="space-y-3 mb-4">
      <div class="h-4 bg-gray-300 rounded w-full"></div>
      <div class="h-4 bg-gray-300 rounded w-4/5"></div>
      <div class="h-4 bg-gray-300 rounded w-3/5" v-if="variant === 'detailed'"></div>
    </div>

    <!-- Media placeholder (conditional) -->
    <div 
      v-if="showMedia"
      class="w-full h-64 bg-gray-300 rounded-lg mb-4"
    ></div>

    <!-- Actions -->
    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
      <div class="flex space-x-6">
        <div class="flex items-center space-x-2">
          <div class="w-5 h-5 bg-gray-300 rounded"></div>
          <div class="h-3 bg-gray-300 rounded w-8"></div>
        </div>
        <div class="flex items-center space-x-2">
          <div class="w-5 h-5 bg-gray-300 rounded"></div>
          <div class="h-3 bg-gray-300 rounded w-8"></div>
        </div>
        <div class="flex items-center space-x-2">
          <div class="w-5 h-5 bg-gray-300 rounded"></div>
          <div class="h-3 bg-gray-300 rounded w-8"></div>
        </div>
      </div>
      <div class="w-5 h-5 bg-gray-300 rounded"></div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Props {
  variant?: 'simple' | 'detailed'
  showMedia?: boolean
}

withDefaults(defineProps<Props>(), {
  variant: 'simple',
  showMedia: false
})
</script> 