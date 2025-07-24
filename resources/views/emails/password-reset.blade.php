@extends('emails.layout')

@section('content')
    <div class="greeting">
        Reset Your Password
    </div>
    
    <div class="message">
        <p>Hi {{ $user->name }},</p>
        
        <p>We received a request to reset your password for your {{ config('app.name') }} account. Click the button below to reset your password.</p>
        
        <p><strong>This password reset link will expire in 60 minutes.</strong></p>
    </div>
    
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{ $resetUrl }}" class="button">
            Reset Password
        </a>
    </div>
    
    <div class="divider"></div>
    
    <div class="message">
        <p><strong>Didn't request a password reset?</strong></p>
        <p>If you didn't request a password reset, please ignore this email. Your password will remain unchanged.</p>
        
        <p><strong>Security tip:</strong> If you're concerned about the security of your account, we recommend changing your password and reviewing your recent account activity.</p>
        
        <p><strong>Having trouble with the button?</strong></p>
        <p>Copy and paste this URL into your browser:</p>
        <p style="word-break: break-all; color: #6b7280; font-size: 14px; background-color: #f3f4f6; padding: 12px; border-radius: 6px;">
            {{ $resetUrl }}
        </p>
        
        <p style="margin-top: 24px;">
            Best regards,<br>
            <strong>The {{ config('app.name') }} Team</strong>
        </p>
    </div>
@endsection 