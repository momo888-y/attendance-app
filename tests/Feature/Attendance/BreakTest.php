<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_休憩ボタンが正しく機能する(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $index = $this->actingAs($user)->get('/attendance');
        $index->assertSee('休憩入');

        $store = $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $store->assertRedirect('/attendance');

        $after = $this->actingAs($user)->get('/attendance');
        $after->assertSee('休憩中');
    }

    public function test_休憩は一日に何回でもできる(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertSee('休憩入');
    }

    public function test_休憩戻ボタンが正しく機能する(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $index = $this->actingAs($user)->get('/attendance');
        $index->assertSee('休憩戻');

        $store = $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);
        $store->assertRedirect('/attendance');

        $after = $this->actingAs($user)->get('/attendance');
        $after->assertSee('出勤中');
    }

    public function test_休憩戻は一日に何回でもできる(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $response = $this->actingAs($user)->get('/attendance');

        $response->assertSee('休憩戻');
    }

    public function test_休憩時刻が勤怠一覧画面で確認できる(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);
        $this->actingAs($user)->post('/attendance', ['action' => 'break_out']);

        $record = AttendanceRecord::where('user_id', $user->id)
            ->where('date', today()->toDateString())
            ->with('breaks')
            ->first();
        $break = $record->breaks->first();

        $breakMinutes = Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
        $expectedTotal = sprintf('%d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee($expectedTotal);
    }
}
