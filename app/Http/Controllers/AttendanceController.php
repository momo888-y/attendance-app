<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class AttendanceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $formattedDate = now()->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $record = $user->attendanceRecords()
            ->where('date', today()->toDateString())
            ->first();

        if ($request->input('action') === 'clock_in') {
            AttendanceRecord::create([
                'user_id' => $user->id,
                'date' => today()->toDateString(),
                'clock_in' => now()->format('H:i:s'),
            ]);
        } elseif ($request->input('action') === 'break_in') {
            AttendanceBreak::create([
                'attendance_record_id' => $record->id,
                'break_in' => now()->format('H:i:s'),
            ]);
        } elseif ($request->input('action') === 'break_out') {
            $record->breaks()
                ->whereNull('break_out')
                ->update(['break_out' => now()->format('H:i:s')]);
        } elseif ($request->input('action') === 'clock_out') {
            $record->update(['clock_out' => now()->format('H:i:s')]);
        }

        return redirect('/attendance');
    }

    public function list(Request $request)
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

        $formattedAttendanceRecords = [];

        foreach (CarbonPeriod::create($date, $date->copy()->endOfMonth()) as $day) {
            $record = $attendanceRecords->get($day->toDateString());

            if (! $record) {
                $formattedAttendanceRecords[] = [
                    'id' => null,
                    'date' => $day->isoFormat('MM/DD(ddd)'),
                    'clock_in' => '',
                    'clock_out' => '',
                    'total_break_time' => null,
                    'total_time' => null,
                ];
                continue;
            }

            $breakMinutes = 0;
            foreach ($record->breaks as $break) {
                if ($break->break_out) {
                    $breakMinutes += Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out));
                }
            }

            $totalTime = null;
            if ($record->clock_out) {
                $workMinutes = Carbon::parse($record->clock_in)->diffInMinutes(Carbon::parse($record->clock_out)) - $breakMinutes;
                $totalTime = sprintf('%02d:%02d', intdiv($workMinutes, 60), $workMinutes % 60);
            }

            $formattedAttendanceRecords[] = [
                'id' => $record->id,
                'date' => $day->isoFormat('MM/DD(ddd)'),
                'clock_in' => Carbon::parse($record->clock_in)->format('H:i'),
                'clock_out' => $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => sprintf('%02d:%02d', intdiv($breakMinutes, 60), $breakMinutes % 60),
                'total_time' => $totalTime,
            ];
        }

        return view('user.user-attendance-list', compact('date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }
}
