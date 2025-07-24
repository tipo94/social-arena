<template>
  <div 
    ref="target" 
    :class="targetClass"
  >
    <slot v-if="isIntersecting" />
    <slot v-else name="placeholder">
      <div class="flex justify-center py-4">
        <div class="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600"></div>
      </div>
    </slot>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue'

interface Props {
  threshold?: number
  rootMargin?: string
  targetClass?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  threshold: 0.1,
  rootMargin: '100px',
  targetClass: '',
  disabled: false
})

interface Emits {
  (e: 'intersect'): void
  (e: 'enter'): void
  (e: 'leave'): void
}

const emit = defineEmits<Emits>()

const target = ref<HTMLElement>()
const isIntersecting = ref(false)
let observer: IntersectionObserver | null = null

const createObserver = () => {
  if (!target.value || props.disabled) return

  const options: IntersectionObserverInit = {
    threshold: props.threshold,
    rootMargin: props.rootMargin
  }

  observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      const wasIntersecting = isIntersecting.value
      isIntersecting.value = entry.isIntersecting

      if (entry.isIntersecting) {
        emit('intersect')
        if (!wasIntersecting) {
          emit('enter')
        }
      } else if (wasIntersecting) {
        emit('leave')
      }
    })
  }, options)

  observer.observe(target.value)
}

const destroyObserver = () => {
  if (observer) {
    observer.disconnect()
    observer = null
  }
}

onMounted(() => {
  createObserver()
})

onBeforeUnmount(() => {
  destroyObserver()
})

watch(() => props.disabled, (disabled) => {
  if (disabled) {
    destroyObserver()
    isIntersecting.value = true
  } else {
    createObserver()
  }
}, { immediate: true })
</script> 