<template>
  <div class="page-container">
    <div class="content-wrapper">
      <!-- Hero Section -->
      <section class="text-center mb-16">
        <div class="mb-8">
          <span class="badge badge-primary mb-4 inline-block">New Platform</span>
          <h1 class="text-4xl font-bold text-neutral-900 sm:text-5xl md:text-6xl mb-4">
            Welcome to AI-Book
          </h1>
          <p class="text-lg text-neutral-600 max-w-3xl mx-auto mb-8">
            The social networking platform for book lovers, powered by AI recommendations and community discussions.
            Connect with fellow readers and discover your next favorite book.
          </p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-8">
          <RouterLink to="/register" class="btn btn-primary btn-lg">
            📚 Start Reading
          </RouterLink>
          <RouterLink to="/about" class="btn btn-secondary btn-lg">
            Learn More
          </RouterLink>
        </div>

        <!-- User Stats Preview -->
        <div class="flex items-center justify-center gap-8 text-sm text-neutral-600">
          <div class="flex items-center gap-2">
            <div class="avatar-group">
              <div class="avatar avatar-xs">
                <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=24&h=24&fit=crop&crop=face" alt="User" />
              </div>
              <div class="avatar avatar-xs">
                <img src="https://images.unsplash.com/photo-1494790108755-2616b612c2c2?w=24&h=24&fit=crop&crop=face" alt="User" />
              </div>
              <div class="avatar avatar-xs">
                <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=24&h=24&fit=crop&crop=face" alt="User" />
              </div>
            </div>
            <span>1,000+ readers</span>
          </div>
          <span class="text-neutral-400">•</span>
          <span>10,000+ books tracked</span>
          <span class="text-neutral-400">•</span>
          <span>500+ discussions daily</span>
        </div>
      </section>

      <!-- Features Section -->
      <section class="mb-16">
        <div class="text-center mb-12">
          <h2 class="text-3xl font-bold text-neutral-900 mb-4">
            Everything you need for social reading
          </h2>
          <p class="text-lg text-neutral-600 max-w-2xl mx-auto">
            From AI-powered recommendations to vibrant community discussions, 
            AI-Book brings all aspects of reading together in one place.
          </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          <div class="card card-hover text-center">
            <div class="card-body">
              <div class="flex items-center justify-center size-16 rounded-2xl bg-primary-100 text-primary-600 mx-auto mb-6 text-2xl">
                📚
              </div>
              <h3 class="text-xl font-semibold text-neutral-900 mb-3">Smart Discovery</h3>
              <p class="text-neutral-600 mb-4">
                Our AI analyzes your reading history, preferences, and behavior to suggest books 
                you'll love, tailored specifically to your taste.
              </p>
              <div class="flex items-center justify-center gap-2">
                <span class="badge badge-primary">AI-Powered</span>
                <span class="badge badge-accent">Personalized</span>
              </div>
            </div>
          </div>
          
          <div class="card card-hover text-center">
            <div class="card-body">
              <div class="flex items-center justify-center size-16 rounded-2xl bg-secondary-100 text-secondary-600 mx-auto mb-6 text-2xl">
                👥
              </div>
              <h3 class="text-xl font-semibold text-neutral-900 mb-3">Social Reading</h3>
              <p class="text-neutral-600 mb-4">
                Connect with friends, join reading groups, share reviews, and discover what 
                others are reading in your network.
              </p>
              <div class="flex items-center justify-center gap-2">
                <span class="badge badge-secondary">Community</span>
                <span class="badge badge-info">Social</span>
              </div>
            </div>
          </div>
          
          <div class="card card-hover text-center">
            <div class="card-body">
              <div class="flex items-center justify-center size-16 rounded-2xl bg-accent-100 text-accent-600 mx-auto mb-6 text-2xl">
                📊
              </div>
              <h3 class="text-xl font-semibold text-neutral-900 mb-3">Reading Analytics</h3>
              <p class="text-neutral-600 mb-4">
                Track your reading progress, set goals, manage your to-read list, and get 
                insights into your reading habits.
              </p>
              <div class="flex items-center justify-center gap-2">
                <span class="badge badge-accent">Analytics</span>
                <span class="badge badge-warning">Goals</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Social Proof Section -->
      <section class="mb-16">
        <div class="card bg-gradient-to-r from-primary-50 to-secondary-50 border-primary-200">
          <div class="card-body text-center">
            <h3 class="text-2xl font-bold text-neutral-900 mb-4">
              Join thousands of book lovers
            </h3>
            <p class="text-neutral-600 mb-6 max-w-2xl mx-auto">
              From casual readers to literary critics, our community welcomes everyone who loves books.
            </p>
            
            <!-- Mock User Testimonials -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
              <div class="flex items-center gap-3 text-left">
                <div class="avatar avatar-md avatar-online">
                  <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=48&h=48&fit=crop&crop=face" alt="Sarah" />
                </div>
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-semibold text-neutral-900">Sarah Chen</span>
                    <span class="badge badge-success badge-dot">Active</span>
                  </div>
                  <p class="text-sm text-neutral-600">"Found 12 amazing books this month!"</p>
                </div>
              </div>
              
              <div class="flex items-center gap-3 text-left">
                <div class="avatar avatar-md">
                  <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=48&h=48&fit=crop&crop=face" alt="Marcus" />
                </div>
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-semibold text-neutral-900">Marcus Wright</span>
                    <span class="badge badge-primary">Top Reviewer</span>
                  </div>
                  <p class="text-sm text-neutral-600">"The AI recommendations are spot-on."</p>
                </div>
              </div>
              
              <div class="flex items-center gap-3 text-left">
                <div class="avatar avatar-md">
                  <img src="https://images.unsplash.com/photo-1494790108755-2616b612c2c2?w=48&h=48&fit=crop&crop=face" alt="Emily" />
                </div>
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="font-semibold text-neutral-900">Emily Rodriguez</span>
                    <span class="badge badge-warning badge-dot">Reading</span>
                  </div>
                  <p class="text-sm text-neutral-600">"Love the book club discussions!"</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- CTA Section -->
      <section class="text-center">
        <div class="alert alert-info max-w-2xl mx-auto">
          <div class="alert-icon">🚧</div>
          <div class="alert-content">
            <div class="alert-title">Platform in Development</div>
            <div class="alert-description">
              AI-Book is currently under active development. This demo showcases our design system 
              and planned features. Full functionality will be implemented throughout the development process.
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
// Enhanced home page showcasing design system components
</script> 