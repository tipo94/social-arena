@extends('emails.layout')

@section('content')
    <div class="greeting">
        Email Configuration Test ✅
    </div>
    
    <div class="message">
        <p><strong>Congratulations!</strong></p>
        
        <p>Your email configuration is working correctly. This test email was sent successfully at {{ $testTime }}.</p>
        
        <p>Here are the current mail settings:</p>
        
        <ul style="margin: 16px 0; padding-left: 20px; color: #4b5563;">
            <li style="margin: 8px 0;"><strong>Mailer:</strong> {{ config('mail.default') }}</li>
            <li style="margin: 8px 0;"><strong>From Address:</strong> {{ config('mail.from.address') }}</li>
            <li style="margin: 8px 0;"><strong>From Name:</strong> {{ config('mail.from.name') }}</li>
            <li style="margin: 8px 0;"><strong>Application:</strong> {{ config('app.name') }}</li>
            <li style="margin: 8px 0;"><strong>Environment:</strong> {{ config('app.env') }}</li>
        </ul>
        
        <p>Your email system is ready to send notifications to users!</p>
        
        <p style="margin-top: 24px;">
            Best regards,<br>
            <strong>The {{ config('app.name') }} Team</strong>
        </p>
    </div>
@endsection 