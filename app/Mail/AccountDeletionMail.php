<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        protected User $user,
        protected string $type // 'requested', 'cancelled', 'completed'
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = match ($this->type) {
            'requested' => 'Account Deletion Requested - ' . config('app.name'),
            'cancelled' => 'Account Deletion Cancelled - ' . config('app.name'),
            'completed' => 'Account Successfully Deleted - ' . config('app.name'),
            default => 'Account Update - ' . config('app.name'),
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deletion',
            with: [
                'user' => $this->user,
                'type' => $this->type,
                'gracePeriodDays' => \App\Services\AccountDeletionService::GRACE_PERIOD_DAYS,
                'deletionDate' => $this->user->will_be_deleted_at,
                'supportEmail' => config('mail.support_email', 'support@' . config('app.domain')),
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
} 