<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月10日(水) 09:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 10, 9, 0, 0));
    }

    public function test_退勤ボタンが正しく機能する(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $index = $this->actingAs($user)->get('/attendance');
        $index->assertSee('退勤');

        $store = $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);
        $store->assertRedirect('/attendance');

        $after = $this->actingAs($user)->get('/attendance');
        $after->assertSee('退勤済');
    }

    public function test_退勤時刻が勤怠一覧画面で確認できる(): void
    {
        $user = $this->createUser();

        // 09:00 に出勤 → 18:00 に退勤
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $this->travelTo(Carbon::create(2026, 6, 10, 18, 0, 0));
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', today()->toDateString())
            ->first();

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee(Carbon::parse($record->clock_out)->format('H:i'));
    }
}
