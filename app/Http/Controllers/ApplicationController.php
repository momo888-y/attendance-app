<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\ProposalBreak;
use Carbon\Carbon;
use App\Models\AttendanceRecord;
use App\Models\AttendanceBreak;

class ApplicationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        if ($user->admin_status) {
            $applications = Application::with(['user', 'AttendanceRecord'])
                ->latest()
                ->get();

            return view('admin.admin-application-list', compact('applications'));
        }

        $applications = $user->applications()
            ->latest()
            ->get();

        $formattedApplications = [];
        foreach ($applications as $application) {
            $formattedApplications[] = [
                'id' => $application->id,
                'approval_status' => $application->approval_status,
                'date' => $application->new_date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => Carbon::parse($application->application_date)->format('Y/m/d'),
            ];
        }

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }

    public function store(ApplicationRequest $request, $id)
    {
        $user = Auth::user();

        if ($user->admin_status) {
            $record = AttendanceRecord::findOrFail($id);

            $record->update([
                'clock_in' => $request->input('new_clock_in'),
                'clock_out' => $request->input('new_clock_out'),
                'comment' => $request->input('comment'),
            ]);

            $record->breaks()->delete();

            foreach ($request->input('new_break_in', []) as $index => $breakIn) {
                if (! $breakIn) {
                    continue;
                }

                AttendanceBreak::create([
                    'attendance_record_id' => $record->id,
                    'break_in' => $breakIn,
                    'break_out' => $request->input('new_break_out.' . $index),
                ]);
            }

            return redirect('/attendance/' . $record->id);
        }

        $record = $user->attendanceRecords()->findOrFail($id);

        $application = Application::create([
            'user_id' => $user->id,
            'attendance_record_id' => $record->id,
            'new_date' => $record->date,
            'new_clock_in' => $request->input('new_clock_in'),
            'new_clock_out' => $request->input('new_clock_out'),
            'comment' => $request->input('comment'),
            'application_date' => today()->toDateString(),
        ]);

        foreach ($request->input('new_break_in', []) as $index => $breakIn) {
            if (! $breakIn) {
                continue;
            }

            ProposalBreak::create([
                'application_id' => $application->id,
                'break_in' => $breakIn,
                'break_out' => $request->input('new_break_out.' . $index),
            ]);
        }

        return redirect('/attendance/' . $record->id);
    }

    public function show($id)
    {
        $application = Auth::user()->applications()->findOrFail($id);

        return redirect('/attendance/' . $application->attendance_record_id);
    }

    public function approveShow($attendance_correct_request_id)
    {
        $application = Application::with(['user', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);

        $user = $application->user;

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    public function approve($attendance_correct_request_id)
    {
        $application = Application::with(['AttendanceRecord', 'proposalBreaks'])
            ->findOrFail($attendance_correct_request_id);

        $record = $application->AttendanceRecord;

        $record->update([
            'clock_in' => $application->new_clock_in,
            'clock_out' => $application->new_clock_out,
            'comment' => $application->comment,
        ]);

        $record->breaks()->delete();

        foreach ($application->proposalBreaks as $proposalBreak) {
            AttendanceBreak::create([
                'attendance_record_id' => $record->id,
                'break_in' => $proposalBreak->break_in,
                'break_out' => $proposalBreak->break_out,
            ]);
        }

        $application->update(['approval_status' => '承認済み']);

        return redirect('/stamp_correction_request/approve/' . $application->id);
    }
}
