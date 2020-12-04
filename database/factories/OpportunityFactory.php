<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Opportunity;
use Illuminate\Support\Str;
use Faker\Generator as Faker;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| This directory should contain each of the model factory definitions for
| your application. Factories provide a convenient way to generate new
| model instances for testing / seeding your application's database.
|
*/

$factory->define(Opportunity::class, function (Faker $faker) {
    return [
        Opportunity::TITLE => $faker->name,
        Opportunity::POSITION => $faker->name,
        Opportunity::DESCRIPTION => $faker->paragraphs(3),
        Opportunity::ORIGINAL => $faker->randomHtml(3),
        Opportunity::SALARY => $faker->numberBetween(1000, 12000),
        Opportunity::COMPANY => $faker->name,
        Opportunity::LOCATION => $faker->state,
        Opportunity::FILES => [$faker->imageUrl()],
        Opportunity::TELEGRAM_ID => $faker->randomNumber(),
        Opportunity::STATUS => $faker->boolean,
        Opportunity::TELEGRAM_USER_ID => $faker->randomNumber(),
        Opportunity::URLS => [$faker->url],
        Opportunity::ORIGIN => $faker->name,
        Opportunity::TAGS => $faker->shuffleArray([]),
        Opportunity::EMAILS => [$faker->email],
        Opportunity::APPROVER => '{"id":788018187,"is_bot":false,"first_name":"asdfasdf","last_name":"asdfasdf","username":"asdfasdf","language_code":"pt-br"}',
    ];
});
