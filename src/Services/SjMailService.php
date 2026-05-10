<?php

namespace Platform\Syltjunkie\Services;

use Postmark\PostmarkClient;
use Platform\Syltjunkie\Models\SjEntityOwner;

class SjMailService
{
    public static function sendMagicLink(
        SjEntityOwner $owner,
        string $token,
        ?string $redirectUrl = null,
        ?string $fromAddress = null,
    ): void {
        $frontendUrl = rtrim(config('syltjunkie.owner_auth.frontend_url', 'https://syltjunkie.de'), '/');

        $params = [
            'email' => $owner->email,
            'token' => $token,
        ];

        if ($redirectUrl) {
            $params['redirect'] = $redirectUrl;
        }

        $magicLink = $frontendUrl . '/auth/verify?' . http_build_query($params);
        $ownerName = $owner->name ?? $owner->email;

        $html = view('syltjunkie::emails.magic-link', [
            'magicLink' => $magicLink,
            'ownerName' => $ownerName,
        ])->render();

        $from = $fromAddress
            ?? config('syltjunkie.owner_auth.allowed_from_addresses.0')
            ?? env('POSTMARK_FROM', config('mail.from.address'));

        $serverToken = env('POSTMARK_SERVER_TOKEN');

        $client = new PostmarkClient($serverToken);

        $client->sendEmail(
            $from,
            $owner->email,
            'Dein Login-Link für Syltjunkie',
            $html,
        );
    }
}
