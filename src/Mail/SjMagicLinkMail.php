<?php

namespace Platform\Syltjunkie\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Platform\Syltjunkie\Models\SjEntityOwner;

class SjMagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public SjEntityOwner $owner;
    public string $magicLink;

    public function __construct(SjEntityOwner $owner, string $token, ?string $redirectUrl = null)
    {
        $this->owner = $owner;

        $frontendUrl = rtrim(config('syltjunkie.owner_auth.frontend_url', 'https://syltjunkie.de'), '/');

        $params = [
            'email' => $owner->email,
            'token' => $token,
        ];

        if ($redirectUrl) {
            $params['redirect'] = $redirectUrl;
        }

        $this->magicLink = $frontendUrl . '/auth/verify?' . http_build_query($params);
    }

    public function build(): self
    {
        return $this
            ->subject('Dein Login-Link für Syltjunkie')
            ->view('syltjunkie::emails.magic-link', [
                'magicLink' => $this->magicLink,
                'ownerName' => $this->owner->name ?? $this->owner->email,
            ]);
    }
}
