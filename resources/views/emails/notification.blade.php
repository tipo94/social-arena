@extends('emails.layout')

@section('content')
    <div class="greeting">
        {{ $notificationData['title'] }}
    </div>
    
    <div class="message">
        <p>Hi {{ $user->name }},</p>
        
        <p>{{ $notificationData['message'] }}</p>
        
        @if(isset($data['preview']) && $data['preview'])
            <div style="background-color: #f9fafb; border-left: 4px solid #4f46e5; padding: 16px; margin: 16px 0; border-radius: 6px;">
                <p style="margin: 0; font-style: italic; color: #6b7280;">
                    "{{ $data['preview'] }}"
                </p>
            </div>
        @endif
    </div>
    
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{ $notificationData['action_url'] }}" class="button">
            {{ $notificationData['action_text'] }}
        </a>
    </div>
    
    <div class="divider"></div>
    
    <div class="message">
        <p><strong>Manage your notifications</strong></p>
        <p>You can adjust your notification preferences in your account settings.</p>
        
        <div style="text-align: center; margin: 16px 0;">
            <a href="{{ config('app.url') }}/settings/notifications" class="button button-secondary">
                Notification Settings
            </a>
        </div>
        
        <p style="margin-top: 24px;">
            Best regards,<br>
            <strong>The {{ config('app.name') }} Team</strong>
        </p>
    </div>
@endsection 