<?php

namespace Platform\Syltjunkie\Services;

use Postmark\PostmarkClient;
use Platform\Syltjunkie\Models\SjEntityOwner;

class SjMailService
{
    public static function sendMagicLink(
        SjEntityOwner $owner,
        string $token,
    ): void {
        $from = $owner->from_address;
        if (!$from) {
            throw new \RuntimeException("Keine Absender-Adresse für Owner #{$owner->id} ({$owner->email}) hinterlegt.");
        }

        $redirectUrl = $owner->redirect_url;
        if (!$redirectUrl) {
            throw new \RuntimeException("Keine Redirect-URL für Owner #{$owner->id} ({$owner->email}) hinterlegt.");
        }

        $magicLink = rtrim($redirectUrl, '/') . '?' . http_build_query([
            'email' => $owner->email,
            'token' => $token,
        ]);
        $ownerName = $owner->name ?? $owner->email;

        $html = view('syltjunkie::emails.magic-link', [
            'magicLink' => $magicLink,
            'ownerName' => $ownerName,
        ])->render();

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
