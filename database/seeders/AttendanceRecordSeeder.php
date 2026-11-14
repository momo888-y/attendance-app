<?php

namespace Database\Seeders;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class AttendanceRecordSeeder extends Seeder
{
    /** 決まったデータを作るユーザー */
    private const REPORT_USER_EMAIL = 'user1@example.com';

    /** 過去何ヶ月分を作るか（今月は含まない） */
    private const PAST_MONTHS = 5;

    /** 過去の各月に作る平日の日数 */
    private const PAST_DAYS_PER_MONTH = 15;

    /** 今月のパターン：[出勤, 退勤, 日数] */
    private const CURRENT_MONTH_PATTERNS = [
        ['09:00:00', '18:00:00', 10], // 通常
        ['09:00:00', '20:00:00', 3],  // 残業
        ['09:30:00', '18:00:00', 2],  // 遅刻
        ['09:00:00', '17:00:00', 1],  // 早退
        ['08:00:00', '21:00:00', 1],  // 長時間労働
    ];

    /**
     * user1 には要件シートどおりの決まったデータを、それ以外のユーザーにはランダムなデータを作る
     */
    public function run(): void
    {
        $reportUser = User::where('email', self::REPORT_USER_EMAIL)->first();

        $this->seedReportUser($reportUser);

        User::where('id', '!=', $reportUser->id)
            ->get()
            ->each(fn($user) => $this->seedRandomUser($user));
    }

    /**
     * user1 用：過去5ヶ月（各月平日15日の通常勤務）と、今月17日分のパターンを作る
     */
    private function seedReportUser(User $user): void
    {
        $thisMonth = Carbon::today()->startOfMonth();

        collect(range(1, self::PAST_MONTHS))
            ->each(function ($monthsAgo) use ($user, $thisMonth) {
                $this->weekdaysOf($thisMonth->copy()->subMonths($monthsAgo), self::PAST_DAYS_PER_MONTH)
                    ->each(fn($date) => $this->createRecord($user, $date, '09:00:00', '18:00:00'));
            });

        $patterns = collect(self::CURRENT_MONTH_PATTERNS)
            ->flatMap(fn($pattern) => array_fill(0, $pattern[2], [$pattern[0], $pattern[1]]));

        $this->weekdaysOf($thisMonth, $patterns->count())
            ->each(fn($date, $index) => $this->createRecord($user, $date, $patterns[$index][0], $patterns[$index][1]));
    }

    /**
     * user1 以外用：先月の1日から昨日までの平日に、ランダムな時刻の勤怠を作る
     */
    private function seedRandomUser(User $user): void
    {
        collect(CarbonPeriod::create(Carbon::today()->subMonthNoOverflow()->startOfMonth(), Carbon::yesterday()))
            ->reject(fn($date) => $date->isWeekend())
            ->each(function ($date) use ($user) {
                $attendanceRecord = AttendanceRecord::factory()->create([
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                ]);

                AttendanceBreak::factory()->create([
                    'attendance_record_id' => $attendanceRecord->id,
                ]);
            });
    }

    /**
     * 指定した月の平日を、月初から指定した日数だけ返す
     */
    private function weekdaysOf(Carbon $month, int $count): Collection
    {
        return collect(CarbonPeriod::create($month->copy()->startOfMonth(), $month->copy()->endOfMonth()))
            ->reject(fn($date) => $date->isWeekend())
            ->take($count)
            ->values();
    }

    /**
     * 勤怠1日分と、固定の休憩（12:00〜13:00）を作る
     */
    private function createRecord(User $user, Carbon $date, string $clockIn, string $clockOut): void
    {
        $attendanceRecord = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date->toDateString(),
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        AttendanceBreak::create([
            'attendance_record_id' => $attendanceRecord->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }
}
