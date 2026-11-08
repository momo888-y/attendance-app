<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月10日(水) 09:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 10, 9, 0, 0));
    }

    private function createCorrectionApplication($user, array $overrides = [])
    {
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        return Application::create(array_merge([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'new_date' => $record->date,
            'new_clock_in' => '09:30:00',
            'new_clock_out' => '18:30:00',
            'comment' => 'テスト申請',
            'application_date' => today()->toDateString(),
        ], $overrides));
    }

    public function test_承認待ちの修正申請が全て表示されている(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $userA = $this->createUser(['name' => 'スタッフL']);
        $userB = $this->createUser(['name' => 'スタッフM']);

        $this->createCorrectionApplication($userA, ['comment' => '申請Aです']);
        $this->createCorrectionApplication($userB, ['comment' => '申請Bです']);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('申請Aです');
        $response->assertSee('申請Bです');
    }

    public function test_承認済みの修正申請が全て表示されている(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $userA = $this->createUser(['name' => 'スタッフN']);
        $userB = $this->createUser(['name' => 'スタッフO']);

        $this->createCorrectionApplication($userA, ['comment' => '承認済みA', 'approval_status' => '承認済み']);
        $this->createCorrectionApplication($userB, ['comment' => '承認済みB', 'approval_status' => '承認済み']);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/list');

        $response->assertStatus(200);
        $response->assertSee('承認済みA');
        $response->assertSee('承認済みB');
    }

    public function test_修正申請の詳細内容が正しく表示されている(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフP']);
        $application = $this->createCorrectionApplication($user, ['comment' => '詳細確認用の申請']);
        ProposalBreak::create([
            'application_id' => $application->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/stamp_correction_request/approve/' . $application->id);

        $response->assertStatus(200);
        $response->assertSee('スタッフP');
        $response->assertSee('09:30');
        $response->assertSee('18:30');
        $response->assertSee('12:00');
        $response->assertSee('13:00');
        $response->assertSee('詳細確認用の申請');
    }

    public function test_修正申請の承認処理が正しく行われる(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフQ']);
        $application = $this->createCorrectionApplication($user, ['comment' => '承認処理確認']);

        $response = $this->actingAs($admin)->post('/stamp_correction_request/approve/' . $application->id);

        $response->assertRedirect('/stamp_correction_request/approve/' . $application->id);

        $application->refresh();
        $this->assertSame('承認済み', $application->approval_status);

        $record = AttendanceRecord::find($application->attendance_record_id);
        $this->assertSame('09:30:00', $record->clock_in);
        $this->assertSame('18:30:00', $record->clock_out);
        $this->assertSame('承認処理確認', $record->comment);
    }
}
