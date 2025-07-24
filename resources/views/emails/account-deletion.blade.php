<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Account Deletion - {{ $appName }}</title>
    <style>
        /* Reset styles */
        body, table, td, p, h1, h2, h3, h4, h5, h6 { margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        
        /* Container */
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        
        /* Header */
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 28px; font-weight: 600; margin: 0; }
        .header .subtitle { color: #e2e8f0; font-size: 16px; margin-top: 8px; }
        
        /* Content */
        .content { padding: 40px 30px; }
        .content h2 { color: #1a202c; font-size: 24px; font-weight: 600; margin-bottom: 20px; }
        .content p { color: #4a5568; font-size: 16px; line-height: 1.6; margin-bottom: 20px; }
        
        /* Alert boxes */
        .alert { padding: 20px; border-radius: 8px; margin: 20px 0; }
        .alert-warning { background-color: #fef5e7; border-left: 4px solid #f6ad55; }
        .alert-danger { background-color: #fed7d7; border-left: 4px solid #fc8181; }
        .alert-success { background-color: #f0fff4; border-left: 4px solid #68d391; }
        .alert-info { background-color: #ebf8ff; border-left: 4px solid #63b3ed; }
        
        /* Button */
        .btn { display: inline-block; padding: 12px 30px; text-decoration: none; border-radius: 6px; font-weight: 600; text-align: center; margin: 10px 5px; }
        .btn-primary { background-color: #667eea; color: #ffffff; }
        .btn-danger { background-color: #e53e3e; color: #ffffff; }
        .btn-success { background-color: #38a169; color: #ffffff; }
        
        /* Info box */
        .info-box { background-color: #f7fafc; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .info-row { display: flex; justify-content: space-between; margin: 8px 0; }
        .info-label { font-weight: 600; color: #2d3748; }
        .info-value { color: #4a5568; }
        
        /* Footer */
        .footer { background-color: #f7fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; }
        .footer p { color: #718096; font-size: 14px; margin: 5px 0; }
        .footer a { color: #667eea; text-decoration: none; }
        
        /* Responsive */
        @media only screen and (max-width: 600px) {
            .content { padding: 20px; }
            .header { padding: 30px 20px; }
            .header h1 { font-size: 24px; }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="header">
            <h1>{{ $appName }}</h1>
            @if($type === 'requested')
                <div class="subtitle">Account Deletion Requested</div>
            @elseif($type === 'cancelled')
                <div class="subtitle">Account Deletion Cancelled</div>
            @elseif($type === 'final_warning')
                <div class="subtitle">Final Deletion Warning</div>
            @else
                <div class="subtitle">Account Update</div>
            @endif
        </div>

        <!-- Content -->
        <div class="content">
            <h2>Hello {{ $user->name }}</h2>

            @if($type === 'requested')
                <!-- Deletion Requested -->
                <p>Your account deletion request has been received and processed. Your account has been temporarily deactivated and is scheduled for permanent deletion.</p>
                
                <div class="alert alert-warning">
                    <strong>Important:</strong> Your account and all associated data will be permanently deleted on <strong>{{ $deletionDate ? $deletionDate->format('F j, Y \a\t g:i A') : 'the scheduled date' }}</strong>.
                </div>

                <div class="info-box">
                    <div class="info-row">
                        <span class="info-label">Grace Period:</span>
                        <span class="info-value">{{ $gracePeriodDays }} days</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Deletion Date:</span>
                        <span class="info-value">{{ $deletionDate ? $deletionDate->format('F j, Y \a\t g:i A') : 'Not scheduled' }}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Account Status:</span>
                        <span class="info-value">Temporarily Deactivated</span>
                    </div>
                </div>

                <p><strong>What happens during the grace period:</strong></p>
                <ul style="color: #4a5568; margin-left: 20px; margin-bottom: 20px;">
                    <li>Your account is temporarily deactivated and hidden from other users</li>
                    <li>You can still log in and cancel the deletion request</li>
                    <li>Your data remains intact and can be fully restored</li>
                    <li>After the grace period expires, deletion becomes permanent and irreversible</li>
                </ul>

                <p><strong>To cancel this deletion request:</strong></p>
                <p>Log into your account and visit your account settings, or click the button below:</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $appUrl }}/account/deletion/cancel" class="btn btn-success">Cancel Deletion Request</a>
                    <a href="{{ $appUrl }}/account/settings" class="btn btn-primary">Account Settings</a>
                </div>

            @elseif($type === 'cancelled')
                <!-- Deletion Cancelled -->
                <p>Great news! Your account deletion request has been successfully cancelled.</p>
                
                <div class="alert alert-success">
                    <strong>Account Restored:</strong> Your account has been reactivated and is now fully functional.
                </div>

                <p>Your account and all your data have been fully restored. You can continue using {{ $appName }} as normal.</p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $appUrl }}/dashboard" class="btn btn-primary">Go to Dashboard</a>
                </div>

            @elseif($type === 'final_warning')
                <!-- Final Warning -->
                <p>This is your final notice that your account is scheduled for permanent deletion in approximately 1 hour.</p>
                
                <div class="alert alert-danger">
                    <strong>Last Chance:</strong> This is your final opportunity to cancel the deletion request. After this time, the deletion will be irreversible.
                </div>

                <p>If you want to keep your account, you must cancel the deletion request immediately:</p>

                <div style="text-align: center; margin: 30px 0;">
                    <a href="{{ $appUrl }}/account/deletion/cancel" class="btn btn-danger">Cancel Deletion Now</a>
                </div>

                <p><strong>What will be deleted:</strong></p>
                <ul style="color: #4a5568; margin-left: 20px; margin-bottom: 20px;">
                    <li>Your profile and personal information</li>
                    <li>All your posts and comments</li>
                    <li>Your friend connections and messages</li>
                    <li>All uploaded files and media</li>
                    <li>Account settings and preferences</li>
                </ul>

            @else
                <!-- Default/Unknown type -->
                <p>This is a notification regarding your account deletion request.</p>
                
                <div class="alert alert-info">
                    If you have any questions about this email, please contact our support team.
                </div>
            @endif

            @if($type !== 'final_warning')
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;">
                
                <p><strong>Need help?</strong></p>
                <p>If you have any questions or concerns about this process, please don't hesitate to contact our support team.</p>
            @endif
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>{{ $appName }}</strong></p>
            <p>
                <a href="{{ $appUrl }}">Website</a> | 
                <a href="mailto:{{ $supportEmail }}">Support</a> | 
                <a href="{{ $appUrl }}/privacy">Privacy Policy</a>
            </p>
            <p style="margin-top: 20px; color: #a0aec0; font-size: 12px;">
                This email was sent to {{ $user->email }} regarding your account deletion request.
                <br>
                @if($type === 'requested' || $type === 'final_warning')
                    If you did not request account deletion, please contact support immediately.
                @endif
            </p>
        </div>
    </div>
</body>
</html> 