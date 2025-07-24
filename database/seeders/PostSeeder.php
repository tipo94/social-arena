<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Carbon\Carbon;

class PostSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $users = User::where('role', 'user')->with('ownedGroups')->get();
        
        if ($users->isEmpty()) {
            $this->command->error('No users found. Please run UserSeeder first.');
            return;
        }

        // Book-related post content
        $postTemplates = [
            [
                'type' => 'text',
                'content' => 'Just finished reading "{{book}}" by {{author}} and I am absolutely blown away! The way {{author}} weaves {{element}} throughout the narrative is masterful. Has anyone else read this? Would love to discuss!',
                'books' => [
                    ['title' => 'The Seven Husbands of Evelyn Hugo', 'author' => 'Taylor Jenkins Reid', 'element' => 'character development'],
                    ['title' => 'Klara and the Sun', 'author' => 'Kazuo Ishiguro', 'element' => 'emotional depth'],
                    ['title' => 'Project Hail Mary', 'author' => 'Andy Weir', 'element' => 'scientific accuracy'],
                ]
            ],
            [
                'type' => 'book_review',
                'content' => '⭐⭐⭐⭐⭐ BOOK REVIEW: "{{book}}" by {{author}}\n\nThis book completely exceeded my expectations! {{review_detail}} I couldn\'t put it down and finished it in one sitting. Highly recommend to anyone who enjoys {{genre}}.',
                'books' => [
                    ['title' => 'The Thursday Murder Club', 'author' => 'Richard Osman', 'review_detail' => 'The mystery kept me guessing until the very end, and the characters felt like real people I\'d want to have tea with.', 'genre' => 'cozy mysteries'],
                    ['title' => 'Circe', 'author' => 'Madeline Miller', 'review_detail' => 'Miller\'s prose is absolutely gorgeous, and her reimagining of Greek mythology is both powerful and deeply moving.', 'genre' => 'mythology retellings'],
                ]
            ],
            [
                'type' => 'text',
                'content' => 'Currently reading "{{book}}" and wow, {{author}} has such a unique writing style! About halfway through and {{current_thoughts}}. Anyone else reading this one?',
                'books' => [
                    ['title' => 'The Midnight Library', 'author' => 'Matt Haig', 'current_thoughts' => 'the philosophical questions about life choices are really making me think'],
                    ['title' => 'Mexican Gothic', 'author' => 'Silvia Moreno-Garcia', 'current_thoughts' => 'the atmospheric horror is giving me chills in the best way'],
                ]
            ],
            [
                'type' => 'text',
                'content' => 'Book recommendation time! If you loved "{{popular_book}}", you NEED to read "{{recommended_book}}" by {{author}}. {{connection_reason}} Trust me on this one! 📚✨',
                'recommendations' => [
                    ['popular_book' => 'The Song of Achilles', 'recommended_book' => 'Circe', 'author' => 'Madeline Miller', 'connection_reason' => 'Same beautiful prose and mythology, but with a powerful female protagonist'],
                    ['popular_book' => 'Where the Crawdads Sing', 'recommended_book' => 'The Seven Moons of Maali Almeida', 'author' => 'Shehan Karunatilaka', 'connection_reason' => 'Both have that perfect blend of mystery and beautiful nature writing'],
                ]
            ],
            [
                'type' => 'text',
                'content' => 'Unpopular opinion: {{controversial_take}} What do you all think? Am I totally off base here? Let\'s discuss! 🤔',
                'opinions' => [
                    'I actually prefer movie adaptations that take creative liberties with the source material rather than trying to be exactly faithful',
                    'Young Adult fiction often tackles more complex themes than most "literary" fiction',
                    'Reading physical books vs. e-books vs. audiobooks are all equally valid ways to experience stories',
                    'Sometimes a book series should end even if it\'s popular - not everything needs 7+ books',
                ]
            ],
            [
                'type' => 'text',
                'content' => 'Reading goal update: {{progress}} this year! Currently working on {{current_goal}}. How are your reading goals going? 📖',
                'goals' => [
                    ['progress' => 'Just hit book #15', 'current_goal' => 'reading more diverse authors'],
                    ['progress' => 'Finished my 3rd non-fiction book', 'current_goal' => 'balancing fiction and non-fiction'],
                    ['progress' => 'Read in 4 different languages so far', 'current_goal' => 'expanding my multilingual reading'],
                ]
            ],
            [
                'type' => 'text',
                'content' => 'TBR pile confession: {{confession}} Anyone else have this problem? My shelf is judging me... 📚😅',
                'confessions' => [
                    'I bought 5 new books this week even though I have 47 unread books already',
                    'I\'ve been carrying the same book around for 3 months and I\'m only on page 50',
                    'I organize my TBR by mood and somehow still can never find the "right" book to read',
                    'I have books I bought 3 years ago that are still in their Amazon packaging',
                ]
            ],
            [
                'type' => 'text',
                'content' => 'Bookstore visit today and {{bookstore_experience}} Left with {{purchase_result}}. Why is self-control so hard in bookstores? 💸📚',
                'experiences' => [
                    ['bookstore_experience' => 'spent 2 hours just browsing and talking to the amazing staff', 'purchase_result' => '6 books I definitely don\'t need but absolutely wanted'],
                    ['bookstore_experience' => 'went in for one specific book', 'purchase_result' => 'that book plus 4 others that "followed me home"'],
                    ['bookstore_experience' => 'discovered a new indie author in the staff picks section', 'purchase_result' => 'their entire backlist'],
                ]
            ],
        ];

        $groups = $users->flatMap(function ($user) {
            return $user->ownedGroups;
        });

        // Create posts for users
        foreach ($users as $user) {
            $postCount = rand(3, 12);
            
            for ($i = 0; $i < $postCount; $i++) {
                $template = $postTemplates[array_rand($postTemplates)];
                $content = $this->generatePostContent($template);
                $isGroupPost = rand(0, 100) < 30; // 30% chance of group post
                $visibility = $isGroupPost ? 'group' : ['public', 'friends', 'private'][rand(0, 2)];
                $groupId = null;
                
                if ($isGroupPost && $groups->isNotEmpty()) {
                    $group = $groups->random();
                    $groupId = $group->id;
                }

                $post = $user->posts()->create([
                    'group_id' => $groupId,
                    'content' => $content,
                    'type' => $template['type'],
                    'visibility' => $visibility,
                    'metadata' => $this->generatePostMetadata($template),
                    'published_at' => Carbon::now()->subDays(rand(0, 60))->subHours(rand(0, 23)),
                    'likes_count' => 0, // Will be updated by likes
                    'comments_count' => 0, // Will be updated by comments
                    'shares_count' => rand(0, 5),
                ]);

                // Update group post count if it's a group post
                if ($groupId) {
                    $group = $groups->firstWhere('id', $groupId);
                    if ($group) {
                        $group->increment('posts_count');
                        $group->update(['last_post_at' => $post->published_at]);
                    }
                }
            }

            // Update user profile post count
            $user->profile()->increment('posts_count', $postCount);
        }

        // Create some scheduled posts for the future
        $futurePostsUsers = $users->random(rand(3, 7));
        foreach ($futurePostsUsers as $user) {
            $template = $postTemplates[array_rand($postTemplates)];
            $content = $this->generatePostContent($template);
            
            $user->posts()->create([
                'content' => $content,
                'type' => $template['type'],
                'visibility' => 'public',
                'metadata' => $this->generatePostMetadata($template),
                'published_at' => Carbon::now()->addDays(rand(1, 7))->addHours(rand(9, 17)),
                'is_scheduled' => true,
            ]);
        }
    }

    private function generatePostContent(array $template): string
    {
        $content = $template['content'];
        
        switch ($template['type']) {
            case 'text':
                if (isset($template['books'])) {
                    $book = $template['books'][array_rand($template['books'])];
                    if (isset($book['element'])) {
                        $content = str_replace(['{{book}}', '{{author}}', '{{element}}'], 
                            [$book['title'], $book['author'], $book['element']], $content);
                    } elseif (isset($book['current_thoughts'])) {
                        $content = str_replace(['{{book}}', '{{author}}', '{{current_thoughts}}'], 
                            [$book['title'], $book['author'], $book['current_thoughts']], $content);
                    } else {
                        $content = str_replace(['{{book}}', '{{author}}'], 
                            [$book['title'], $book['author']], $content);
                    }
                } elseif (isset($template['recommendations'])) {
                    $rec = $template['recommendations'][array_rand($template['recommendations'])];
                    $content = str_replace(['{{popular_book}}', '{{recommended_book}}', '{{author}}', '{{connection_reason}}'],
                        [$rec['popular_book'], $rec['recommended_book'], $rec['author'], $rec['connection_reason']], $content);
                } elseif (isset($template['opinions'])) {
                    $opinion = $template['opinions'][array_rand($template['opinions'])];
                    $content = str_replace('{{controversial_take}}', $opinion, $content);
                } elseif (isset($template['goals'])) {
                    $goal = $template['goals'][array_rand($template['goals'])];
                    $content = str_replace(['{{progress}}', '{{current_goal}}'], 
                        [$goal['progress'], $goal['current_goal']], $content);
                } elseif (isset($template['confessions'])) {
                    $confession = $template['confessions'][array_rand($template['confessions'])];
                    $content = str_replace('{{confession}}', $confession, $content);
                } elseif (isset($template['experiences'])) {
                    $exp = $template['experiences'][array_rand($template['experiences'])];
                    $content = str_replace(['{{bookstore_experience}}', '{{purchase_result}}'],
                        [$exp['bookstore_experience'], $exp['purchase_result']], $content);
                }
                break;
                
            case 'book_review':
                $book = $template['books'][array_rand($template['books'])];
                $content = str_replace(['{{book}}', '{{author}}', '{{review_detail}}', '{{genre}}'],
                    [$book['title'], $book['author'], $book['review_detail'], $book['genre']], $content);
                break;
        }
        
        return $content;
    }

    private function generatePostMetadata(array $template): ?array
    {
        switch ($template['type']) {
            case 'book_review':
                return [
                    'rating' => rand(3, 5),
                    'book_data' => [
                        'isbn' => '978' . rand(1000000000, 9999999999),
                        'genre' => ['Fiction', 'Mystery', 'Romance', 'Science Fiction', 'Fantasy'][rand(0, 4)],
                        'pages' => rand(200, 500),
                        'publication_year' => rand(2020, 2024),
                    ],
                    'reading_time' => rand(2, 14) . ' days',
                ];
                
            case 'text':
                if (strpos($template['content'], '{{book}}') !== false) {
                    return [
                        'mentions_book' => true,
                        'discussion_topic' => ['plot', 'characters', 'writing_style', 'themes'][rand(0, 3)],
                    ];
                }
                break;
        }
        
        return null;
    }
}
