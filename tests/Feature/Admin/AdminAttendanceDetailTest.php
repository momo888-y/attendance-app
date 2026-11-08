<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月10日(水) 09:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 10, 9, 0, 0));
    }

    private function createRecord($user)
    {
        return AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_勤怠詳細画面に表示されるデータが選択したものになっている(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフE']);
        $record = $this->createRecord($user);
        AttendanceBreak::factory()->create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/attendance/' . $record->id);

        $response->assertStatus(200);
        $response->assertSee('スタッフE');
        $response->assertSee(today()->format('n月j日'));
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
    }

    public function test_出勤時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($admin)->post('/attendance/' . $record->id, [
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '管理者修正',
        ]);

        $response->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_休憩開始時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($admin)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['19:30'],
            'comment' => '管理者修正',
        ]);

        $response->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_休憩終了時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($admin)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['19:00'],
            'comment' => '管理者修正',
        ]);

        $response->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_備考欄が未入力の場合エラーメッセージが表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($admin)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '',
        ]);

        $response->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }
}
