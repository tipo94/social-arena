<template>
  <div class="space-y-4">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Book Review Details</h3>
    
    <!-- Book Title -->
    <div>
      <label for="book-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Book Title *
      </label>
      <input
        id="book-title"
        type="text"
        :value="metadata.book_title"
        @input="updateField('book_title', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="Enter the book title"
        required
      />
    </div>

    <!-- Book Author -->
    <div>
      <label for="book-author" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Author *
      </label>
      <input
        id="book-author"
        type="text"
        :value="metadata.book_author"
        @input="updateField('book_author', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="Enter the author's name"
        required
      />
    </div>

    <!-- Reading Status -->
    <div>
      <label for="reading-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Reading Status *
      </label>
      <select
        id="reading-status"
        :value="metadata.reading_status"
        @change="updateField('reading_status', ($event.target as HTMLSelectElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        required
      >
        <option value="">Select reading status</option>
        <option value="want_to_read">Want to Read</option>
        <option value="currently_reading">Currently Reading</option>
        <option value="finished">Finished</option>
        <option value="dnf">Did Not Finish</option>
      </select>
    </div>

    <!-- Rating -->
    <div>
      <label for="rating" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Rating (1-5 stars)
      </label>
      <div class="flex items-center space-x-1">
        <div
          v-for="star in 5"
          :key="star"
          @click="updateField('rating', star)"
          class="cursor-pointer text-2xl transition-colors"
          :class="star <= (metadata.rating || 0) ? 'text-yellow-400' : 'text-gray-300'"
        >
          ★
        </div>
        <button
          v-if="metadata.rating"
          @click="updateField('rating', undefined)"
          class="ml-2 text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
          Clear
        </button>
      </div>
    </div>

    <!-- Book Cover URL -->
    <div>
      <label for="book-cover" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Book Cover URL
      </label>
      <input
        id="book-cover"
        type="url"
        :value="metadata.book_cover_url"
        @input="updateField('book_cover_url', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="https://example.com/book-cover.jpg"
      />
    </div>

    <!-- ISBN -->
    <div>
      <label for="isbn" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        ISBN
      </label>
      <input
        id="isbn"
        type="text"
        :value="metadata.book_isbn"
        @input="updateField('book_isbn', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="978-0123456789"
      />
    </div>

    <!-- Reading Dates -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label for="reading-started" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Started Reading
        </label>
        <input
          id="reading-started"
          type="date"
          :value="metadata.reading_started_at"
          @input="updateField('reading_started_at', ($event.target as HTMLInputElement).value)"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        />
      </div>
      <div>
        <label for="reading-finished" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
          Finished Reading
        </label>
        <input
          id="reading-finished"
          type="date"
          :value="metadata.reading_finished_at"
          @input="updateField('reading_finished_at', ($event.target as HTMLInputElement).value)"
          class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        />
      </div>
    </div>

    <!-- Genres -->
    <div>
      <label for="genres" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Genres
      </label>
      <input
        id="genres"
        type="text"
        :value="(metadata.genres || []).join(', ')"
        @input="updateGenres(($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="Fiction, Fantasy, Adventure (comma-separated)"
      />
    </div>

    <!-- Publisher -->
    <div>
      <label for="publisher" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Publisher
      </label>
      <input
        id="publisher"
        type="text"
        :value="metadata.publisher"
        @input="updateField('publisher', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
        placeholder="Publisher name"
      />
    </div>

    <!-- Publication Date -->
    <div>
      <label for="publication-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
        Publication Date
      </label>
      <input
        id="publication-date"
        type="date"
        :value="metadata.publication_date"
        @input="updateField('publication_date', ($event.target as HTMLInputElement).value)"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
import type { BookReviewMetadata } from '@/types/posts'

interface Props {
  modelValue: Partial<BookReviewMetadata>
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => ({
    book_title: '',
    book_author: '',
    reading_status: 'want_to_read'
  })
})

const emit = defineEmits<{
  'update:modelValue': [value: Partial<BookReviewMetadata>]
}>()

const metadata = computed(() => props.modelValue)

const updateField = (field: keyof BookReviewMetadata, value: any) => {
  const updated = { ...metadata.value, [field]: value }
  if (value === '' || value === undefined) {
    delete updated[field]
  }
  emit('update:modelValue', updated)
}

const updateGenres = (value: string) => {
  const genres = value.split(',').map(g => g.trim()).filter(g => g.length > 0)
  updateField('genres', genres.length > 0 ? genres : undefined)
}
</script> 