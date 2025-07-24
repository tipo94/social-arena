<?php

namespace App\Console\Commands;

use App\Services\EmailService;
use Illuminate\Console\Command;

class TestEmailConfiguration extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'email:test {email : Email address to send test email to}';

    /**
     * The console command description.
     */
    protected $description = 'Test email configuration by sending a test email';

    /**
     * Execute the console command.
     */
    public function handle(EmailService $emailService): int
    {
        $email = $this->argument('email');

        $this->info("Testing email configuration...");
        $this->info("Sending test email to: {$email}");

        $result = $emailService->testEmailConfiguration($email);

        if ($result['success']) {
            $this->info("✅ Email sent successfully!");
            $this->line("Configuration Details:");
            $this->line("• Mailer: " . config('mail.default'));
            $this->line("• From: " . config('mail.from.address') . " (" . config('mail.from.name') . ")");
            $this->line("• Environment: " . config('app.env'));

            return self::SUCCESS;
        } else {
            $this->error("❌ Email failed to send!");
            $this->error("Error: " . $result['error']);

            $this->newLine();
            $this->warn("Common issues to check:");
            $this->line("• MAIL_HOST and MAIL_PORT settings");
            $this->line("• MAIL_USERNAME and MAIL_PASSWORD (if required)");
            $this->line("• MAIL_ENCRYPTION settings");
            $this->line("• Firewall blocking SMTP ports");
            $this->line("• MailHog running (for development)");

            return self::FAILURE;
        }
    }
} 