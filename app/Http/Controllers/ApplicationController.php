<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\ProposalBreak;
use Carbon\Carbon;

class ApplicationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

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
}
