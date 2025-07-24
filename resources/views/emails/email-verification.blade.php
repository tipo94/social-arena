@extends('emails.layout')

@section('content')
    <div class="greeting">
        Verify Your Email Address
    </div>
    
    <div class="message">
        <p>Hi {{ $user->name }},</p>
        
        <p>Thank you for registering with {{ config('app.name') }}! To complete your registration and ensure the security of your account, please verify your email address by clicking the button below.</p>
        
        <p><strong>This verification link will expire in 60 minutes.</strong></p>
    </div>
    
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{ $verificationUrl }}" class="button">
            Verify Email Address
        </a>
    </div>
    
    <div class="divider"></div>
    
    <div class="message">
        <p><strong>Didn't register for {{ config('app.name') }}?</strong></p>
        <p>If you didn't create an account with us, please ignore this email. No further action is required.</p>
        
        <p><strong>Having trouble with the button?</strong></p>
        <p>Copy and paste this URL into your browser:</p>
        <p style="word-break: break-all; color: #6b7280; font-size: 14px; background-color: #f3f4f6; padding: 12px; border-radius: 6px;">
            {{ $verificationUrl }}
        </p>
        
        <p style="margin-top: 24px;">
            Best regards,<br>
            <strong>The {{ config('app.name') }} Team</strong>
        </p>
    </div>
@endsection 