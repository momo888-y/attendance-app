<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;

class AttendanceController extends Controller
{
    /**
     * 打刻画面を表示する（今日の日付と現在時刻を渡す）
     */
    public function index(): View
    {
        $user = Auth::user();
        $formattedDate = now()->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    /**
     * 打刻する（出勤・休憩入・休憩戻・退勤のどれかを action で受け取る）
     */
    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        $action = $request->input('action');

        if ($action === 'clock_in') {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => today()->toDateString(),
                'clock_in' => now()->format('H:i:s'),
            ]);

            return redirect('/attendance');
        }

        $record = $user->attendanceRecords()
            ->where('date', today()->toDateString())
            ->first();

        if ($action === 'break_in') {
            AttendanceBreak::create([
                'attendance_record_id' => $record->id,
                'break_in' => now()->format('H:i:s'),
            ]);

            return redirect('/attendance');
        }

        if ($action === 'break_out') {
            $record->breaks()
                ->whereNull('break_out')
                ->update(['break_out' => now()->format('H:i:s')]);

            return redirect('/attendance');
        }

        if ($action === 'clock_out') {
            $record->update(['clock_out' => now()->format('H:i:s')]);
        }

        return redirect('/attendance');
    }

    /**
     * 一般ユーザー用：指定した月（未指定なら今月）の自分の勤怠一覧を表示する
     */
    public function list(Request $request): View
    {
        $user = Auth::user();

        $date = Carbon::parse($request->input('date', now()->format('Y-m')))->startOfMonth();
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy('date');

        $formattedAttendanceRecords = collect(CarbonPeriod::create($date, $date->copy()->endOfMonth()))
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

        return view('user.user-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }

    /**
     * 勤怠詳細画面を表示する
     * 管理者なら誰の勤怠でも、一般ユーザーなら自分の勤怠だけを表示する
     */
    public function show(string $id): View
    {
        $user = Auth::user();

        if ($user->admin_status) {
            return $this->showForAdmin($id);
        }

        return $this->showForUser($user, $id);
    }

    /**
     * 管理者用：勤怠記録の内容をそのまま詳細画面に表示する
     */
    private function showForAdmin(string $id): View
    {
        $record = AttendanceRecord::with(['breaks', 'user'])->findOrFail($id);

        $date = Carbon::parse($record->date);

        $attendanceRecord = [
            'id' => $record->id,
            'year' => $date->format('Y年'),
            'date' => $date->format('n月j日'),
            'clock_in' => Carbon::parse($record->clock_in)->format('H:i'),
            'clock_out' => $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
            'breaks' => $this->formatBreaks($record->breaks),
            'comment' => $record->comment,
        ];

        $user = $record->user;

        return view('admin.admin-detail', compact('user', 'attendanceRecord'));
    }

    /**
     * 一般ユーザー用：自分の勤怠詳細を表示する
     * 承認待ちの申請があれば、勤怠記録ではなく申請中の内容を表示する
     */
    private function showForUser(User $user, string $id): View
    {
        $record = $user->attendanceRecords()
            ->with('breaks')
            ->findOrFail($id);

        $application = $record->applications()
            ->where('approval_status', Application::STATUS_PENDING)
            ->first();

        $source = $application ?? $record;
        $clockIn = $application ? $application->new_clock_in : $record->clock_in;
        $clockOut = $application ? $application->new_clock_out : $record->clock_out;
        $breakItems = $application ? $application->proposalBreaks : $record->breaks;

        $date = Carbon::parse($record->date);

        $data = [
            'id' => $record->id,
            'application' => $application,
            'year' => $date->format('Y年'),
            'date' => $date->format('n月j日'),
            'clock_in' => Carbon::parse($clockIn)->format('H:i'),
            'clock_out' => $clockOut ? Carbon::parse($clockOut)->format('H:i') : '',
            'breaks' => $this->formatBreaks($breakItems),
            'comment' => $source->comment,
        ];

        return view('user.user-detail', compact('user', 'data'));
    }

    /**
     * 休憩（breaks または proposal_breaks）を、詳細画面で使う「開始・終了」の形にそろえる
     */
    private function formatBreaks(Collection $breaks): array
    {
        return $breaks->map(fn($break) => [
            'break_in' => Carbon::parse($break->break_in)->format('H:i'),
            'break_out' => $break->break_out ? Carbon::parse($break->break_out)->format('H:i') : '',
        ])->all();
    }
}
