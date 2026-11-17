<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StampGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月10日(水) 12:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 10, 12, 0, 0));
    }

    public function test_出勤済みの日に出勤を送っても勤怠は増えない(): void
    {
        $user = $this->createUser();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'clock_in']);

        $response->assertRedirect('/attendance');
        $this->assertSame(1, AttendanceRecord::where('user_id', $user->id)->count());
    }

    public function test_出勤前に休憩入を送っても何も記録されない(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'break_in']);

        $response->assertRedirect('/attendance');
        $this->assertSame(0, AttendanceRecord::count());
        $this->assertSame(0, AttendanceBreak::count());
    }

    public function test_休憩中に退勤を送っても退勤されない(): void
    {
        $user = $this->createUser();
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => null,
        ]);
        AttendanceBreak::create([
            'attendance_record_id' => $record->id,
            'break_in' => '11:30:00',
        ]);

        $response = $this->actingAs($user)->post('/attendance', ['action' => 'clock_out']);

        $response->assertRedirect('/attendance');
        $this->assertNull($record->fresh()->clock_out);
    }
}
