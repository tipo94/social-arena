<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Carbon\Carbon;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        // Create admin user
        $admin = User::create([
            'name' => 'AI-Book Admin',
            'email' => 'admin@ai-book.com',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
            'last_login_at' => now(),
            'last_activity_at' => now(),
            'timezone' => 'UTC',
            'locale' => 'en',
            'login_count' => 15,
        ]);

        // Create admin profile
        $admin->profile()->create([
            'bio' => 'Platform administrator passionate about connecting book lovers worldwide.',
            'location' => 'San Francisco, CA',
            'favorite_genres' => ['Science Fiction', 'Technology', 'Business'],
            'reading_goals' => ['Read 50 books this year', 'Explore more diverse authors'],
            'reading_speed' => 'fast',
            'languages' => ['English', 'Spanish'],
            'books_read_count' => 127,
            'reviews_written_count' => 89,
            'friends_count' => 0, // Will be updated by relationships
            'groups_count' => 0, // Will be updated by memberships
            'posts_count' => 0, // Will be updated by posts
            'profile_completion_percentage' => 95,
            'is_verified' => true,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Sample book lovers with diverse interests
        $users = [
            [
                'name' => 'Emma Thompson',
                'email' => 'emma@example.com',
                'bio' => 'Fantasy enthusiast and aspiring writer. Currently working on my first novel while devouring everything from Sanderson to Le Guin.',
                'location' => 'London, UK',
                'favorite_genres' => ['Fantasy', 'Science Fiction', 'Young Adult'],
                'reading_speed' => 'average',
                'books_read' => 89,
                'reviews_written' => 45,
            ],
            [
                'name' => 'Marcus Chen',
                'email' => 'marcus@example.com',
                'bio' => 'Philosophy professor by day, mystery novel aficionado by night. Always up for discussions about ethics and human nature.',
                'location' => 'Boston, MA',
                'favorite_genres' => ['Mystery', 'Philosophy', 'Crime', 'History'],
                'reading_speed' => 'fast',
                'books_read' => 156,
                'reviews_written' => 78,
            ],
            [
                'name' => 'Sofia Rodriguez',
                'email' => 'sofia@example.com',
                'bio' => 'Romance reader and book blogger. I believe in the power of love stories to change the world, one HEA at a time.',
                'location' => 'Madrid, Spain',
                'favorite_genres' => ['Romance', 'Contemporary Fiction', 'Memoirs'],
                'reading_speed' => 'very_fast',
                'books_read' => 203,
                'reviews_written' => 134,
            ],
            [
                'name' => 'James Wilson',
                'email' => 'james@example.com',
                'bio' => 'History buff and non-fiction devotee. If it happened before 1900, I probably want to read about it.',
                'location' => 'Edinburgh, Scotland',
                'favorite_genres' => ['History', 'Biography', 'Political Science'],
                'reading_speed' => 'slow',
                'books_read' => 67,
                'reviews_written' => 23,
            ],
            [
                'name' => 'Priya Patel',
                'email' => 'priya@example.com',
                'bio' => 'Tech entrepreneur who loves sci-fi and self-help books. Always looking for the next big idea.',
                'location' => 'Mumbai, India',
                'favorite_genres' => ['Science Fiction', 'Business', 'Self-Help', 'Technology'],
                'reading_speed' => 'fast',
                'books_read' => 142,
                'reviews_written' => 67,
            ],
            [
                'name' => 'David Kim',
                'email' => 'david@example.com',
                'bio' => 'Graphic novel enthusiast and comic book creator. Visual storytelling is my passion.',
                'location' => 'Seoul, South Korea',
                'favorite_genres' => ['Graphic Novels', 'Comics', 'Art', 'Design'],
                'reading_speed' => 'average',
                'books_read' => 234,
                'reviews_written' => 89,
            ],
            [
                'name' => 'Isabella Garcia',
                'email' => 'isabella@example.com',
                'bio' => 'Literary fiction lover and book club organizer. I host monthly discussions for fellow readers.',
                'location' => 'Barcelona, Spain',
                'favorite_genres' => ['Literary Fiction', 'Classics', 'Poetry'],
                'reading_speed' => 'average',
                'books_read' => 98,
                'reviews_written' => 56,
            ],
            [
                'name' => 'Michael O\'Connor',
                'email' => 'michael@example.com',
                'bio' => 'Thriller and horror fan. The scarier, the better! Also enjoy true crime podcasts.',
                'location' => 'Dublin, Ireland',
                'favorite_genres' => ['Horror', 'Thriller', 'True Crime', 'Suspense'],
                'reading_speed' => 'fast',
                'books_read' => 167,
                'reviews_written' => 91,
            ],
            [
                'name' => 'Aisha Johnson',
                'email' => 'aisha@example.com',
                'bio' => 'Educator focused on diverse voices and inclusive literature. Championing underrepresented authors.',
                'location' => 'Atlanta, GA',
                'favorite_genres' => ['African American Literature', 'Education', 'Social Justice'],
                'reading_speed' => 'average',
                'books_read' => 145,
                'reviews_written' => 78,
            ],
            [
                'name' => 'Noah Anderson',
                'email' => 'noah@example.com',
                'bio' => 'Young adult librarian who stays current with the latest YA trends. Helping teens find their next favorite book.',
                'location' => 'Portland, OR',
                'favorite_genres' => ['Young Adult', 'Middle Grade', 'Children\'s Literature'],
                'reading_speed' => 'very_fast',
                'books_read' => 289,
                'reviews_written' => 156,
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'user',
                'is_active' => true,
                'last_login_at' => Carbon::now()->subDays(rand(0, 30)),
                'last_activity_at' => Carbon::now()->subHours(rand(1, 72)),
                'is_online' => rand(0, 100) < 20, // 20% chance of being online
                'timezone' => ['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo'][rand(0, 3)],
                'locale' => ['en', 'es', 'fr'][rand(0, 2)],
                'theme' => ['light', 'dark', 'auto'][rand(0, 2)],
                'login_count' => rand(5, 150),
                'last_password_change' => Carbon::now()->subDays(rand(30, 365)),
            ]);

            // Create user profile
            $user->profile()->create([
                'bio' => $userData['bio'],
                'location' => $userData['location'],
                'birth_date' => Carbon::now()->subYears(rand(18, 65)),
                'gender' => ['male', 'female', 'non_binary', 'prefer_not_to_say'][rand(0, 3)],
                'favorite_genres' => $userData['favorite_genres'],
                'favorite_authors' => $this->getRandomAuthors(),
                'reading_goals' => $this->getRandomReadingGoals(),
                'reading_speed' => $userData['reading_speed'],
                'languages' => $this->getRandomLanguages(),
                'is_private_profile' => rand(0, 100) < 15, // 15% private profiles
                'show_reading_activity' => rand(0, 100) < 85, // 85% show activity
                'show_friends_list' => rand(0, 100) < 70, // 70% show friends
                'allow_friend_requests' => rand(0, 100) < 90, // 90% allow requests
                'allow_group_invites' => rand(0, 100) < 80, // 80% allow invites
                'allow_book_recommendations' => rand(0, 100) < 95, // 95% allow recommendations
                'email_notifications' => rand(0, 100) < 75,
                'push_notifications' => rand(0, 100) < 60,
                'notification_likes' => rand(0, 100) < 80,
                'notification_comments' => rand(0, 100) < 85,
                'notification_friend_requests' => rand(0, 100) < 95,
                'books_read_count' => $userData['books_read'],
                'reviews_written_count' => $userData['reviews_written'],
                'friends_count' => 0, // Will be updated by relationships
                'groups_count' => 0, // Will be updated by memberships
                'posts_count' => 0, // Will be updated by posts
                'profile_completion_percentage' => rand(60, 100),
                'is_verified' => rand(0, 100) < 10, // 10% verified users
                'verified_at' => rand(0, 100) < 10 ? now() : null,
                'is_active' => true,
                'last_profile_update' => Carbon::now()->subDays(rand(1, 90)),
            ]);
        }

        // Create a few moderators
        for ($i = 0; $i < 3; $i++) {
            $moderator = User::create([
                'name' => "Moderator " . ($i + 1),
                'email' => "moderator" . ($i + 1) . "@ai-book.com",
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'role' => 'moderator',
                'is_active' => true,
                'last_login_at' => Carbon::now()->subHours(rand(1, 24)),
                'last_activity_at' => Carbon::now()->subHours(rand(1, 12)),
                'timezone' => 'UTC',
                'locale' => 'en',
                'login_count' => rand(50, 200),
            ]);

            $moderator->profile()->create([
                'bio' => 'Community moderator helping maintain a safe and welcoming environment for all book lovers.',
                'favorite_genres' => ['Fiction', 'Non-Fiction'],
                'books_read_count' => rand(50, 150),
                'reviews_written_count' => rand(20, 80),
                'profile_completion_percentage' => 100,
                'is_verified' => true,
                'verified_at' => now(),
                'is_active' => true,
            ]);
        }
    }

    private function getRandomAuthors(): array
    {
        $authors = [
            'Brandon Sanderson', 'Agatha Christie', 'Stephen King', 'J.K. Rowling',
            'Toni Morrison', 'Gabriel García Márquez', 'Ursula K. Le Guin',
            'Isaac Asimov', 'Virginia Woolf', 'George Orwell', 'Octavia Butler',
            'Haruki Murakami', 'Chimamanda Ngozi Adichie', 'Neil Gaiman',
        ];

        return array_slice($authors, 0, rand(3, 7));
    }

    private function getRandomReadingGoals(): array
    {
        $goals = [
            'Read 50 books this year',
            'Explore more diverse authors',
            'Read classics I\'ve been putting off',
            'Discover new genres',
            'Read more non-fiction',
            'Support indie authors',
            'Read in multiple languages',
            'Join a book club',
        ];

        return array_slice($goals, 0, rand(2, 4));
    }

    private function getRandomLanguages(): array
    {
        $languages = ['English', 'Spanish', 'French', 'German', 'Italian', 'Portuguese', 'Japanese', 'Korean', 'Mandarin'];
        return array_slice($languages, 0, rand(1, 3));
    }
}
