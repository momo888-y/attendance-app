<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminAttendanceController extends Controller
{
    /**
     * 管理者用：指定した日（未指定なら今日）の全スタッフの勤怠一覧を表示する
     */
    public function index(Request $request): View
    {
        $date = Carbon::parse($request->input('date', today()->toDateString()));
        $previousDay = $date->copy()->subDay()->format('Y-m-d');
        $nextDay = $date->copy()->addDay()->format('Y-m-d');

        $users = User::where('admin_status', false)->get();

        $attendanceRecords = AttendanceRecord::with('breaks')
            ->where('date', $date->toDateString())
            ->get();

        return view('admin.admin-attendance-list', compact('date', 'previousDay', 'nextDay', 'users', 'attendanceRecords'));
    }

    /**
     * 管理者用：スタッフ（一般ユーザー）の一覧を表示する
     */
    public function staffList(): View
    {
        $users = User::where('admin_status', false)->get();

        return view('admin.staff-list', compact('users'));
    }

    /**
     * 管理者用：指定したスタッフの、指定した月（未指定なら今月）の勤怠一覧を表示する
     */
    public function staffAttendance(Request $request, string $id): View
    {
        $user = User::findOrFail($id);

        $date = Carbon::parse($request->input('date', now()->format('Y-m')))->startOfMonth();
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $formattedAttendanceRecords = $this->formatMonthlyRecords($user, $date);

        return view('admin.staff-attendance-list', compact('user', 'date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    /**
     * 管理者用：指定したスタッフの、指定した月の勤怠を CSV でダウンロードする
     */
    public function export(Request $request): StreamedResponse
    {
        $user = User::findOrFail($request->input('user_id'));
        $date = Carbon::parse($request->input('year_month'))->startOfMonth();

        $rows = $this->formatMonthlyRecords($user, $date);
        $fileName = $user->name . '_' . $date->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            // Excel で開いたときに文字化けしないよう、先頭に BOM を付ける
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['日付', '出勤', '退勤', '休憩', '合計']);

            $rows->each(fn($row) => fputcsv($handle, [
                $row['date'],
                $row['clock_in'],
                $row['clock_out'],
                $row['total_break_time'],
                $row['total_time'],
            ]));

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * スタッフの1か月分の勤怠を、1日1行の形にそろえる（画面と CSV で共通）
     */
    private function formatMonthlyRecords(User $user, Carbon $date): Collection
    {
        $attendanceRecords = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy('date');

        return collect(CarbonPeriod::create($date, $date->copy()->endOfMonth()))
            ->map(function ($day) use ($attendanceRecords) {
                $record = $attendanceRecords->get($day->toDateString());

                return [
                    'id' => $record ? $record->id : null,
                    'date' => $day->isoFormat('MM/DD(ddd)'),
                    'clock_in' => $record ? Carbon::parse($record->clock_in)->format('H:i') : '',
                    'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                    'total_break_time' => $record ? $record->total_break_time : null,
                    'total_time' => $record ? $record->total_time : null,
                ];
            });
    }
}
