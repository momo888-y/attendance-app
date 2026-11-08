<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月10日(水) 09:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 10, 9, 0, 0));
    }

    public function test_現在の日時情報がUIと同じ形式で出力されている(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee(now()->isoFormat('YYYY年M月D日(ddd)'));
        $response->assertSee(now()->format('H:i'));
    }

    public function test_勤務外の場合ステータスが正しく表示される(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('勤務外');
    }

    public function test_出勤中の場合ステータスが正しく表示される(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('出勤中');
    }

    public function test_休憩中の場合ステータスが正しく表示される(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);
        AttendanceBreak::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => null,
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('休憩中');
    }

    public function test_退勤済の場合ステータスが正しく表示される(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertStatus(200);
        $response->assertSee('退勤済');
    }
}
