<?php

namespace MartijnWagena\Gmail\Providers;

use Illuminate\Support\ServiceProvider;
use MartijnWagena\Gmail\Gmail;

class GmailServiceProvider extends ServiceProvider {

    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/gmail.php' => config_path('gmail.php'),
        ], 'config');
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/gmail.php', 'gmail'
        );

        $config = $this->app['config']->get('gmail');
        $this->app->instance('gmail', new Gmail($config));
    }

    public function provides()
    {
        return ['gmail'];
    }


}