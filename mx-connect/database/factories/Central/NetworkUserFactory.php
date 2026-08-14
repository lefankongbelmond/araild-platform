<?php

namespace Database\Factories\Central;

use App\Models\Central\NetworkUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class NetworkUserFactory extends Factory
{
    protected $model = NetworkUser::class;

    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'email'      => $this->faker->unique()->safeEmail(),
            'password'   => Hash::make('password'),
            'active'     => true,
            'mfa_enabled'=> false,
        ];
    }
}
