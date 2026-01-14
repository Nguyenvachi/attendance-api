<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'action' => fake()->randomElement(['POST', 'PUT', 'PATCH', 'DELETE']),
            'model' => fake()->randomElement(['User', 'Shift', 'Attendance', 'LeaveRequest']),
            'model_id' => fake()->numberBetween(1, 100),
            'old_data' => ['name' => 'Old Value', 'status' => 'old'],
            'new_data' => ['name' => 'New Value', 'status' => 'new'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
