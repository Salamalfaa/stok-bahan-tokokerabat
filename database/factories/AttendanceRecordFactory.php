<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
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
            'user_id' => User::factory()->state(['role' => User::ROLE_OPERATOR]),
            'actor_id' => null,
            'attendance_date' => today()->toDateString(),
            'status' => AttendanceRecord::STATUS_PRESENT,
            'late_hours' => null,
            'note' => null,
        ];
    }
}
