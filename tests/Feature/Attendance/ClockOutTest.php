<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClockOutTest extends TestCase
{
    use RefreshDatabase;

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

        $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', today()->toDateString())
            ->first();

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee(Carbon::parse($record->clock_out)->format('H:i'));
    }
}
