<?php

namespace Platform\Syltjunkie;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SyltjunkieServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/syltjunkie.php', 'syltjunkie');
    }

    public function boot(): void
    {
        $this->app['router']->aliasMiddleware(
            'sj.owner.auth',
            \Platform\Syltjunkie\Http\Middleware\SjOwnerAuthenticate::class
        );

        if (
            config()->has('syltjunkie.routing') &&
            config()->has('syltjunkie.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'syltjunkie',
                'title'      => 'Syltjunkie',
                'routing'    => config('syltjunkie.routing'),
                'guard'      => config('syltjunkie.guard'),
                'navigation' => config('syltjunkie.navigation'),
                'sidebar'    => config('syltjunkie.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('syltjunkie')) {
            ModuleRouter::group('syltjunkie', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });

            ModuleRouter::apiGroup('syltjunkie', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/syltjunkie.php' => config_path('syltjunkie.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'syltjunkie');

        $this->registerLivewireComponents();

        $this->registerTools();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Syltjunkie\Console\SnapshotCommand::class,
                \Platform\Syltjunkie\Console\Commands\PublishScheduledPosts::class,
                \Platform\Syltjunkie\Console\Commands\SyncInstagramMedia::class,
                \Platform\Syltjunkie\Console\Commands\SyncInstagramInsights::class,
                \Platform\Syltjunkie\Console\Commands\SyncFacebookPosts::class,
                \Platform\Syltjunkie\Console\Commands\FetchEntityData::class,
                \Platform\Syltjunkie\Console\Commands\DiscoverKeywords::class,
                \Platform\Syltjunkie\Console\Commands\FetchGoogleTrends::class,
                \Platform\Syltjunkie\Console\Commands\MatchNearbyImages::class,
                \Platform\Syltjunkie\Console\Commands\FetchWeather::class,
                \Platform\Syltjunkie\Console\Commands\BackfillImageTakenAt::class,
            ]);
        }

        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('syltjunkie:match-nearby-images')
                ->dailyAt('02:30')
                ->withoutOverlapping()
                ->runInBackground();

            $schedule->command('syltjunkie:sync-instagram-media')
                ->dailyAt('03:00')
                ->withoutOverlapping()
                ->runInBackground();

            $schedule->command('syltjunkie:sync-instagram-insights')
                ->dailyAt('03:30')
                ->withoutOverlapping()
                ->runInBackground();

            $schedule->command('syltjunkie:sync-facebook-posts')
                ->dailyAt('04:00')
                ->withoutOverlapping()
                ->runInBackground();

            // Google Business: nightly, rotates ~200 entities per run (3-day freshness)
            $schedule->command('syltjunkie:fetch-entity-data --type=google_business --detect-trends')
                ->dailyAt('04:30')
                ->withoutOverlapping()
                ->runInBackground();

            // Keyword Rankings: nightly, rotates ~50 domains per run (14-day freshness)
            $schedule->command('syltjunkie:fetch-entity-data --type=rankings --detect-trends')
                ->dailyAt('05:00')
                ->withoutOverlapping()
                ->runInBackground();

            // Keyword Discovery: monthly, expand seeds + detect opportunities
            $schedule->command('syltjunkie:discover-keywords --detect-opportunities')
                ->monthlyOn(1, '06:00')
                ->withoutOverlapping()
                ->runInBackground();

            // Weather: daily, fetch current + 7-day forecast from Bright Sky (DWD)
            $schedule->command('syltjunkie:fetch-weather')
                ->dailyAt('05:30')
                ->withoutOverlapping()
                ->runInBackground();

            // Google Trends: monthly (15th), 2 weeks after keyword discovery
            $schedule->command('syltjunkie:fetch-google-trends')
                ->monthlyOn(15, '06:30')
                ->withoutOverlapping()
                ->runInBackground();
        });

        // Error Reporter Registration
        try {
            resolve(\Platform\Core\Services\ErrorReporterRegistry::class)
                ->register('syltjunkie', 'Platform\\Syltjunkie');
        } catch (\Throwable $e) {}
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Entity Type Groups & Types - CRUD
            $registry->register(new \Platform\Syltjunkie\Tools\ListEntityTypeGroupsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateEntityTypeGroupTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateEntityTypeGroupTool());
            $registry->register(new \Platform\Syltjunkie\Tools\DeleteEntityTypeGroupTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListEntityTypesTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateEntityTypeTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateEntityTypeTool());
            $registry->register(new \Platform\Syltjunkie\Tools\DeleteEntityTypeTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListEntitiesTool());
            $registry->register(new \Platform\Syltjunkie\Tools\GetEntityTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListRelationTypesTool());

            // Entity Graph - Write Tools
            $registry->register(new \Platform\Syltjunkie\Tools\CreateEntityTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateEntityTool());
            $registry->register(new \Platform\Syltjunkie\Tools\DeleteEntityTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateEntityRelationshipTool());
            $registry->register(new \Platform\Syltjunkie\Tools\DeleteEntityRelationshipTool());

            // Entity URLs & Snapshots
            $registry->register(new \Platform\Syltjunkie\Tools\ListEntityUrlsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateEntityUrlTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateEntityUrlTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListUrlSnapshotsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateUrlSnapshotTool());
            $registry->register(new \Platform\Syltjunkie\Tools\DiscoverEntityUrlsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\FetchUrlSnapshotsTool());

            // Page Snapshots & Change Detection
            $registry->register(new \Platform\Syltjunkie\Tools\FetchPageSnapshotsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListPageSnapshotsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListPageChangesTool());

            // Google Business Profile
            $registry->register(new \Platform\Syltjunkie\Tools\FetchGoogleBusinessTool());

            // Trend Signals
            $registry->register(new \Platform\Syltjunkie\Tools\DetectTrendSignalsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListTrendSignalsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateTrendSignalTool());

            // Entity Discovery
            $registry->register(new \Platform\Syltjunkie\Tools\DiscoverEntitiesBySearchTool());

            // Keywords
            $registry->register(new \Platform\Syltjunkie\Tools\ListKeywordsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\GetKeywordTool());

            // Content Pieces
            $registry->register(new \Platform\Syltjunkie\Tools\ListContentPiecesTool());
            $registry->register(new \Platform\Syltjunkie\Tools\GetContentPieceTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateContentPieceTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateContentPieceTool());

            // Channel Posts
            $registry->register(new \Platform\Syltjunkie\Tools\ListChannelPostsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateChannelPostTool());
            $registry->register(new \Platform\Syltjunkie\Tools\PublishChannelPostTool());

            // Shop
            $registry->register(new \Platform\Syltjunkie\Tools\ListShopProductsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateShopProductTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateShopProductTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateShopVariantsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListShopOrdersTool());
        } catch (\Throwable $e) {}
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Syltjunkie\\Livewire';
        $prefix = 'syltjunkie';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
