<?php

namespace Tests\Feature\Api;

use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 勤怠1日分と休憩（12:00〜13:00）を作る
     */
    private function createRecord(User $user, string $date = '2026-05-01'): AttendanceRecord
    {
        $record = AttendanceRecord::create([
            'user_id' => $user->id,
            'date' => $date,
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        AttendanceBreak::create([
            'attendance_record_id' => $record->id,
            'break_in' => '12:00:00',
            'break_out' => '13:00:00',
        ]);

        return $record;
    }

    // ===== ID 17：読み取り系 =====

    public function test_勤怠一覧が_jsonで取得できる(): void
    {
        $user = $this->createUser();
        $this->createRecord($user, '2026-05-01');
        $this->createRecord($user, '2026-05-02');

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
        $response->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total'],
        ]);
    }

    public function test_勤怠詳細が_jsonで取得できる(): void
    {
        $user = $this->createUser(['name' => 'APIテスト太郎']);
        $record = $this->createRecord($user);

        $response = $this->getJson('/api/v1/attendance-records/'.$record->id);

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $record->id);
        $response->assertJsonPath('data.user.name', 'APIテスト太郎');
        $response->assertJsonPath('data.breaks.0.break_in', '12:00:00');
        $response->assertJsonPath('data.applications', []);
    }

    public function test_存在しない_idでは404とエラー_jsonが返る(): void
    {
        $response = $this->getJson('/api/v1/attendance-records/99999');

        $response->assertStatus(404);
        $response->assertExactJson(['error' => '勤怠情報が見つかりませんでした。']);
    }

    // ===== ID 18：書き込み系 =====

    public function test_postで勤怠が作成される(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/attendance-records', [
            'date' => '2026-05-10',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $user->id,
            'date' => '2026-05-10',
            'clock_in' => '09:00:00',
        ]);
    }

    public function test_バリデーションエラー時に422と日本語エラーメッセージが返る(): void
    {
        Sanctum::actingAs($this->createUser(), ['*']);

        $response = $this->postJson('/api/v1/attendance-records', [
            'clock_in' => '9時',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.date.0', '勤怠日は必須です。');
        $response->assertJsonPath('errors.clock_in.0', '出勤時刻は HH:MM:SS 形式で指定してください。');
    }

    public function test_putで勤怠が更新され、存在しない_idでは404が返る(): void
    {
        $user = $this->createUser();
        $record = $this->createRecord($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->putJson('/api/v1/attendance-records/'.$record->id, [
            'clock_out' => '19:00:00',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('attendance_records', [
            'id' => $record->id,
            'clock_in' => '09:00:00',
            'clock_out' => '19:00:00',
        ]);

        $this->putJson('/api/v1/attendance-records/99999', ['clock_out' => '19:00:00'])
            ->assertStatus(404);
    }

    public function test_deleteで勤怠が削除され、存在しない_idでは404が返る(): void
    {
        $user = $this->createUser();
        $record = $this->createRecord($user);
        Sanctum::actingAs($user, ['*']);

        $response = $this->deleteJson('/api/v1/attendance-records/'.$record->id);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('attendance_records', ['id' => $record->id]);

        $this->deleteJson('/api/v1/attendance-records/99999')
            ->assertStatus(404);
    }

    // ===== ID 19：Sanctum 認証と認可 =====

    public function test_未認証時に書き込み系_apiで401が返る(): void
    {
        $record = $this->createRecord($this->createUser());

        $this->postJson('/api/v1/attendance-records', [])
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->putJson('/api/v1/attendance-records/'.$record->id, [])
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);

        $this->deleteJson('/api/v1/attendance-records/'.$record->id)
            ->assertStatus(401)
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_他ユーザーの勤怠を更新・削除しようとすると403が返る(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $record = $this->createRecord($owner);
        Sanctum::actingAs($otherUser, ['*']);

        $this->putJson('/api/v1/attendance-records/'.$record->id, ['clock_out' => '19:00:00'])
            ->assertStatus(403)
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);

        $this->deleteJson('/api/v1/attendance-records/'.$record->id)
            ->assertStatus(403)
            ->assertExactJson(['error' => 'この操作を実行する権限がありません。']);
    }

    public function test_管理者は他ユーザーの勤怠を更新できる(): void
    {
        $owner = $this->createUser();
        $admin = $this->createUser(['admin_status' => true]);
        $record = $this->createRecord($owner);
        Sanctum::actingAs($admin, ['*']);

        $this->putJson('/api/v1/attendance-records/'.$record->id, ['clock_out' => '19:00:00'])
            ->assertStatus(200);
    }
}
