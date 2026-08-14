<?php

namespace App\Mail;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\I18n;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl,
        public ?string $locale = null
    ) {
    }

    public function build(): self
    {
        $locale = $this->locale ?: $this->user->preferred_locale ?: 'en';

        return $this->subject(I18n::get('email.password_reset.subject', locale: $locale))
            ->view('emails.password-reset-link', [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'settings' => SiteSetting::current(),
                'locale' => $locale,
                'copy' => [
                    'title' => I18n::get('email.password_reset.title', locale: $locale),
                    'greeting' => I18n::get('email.password_reset.greeting', ['name' => $this->user->name ?? 'customer'], $locale),
                    'body' => I18n::get('email.password_reset.copy', locale: $locale),
                    'button' => I18n::get('email.password_reset.button', locale: $locale),
                    'footer' => I18n::get('email.password_reset.footer', locale: $locale),
                ],
            ]);
    }
}
