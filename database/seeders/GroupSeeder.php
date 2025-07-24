<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Str;
use Carbon\Carbon;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $users = User::where('role', 'user')->get();
        
        if ($users->isEmpty()) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        $groups = [
            [
                'name' => 'Science Fiction Book Club',
                'description' => 'Exploring the infinite possibilities of science fiction literature. From classic Asimov to modern Liu Cixin, we discuss the books that imagine our future.',
                'type' => 'book_club',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'Be respectful, no spoilers in titles, use spoiler tags when discussing plot points.',
                'current_books' => [
                    ['title' => 'The Three-Body Problem', 'author' => 'Liu Cixin', 'deadline' => '2025-02-15'],
                    ['title' => 'Klara and the Sun', 'author' => 'Kazuo Ishiguro', 'deadline' => '2025-03-01']
                ],
                'next_meeting_date' => '2025-02-01',
                'is_featured' => true,
            ],
            [
                'name' => 'Mystery & Thriller Enthusiasts',
                'description' => 'Unraveling plots and discussing red herrings. If you love a good whodunit or edge-of-your-seat thriller, this is your community.',
                'type' => 'genre_discussion',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'No spoilers without warnings, recommendations welcome, discuss adaptations too.',
                'current_books' => [
                    ['title' => 'The Thursday Murder Club', 'author' => 'Richard Osman', 'deadline' => '2025-02-10']
                ],
                'is_featured' => true,
            ],
            [
                'name' => 'Diverse Voices Reading Circle',
                'description' => 'Celebrating literature from underrepresented authors and exploring diverse perspectives from around the world.',
                'type' => 'reading_challenge',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'Focus on diverse authors, be open to new perspectives, create a safe space for discussion.',
                'current_books' => [
                    ['title' => 'The Seven Husbands of Evelyn Hugo', 'author' => 'Taylor Jenkins Reid', 'deadline' => '2025-01-30'],
                    ['title' => 'Circe', 'author' => 'Madeline Miller', 'deadline' => '2025-02-20']
                ],
                'next_meeting_date' => '2025-01-25',
            ],
            [
                'name' => 'Brandon Sanderson Fan Club',
                'description' => 'All things Cosmere and beyond. Discussing the intricate magic systems and epic worldbuilding of Brandon Sanderson.',
                'type' => 'author_fan',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'Clearly mark spoilers by series, theory discussions encouraged, be respectful of different interpretations.',
                'current_books' => [
                    ['title' => 'The Way of Kings', 'author' => 'Brandon Sanderson', 'deadline' => '2025-03-15']
                ],
                'next_meeting_date' => '2025-02-05',
            ],
            [
                'name' => 'Romance Readers United',
                'description' => 'From sweet contemporary to steamy historical, we celebrate all subgenres of romance. HEA guaranteed in our discussions!',
                'type' => 'genre_discussion',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'Respect all subgenres, content warnings appreciated, no book shaming.',
                'current_books' => [
                    ['title' => 'Beach Read', 'author' => 'Emily Henry', 'deadline' => '2025-02-01'],
                    ['title' => 'The Hating Game', 'author' => 'Sally Thorne', 'deadline' => '2025-02-15']
                ],
                'is_featured' => true,
            ],
            [
                'name' => 'Philosophy & Deep Thoughts',
                'description' => 'Exploring philosophical texts and books that make us question everything. From ancient wisdom to modern ethics.',
                'type' => 'genre_discussion',
                'privacy' => 'public',
                'join_policy' => 'request',
                'rules' => 'Come with an open mind, cite sources when making claims, respect different viewpoints.',
                'current_books' => [
                    ['title' => 'Meditations', 'author' => 'Marcus Aurelius', 'deadline' => '2025-02-28']
                ],
                'next_meeting_date' => '2025-02-10',
            ],
            [
                'name' => 'Young Adult Favorites',
                'description' => 'Discussing the latest and greatest in YA literature. From coming-of-age stories to dystopian adventures.',
                'type' => 'genre_discussion',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'Age-appropriate discussions, be mindful of younger members, celebrate growth and learning.',
                'current_books' => [
                    ['title' => 'The House You Pass on the Way', 'author' => 'Jacqueline Woodson', 'deadline' => '2025-01-31']
                ],
            ],
            [
                'name' => 'Non-Fiction Knowledge Seekers',
                'description' => 'Learning from the real world through biographies, history, science, and self-improvement books.',
                'type' => 'genre_discussion',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'Fact-check when possible, share additional resources, apply learnings to discussions.',
                'current_books' => [
                    ['title' => 'Educated', 'author' => 'Tara Westover', 'deadline' => '2025-02-05']
                ],
            ],
            [
                'name' => 'Literary Fiction Society',
                'description' => 'Analyzing and discussing award-winning and critically acclaimed literary fiction from around the world.',
                'type' => 'genre_discussion',
                'privacy' => 'closed',
                'join_policy' => 'request',
                'rules' => 'Thoughtful analysis required, academic discussion welcome, cite literary criticism when relevant.',
                'current_books' => [
                    ['title' => 'Beloved', 'author' => 'Toni Morrison', 'deadline' => '2025-02-20']
                ],
                'next_meeting_date' => '2025-02-08',
            ],
            [
                'name' => '2025 Reading Challenge',
                'description' => 'Setting and achieving ambitious reading goals together. Share progress, get recommendations, and stay motivated!',
                'type' => 'reading_challenge',
                'privacy' => 'public',
                'join_policy' => 'open',
                'rules' => 'Support each other, no judgment on reading speed, share progress regularly.',
                'reading_schedule' => [
                    'January' => '4 books',
                    'February' => '4 books',
                    'March' => '4 books',
                    'Goal' => '50 books by year end'
                ],
            ],
        ];

        foreach ($groups as $groupData) {
            // Assign random owner
            $owner = $users->random();
            
            $group = $owner->ownedGroups()->create([
                'name' => $groupData['name'],
                'description' => $groupData['description'],
                'slug' => Str::slug($groupData['name']),
                'type' => $groupData['type'],
                'privacy' => $groupData['privacy'],
                'join_policy' => $groupData['join_policy'],
                'rules' => $groupData['rules'] ?? null,
                'current_books' => $groupData['current_books'] ?? null,
                'reading_schedule' => $groupData['reading_schedule'] ?? null,
                'next_meeting_date' => isset($groupData['next_meeting_date']) ? Carbon::parse($groupData['next_meeting_date']) : null,
                'is_active' => true,
                'is_featured' => $groupData['is_featured'] ?? false,
                'members_count' => 1, // Owner
                'last_activity_at' => Carbon::now()->subDays(rand(0, 7)),
                'last_post_at' => Carbon::now()->subDays(rand(0, 3)),
                'allow_member_posts' => true,
                'allow_member_invites' => $groupData['privacy'] !== 'secret',
            ]);

            // Owner becomes a member with owner role
            $group->memberships()->create([
                'user_id' => $owner->id,
                'status' => 'approved',
                'role' => 'owner',
                'requested_at' => $group->created_at,
                'approved_at' => $group->created_at,
                'approved_by' => $owner->id,
                'can_post' => true,
                'can_comment' => true,
                'can_invite' => true,
                'can_moderate' => true,
                'last_activity_at' => Carbon::now()->subDays(rand(0, 3)),
            ]);

            // Add random members to each group
            $memberCount = rand(5, 25);
            $potentialMembers = $users->where('id', '!=', $owner->id)->shuffle()->take($memberCount);
            
            foreach ($potentialMembers as $member) {
                $isApproved = rand(0, 100) < 85; // 85% approval rate
                $joinedDate = Carbon::now()->subDays(rand(1, 60));
                
                $membership = $group->memberships()->create([
                    'user_id' => $member->id,
                    'status' => $isApproved ? 'approved' : 'pending',
                    'role' => 'member',
                    'requested_at' => $joinedDate,
                    'approved_at' => $isApproved ? $joinedDate->addHours(rand(1, 72)) : null,
                    'approved_by' => $isApproved ? $owner->id : null,
                    'can_post' => true,
                    'can_comment' => true,
                    'can_invite' => $group->allow_member_invites,
                    'can_moderate' => false,
                    'last_activity_at' => $isApproved ? Carbon::now()->subDays(rand(0, 14)) : null,
                ]);

                if ($isApproved) {
                    $group->increment('members_count');
                } else {
                    $group->increment('pending_requests_count');
                }
            }

            // Assign some moderators to larger groups
            if ($group->members_count > 15) {
                $moderators = $group->memberships()
                    ->where('status', 'approved')
                    ->where('role', 'member')
                    ->inRandomOrder()
                    ->take(rand(1, 2))
                    ->get();

                foreach ($moderators as $membership) {
                    $membership->update([
                        'role' => 'moderator',
                        'can_moderate' => true,
                    ]);
                }
            }

            // Update user profile group counts
            $approvedMemberIds = $group->memberships()
                ->where('status', 'approved')
                ->pluck('user_id');
            
            foreach ($approvedMemberIds as $userId) {
                $user = User::find($userId);
                if ($user && $user->profile) {
                    $user->profile->increment('groups_count');
                }
            }
        }
    }
}
