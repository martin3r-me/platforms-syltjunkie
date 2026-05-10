<?php

namespace Platform\Syltjunkie\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Platform\Core\Services\ContextFileService;
use Platform\Syltjunkie\Models\SjImage;

class ImageIndex extends Component
{
    use WithPagination, WithFileUploads;

    public string $search = '';
    public string $filterTag = '';
    public string $filterUsage = '';
    public string $viewMode = 'grid';
    public $pendingUploads = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterTag(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUsage(): void
    {
        $this->resetPage();
    }

    public array $uploadErrors = [];

    public function uploadImages(): void
    {
        $this->validate([
            'pendingUploads' => 'required|array|min:1',
            'pendingUploads.*' => 'image|max:20480',
        ]);

        $team = Auth::user()->currentTeam;
        $service = app(ContextFileService::class);
        $this->uploadErrors = [];
        $uploaded = 0;

        foreach ($this->pendingUploads as $file) {
            $fileName = $file->getClientOriginalName();

            // EXIF pruefen: GPS + Datum muessen vorhanden sein
            $exif = @exif_read_data($file->getRealPath());
            $hasGps = isset($exif['GPSLatitude'], $exif['GPSLongitude']);
            $takenAt = $this->extractTakenAt($exif);

            if (!$hasGps || !$takenAt) {
                $missing = [];
                if (!$hasGps) $missing[] = 'GPS-Koordinaten';
                if (!$takenAt) $missing[] = 'Aufnahmedatum';
                $this->uploadErrors[] = "{$fileName}: " . implode(' und ', $missing) . ' fehlt.';
                continue;
            }

            $result = $service->uploadForContext(
                $file,
                SjImage::class,
                0,
                [
                    'team_id' => $team->id,
                    'user_id' => Auth::id(),
                    'folder' => 'syltjunkie',
                    'generate_variants' => true,
                ]
            );

            $contextFile = \Platform\Core\Models\ContextFile::find($result['id']);
            $meta = $contextFile->meta ?? [];

            SjImage::create([
                'team_id' => $team->id,
                'context_file_id' => $result['id'],
                'latitude' => $meta['gps']['latitude'] ?? null,
                'longitude' => $meta['gps']['longitude'] ?? null,
                'taken_at' => $takenAt,
                'title' => pathinfo($fileName, PATHINFO_FILENAME),
            ]);

            $uploaded++;
        }

        $this->pendingUploads = [];

        if ($uploaded > 0 && empty($this->uploadErrors)) {
            session()->flash('success', "{$uploaded} Bild(er) hochgeladen.");
        } elseif ($uploaded > 0) {
            session()->flash('success', "{$uploaded} Bild(er) hochgeladen, einige abgelehnt.");
        }
    }

    protected function extractTakenAt(?array $exif): ?string
    {
        if (!$exif) {
            return null;
        }

        // EXIF DateTimeOriginal ist das primaere Feld
        $dateStr = $exif['DateTimeOriginal'] ?? $exif['DateTimeDigitized'] ?? $exif['DateTime'] ?? null;

        if (!$dateStr) {
            return null;
        }

        // Format: "2024:03:15 14:30:00" → "2024-03-15"
        try {
            return \Carbon\Carbon::createFromFormat('Y:m:d H:i:s', $dateStr)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function deleteImage(int $id): void
    {
        $team = Auth::user()->currentTeam;
        $image = SjImage::where('team_id', $team->id)->findOrFail($id);

        $service = app(ContextFileService::class);
        $service->delete($image->context_file_id, $team->id);

        $image->forceDelete();
    }

    public function updateTitle(int $id, string $title): void
    {
        $team = Auth::user()->currentTeam;
        $image = SjImage::where('team_id', $team->id)->findOrFail($id);
        $image->update(['title' => $title]);
    }

    public function addTag(int $id, string $tag): void
    {
        $team = Auth::user()->currentTeam;
        $image = SjImage::where('team_id', $team->id)->findOrFail($id);
        $tag = trim($tag);
        if (!$tag) return;

        $tags = $image->tags ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $image->update(['tags' => $tags]);
        }
    }

    public function removeTag(int $id, string $tag): void
    {
        $team = Auth::user()->currentTeam;
        $image = SjImage::where('team_id', $team->id)->findOrFail($id);

        $tags = array_values(array_filter($image->tags ?? [], fn($t) => $t !== $tag));
        $image->update(['tags' => $tags ?: null]);
    }

    public function render()
    {
        $team = Auth::user()->currentTeam;

        $query = SjImage::where('team_id', $team->id)
            ->with(['contextFile.variants', 'entities'])
            ->withCount(['channelPosts', 'contentPieces']);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                  ->orWhere('photographer', 'like', "%{$this->search}%")
                  ->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterTag) {
            $query->whereJsonContains('tags', $this->filterTag);
        }

        if ($this->filterUsage === 'posted') {
            $query->has('channelPosts');
        } elseif ($this->filterUsage === 'content') {
            $query->has('contentPieces');
        } elseif ($this->filterUsage === 'unused') {
            $query->doesntHave('channelPosts')
                  ->doesntHave('contentPieces')
                  ->doesntHave('entities');
        } elseif ($this->filterUsage === 'used') {
            $query->where(function ($q) {
                $q->has('channelPosts')
                  ->orHas('contentPieces')
                  ->orHas('entities');
            });
        }

        $images = $query->orderByDesc('created_at')->paginate(24);

        // Alle verwendeten Tags für Filter-Dropdown sammeln
        $allTags = SjImage::where('team_id', $team->id)
            ->whereNotNull('tags')
            ->pluck('tags')
            ->flatten()
            ->unique()
            ->sort()
            ->values();

        $totalCount = SjImage::where('team_id', $team->id)->count();

        // Geo-tagged images for map (all, not paginated)
        $mapImages = [];
        if ($this->viewMode === 'map') {
            $mapQuery = SjImage::where('team_id', $team->id)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->with('contextFile.variants');

            if ($this->search) {
                $mapQuery->where(function ($q) {
                    $q->where('title', 'like', "%{$this->search}%")
                      ->orWhere('photographer', 'like', "%{$this->search}%")
                      ->orWhere('description', 'like', "%{$this->search}%");
                });
            }

            if ($this->filterTag) {
                $mapQuery->whereJsonContains('tags', $this->filterTag);
            }

            $mapImages = $mapQuery->get()->map(fn($img) => [
                'id' => $img->id,
                'lat' => (float) $img->latitude,
                'lng' => (float) $img->longitude,
                'title' => $img->title ?? 'Ohne Titel',
                'thumbnail' => $img->thumbnail_url,
                'tags' => $img->tags ?? [],
            ])->values()->toArray();
        }

        $geoCount = SjImage::where('team_id', $team->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->count();

        $postedCount = SjImage::where('team_id', $team->id)->has('channelPosts')->count();
        $inContentCount = SjImage::where('team_id', $team->id)->has('contentPieces')->count();

        return view('syltjunkie::livewire.image-index', [
            'images' => $images,
            'allTags' => $allTags,
            'totalCount' => $totalCount,
            'mapImages' => $mapImages,
            'geoCount' => $geoCount,
            'postedCount' => $postedCount,
            'inContentCount' => $inContentCount,
        ])->layout('platform::layouts.app');
    }
}
