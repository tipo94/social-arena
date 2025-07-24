# AI-Book Email System

This document describes the comprehensive email notification system for the AI-Book social networking platform.

## Overview

The email system is designed to handle all email communications including user notifications, authentication emails, welcome messages, and system alerts. It supports both immediate delivery and queued processing for optimal performance.

## Email Architecture

### Email Service

The `EmailService` class provides a unified interface for all email operations:

- **Immediate Sending**: Direct email delivery
- **Queued Delivery**: Background email processing
- **Bulk Operations**: Efficient mass email sending
- **Error Handling**: Comprehensive error tracking and logging
- **Testing**: Email configuration validation

### Email Types

#### Authentication Emails
- **Welcome Email**: Sent after user registration
- **Email Verification**: Account activation emails
- **Password Reset**: Secure password reset links

#### Notification Emails
- **Friend Requests**: New friend request alerts
- **Comments**: Comment notifications on posts
- **Likes**: Post like notifications
- **Mentions**: User mention alerts
- **Group Invitations**: Group membership invites
- **Messages**: New message notifications

#### System Emails
- **Test Emails**: Configuration validation
- **Admin Alerts**: System notifications

## Configuration

### Environment Variables

Configure email settings in your `.env` file:

```bash
# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@ai-book.com"
MAIL_FROM_NAME="${APP_NAME}"

# MailHog (Development)
MAILHOG_HOST=mailhog
MAILHOG_PORT=1025

# Production Email Services
# For Mailgun:
# MAIL_MAILER=mailgun
# MAILGUN_DOMAIN=your-domain.com
# MAILGUN_SECRET=your-secret-key

# For AWS SES:
# MAIL_MAILER=ses
# AWS_ACCESS_KEY_ID=your-access-key
# AWS_SECRET_ACCESS_KEY=your-secret
# AWS_DEFAULT_REGION=us-east-1

# Email Templates
MAIL_BRAND_COLOR=#4f46e5
MAIL_LOGO_URL=
MAIL_COMPANY_NAME="AI-Book"
MAIL_COMPANY_ADDRESS="123 Tech Street, Silicon Valley, CA"

# Queue Settings
MAIL_QUEUE=emails
MAIL_DELAY=0
MAIL_RETRY_AFTER=300
MAIL_MAX_ATTEMPTS=3
```

### Mail Drivers

The system supports multiple mail drivers:

#### Development
- **MailHog**: Local email testing (default for development)
- **Log**: Email content logged to files
- **Array**: In-memory storage for testing

#### Production
- **SMTP**: Generic SMTP server support
- **Mailgun**: Mailgun email service
- **SES**: Amazon Simple Email Service
- **Postmark**: Postmark email service
- **Failover**: Automatic fallback between services

## EmailService API

### Basic Operations

```php
use App\Services\EmailService;
use App\Mail\WelcomeEmail;

$emailService = new EmailService();

// Send immediate email
$result = $emailService->sendEmail('user@example.com', new WelcomeEmail($user), $user);

// Queue email for later delivery
$result = $emailService->queueEmail('user@example.com', new WelcomeEmail($user), 60);

// Send bulk emails
$recipients = ['user1@example.com', 'user2@example.com'];
$result = $emailService->sendBulkEmail($recipients, new WelcomeEmail($user));
```

### Notification Helpers

```php
// Send welcome email
$result = $emailService->sendWelcomeEmail($user);

// Send email verification
$result = $emailService->sendEmailVerification($user);

// Send password reset
$result = $emailService->sendPasswordReset($user, $token);

// Send notification
$result = $emailService->sendNotificationEmail($user, 'friend_request', [
    'sender_name' => 'John Doe'
]);
```

### Testing and Monitoring

```php
// Test email configuration
$result = $emailService->testEmailConfiguration('test@example.com');

// Get email statistics
$stats = $emailService->getEmailStats('24h');
```

## HTTP API Endpoints

### Test Configuration

```http
POST /api/email/test-configuration
Authorization: Bearer {token}
Content-Type: application/json

{
  "email": "test@example.com"
}
```

### Send Welcome Email

```http
POST /api/email/send-welcome
Authorization: Bearer {token}
```

### Send Test Notification

```http
POST /api/email/send-test-notification
Authorization: Bearer {token}
Content-Type: application/json

{
  "type": "friend_request",
  "data": {
    "sender_name": "John Doe"
  }
}
```

### Get Email Statistics

```http
GET /api/email/stats?period=24h
Authorization: Bearer {token}
```

## Email Templates

### Base Layout

All emails use a consistent base layout (`emails.layout`) featuring:

- **Responsive Design**: Mobile-friendly email templates
- **Brand Consistency**: Configurable colors and branding
- **Dark Mode Support**: Automatic dark mode detection
- **Accessibility**: Screen reader compatible markup

### Template Structure

```blade
@extends('emails.layout')

@section('content')
    <div class="greeting">
        Hello {{ $user->name }}!
    </div>
    
    <div class="message">
        <p>Your email content here...</p>
    </div>
    
    <div style="text-align: center; margin: 32px 0;">
        <a href="{{ $actionUrl }}" class="button">
            Call to Action
        </a>
    </div>
@endsection
```

### Available CSS Classes

- `.greeting`: Main heading style
- `.message`: Body content styling
- `.button`: Primary action button
- `.button-secondary`: Secondary action button
- `.divider`: Content separator line

## Queue Management

### Queue Configuration

Emails are processed through Laravel's queue system:

```php
// Queue configuration in config/queue.php
'default' => env('QUEUE_CONNECTION', 'database'),

// Email-specific queue settings
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'queue' => env('REDIS_QUEUE', 'default'),
    ],
],
```

### Queue Workers

Start queue workers to process emails:

```bash
# Start queue worker
php artisan queue:work

# Process specific queue
php artisan queue:work --queue=emails

# Process with memory limit
php artisan queue:work --memory=512
```

### Queue Monitoring

```bash
# Check queue status
php artisan queue:monitor

# Restart queue workers
php artisan queue:restart

# Clear failed jobs
php artisan queue:flush
```

## Testing

### Email Testing in Development

Using MailHog for local email testing:

1. **Start MailHog**: Included in Docker Compose
2. **Access Web UI**: http://localhost:8025
3. **Send Test Email**: All emails captured by MailHog

### Automated Testing

```php
// In tests, use Mail facade
use Illuminate\Support\Facades\Mail;

Mail::fake();

// Test email sending
$emailService->sendWelcomeEmail($user);

// Assert email was sent
Mail::assertSent(WelcomeEmail::class, function ($mail) use ($user) {
    return $mail->hasTo($user->email);
});
```

### Console Testing

```bash
# Test email configuration
php artisan email:test user@example.com
```

## Console Commands

### Test Email Configuration

```bash
php artisan email:test {email}
```

Tests email configuration by sending a test email to the specified address.

### Queue Management

```bash
# Process email queue
php artisan queue:work --queue=emails

# Monitor queue
php artisan queue:monitor emails

# Clear failed jobs
php artisan queue:flush
```

## Production Setup

### Email Service Configuration

#### Mailgun Setup

```bash
MAIL_MAILER=mailgun
MAILGUN_DOMAIN=mg.yourdomain.com
MAILGUN_SECRET=your-secret-key
```

#### AWS SES Setup

```bash
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_SES_REGION=us-east-1
```

#### SMTP Setup

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### Performance Optimization

1. **Queue Processing**: Use Redis for fast queue processing
2. **Multiple Workers**: Run multiple queue workers for high volume
3. **Rate Limiting**: Configure appropriate send rates
4. **Monitoring**: Set up email delivery monitoring

### Failover Configuration

Configure multiple email services for redundancy:

```php
// In config/mail.php
'mailers' => [
    'failover' => [
        'transport' => 'failover',
        'mailers' => [
            'mailgun',
            'ses',
            'smtp',
        ],
        'retry_after' => 60,
    ],
],
```

## Security Considerations

### Email Security

1. **SPF Records**: Configure SPF for your domain
2. **DKIM Signing**: Enable DKIM authentication
3. **DMARC Policy**: Set up DMARC for email security
4. **TLS Encryption**: Use encrypted connections
5. **Rate Limiting**: Prevent email abuse

### Content Security

1. **Input Sanitization**: Sanitize email content
2. **Link Validation**: Validate all email links
3. **Attachment Scanning**: Scan email attachments
4. **Unsubscribe Links**: Include unsubscribe options

## Monitoring and Analytics

### Email Metrics

Track important email metrics:

- **Delivery Rate**: Successful email deliveries
- **Bounce Rate**: Failed deliveries
- **Open Rate**: Email opens (if tracking enabled)
- **Click Rate**: Link clicks
- **Unsubscribe Rate**: Opt-out requests

### Error Tracking

All email errors are logged with:

- **Timestamp**: When error occurred
- **Recipient**: Target email address
- **Error Message**: Detailed error information
- **Mailer Type**: Which mailer failed
- **User Context**: Associated user information

### Performance Monitoring

Monitor email system performance:

```bash
# Check queue size
php artisan queue:monitor

# View failed jobs
php artisan queue:failed

# Email service health check
php artisan email:test admin@yoursite.com
```

## Troubleshooting

### Common Issues

**Emails not sending**:
- Check MAIL_MAILER configuration
- Verify SMTP credentials
- Ensure queue workers are running
- Check firewall settings

**MailHog not receiving emails**:
- Verify MailHog container is running
- Check MAIL_HOST=mailhog setting
- Restart Docker containers

**Queue jobs failing**:
- Check failed_jobs table
- Verify queue worker memory limits
- Review error logs
- Restart queue workers

**Template rendering errors**:
- Check Blade syntax in templates
- Verify template file paths
- Clear view cache: `php artisan view:clear`

### Debug Commands

```bash
# Test email configuration
php artisan email:test test@example.com

# Check mail configuration
php artisan config:show mail

# View queue status
php artisan queue:monitor

# Process queue manually
php artisan queue:work --once

# Clear caches
php artisan config:clear
php artisan view:clear
```

## Best Practices

### Email Design

1. **Mobile First**: Design for mobile devices
2. **Simple Layout**: Keep templates clean and readable
3. **Clear CTAs**: Use prominent call-to-action buttons
4. **Alt Text**: Include alt text for images
5. **Testing**: Test across email clients

### Content Guidelines

1. **Personalization**: Use recipient's name and relevant data
2. **Clear Subject**: Write descriptive subject lines
3. **Concise Content**: Keep emails brief and focused
4. **Professional Tone**: Maintain consistent brand voice
5. **Legal Compliance**: Include required legal information

### Technical Best Practices

1. **Queue Processing**: Use queues for non-critical emails
2. **Error Handling**: Implement comprehensive error handling
3. **Logging**: Log all email activities
4. **Testing**: Maintain thorough test coverage
5. **Monitoring**: Set up email delivery monitoring

This email system provides a robust, scalable foundation for all email communications in the AI-Book social networking platform. 