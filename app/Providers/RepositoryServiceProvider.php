<?php

namespace App\Providers;

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
        $this->app->bind(\App\Contracts\Repositories\InfoRepository::class, \App\Repositories\InfoRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\EducationRepository::class, \App\Repositories\EducationRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\ExperienceRepository::class, \App\Repositories\ExperienceRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\SkillRepository::class, \App\Repositories\SkillRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\ProjectRepository::class, \App\Repositories\ProjectRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\TagRepository::class, \App\Repositories\TagRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\ContactRepository::class, \App\Repositories\ContactRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\UserRepository::class, \App\Repositories\UserRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\TimeReportRepository::class, \App\Repositories\TimeReportRepositoryEloquent::class);
        $this->app->bind(\App\Contracts\Repositories\OpportunityRepository::class, \App\Repositories\OpportunityRepositoryEloquent::class);
        //:end-bindings:
    }
}
