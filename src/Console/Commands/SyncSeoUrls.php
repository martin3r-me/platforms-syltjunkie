<?php

namespace Platform\Syltjunkie\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Contracts\SeoUrlServiceInterface;
use Platform\Syltjunkie\Models\SjEntityUrl;

/**
 * Backfill: spiegelt bestehende Syltjunkie-Entitäts-URLs (website) ins zentrale
 * SEO-Modul. Für den erstmaligen Abgleich; laufend erledigt das der Observer.
 */
class SyncSeoUrls extends Command
{
    protected $signature = 'syltjunkie:sync-seo-urls
                            {--team= : Nur ein bestimmtes Team}';

    protected $description = 'Registriert Syltjunkie-Entitäts-URLs (website) im zentralen SEO-Modul';

    public function handle(): int
    {
        if (! app()->bound(SeoUrlServiceInterface::class)) {
            $this->error('SEO-Modul nicht verfügbar (Contract nicht gebunden).');
            return self::FAILURE;
        }

        /** @var SeoUrlServiceInterface $seo */
        $seo = app(SeoUrlServiceInterface::class);

        $query = SjEntityUrl::where('platform', 'website')->where('is_active', true);
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $count = 0;
        $errors = 0;

        $query->orderBy('id')->chunkById(200, function ($rows) use ($seo, &$count, &$errors) {
            foreach ($rows as $url) {
                try {
                    $seo->register((int) $url->team_id, (string) $url->url, [
                        'source_module' => 'syltjunkie',
                        'source_type' => 'sj_entity_url',
                        'source_id' => (int) $url->id,
                        'reason' => 'backfill',
                        'is_own' => true,
                        'priority' => $url->is_primary ? 80 : 60,
                    ]);
                    $count++;
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
        });

        $this->info("{$count} URLs ins SEO-Modul gespiegelt".($errors ? ", {$errors} Fehler" : '').'.');

        return self::SUCCESS;
    }
}
