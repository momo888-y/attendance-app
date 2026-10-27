<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\ProposalBreak;

class ApplicationController extends Controller
{
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
