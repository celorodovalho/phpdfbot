<?php

namespace App\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Class AppServiceProvider
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->register(RepositoryServiceProvider::class);
        $this->app->register(ResponseMacroServiceProvider::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        array_map(static function ($filename) {
            include_once($filename);
        }, glob(app_path() . '/{,*/,*/*/,*/*/*/}*.php', GLOB_BRACE));

        Schema::defaultStringLength(191);

        Validator::extend('alpha_spaces', static function ($attribute, $value) {
            // This will only accept alpha and spaces.
            // If you want to accept hyphens use: /^[\pL\s-]+$/u.
            return preg_match('/^[\pL\s]+$/u', $value, $attribute);
        });

        Validator::extend('not_contains', function ($attribute, $value, $parameters, $validator) {
            $param = reset($parameters);
            $dependentValue = Arr::get($validator->getData(), $param);

            return
                blank($dependentValue)
                && Str::contains(mb_strtolower($value), Config::get('constants.requiredWords'));
        });

        Validator::extend('contains', function ($attribute, $value) {
            return !Str::contains(mb_strtolower($value), Config::get('constants.deniedWords'));
        });
    }
}
