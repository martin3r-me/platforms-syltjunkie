<?php

namespace Platform\Syltjunkie\Observers;

use Platform\Core\Contracts\SeoUrlServiceInterface;
use Platform\Syltjunkie\Models\SjEntityUrl;

/**
 * Spiegelt Syltjunkie-Entitäts-URLs ins zentrale SEO-Modul (Signal-Engine).
 *
 * Lose gekoppelt: ruft das SEO-Modul nur, wenn dessen Contract gebunden ist, und
 * fängt jeden Fehler ab — ein Problem im SEO-Modul darf einen Syltjunkie-Save nie
 * brechen. Nur echte Web-Seiten (platform=website) werden gespiegelt; Social-
 * Profile o.Ä. sind keine SEO-relevanten, rankenden Seiten.
 */
class SjEntityUrlObserver
{
    /** Plattformen, deren URLs als eigene, rankbare Seiten ins SEO-Modul gehören. */
    protected const SEO_PLATFORMS = ['website'];

    public function created(SjEntityUrl $url): void
    {
        $this->sync($url);
    }

    public function updated(SjEntityUrl $url): void
    {
        $this->sync($url);
    }

    public function deleted(SjEntityUrl $url): void
    {
        $seo = $this->seo();
        if (! $seo) {
            return;
        }

        try {
            $seo->unregister((int) $url->team_id, (string) $url->url, 'syltjunkie', 'sj_entity_url', (int) $url->id);
        } catch (\Throwable $e) {
            // bewusst geschluckt — SEO-Modul-Fehler dürfen Syltjunkie nicht beeinflussen
        }
    }

    protected function sync(SjEntityUrl $url): void
    {
        $seo = $this->seo();
        if (! $seo) {
            return;
        }

        if (! in_array($url->platform, self::SEO_PLATFORMS, true) || ! $url->is_active) {
            return;
        }

        try {
            $seo->register((int) $url->team_id, (string) $url->url, [
                'source_module' => 'syltjunkie',
                'source_type' => 'sj_entity_url',
                'source_id' => (int) $url->id,
                'reason' => 'discovered',
                'is_own' => true,
                'priority' => $url->is_primary ? 80 : 60,
                // Syltjunkie-URLs bleiben Plausible-opt-out (fremde Instanz,
                // kein Traffic hier) — bis sie bewusst zugeordnet werden.
                'plausible_enabled' => false,
            ]);
        } catch (\Throwable $e) {
            // bewusst geschluckt (siehe deleted())
        }
    }

    protected function seo(): ?SeoUrlServiceInterface
    {
        if (! app()->bound(SeoUrlServiceInterface::class)) {
            return null;
        }

        return app(SeoUrlServiceInterface::class);
    }
}
