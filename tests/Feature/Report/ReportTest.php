<?php

namespace Tests\Feature\Report;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月20日 12:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 20, 12, 0, 0));
    }

    /**
     * 勤怠1日分と、休憩 12:00〜13:00 を作る
     */
    private function createRecord(User $user, string $date, string $clockIn, string $clockOut): void
    {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
        ]);

        AttendanceBreak::create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);
    }

    public function test_ゲストはレポートページにアクセスできない(): void
    {
        $response = $this->get('/attendance/report');

        $response->assertRedirect('/login');
    }

    public function test_認証ユーザーの統計情報が正しく計算される(): void
    {
        $user = $this->createUser();

        // 6ヶ月より前（集計に入らない）
        $this->createRecord($user, '2025-12-10', '09:00:00', '18:00:00');
        // 先月：通常 480分
        $this->createRecord($user, '2026-05-15', '09:00:00', '18:00:00');
        // 今月：残業 600分（残業120分）
        $this->createRecord($user, '2026-06-01', '09:00:00', '20:00:00');
        // 今月：遅刻 450分
        $this->createRecord($user, '2026-06-02', '09:30:00', '18:00:00');
        // 今月：早退 420分
        $this->createRecord($user, '2026-06-03', '09:00:00', '17:00:00');
        // 今月：長時間労働 720分（残業240分）
        $this->createRecord($user, '2026-06-04', '08:00:00', '21:00:00');

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);

        $response->assertViewHas('summary', [
            'total_work_minutes' => 2670,
            'total_overtime_minutes' => 360,
            'avg_work_minutes' => 534,
        ]);

        $response->assertViewHas('monthlyTrend', fn($monthlyTrend) => $monthlyTrend->toArray() == [
            ['month' => '2026/01', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/02', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/03', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/04', 'work_minutes' => 0, 'overtime_minutes' => 0],
            ['month' => '2026/05', 'work_minutes' => 480, 'overtime_minutes' => 0],
            ['month' => '2026/06', 'work_minutes' => 2190, 'overtime_minutes' => 360],
        ]);

        $response->assertViewHas('anomalies', [
            'late_count' => 1,
            'early_leave_count' => 1,
            'long_work_count' => 1,
        ]);
    }

    public function test_勤怠記録がないユーザーで安全に処理される(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/attendance/report');

        $response->assertStatus(200);

        $response->assertViewHas('summary', [
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'avg_work_minutes' => 0,
        ]);

        $response->assertViewHas('monthlyTrend', fn($monthlyTrend) => $monthlyTrend->count() === 6
            && $monthlyTrend->sum('work_minutes') === 0
            && $monthlyTrend->sum('overtime_minutes') === 0);

        $response->assertViewHas('anomalies', [
            'late_count' => 0,
            'early_leave_count' => 0,
            'long_work_count' => 0,
        ]);
    }
}
