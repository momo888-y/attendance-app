<?php

namespace Tests\Feature\Admin;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAttendanceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_その日になされた全ユーザーの勤怠情報が正確に確認できる(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $userA = $this->createUser(['name' => 'スタッフA']);
        $userB = $this->createUser(['name' => 'スタッフB']);

        AttendanceRecord::factory()->create([
            'user_id' => $userA->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $userB->id,
            'date' => today()->toDateString(),
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $userA->id,
            'date' => today()->subDay()->toDateString(),
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('スタッフA');
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('スタッフB');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
        $response->assertDontSee('08:00');
        $response->assertDontSee('17:00');
    }

    public function test_遷移した際に現在の日付が表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);

        $response = $this->actingAs($admin)->get('/admin/attendance/list');

        $response->assertStatus(200);
        $response->assertSee(today()->format('Y/m/d'));
    }

    public function test_前日を押下した時に前の日の勤怠情報が表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフC']);
        $yesterday = today()->subDay();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $yesterday->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $index = $this->actingAs($admin)->get('/admin/attendance/list');
        $index->assertSee('前日');

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=' . $yesterday->toDateString());

        $response->assertStatus(200);
        $response->assertSee($yesterday->format('Y/m/d'));
        $response->assertSee('スタッフC');
    }

    public function test_翌日を押下した時に次の日の勤怠情報が表示される(): void
    {
        $admin = $this->createUser(['admin_status' => true]);
        $user = $this->createUser(['name' => 'スタッフD']);
        $tomorrow = today()->addDay();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $tomorrow->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $index = $this->actingAs($admin)->get('/admin/attendance/list');
        $index->assertSee('翌日');

        $response = $this->actingAs($admin)->get('/admin/attendance/list?date=' . $tomorrow->toDateString());

        $response->assertStatus(200);
        $response->assertSee($tomorrow->format('Y/m/d'));
        $response->assertSee('スタッフD');
    }
}
