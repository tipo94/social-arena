<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmailService;
use App\Mail\WelcomeEmail;
use App\Mail\TestEmail;
use App\Mail\NotificationMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailTest extends TestCase
{
    protected EmailService $emailService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->emailService = new EmailService();
        Mail::fake();
    }

    /**
     * Test email service can send email
     */
    public function test_can_send_email(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);
        $mailable = new TestEmail();

        $result = $this->emailService->sendEmail($user->email, $mailable, $user);

        $this->assertTrue($result['success']);
        $this->assertEquals($user->email, $result['to']);
        
        Mail::assertSent(TestEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    /**
     * Test email service can queue email
     */
    public function test_can_queue_email(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);
        $mailable = new WelcomeEmail($user);

        $result = $this->emailService->queueEmail($user->email, $mailable);

        $this->assertTrue($result['success']);
        $this->assertEquals($user->email, $result['to']);
        
        Mail::assertQueued(WelcomeEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->user->id === $user->id;
        });
    }

    /**
     * Test welcome email sending
     */
    public function test_can_send_welcome_email(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);

        $result = $this->emailService->sendWelcomeEmail($user);

        $this->assertTrue($result['success']);
        
        Mail::assertQueued(WelcomeEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->user->id === $user->id;
        });
    }

    /**
     * Test notification email sending
     */
    public function test_can_send_notification_email(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);
        $notificationType = 'friend_request';
        $data = ['sender_name' => 'John Doe'];

        $result = $this->emailService->sendNotificationEmail($user, $notificationType, $data);

        $this->assertTrue($result['success']);
        
        Mail::assertQueued(NotificationMail::class, function ($mail) use ($user, $notificationType) {
            return $mail->hasTo($user->email) && 
                   $mail->user->id === $user->id && 
                   $mail->type === $notificationType;
        });
    }

    /**
     * Test bulk email sending
     */
    public function test_can_send_bulk_email(): void
    {
        $recipients = ['user1@example.com', 'user2@example.com', 'user3@example.com'];
        $mailable = new TestEmail();

        $result = $this->emailService->sendBulkEmail($recipients, $mailable);

        $this->assertEquals(3, $result['sent']);
        $this->assertEquals(0, $result['failed']);
        
        Mail::assertSent(TestEmail::class, 3);
    }

    /**
     * Test email configuration test endpoint
     */
    public function test_email_configuration_endpoint(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/test-configuration', [
                'email' => 'test@example.com'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertSent(TestEmail::class);
    }

    /**
     * Test send welcome email endpoint
     */
    public function test_send_welcome_email_endpoint(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/send-welcome');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertQueued(WelcomeEmail::class);
    }

    /**
     * Test send test notification endpoint
     */
    public function test_send_test_notification_endpoint(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/email/send-test-notification', [
                'type' => 'friend_request',
                'data' => ['sender_name' => 'Test User']
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        Mail::assertQueued(NotificationMail::class);
    }

    /**
     * Test email stats endpoint
     */
    public function test_email_stats_endpoint(): void
    {
        $user = User::factory()->make(['id' => 1, 'email' => 'test@example.com']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/email/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'period',
                    'total_sent',
                    'total_failed',
                    'success_rate',
                ]
            ]);
    }

    /**
     * Test unauthorized access to email endpoints
     */
    public function test_email_endpoints_require_authentication(): void
    {
        $response = $this->postJson('/api/email/test-configuration', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(401);
    }
} 