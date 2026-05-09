<?php

namespace Platform\Syltjunkie\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\ContextFile;
use Platform\Core\Services\ContextFileService;

class SjMediaDownloadService
{
    protected ContextFileService $contextFileService;

    public function __construct(ContextFileService $contextFileService)
    {
        $this->contextFileService = $contextFileService;
    }

    public function downloadAndStore(string $url, string $contextType, int $contextId, array $meta = [], ?\Illuminate\Console\Command $command = null): ?ContextFile
    {
        if (empty($url)) {
            return null;
        }

        try {
            $response = Http::timeout(30)->get($url);

            if (!$response->successful()) {
                Log::warning('SjMediaDownloadService: Failed to download', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $tempPath = tempnam(sys_get_temp_dir(), 'sj_media_');
            if (!$tempPath) {
                return null;
            }

            file_put_contents($tempPath, $response->body());

            $originalName = basename(parse_url($url, PHP_URL_PATH));
            if (empty($originalName) || $originalName === '/') {
                $extension = $this->getExtensionFromMimeType($response->header('Content-Type'));
                $originalName = 'media_' . time() . '.' . $extension;
            }

            $contentType = $response->header('Content-Type');
            $mimeType = $contentType ? trim(explode(';', $contentType)[0]) : 'application/octet-stream';

            $uploadedFile = new \Illuminate\Http\UploadedFile(
                $tempPath,
                $originalName,
                $mimeType,
                null,
                true
            );

            $contextModel = $contextType::find($contextId);
            if (!$contextModel) {
                @unlink($tempPath);
                return null;
            }

            $teamId = $contextModel->team_id ?? null;
            if (!$teamId) {
                @unlink($tempPath);
                return null;
            }

            $result = $this->contextFileService->uploadForContext(
                $uploadedFile,
                $contextType,
                $contextId,
                [
                    'keep_original' => $meta['keep_original'] ?? false,
                    'generate_variants' => $meta['generate_variants'] ?? false,
                    'team_id' => $teamId,
                    'folder' => 'syltjunkie/instagram',
                ]
            );

            @unlink($tempPath);

            $contextFile = ContextFile::find($result['id'] ?? null);

            if ($contextFile && !empty($meta)) {
                $existingMeta = $contextFile->meta ?? [];
                $contextFile->update([
                    'meta' => array_merge($existingMeta, [
                        'source_url' => $url,
                        'downloaded_at' => now()->toIso8601String(),
                    ], $meta),
                ]);
            }

            return $contextFile;
        } catch (\Exception $e) {
            Log::error('SjMediaDownloadService: Error downloading', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            if (isset($tempPath) && file_exists($tempPath)) {
                @unlink($tempPath);
            }

            return null;
        }
    }

    protected function getExtensionFromMimeType(?string $mimeType): string
    {
        if (!$mimeType) {
            return 'jpg';
        }

        $mimeType = trim(explode(';', $mimeType)[0]);

        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            default => 'jpg',
        };
    }
}
