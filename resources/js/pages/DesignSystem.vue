<template>
  <div class="page-container">
    <div class="content-wrapper">
      <div class="mb-8">
        <h1 class="section-header">AI-Book Design System</h1>
        <p class="section-subtitle">
          A comprehensive showcase of all available components and styles for the AI-Book platform.
        </p>
      </div>

      <!-- Buttons Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Button Components</h2>
        
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Button Variants</h3>
          <div class="flex flex-wrap gap-4">
            <button class="btn btn-primary">Primary</button>
            <button class="btn btn-secondary">Secondary</button>
            <button class="btn btn-accent">Accent</button>
            <button class="btn btn-ghost">Ghost</button>
            <button class="btn btn-danger">Danger</button>
            <button class="btn" disabled>Disabled</button>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Button Sizes</h3>
          <div class="flex flex-wrap items-center gap-4">
            <button class="btn btn-primary btn-xs">Extra Small</button>
            <button class="btn btn-primary btn-sm">Small</button>
            <button class="btn btn-primary">Default</button>
            <button class="btn btn-primary btn-lg">Large</button>
            <button class="btn btn-primary btn-xl">Extra Large</button>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Icon Buttons</h3>
          <div class="flex flex-wrap items-center gap-4">
            <button class="btn btn-primary btn-icon-sm">❤️</button>
            <button class="btn btn-secondary btn-icon">📚</button>
            <button class="btn btn-accent btn-icon">🔗</button>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Social Interaction Buttons</h3>
          <div class="flex flex-wrap gap-4">
            <button class="interaction-btn like-btn">❤️ Like</button>
            <button class="interaction-btn like-btn like-btn-active">❤️ Liked</button>
            <button class="interaction-btn share-btn">🔗 Share</button>
            <button class="interaction-btn">💬 Comment</button>
            <button class="btn follow-btn btn-sm">Follow</button>
            <button class="btn following-btn btn-sm">Following</button>
          </div>
        </div>
      </section>

      <!-- Cards Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Card Components</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          <div class="card">
            <div class="card-body">
              <h3 class="text-lg font-semibold text-neutral-900 mb-2">Basic Card</h3>
              <p class="text-neutral-600">A simple card with just body content.</p>
            </div>
          </div>

          <div class="card card-hover">
            <div class="card-body">
              <h3 class="text-lg font-semibold text-neutral-900 mb-2">Hover Card</h3>
              <p class="text-neutral-600">This card has hover effects. Try hovering over it!</p>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <h3 class="text-lg font-semibold text-neutral-900">Card with Header</h3>
            </div>
            <div class="card-body">
              <p class="text-neutral-600">This card includes a header section.</p>
            </div>
            <div class="card-footer">
              <button class="btn btn-primary btn-sm">Action</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Avatars Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Avatar Components</h2>
        
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Avatar Sizes</h3>
          <div class="flex flex-wrap items-center gap-4">
            <div class="avatar avatar-xs">XS</div>
            <div class="avatar avatar-sm">SM</div>
            <div class="avatar avatar-md">MD</div>
            <div class="avatar avatar-lg">LG</div>
            <div class="avatar avatar-xl">XL</div>
            <div class="avatar avatar-2xl">2XL</div>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Avatar with Image</h3>
          <div class="flex flex-wrap items-center gap-4">
            <div class="avatar avatar-sm">
              <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=32&h=32&fit=crop&crop=face" alt="User" />
            </div>
            <div class="avatar avatar-md">
              <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=48&h=48&fit=crop&crop=face" alt="User" />
            </div>
            <div class="avatar avatar-lg avatar-online">
              <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=64&h=64&fit=crop&crop=face" alt="User" />
            </div>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Avatar Group</h3>
          <div class="avatar-group">
            <div class="avatar avatar-md">
              <img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?w=48&h=48&fit=crop&crop=face" alt="User 1" />
            </div>
            <div class="avatar avatar-md">
              <img src="https://images.unsplash.com/photo-1494790108755-2616b612c2c2?w=48&h=48&fit=crop&crop=face" alt="User 2" />
            </div>
            <div class="avatar avatar-md">
              <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=48&h=48&fit=crop&crop=face" alt="User 3" />
            </div>
            <div class="avatar avatar-md">+5</div>
          </div>
        </div>
      </section>

      <!-- Badges Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Badge Components</h2>
        
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Badge Variants</h3>
          <div class="flex flex-wrap gap-4">
            <span class="badge badge-primary">Primary</span>
            <span class="badge badge-secondary">Secondary</span>
            <span class="badge badge-accent">Accent</span>
            <span class="badge badge-success">Success</span>
            <span class="badge badge-warning">Warning</span>
            <span class="badge badge-danger">Danger</span>
            <span class="badge badge-info">Info</span>
            <span class="badge badge-neutral">Neutral</span>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Dot Badges</h3>
          <div class="flex flex-wrap gap-4">
            <span class="badge badge-success badge-dot">Online</span>
            <span class="badge badge-warning badge-dot">Away</span>
            <span class="badge badge-danger badge-dot">Busy</span>
            <span class="badge badge-neutral badge-dot">Offline</span>
          </div>
        </div>
      </section>

      <!-- Alerts Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Alert Components</h2>
        
        <div class="space-y-4">
          <div class="alert alert-success">
            <div class="alert-icon">✅</div>
            <div class="alert-content">
              <div class="alert-title">Success!</div>
              <div class="alert-description">Your book has been successfully added to your reading list.</div>
            </div>
          </div>

          <div class="alert alert-warning">
            <div class="alert-icon">⚠️</div>
            <div class="alert-content">
              <div class="alert-title">Warning</div>
              <div class="alert-description">You have 3 overdue books in your reading list.</div>
            </div>
          </div>

          <div class="alert alert-danger">
            <div class="alert-icon">❌</div>
            <div class="alert-content">
              <div class="alert-title">Error</div>
              <div class="alert-description">Failed to load your recommendations. Please try again.</div>
            </div>
          </div>

          <div class="alert alert-info">
            <div class="alert-icon">ℹ️</div>
            <div class="alert-content">
              <div class="alert-title">New Feature</div>
              <div class="alert-description">AI-powered book matching is now available in beta!</div>
            </div>
          </div>
        </div>
      </section>

      <!-- Navigation Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Navigation Components</h2>
        
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Horizontal Navigation</h3>
          <nav class="nav">
            <a href="#" class="nav-link nav-link-active">Home</a>
            <a href="#" class="nav-link">Discover</a>
            <a href="#" class="nav-link">My Books</a>
            <a href="#" class="nav-link">Social</a>
            <a href="#" class="nav-link">Profile</a>
          </nav>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Vertical Navigation</h3>
          <nav class="nav nav-vertical max-w-xs">
            <a href="#" class="nav-link nav-link-active">📚 My Library</a>
            <a href="#" class="nav-link">🔍 Discover</a>
            <a href="#" class="nav-link">👥 Friends</a>
            <a href="#" class="nav-link">📈 Statistics</a>
            <a href="#" class="nav-link">⚙️ Settings</a>
          </nav>
        </div>
      </section>

      <!-- Forms Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Form Components</h2>
        
        <div class="max-w-md">
          <div class="form-group">
            <label class="form-label" for="book-title">Book Title</label>
            <input type="text" id="book-title" class="form-input" placeholder="Enter book title..." />
            <div class="form-help">The title of the book you want to add to your list.</div>
          </div>

          <div class="form-group">
            <label class="form-label" for="author">Author</label>
            <input type="text" id="author" class="form-input" placeholder="Enter author name..." />
          </div>

          <div class="form-group">
            <label class="form-label" for="rating">Rating</label>
            <input type="number" id="rating" class="form-input" min="1" max="5" />
            <div class="form-error">Please enter a rating between 1 and 5.</div>
          </div>
        </div>
      </section>

      <!-- Loading Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Loading Components</h2>
        
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Spinners</h3>
          <div class="flex items-center gap-4">
            <div class="spinner spinner-sm"></div>
            <div class="spinner"></div>
            <div class="spinner spinner-lg"></div>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Skeleton Loading</h3>
          <div class="card max-w-sm">
            <div class="card-body">
              <div class="flex items-center gap-4 mb-4">
                <div class="skeleton skeleton-avatar avatar-md"></div>
                <div class="flex-1">
                  <div class="skeleton skeleton-text"></div>
                  <div class="skeleton skeleton-text"></div>
                </div>
              </div>
              <div class="skeleton skeleton-text"></div>
              <div class="skeleton skeleton-text"></div>
              <div class="skeleton skeleton-text"></div>
            </div>
          </div>
        </div>
      </section>

      <!-- Layout Section -->
      <section class="mb-12">
        <h2 class="text-2xl font-bold text-neutral-900 mb-6">Layout Components</h2>
        
        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Dividers</h3>
          <div>
            <p class="text-neutral-600">Content above divider</p>
            <div class="divider"></div>
            <p class="text-neutral-600">Content below divider</p>
          </div>
        </div>

        <div class="mb-6">
          <h3 class="text-lg font-semibold text-neutral-800 mb-4">Vertical Divider</h3>
          <div class="flex items-center max-w-md">
            <p class="text-neutral-600 flex-1">Left content</p>
            <div class="divider-vertical"></div>
            <p class="text-neutral-600 flex-1">Right content</p>
          </div>
        </div>
      </section>

    </div>
  </div>
</template>

<script setup lang="ts">
// Design system showcase - no logic needed
</script> 