<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => fake()->date(),
            'clock_in' => Carbon::createFromTime(8, 45)->addMinutes(fake()->numberBetween(0, 30))->format('H:i:s'),
            'clock_out' => Carbon::createFromTime(17, 45)->addMinutes(fake()->numberBetween(0, 75))->format('H:i:s'),
            'comment' => null,
        ];
    }
}
