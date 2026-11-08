<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\AttendanceRecord;
use Carbon\Carbon;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
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

    public function staffList()
    {
        $users = User::where('admin_status', false)->get();

        return view('admin.staff-list', compact('users'));
    }

    public function staffAttendance(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $date = Carbon::parse($request->input('date', now()->format('Y-m')))->startOfMonth();
        $previousMonth = $date->copy()->subMonth()->format('Y-m');
        $nextMonth = $date->copy()->addMonth()->format('Y-m');

        $attendanceRecords = $user->attendanceRecords()
            ->with('breaks')
            ->whereBetween('date', [$date->toDateString(), $date->copy()->endOfMonth()->toDateString()])
            ->get()
            ->keyBy('date');

        $formattedAttendanceRecords = [];

        foreach (\Carbon\CarbonPeriod::create($date, $date->copy()->endOfMonth()) as $day) {
            $record = $attendanceRecords->get($day->toDateString());

            $formattedAttendanceRecords[] = [
                'id' => $record ? $record->id : null,
                'date' => $day->isoFormat('MM/DD(ddd)'),
                'clock_in' => $record ? Carbon::parse($record->clock_in)->format('H:i') : '',
                'clock_out' => $record && $record->clock_out ? Carbon::parse($record->clock_out)->format('H:i') : '',
                'total_break_time' => $record ? $record->total_break_time : null,
                'total_time' => $record ? $record->total_time : null,
            ];
        }

        return view('admin.staff-attendance-list', compact('user', 'date', 'previousMonth', 'nextMonth', 'formattedAttendanceRecords'));
    }
}
