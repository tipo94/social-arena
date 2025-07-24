<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Carbon\Carbon;

class SocialInteractionSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $users = User::where('role', 'user')->with('posts')->get();
        
        if ($users->isEmpty()) {
            $this->command->error('No users found. Please run UserSeeder and PostSeeder first.');
            return;
        }

        // Create friendships
        $this->createFriendships($users);
        
        // Create likes on posts
        $this->createLikes($users);
        
        // Create comments on posts
        $this->createComments($users);
        
        // Create notifications for interactions
        $this->createNotifications($users);
        
        // Update denormalized counters
        $this->updateCounters();
    }

    private function createFriendships($users)
    {
        $friendshipCount = 0;
        
        foreach ($users as $user) {
            $potentialFriends = $users->where('id', '!=', $user->id);
            $friendsToMake = rand(3, 15);
            $selectedFriends = $potentialFriends->random(min($friendsToMake, $potentialFriends->count()));
            
            foreach ($selectedFriends as $friend) {
                // Check if friendship already exists (either direction)
                $existingFriendship = $user->sentFriendRequests()
                    ->where('friend_id', $friend->id)
                    ->orWhere(function($query) use ($user, $friend) {
                        $query->where('user_id', $friend->id)->where('friend_id', $user->id);
                    })->first();
                
                if (!$existingFriendship) {
                    $status = ['accepted', 'pending', 'declined'][array_rand(['accepted', 'pending', 'declined'])];
                    $requestedAt = Carbon::now()->subDays(rand(1, 90));
                    
                    $friendship = $user->sentFriendRequests()->create([
                        'friend_id' => $friend->id,
                        'status' => $status,
                        'requested_at' => $requestedAt,
                        'accepted_at' => $status === 'accepted' ? $requestedAt->addHours(rand(1, 168)) : null,
                        'can_see_posts' => rand(0, 100) < 95, // 95% allow post visibility
                        'can_send_messages' => rand(0, 100) < 90, // 90% allow messages
                        'show_in_friends_list' => rand(0, 100) < 85, // 85% show in friends list
                        'mutual_friends_count' => 0, // Will be calculated later
                    ]);
                    
                    $friendshipCount++;
                }
            }
        }
        
        // Update mutual friends count and user profile friends count
        $this->updateMutualFriendsCount();
        $this->updateUserFriendsCount();
        
        $this->command->info("Created {$friendshipCount} friendships");
    }

    private function createLikes($users)
    {
        $allPosts = $users->flatMap(function ($user) {
            return $user->posts;
        });
        
        $likeCount = 0;
        
        foreach ($allPosts as $post) {
            $likerCount = rand(0, min(15, $users->count()));
            $likers = $users->random($likerCount);
            
            foreach ($likers as $liker) {
                // Don't let users like their own posts always, but sometimes they might
                if ($liker->id === $post->user_id && rand(0, 100) < 90) {
                    continue;
                }
                
                // Check if already liked
                $existingLike = $post->likes()->where('user_id', $liker->id)->first();
                if (!$existingLike) {
                    $post->likes()->create([
                        'user_id' => $liker->id,
                        'type' => ['like', 'love', 'laugh', 'wow'][array_rand(['like', 'love', 'laugh', 'wow'])],
                        'created_at' => $post->published_at->addMinutes(rand(10, 1440)), // Like within 24 hours of post
                    ]);
                    
                    $likeCount++;
                }
            }
        }
        
        $this->command->info("Created {$likeCount} likes");
    }

    private function createComments($users)
    {
        $allPosts = $users->flatMap(function ($user) {
            return $user->posts;
        });
        
        $commentTemplates = [
            "This is such a great recommendation! Adding it to my TBR list right now 📚",
            "I completely agree! {{author}} has such a unique writing style.",
            "I had the same reaction when I read this book! The ending was incredible.",
            "Thanks for sharing this review. You've convinced me to give it a try!",
            "Interesting perspective! I saw it differently but I love hearing other interpretations.",
            "Have you read {{author}}'s other books? I'd love to know what you think of those too.",
            "This book has been on my shelf for months. Your post is the push I needed to finally read it!",
            "I loved this one too! Did you catch all the references to {{author}}'s previous work?",
            "Great review! What's next on your reading list?",
            "I couldn't put this book down either. Finished it in one sitting!",
            "Thanks for the heads up about the content warnings. Really appreciate thoughtful reviewers.",
            "This sounds right up my alley. Any other similar books you'd recommend?",
        ];
        
        $commentCount = 0;
        
        foreach ($allPosts as $post) {
            $commenterCount = rand(0, 8);
            $commenters = $users->random($commenterCount);
            
            foreach ($commenters as $commenter) {
                $template = $commentTemplates[array_rand($commentTemplates)];
                $content = str_replace('{{author}}', ['Brandon Sanderson', 'Agatha Christie', 'Toni Morrison'][rand(0, 2)], $template);
                
                $comment = $post->comments()->create([
                    'user_id' => $commenter->id,
                    'content' => $content,
                    'type' => 'text',
                    'depth' => 0,
                    'path' => null, // Will be set after creation
                    'created_at' => $post->published_at->addMinutes(rand(30, 2880)), // Comment within 2 days of post
                ]);
                
                // Set the path for materialized path tree
                $comment->update(['path' => (string) $comment->id]);
                
                $commentCount++;
                
                // Sometimes add replies to comments
                if (rand(0, 100) < 30) { // 30% chance of getting a reply
                    $repliers = $users->where('id', '!=', $commenter->id)->random(rand(1, 2));
                    
                    foreach ($repliers as $replier) {
                        $replyContent = [
                            "Great point! I hadn't thought of it that way.",
                            "I totally agree with your take on this.",
                            "Same here! Such a good book.",
                            "You should definitely check out their other work too!",
                            "Thanks for the recommendation!",
                        ][array_rand(['Great point! I hadn\'t thought of it that way.', 'I totally agree with your take on this.', 'Same here! Such a good book.', 'You should definitely check out their other work too!', 'Thanks for the recommendation!'])];
                        
                        $reply = $post->comments()->create([
                            'user_id' => $replier->id,
                            'parent_id' => $comment->id,
                            'content' => $replyContent,
                            'type' => 'text',
                            'depth' => 1,
                            'path' => $comment->path . '.' . ($comment->replies_count + 1),
                            'created_at' => $comment->created_at->addMinutes(rand(15, 480)),
                        ]);
                        
                        $comment->increment('replies_count');
                        $commentCount++;
                    }
                }
                
                // Add likes to some comments
                if (rand(0, 100) < 40) { // 40% chance of comment getting likes
                    $commentLikers = $users->random(rand(1, 5));
                    foreach ($commentLikers as $liker) {
                        $comment->likes()->create([
                            'user_id' => $liker->id,
                            'type' => 'like',
                            'created_at' => $comment->created_at->addMinutes(rand(5, 120)),
                        ]);
                        $comment->increment('likes_count');
                    }
                }
            }
        }
        
        $this->command->info("Created {$commentCount} comments");
    }

    private function createNotifications($users)
    {
        $notificationCount = 0;
        
        // Create notifications for friend requests
        $friendships = collect();
        foreach ($users as $user) {
            $friendships = $friendships->merge($user->sentFriendRequests);
        }
        
        foreach ($friendships as $friendship) {
            // Friend request notification
            $friendship->friend->notifications()->create([
                'actor_id' => $friendship->user_id,
                'type' => 'friend_request',
                'title' => 'New Friend Request',
                'message' => $friendship->user->name . ' sent you a friend request.',
                'action_url' => '/friends/requests',
                'notifiable_type' => 'App\\Models\\Friendship',
                'notifiable_id' => $friendship->id,
                'data' => ['friendship_id' => $friendship->id],
                'priority' => 'normal',
                'read_at' => $friendship->status === 'accepted' ? $friendship->accepted_at : null,
                'created_at' => $friendship->requested_at,
            ]);
            $notificationCount++;
            
            // Friend request accepted notification
            if ($friendship->status === 'accepted') {
                $friendship->user->notifications()->create([
                    'actor_id' => $friendship->friend_id,
                    'type' => 'friend_request_accepted',
                    'title' => 'Friend Request Accepted',
                    'message' => $friendship->friend->name . ' accepted your friend request.',
                    'action_url' => '/profile/' . $friendship->friend->id,
                    'notifiable_type' => 'App\\Models\\Friendship',
                    'notifiable_id' => $friendship->id,
                    'data' => ['friendship_id' => $friendship->id],
                    'priority' => 'normal',
                    'read_at' => rand(0, 100) < 70 ? $friendship->accepted_at->addHours(rand(1, 24)) : null,
                    'created_at' => $friendship->accepted_at,
                ]);
                $notificationCount++;
            }
        }
        
        // Create notifications for likes and comments (sample)
        $allPosts = $users->flatMap(function ($user) {
            return $user->posts;
        });
        
        foreach ($allPosts->take(20) as $post) { // Sample for performance
            $likes = $post->likes()->with('user')->get();
            foreach ($likes->take(3) as $like) { // First 3 likes get notifications
                $post->user->notifications()->create([
                    'actor_id' => $like->user_id,
                    'type' => 'post_liked',
                    'title' => 'New Like',
                    'message' => $like->user->name . ' liked your post.',
                    'action_url' => '/posts/' . $post->id,
                    'notifiable_type' => 'App\\Models\\Post',
                    'notifiable_id' => $post->id,
                    'data' => ['post_id' => $post->id, 'like_type' => $like->type],
                    'priority' => 'low',
                    'read_at' => rand(0, 100) < 60 ? $like->created_at->addHours(rand(1, 48)) : null,
                    'created_at' => $like->created_at,
                ]);
                $notificationCount++;
            }
        }
        
        $this->command->info("Created {$notificationCount} notifications");
    }

    private function updateCounters()
    {
        // Update post likes and comments counts
        $allPosts = collect();
        $users = User::where('role', 'user')->with('posts.likes', 'posts.comments')->get();
        
        foreach ($users as $user) {
            foreach ($user->posts as $post) {
                $post->update([
                    'likes_count' => $post->likes()->count(),
                    'comments_count' => $post->comments()->count(),
                ]);
            }
        }
        
        $this->command->info("Updated post engagement counters");
    }

    private function updateMutualFriendsCount()
    {
        $friendships = collect();
        $users = User::where('role', 'user')->get();
        
        foreach ($users as $user) {
            $friendships = $friendships->merge($user->sentFriendRequests()->where('status', 'accepted')->get());
        }
        
        foreach ($friendships as $friendship) {
            // Get mutual friends count
            $user1Friends = $friendship->user->acceptedFriends()->pluck('friend_id');
            $user2Friends = $friendship->friend->acceptedFriends()->pluck('friend_id');
            $mutualCount = $user1Friends->intersect($user2Friends)->count();
            
            $friendship->update(['mutual_friends_count' => $mutualCount]);
        }
    }

    private function updateUserFriendsCount()
    {
        $users = User::where('role', 'user')->get();
        
        foreach ($users as $user) {
            $friendsCount = $user->acceptedFriends()->count() + $user->acceptedFriendRequests()->count();
            $user->profile()->update(['friends_count' => $friendsCount]);
        }
    }
}
