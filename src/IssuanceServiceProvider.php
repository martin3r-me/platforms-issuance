<?php

namespace Platform\Issuance;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IssuanceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Issuance\Console\Commands\MigrateFromHcm::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Morph-Map für polymorphe Empfänger
        Relation::morphMap([
            'hcm_employee' => \Platform\Hcm\Models\HcmEmployee::class,
        ]);

        // Config laden
        $this->mergeConfigFrom(__DIR__.'/../config/issuance.php', 'issuance');

        // Modul registrieren
        if (
            config()->has('issuance.routing') &&
            config()->has('issuance.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'issuance',
                'title'      => 'Ausgaben',
                'routing'    => config('issuance.routing'),
                'guard'      => config('issuance.guard'),
                'navigation' => config('issuance.navigation'),
            ]);
        }

        // Routes laden
        if (PlatformCore::getModule('issuance')) {
            ModuleRouter::group('issuance', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        // Migrationen laden
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Config veröffentlichen
        $this->publishes([
            __DIR__.'/../config/issuance.php' => config_path('issuance.php'),
        ], 'config');

        // Views & Livewire
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'issuance');
        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Issuance\\Livewire';
        $prefix = 'issuance';

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
