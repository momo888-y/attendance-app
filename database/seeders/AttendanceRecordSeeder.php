<?php

namespace Database\Seeders;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AttendanceRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $period = CarbonPeriod::create(
            Carbon::today()->subMonthNoOverflow()->startOfMonth(),
            Carbon::yesterday()
        );

        $users = User::all();

        foreach ($users as $user) {
            foreach ($period as $date) {
                if ($date->isWeekend()) {
                    continue;
                }

                $attendanceRecord = AttendanceRecord::factory()->create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                ]);

                AttendanceBreak::factory()->create([
                    'attendance_record_id' => $attendanceRecord->id,
                ]);
            }
        }
    }
}
