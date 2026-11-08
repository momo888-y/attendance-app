<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockInTest extends TestCase
{
    use RefreshDatabase;

    public function test_出勤ボタンが正しく機能する(): void
    {
        $user = $this->createUser();

        $index = $this->actingAs($user)->get('/attendance');
        $index->assertSee('出勤');

        $store = $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $store->assertRedirect('/attendance');

        $after = $this->actingAs($user)->get('/attendance');
        $after->assertSee('出勤中');

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => today()->toDateString(),
        ]);
    }

    public function test_出勤は一日一回のみできる(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertDontSee('出勤');
    }

    public function test_出勤時刻が勤怠一覧画面で確認できる(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', today()->toDateString())
            ->first();

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee(Carbon::parse($record->clock_in)->format('H:i'));
    }
}
