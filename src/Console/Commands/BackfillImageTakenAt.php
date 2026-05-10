<?php

namespace Platform\Syltjunkie\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Platform\Syltjunkie\Models\SjImage;

class BackfillImageTakenAt extends Command
{
    protected $signature = 'syltjunkie:backfill-image-taken-at {--dry-run : Nur anzeigen, nichts schreiben}';

    protected $description = 'Liest DateTimeOriginal aus EXIF der Originalbilder und schreibt taken_at in die DB';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $disk = Storage::disk('public');

        $images = SjImage::whereNull('taken_at')
            ->with('contextFile')
            ->get();

        $this->info("Bilder ohne taken_at: {$images->count()}");

        $updated = 0;
        $skipped = 0;

        foreach ($images as $image) {
            $contextFile = $image->contextFile;

            if (!$contextFile || !$contextFile->path) {
                $skipped++;
                continue;
            }

            // Originalpath aus meta holen oder WebP-Pfad verwenden
            $filePath = $disk->path($contextFile->path);

            if (!file_exists($filePath)) {
                $this->line("  SKIP #{$image->id}: Datei nicht gefunden ({$contextFile->path})");
                $skipped++;
                continue;
            }

            $exif = @exif_read_data($filePath);
            $dateStr = $exif['DateTimeOriginal'] ?? null;

            if (!$dateStr) {
                $this->line("  SKIP #{$image->id}: Kein DateTimeOriginal ({$contextFile->original_name})");
                $skipped++;
                continue;
            }

            try {
                $takenAt = Carbon::createFromFormat('Y:m:d H:i:s', $dateStr)->toDateString();
            } catch (\Throwable) {
                $this->line("  SKIP #{$image->id}: Ungültiges Datumsformat: {$dateStr}");
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  WOULD UPDATE #{$image->id}: taken_at = {$takenAt} ({$contextFile->original_name})");
            } else {
                $image->update(['taken_at' => $takenAt]);
                $this->line("  OK #{$image->id}: taken_at = {$takenAt}");
            }

            $updated++;
        }

        $this->newLine();
        $this->info($dryRun ? "Dry-Run: {$updated} wuerden aktualisiert, {$skipped} uebersprungen." : "{$updated} aktualisiert, {$skipped} uebersprungen.");

        return self::SUCCESS;
    }
}
