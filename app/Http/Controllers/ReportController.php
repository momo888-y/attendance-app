<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    /** 集計する月数（今月を含む） */
    private const MONTHS = 6;

    /** 1日の所定労働時間（分）。これを超えた分が残業 */
    private const STANDARD_WORK_MINUTES = 480;

    /** 長時間労働とみなす労働時間（分）。これを超えたら長時間労働 */
    private const LONG_WORK_MINUTES = 600;

    /** 始業時刻。これより後の出勤は遅刻 */
    private const START_TIME = '09:00:00';

    /** 終業時刻。これより前の退勤は早退 */
    private const END_TIME = '18:00:00';

    /**
     * ログインユーザーの、過去6ヶ月の勤怠レポートを表示する
     */
    public function index(): View
    {
        $startMonth = now()->startOfMonth()->subMonths(self::MONTHS - 1);

        $records = Auth::user()->attendanceRecords()
            ->with('breaks')
            ->where('date', '>=', $startMonth->toDateString())
            ->whereNotNull('clock_out')
            ->get();

        $summary = $this->makeSummary($records);
        $monthlyTrend = $this->makeMonthlyTrend($records, $startMonth);
        $anomalies = $this->makeAnomalies($records);

        return view('reports.index', compact('summary', 'monthlyTrend', 'anomalies'));
    }

    /**
     * 基本サマリー（総労働時間・総残業時間・1日あたりの平均労働時間）を作る
     */
    private function makeSummary(Collection $records): array
    {
        $totalWorkMinutes = $records->sum('work_minutes');

        return [
            'total_work_minutes' => $totalWorkMinutes,
            'total_overtime_minutes' => $records->sum(fn($record) => $this->overtimeMinutes($record)),
            'avg_work_minutes' => $records->isEmpty() ? 0 : intdiv($totalWorkMinutes, $records->count()),
        ];
    }

    /**
     * 月次推移（月ごとの労働時間と残業時間）を作る。データがない月は 0
     */
    private function makeMonthlyTrend(Collection $records, Carbon $startMonth): Collection
    {
        $recordsByMonth = $records->groupBy(fn($record) => Carbon::parse($record->date)->format('Y/m'));

        return collect(range(0, self::MONTHS - 1))
            ->map(function ($offset) use ($startMonth, $recordsByMonth) {
                $month = $startMonth->copy()->addMonths($offset)->format('Y/m');
                $monthRecords = $recordsByMonth->get($month, collect());

                return [
                    'month' => $month,
                    'work_minutes' => $monthRecords->sum('work_minutes'),
                    'overtime_minutes' => $monthRecords->sum(fn($record) => $this->overtimeMinutes($record)),
                ];
            });
    }

    /**
     * 今月の異常検知（遅刻・早退・長時間労働の回数）を作る
     */
    private function makeAnomalies(Collection $records): array
    {
        $thisMonthRecords = $records->filter(fn($record) => Carbon::parse($record->date)->isSameMonth(now()));

        return [
            'late_count' => $thisMonthRecords->filter(fn($record) => $record->clock_in > self::START_TIME)->count(),
            'early_leave_count' => $thisMonthRecords->filter(fn($record) => $record->clock_out < self::END_TIME)->count(),
            'long_work_count' => $thisMonthRecords->filter(fn($record) => $record->work_minutes > self::LONG_WORK_MINUTES)->count(),
        ];
    }

    /**
     * 1日の残業時間（分）。所定の8時間を超えた分。超えていなければ 0
     */
    private function overtimeMinutes(AttendanceRecord $record): int
    {
        return max($record->work_minutes - self::STANDARD_WORK_MINUTES, 0);
    }
}
