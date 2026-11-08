<?php

namespace Tests\Feature\Attendance;

use App\Models\Application;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function createRecord($user)
    {
        return AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
    }

    public function test_出勤時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($user)->post('/attendance/' . $record->id, [
            'new_clock_in' => '19:00',
            'new_clock_out' => '18:00',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '遅刻のため',
        ]);

        $response->assertSessionHasErrors(['new_clock_in' => '出勤時間もしくは退勤時間が不適切な値です']);
    }

    public function test_休憩開始時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($user)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['19:00'],
            'new_break_out' => ['19:30'],
            'comment' => '休憩が遅い',
        ]);

        $response->assertSessionHasErrors(['new_break_in.0' => '休憩時間が不適切な値です']);
    }

    public function test_休憩終了時間が退勤時間より後になっている場合エラーメッセージが表示される(): void
    {
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($user)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => ['12:00'],
            'new_break_out' => ['19:00'],
            'comment' => '休憩が長い',
        ]);

        $response->assertSessionHasErrors(['new_break_out.0' => '休憩時間もしくは退勤時間が不適切な値です']);
    }

    public function test_備考欄が未入力の場合エラーメッセージが表示される(): void
    {
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $response = $this->actingAs($user)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:00',
            'new_clock_out' => '18:00',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '',
        ]);

        $response->assertSessionHasErrors(['comment' => '備考を記入してください']);
    }

    public function test_修正申請処理が実行される(): void
    {
        $user = $this->createUser();
        $admin = $this->createUser(['admin_status' => true]);
        $record = $this->createRecord($user);

        $response = $this->actingAs($user)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '電車遅延のため',
        ]);

        $response->assertRedirect('/attendance/' . $record->id);

        $application = Application::where('attendance_record_id', $record->id)->first();
        $this->assertNotNull($application);
        $this->assertSame('承認待ち', $application->approval_status);

        $approveShow = $this->actingAs($admin)->get('/stamp_correction_request/approve/' . $application->id);
        $approveShow->assertStatus(200);
        $approveShow->assertSee('電車遅延のため');

        $adminList = $this->actingAs($admin)->get('/stamp_correction_request/list');
        $adminList->assertStatus(200);
        $adminList->assertSee('電車遅延のため');
    }

    public function test_承認待ちにログインユーザーが行った申請が全て表示されていること(): void
    {
        $user = $this->createUser();
        $record1 = $this->createRecord($user);
        $record2 = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->subDay()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $this->actingAs($user)->post('/attendance/' . $record1->id, [
            'new_clock_in' => '09:10',
            'new_clock_out' => '18:10',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '申請その1',
        ]);
        $this->actingAs($user)->post('/attendance/' . $record2->id, [
            'new_clock_in' => '09:20',
            'new_clock_out' => '18:20',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '申請その2',
        ]);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('申請その1');
        $response->assertSee('申請その2');
    }

    public function test_承認済みに管理者が承認した修正申請が全て表示されている(): void
    {
        $user = $this->createUser();
        $admin = $this->createUser(['admin_status' => true]);
        $record = $this->createRecord($user);

        $this->actingAs($user)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '承認対象の申請',
        ]);

        $application = Application::where('attendance_record_id', $record->id)->first();

        $this->actingAs($admin)->post('/stamp_correction_request/approve/' . $application->id);

        $response = $this->actingAs($user)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認済み');
        $response->assertSee('承認対象の申請');
    }

    public function test_各申請の詳細を押下すると勤怠詳細画面に遷移する(): void
    {
        $user = $this->createUser();
        $record = $this->createRecord($user);

        $this->actingAs($user)->post('/attendance/' . $record->id, [
            'new_clock_in' => '09:30',
            'new_clock_out' => '18:30',
            'new_break_in' => [''],
            'new_break_out' => [''],
            'comment' => '詳細遷移確認',
        ]);

        $application = Application::where('attendance_record_id', $record->id)->first();

        $response = $this->actingAs($user)->get('/application/' . $application->id);

        $response->assertRedirect('/attendance/' . $record->id);
    }
}
