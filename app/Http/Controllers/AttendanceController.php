<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;

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
}
