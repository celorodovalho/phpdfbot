<?php

namespace App\Providers;

use App\Contracts\Repositories\OpportunityRepository;
use App\Repositories\OpportunityRepositoryEloquent;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(OpportunityRepository::class, OpportunityRepositoryEloquent::class);
    }
}
