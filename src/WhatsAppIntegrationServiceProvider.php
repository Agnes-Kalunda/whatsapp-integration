<?php

namespace Chat\WhatsappIntegration;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class WhatsAppIntegrationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
    
        $this->mergeConfigFrom(
            __DIR__ . '/../config/whatsapp.php', 'whatsapp'
        );

        
        $this->app->singleton(WhatsApp::class, function ($app) {
            return new WhatsApp($app['config']['whatsapp']);
        });
    }

    public function boot()
    {
        
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/whatsapp.php' => $this->app->configPath('whatsapp.php'),
            ], 'whatsapp-config');
        }
    }

    public function provides()
    {
        
        return [WhatsApp::class];
    }
}