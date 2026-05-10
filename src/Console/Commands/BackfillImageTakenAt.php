<?php

namespace Platform\Syltjunkie\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Platform\Core\Services\ContextFileService;
use Platform\Syltjunkie\Models\SjImage;

class BackfillImageTakenAt extends Command
{
    protected $signature = 'syltjunkie:backfill-image-taken-at {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Liest DateTimeOriginal aus EXIF der Originalbilder und schreibt taken_at in die DB';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');
        $contextFileService = app(ContextFileService::class);

        $images = SjImage::whereNull('taken_at')
            ->with('contextFile')
            ->get();

        $this->info("Bilder ohne taken_at: {$images->count()}");

        $updated = 0;
        $deleted = 0;

        foreach ($images as $image) {
            $contextFile = $image->contextFile;
            $takenAt = null;

            if ($contextFile && $contextFile->path) {
                $filePath = $disk->path($contextFile->path);

                if (file_exists($filePath)) {
                    $exif = @exif_read_data($filePath);
                    $dateStr = $exif['DateTimeOriginal'] ?? null;

                    if ($dateStr) {
                        try {
                            $takenAt = Carbon::createFromFormat('Y:m:d H:i:s', $dateStr)->toDateString();
                        } catch (\Throwable) {
                            // Ungültiges Format → bleibt null → wird gelöscht
                        }
                    }
                }
            }

            if ($takenAt) {
                if ($dryRun) {
                    $this->line("  KEEP #{$image->id}: taken_at = {$takenAt} ({$contextFile->original_name})");
                } else {
                    $image->update(['taken_at' => $takenAt]);
                    $this->line("  OK #{$image->id}: taken_at = {$takenAt}");
                }
                $updated++;
            } else {
                $name = $contextFile->original_name ?? "ID {$image->id}";
                $entityCount = $image->entities()->count();

                if ($dryRun) {
                    $this->line("  WOULD DELETE #{$image->id}: {$name} ({$entityCount} Entity-Zuordnungen)");
                } else {
                    // Entity-Zuordnungen (inkl. nearby) entfernen
                    $image->entities()->detach();
                    $image->channelPosts()->detach();
                    $image->contentPieces()->detach();

                    // Datei + Varianten löschen
                    if ($contextFile) {
                        $contextFileService->delete($contextFile->id, $image->team_id);
                    }

                    $image->forceDelete();
                    $this->line("  DELETED #{$image->id}: {$name} ({$entityCount} Zuordnungen entfernt)");
                }
                $deleted++;
            }
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Dry-Run: {$updated} behalten, {$deleted} wuerden geloescht.");
        } else {
            $this->info("{$updated} aktualisiert, {$deleted} geloescht.");
        }

        return self::SUCCESS;
    }
}
