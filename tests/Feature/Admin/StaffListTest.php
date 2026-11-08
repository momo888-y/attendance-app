<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffListTest extends TestCase
{
    use RefreshDatabase;

    public function test_管理者ユーザーが全一般ユーザーの氏名とメールアドレスを確認できる(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $this->createUser(['name' => 'スタッフF', 'email' => 'staff-f@example.com']);
        $this->createUser(['name' => 'スタッフG', 'email' => 'staff-g@example.com']);

        $response = $this->actingAs($admin)->get('/admin/staff/list');

        $response->assertStatus(200);
        $response->assertSee('スタッフF');
        $response->assertSee('staff-f@example.com');
        $response->assertSee('スタッフG');
        $response->assertSee('staff-g@example.com');
        $response->assertDontSee($admin->email);
    }

    public function test_ユーザーの勤怠情報が正しく表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフH']);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id);

        $response->assertStatus(200);
        $response->assertSee('スタッフH');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
    }

    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフI']);
        $previousMonth = today()->subMonth()->startOfMonth();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $previousMonth->copy()->addDays(3)->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $index = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id);
        $index->assertSee('前月');

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?date=' . $previousMonth->format('Y-m'));

        $response->assertStatus(200);
        $response->assertSee($previousMonth->format('Y/m'));
        $response->assertSee('09:00');
    }

    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフJ']);
        $nextMonth = today()->addMonth()->startOfMonth();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $nextMonth->copy()->addDays(3)->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $index = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id);
        $index->assertSee('翌月');

        $response = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id . '?date=' . $nextMonth->format('Y-m'));

        $response->assertStatus(200);
        $response->assertSee($nextMonth->format('Y/m'));
        $response->assertSee('09:00');
    }

    public function test_詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフK']);
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $list = $this->actingAs($admin)->get('/admin/attendance/staff/' . $user->id);
        $list->assertSee('/attendance/' . $record->id, false);

        $response = $this->actingAs($admin)->get('/attendance/' . $record->id);

        $response->assertStatus(200);
    }
}
