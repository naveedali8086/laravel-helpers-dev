<?php

namespace Naveedali8086\LaravelHelpersDev;

use Illuminate\Support\ServiceProvider;
use Naveedali8086\LaravelHelpersDev\Console\AddTraitNameCommand;

class LaravelHelpersDevServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }
        $this->commands([
            AddTraitNameCommand::class
        ]);
    }
}