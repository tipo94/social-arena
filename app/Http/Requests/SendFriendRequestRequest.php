<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SendFriendRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                'different:' . Auth::id(), // Cannot send friend request to self
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'User ID is required.',
            'user_id.integer' => 'User ID must be a valid number.',
            'user_id.exists' => 'The specified user does not exist.',
            'user_id.different' => 'You cannot send a friend request to yourself.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $userId = $this->input('user_id');
            $currentUser = Auth::user();

            if ($userId) {
                try {
                    $targetUser = \App\Models\User::find($userId);
                    
                    if ($targetUser) {
                        // Check if users can be friends
                        if (!\App\Models\Friendship::canBeFriends($currentUser, $targetUser)) {
                            $validator->errors()->add('user_id', 'Cannot send friend request to this user.');
                        }

                        // Check target user's privacy settings
                        if (!$targetUser->profile->allow_friend_requests) {
                            $validator->errors()->add('user_id', 'This user is not accepting friend requests.');
                        }

                        // Check if there's already a pending or accepted friendship
                        $existingFriendship = \App\Models\Friendship::betweenUsers($currentUser->id, $targetUser->id)->first();
                        if ($existingFriendship) {
                            switch ($existingFriendship->status) {
                                case \App\Models\Friendship::STATUS_PENDING:
                                    $validator->errors()->add('user_id', 'A friend request is already pending with this user.');
                                    break;
                                case \App\Models\Friendship::STATUS_ACCEPTED:
                                    $validator->errors()->add('user_id', 'You are already friends with this user.');
                                    break;
                                case \App\Models\Friendship::STATUS_BLOCKED:
                                    $validator->errors()->add('user_id', 'Cannot send friend request to this user.');
                                    break;
                                case \App\Models\Friendship::STATUS_DECLINED:
                                    // Allow sending again after decline, but check privacy settings
                                    if (!$this->canResendAfterDecline($currentUser, $targetUser, $existingFriendship)) {
                                        $validator->errors()->add('user_id', 'Cannot send another friend request to this user.');
                                    }
                                    break;
                            }
                        }

                        // Check friend request visibility settings
                        $visibility = $targetUser->profile->friend_request_visibility ?? 'everyone';
                        if (!$this->canSendBasedOnVisibility($currentUser, $targetUser, $visibility)) {
                            $validator->errors()->add('user_id', 'You cannot send a friend request to this user based on their privacy settings.');
                        }
                    }
                } catch (\Exception $e) {
                    $validator->errors()->add('user_id', 'An error occurred while validating the friend request.');
                }
            }
        });
    }

    /**
     * Check if user can resend friend request after decline.
     */
    protected function canResendAfterDecline($currentUser, $targetUser, $existingFriendship): bool
    {
        // Allow resending if more than 30 days have passed since decline
        if ($existingFriendship->updated_at->addDays(30) > now()) {
            return false;
        }

        // Check if target user still allows friend requests
        return $targetUser->profile->allow_friend_requests;
    }

    /**
     * Check if user can send friend request based on visibility settings.
     */
    protected function canSendBasedOnVisibility($currentUser, $targetUser, $visibility): bool
    {
        return match ($visibility) {
            'everyone' => true,
            'friends_of_friends' => $this->areFriendsOfFriends($currentUser, $targetUser),
            'friends' => $currentUser->isFriendsWith($targetUser), // This would be rare
            'nobody' => false,
            default => false,
        };
    }

    /**
     * Check if users are friends of friends.
     */
    protected function areFriendsOfFriends($user1, $user2): bool
    {
        // Get user1's friends
        $user1Friends = $user1->friends()->pluck('id');
        
        // Get user2's friends
        $user2Friends = $user2->friends()->pluck('id');
        
        // Check if they have mutual friends
        return $user1Friends->intersect($user2Friends)->isNotEmpty();
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function attributes(): array
    {
        return [
            'user_id' => 'user',
        ];
    }
} 