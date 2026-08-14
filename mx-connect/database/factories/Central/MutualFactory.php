<?php

namespace Database\Factories\Central;

use App\Models\Central\Country;
use App\Models\Central\Mutual;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MutualFactory extends Factory
{
    protected $model = Mutual::class;

    public function definition(): array
    {
        $country = Country::query()->inRandomOrder()->first() ?? Country::factory()->create();
        $slug = Str::slug($this->faker->unique()->company());

        return [
            'id'          => 'mut_' . Str::random(6),
            'name'        => $this->faker->company() . ' Mutuelle',
            'slug'        => $slug,
            'country_id'  => $country->id,
            'currency_id' => $country->default_currency_id,
            'locale_id'   => $country->default_locale_id,
            'status'      => 'pending',
        ];
    }
}
