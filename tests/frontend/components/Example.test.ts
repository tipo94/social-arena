import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createRouter, createWebHistory } from 'vue-router'
import { createPinia } from 'pinia'

// Example component test
describe('Example Component Test', () => {
  const router = createRouter({
    history: createWebHistory(),
    routes: [
      { path: '/', component: { template: '<div>Home</div>' } }
    ]
  })

  const pinia = createPinia()

  it('should render properly', () => {
    const wrapper = mount({
      template: '<div class="test-component">Hello World</div>'
    }, {
      global: {
        plugins: [router, pinia]
      }
    })

    expect(wrapper.text()).toContain('Hello World')
    expect(wrapper.classes()).toContain('test-component')
  })

  it('should handle props correctly', () => {
    const TestComponent = {
      props: ['message'],
      template: '<div>{{ message }}</div>'
    }

    const wrapper = mount(TestComponent, {
      props: {
        message: 'Test Message'
      },
      global: {
        plugins: [router, pinia]
      }
    })

    expect(wrapper.text()).toBe('Test Message')
  })
}) 