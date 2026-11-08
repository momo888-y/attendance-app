<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト中の「現在時刻」を 2026年6月10日(水) 09:00 に固定する
        $this->travelTo(Carbon::create(2026, 6, 10, 9, 0, 0));
    }

    public function test_自分が行った勤怠情報が全て表示されている(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();

        $thisMonth = today()->startOfMonth();

        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $thisMonth->copy()->addDays(2)->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $thisMonth->copy()->addDays(5)->toDateString(),
            'clock_in' => '10:00:00',
            'clock_out' => '19:00:00',
        ]);
        AttendanceRecord::factory()->create([
            'user_id' => $other->id,
            'date' => $thisMonth->copy()->addDays(2)->toDateString(),
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
        ]);

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee('09:00');
        $response->assertSee('18:00');
        $response->assertSee('10:00');
        $response->assertSee('19:00');
        $response->assertDontSee('08:00');
        $response->assertDontSee('17:00');
    }

    public function test_勤怠一覧画面に遷移した際に現在の月が表示される(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->get('/attendance/list');

        $response->assertStatus(200);
        $response->assertSee(today()->format('Y/m'));
    }

    public function test_前月を押下した時に表示月の前月の情報が表示される(): void
    {
        $user = $this->createUser();
        $previousMonth = today()->subMonth()->startOfMonth();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $previousMonth->copy()->addDays(3)->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $index = $this->actingAs($user)->get('/attendance/list');
        $index->assertSee('前月');

        $response = $this->actingAs($user)->get('/attendance/list?date=' . $previousMonth->format('Y-m'));

        $response->assertStatus(200);
        $response->assertSee($previousMonth->format('Y/m'));
        $response->assertSee('09:00');
    }

    public function test_翌月を押下した時に表示月の翌月の情報が表示される(): void
    {
        $user = $this->createUser();
        $nextMonth = today()->addMonth()->startOfMonth();
        AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => $nextMonth->copy()->addDays(3)->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $index = $this->actingAs($user)->get('/attendance/list');
        $index->assertSee('翌月');

        $response = $this->actingAs($user)->get('/attendance/list?date=' . $nextMonth->format('Y-m'));

        $response->assertStatus(200);
        $response->assertSee($nextMonth->format('Y/m'));
        $response->assertSee('09:00');
    }

    public function test_詳細を押下するとその日の勤怠詳細画面に遷移する(): void
    {
        $user = $this->createUser();
        $record = AttendanceRecord::factory()->create([
            'user_id' => $user->id,
            'date' => today()->toDateString(),
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
        ]);

        $list = $this->actingAs($user)->get('/attendance/list');
        $list->assertSee('/attendance/' . $record->id, false);

        $response = $this->actingAs($user)->get('/attendance/' . $record->id);

        $response->assertStatus(200);
    }
}
