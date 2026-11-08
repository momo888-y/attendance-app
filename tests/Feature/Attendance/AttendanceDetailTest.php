<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月10日(水) 09:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 10, 9, 0, 0));
    }

    private function createRecordWithBreak()
    {
        $user = $this->createUser(['name' => '鈴木一郎']);
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        AttendanceBreak::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        return [$user, $record];
    }

    public function test_名前がログインユーザーの氏名になっている(): void
    {
        [$user, $record] = $this->createRecordWithBreak();

        $response = $this->actingAs($user)->get('/attendance/' . $record->id);

        $response->assertStatus(200);
        $response->assertSee('鈴木一郎');
    }

    public function test_日付が選択した日付になっている(): void
    {
        [$user, $record] = $this->createRecordWithBreak();

        $response = $this->actingAs($user)->get('/attendance/' . $record->id);

        $response->assertStatus(200);
        $response->assertSee(today()->format('Y年'));
        $response->assertSee(today()->format('n月j日'));
    }

    public function test_出勤退勤にて記されている時間が打刻と一致している(): void
    {
        [$user, $record] = $this->createRecordWithBreak();

        $response = $this->actingAs($user)->get('/attendance/' . $record->id);

        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_休憩にて記されている時間が打刻と一致している(): void
    {
        [$user, $record] = $this->createRecordWithBreak();

        $response = $this->actingAs($user)->get('/attendance/' . $record->id);

        $response->assertStatus(200);
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }
}
