<?php

namespace App\Providers;

use App\Contracts\Repositories\GroupRepository;
use App\Contracts\Repositories\OpportunityRepository;
use App\Contracts\Repositories\UserRepository;
use App\Repositories\GroupRepositoryEloquent;
use App\Repositories\OpportunityRepositoryEloquent;
use App\Repositories\UserRepositoryEloquent;
use Illuminate\Support\ServiceProvider;

/**
 * Class RepositoryServiceProvider
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(OpportunityRepository::class, OpportunityRepositoryEloquent::class);
        $this->app->bind(GroupRepository::class, GroupRepositoryEloquent::class);
        $this->app->bind(UserRepository::class, UserRepositoryEloquent::class);
    }
}
