import { createApp } from 'vue'
import App from './App.vue'
import router from './router'
import { pinia, initializeStores } from './stores'

// Bootstrap axios configuration (must be imported before stores)
import './bootstrap'

// CSS
import '../css/app.css'

// Create the Vue application
const app = createApp(App)

// Use Pinia for state management
app.use(pinia)

// Use Vue Router
app.use(router)

// Initialize stores and mount the application
initializeStores().then(() => {
  // Mount the application after stores are initialized
  app.mount('#app')
}).catch((error) => {
  console.error('Failed to initialize application:', error)
  // Mount anyway to show error state
  app.mount('#app')
}) 