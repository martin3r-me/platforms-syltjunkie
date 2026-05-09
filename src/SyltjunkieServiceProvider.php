<?php

namespace Platform\Syltjunkie;

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
            ]);
        }

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

            // Entity Graph - Read Tools
            $registry->register(new \Platform\Syltjunkie\Tools\ListEntityTypeGroupsTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListEntityTypesTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListEntitiesTool());
            $registry->register(new \Platform\Syltjunkie\Tools\GetEntityTool());
            $registry->register(new \Platform\Syltjunkie\Tools\ListRelationTypesTool());

            // Entity Graph - Write Tools
            $registry->register(new \Platform\Syltjunkie\Tools\CreateEntityTool());
            $registry->register(new \Platform\Syltjunkie\Tools\UpdateEntityTool());
            $registry->register(new \Platform\Syltjunkie\Tools\CreateEntityRelationshipTool());

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
