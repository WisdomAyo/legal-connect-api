<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;


class RouteServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    public const HOME = '/home';


    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
    });
        $this->map();
    }

    /**
     * Define the routes for the application.
     */

  public function map(){
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

        Route::middleware('web')
                ->group(base_path('routes/web.php'));

    }

}


