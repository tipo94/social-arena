@extends('emails.layout')

@section('content')
    <div class="greeting">
        Welcome to {{ config('app.name') }}, {{ $user->name }}! 🎉
    </div>
    
    <div class="message">
        <p>Thank you for joining our community! We're excited to have you on board.</p>
        
        <p>{{ config('app.name') }} is your new platform for connecting with like-minded individuals, sharing ideas, and building meaningful relationships through our AI-powered features.</p>
        
        <p>Here's what you can do to get started:</p>
        
        <ul style="margin: 16px 0; padding-left: 20px; color: #4b5563;">
            <li style="margin: 8px 0;">Complete your profile to help others discover you</li>
            <li style="margin: 8px 0;">Upload a profile picture</li>
            <li style="margin: 8px 0;">Make your first post and introduce yourself</li>
            <li style="margin: 8px 0;">Find and connect with friends</li>
            <li style="margin: 8px 0;">Explore groups that match your interests</li>
        </ul>
    </div>
    
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{ $dashboardUrl }}" class="button">
            Get Started
        </a>
    </div>
    
    <div class="divider"></div>
    
    <div class="message">
        <p><strong>Need help?</strong> We're here for you! Check out our help center or reach out to our support team if you have any questions.</p>
        
        <p>Welcome to the community!</p>
        
        <p style="margin-top: 24px;">
            Best regards,<br>
            <strong>The {{ config('app.name') }} Team</strong>
        </p>
    </div>
@endsection 