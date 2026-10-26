<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\Member;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MemberFactory extends Factory
{
    protected $model = Member::class;

    public function definition(): array
    {
        return [
            'member_code' => 'ADH-' . strtoupper(Str::random(6)),
            'first_name'  => $this->faker->firstName(),
            'last_name'   => $this->faker->lastName(),
            'birth_date'  => $this->faker->date(),
            'sex'         => $this->faker->randomElement(['M', 'F']),
            'phone'       => $this->faker->numerify('+2376########'),
            'status'      => 'active',
            'joined_at'   => now()->toDateString(),
        ];
    }
}
