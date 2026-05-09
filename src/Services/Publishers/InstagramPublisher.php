<?php

namespace Platform\Syltjunkie\Services\Publishers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Integrations\Models\IntegrationsInstagramAccount;
use Platform\Integrations\Services\MetaIntegrationService;
use Platform\Syltjunkie\Contracts\ChannelPublisherContract;
use Platform\Syltjunkie\Models\SjChannel;
use Platform\Syltjunkie\Models\SjChannelPost;

class InstagramPublisher implements ChannelPublisherContract
{
    protected MetaIntegrationService $metaService;

    public function __construct(MetaIntegrationService $metaService)
    {
        $this->metaService = $metaService;
    }

    public function publish(SjChannel $channel, SjChannelPost $post): array
    {
        $connectionId = $channel->integration_connection_id;
        $instagramAccountId = $channel->instagram_account_id;

        if (!$connectionId || !$instagramAccountId) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Channel-Config unvollständig: integration_connection_id oder instagram_account_id fehlt.',
            ];
        }

        $connection = IntegrationConnection::find($connectionId);
        if (!$connection) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'IntegrationConnection nicht gefunden.',
            ];
        }

        $accessToken = $this->metaService->getValidAccessToken($connection);
        if (!$accessToken) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Kein gültiger Meta Access Token. Bitte Verbindung prüfen.',
            ];
        }

        $instagramAccount = IntegrationsInstagramAccount::find($instagramAccountId);
        if (!$instagramAccount) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Account nicht gefunden.',
            ];
        }

        $igUserId = $instagramAccount->external_id;
        $apiVersion = config('integrations.oauth2.providers.meta.api_version', '21.0');

        // Build caption from post caption + hashtags
        $caption = $post->caption ?? '';
        if (!empty($post->hashtags) && is_array($post->hashtags)) {
            $hashtagText = implode(' ', array_map(fn($h) => str_starts_with($h, '#') ? $h : "#{$h}", $post->hashtags));
            $caption = trim($caption . "\n\n" . $hashtagText);
        }

        // Load images
        $images = $post->images()->with('contextFile')->orderByPivot('sort_order')->get();

        return match ($post->post_type) {
            'carousel' => $this->publishCarousel($igUserId, $caption, $images, $accessToken, $apiVersion, $post),
            'reel' => $this->publishReel($igUserId, $caption, $post, $accessToken, $apiVersion),
            default => $this->publishPhoto($igUserId, $caption, $images, $accessToken, $apiVersion, $post),
        };
    }

    private function publishPhoto(string $igUserId, string $caption, $images, string $accessToken, string $apiVersion, SjChannelPost $post): array
    {
        $image = $images->first();
        if (!$image) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Kein Bild am Post vorhanden.',
            ];
        }

        $imageUrl = $image->contextFile?->url;
        if (!$imageUrl) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Bild-URL konnte nicht ermittelt werden.',
            ];
        }

        $containerResponse = Http::post("https://graph.facebook.com/{$apiVersion}/{$igUserId}/media", [
            'image_url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);

        if ($containerResponse->failed()) {
            $error = $containerResponse->json()['error'] ?? [];
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Container-Erstellung fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannter Fehler'),
            ];
        }

        $containerId = $containerResponse->json()['id'] ?? null;
        if (!$containerId) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Container-ID nicht erhalten.',
            ];
        }

        return $this->publishContainer($igUserId, $containerId, $accessToken, $apiVersion, $post);
    }

    private function publishCarousel(string $igUserId, string $caption, $images, string $accessToken, string $apiVersion, SjChannelPost $post): array
    {
        $childContainerIds = [];

        foreach ($images as $image) {
            $imageUrl = $image->contextFile?->url;
            if (!$imageUrl) {
                continue;
            }

            $childResponse = Http::post("https://graph.facebook.com/{$apiVersion}/{$igUserId}/media", [
                'image_url' => $imageUrl,
                'is_carousel_item' => true,
                'access_token' => $accessToken,
            ]);

            if ($childResponse->failed()) {
                $error = $childResponse->json()['error'] ?? [];
                return [
                    'success' => false,
                    'external_post_id' => null,
                    'error' => 'Instagram Carousel-Slide fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannter Fehler'),
                ];
            }

            $childId = $childResponse->json()['id'] ?? null;
            if ($childId) {
                $childContainerIds[] = $childId;
            }
        }

        if (count($childContainerIds) < 2) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Carousel erfordert mindestens 2 Slides (nur ' . count($childContainerIds) . ' erstellt).',
            ];
        }

        $carouselResponse = Http::post("https://graph.facebook.com/{$apiVersion}/{$igUserId}/media", [
            'media_type' => 'CAROUSEL',
            'children' => implode(',', $childContainerIds),
            'caption' => $caption,
            'access_token' => $accessToken,
        ]);

        if ($carouselResponse->failed()) {
            $error = $carouselResponse->json()['error'] ?? [];
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Carousel-Container fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannter Fehler'),
            ];
        }

        $containerId = $carouselResponse->json()['id'] ?? null;
        if (!$containerId) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Carousel Container-ID nicht erhalten.',
            ];
        }

        return $this->publishContainer($igUserId, $containerId, $accessToken, $apiVersion, $post);
    }

    private function publishReel(string $igUserId, string $caption, SjChannelPost $post, string $accessToken, string $apiVersion): array
    {
        $videoUrl = $post->meta_data['video_url'] ?? null;
        if (!$videoUrl) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Keine video_url in meta_data vorhanden.',
            ];
        }

        $containerParams = [
            'media_type' => 'REELS',
            'video_url' => $videoUrl,
            'caption' => $caption,
            'access_token' => $accessToken,
        ];

        if (!empty($post->meta_data['cover_url'])) {
            $containerParams['cover_url'] = $post->meta_data['cover_url'];
        }

        $containerResponse = Http::post("https://graph.facebook.com/{$apiVersion}/{$igUserId}/media", $containerParams);

        if ($containerResponse->failed()) {
            $error = $containerResponse->json()['error'] ?? [];
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Reel-Container fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannter Fehler'),
            ];
        }

        $containerId = $containerResponse->json()['id'] ?? null;
        if (!$containerId) {
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Container-ID nicht erhalten.',
            ];
        }

        return $this->publishContainer($igUserId, $containerId, $accessToken, $apiVersion, $post);
    }

    private function publishContainer(string $igUserId, string $containerId, string $accessToken, string $apiVersion, SjChannelPost $post): array
    {
        $publishResponse = Http::post("https://graph.facebook.com/{$apiVersion}/{$igUserId}/media_publish", [
            'creation_id' => $containerId,
            'access_token' => $accessToken,
        ]);

        if ($publishResponse->failed()) {
            $error = $publishResponse->json()['error'] ?? [];
            Log::error('Instagram media_publish failed', [
                'post_id' => $post->id,
                'container_id' => $containerId,
                'error' => $error,
            ]);
            return [
                'success' => false,
                'external_post_id' => null,
                'error' => 'Instagram Publishing fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannter Fehler'),
            ];
        }

        $mediaId = $publishResponse->json()['id'] ?? null;

        return [
            'success' => true,
            'external_post_id' => $mediaId,
            'error' => null,
        ];
    }
}
