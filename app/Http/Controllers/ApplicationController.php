<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\AttendanceBreak;
use App\Models\AttendanceRecord;
use App\Models\ProposalBreak;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    /**
     * 修正申請の一覧を表示する
     * 管理者なら全員分、一般ユーザーなら自分の申請だけを表示する
     */
    public function index(): View
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

    /**
     * 勤怠の修正を受け付ける
     * 管理者なら勤怠記録をその場で直接書き換え、一般ユーザーなら修正申請を作成する
     */
    public function store(ApplicationRequest $request, string $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->admin_status) {
            $record = $this->updateRecordDirectly($request, $id);

            return redirect('/attendance/' . $record->id);
        }

        $record = $this->createApplication($request, $user, $id);

        return redirect('/attendance/' . $record->id);
    }

    /**
     * 一般ユーザー用：申請に対応する勤怠詳細画面へリダイレクトする
     */
    public function show(string $id): RedirectResponse
    {
        $application = Auth::user()->applications()->findOrFail($id);

        return redirect('/attendance/' . $application->attendance_record_id);
    }

    /**
     * 管理者用：修正申請の承認画面を表示する
     */
    public function approveShow(string $attendanceCorrectRequestId): View
    {
        $application = Application::with(['user', 'proposalBreaks'])
            ->findOrFail($attendanceCorrectRequestId);

        $user = $application->user;

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    /**
     * 管理者用：修正申請を承認し、申請内容で勤怠記録と休憩を上書きする
     */
    public function approve(string $attendanceCorrectRequestId): RedirectResponse
    {
        $application = Application::with(['AttendanceRecord', 'proposalBreaks'])
            ->findOrFail($attendanceCorrectRequestId);

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

        $application->update(['approval_status' => Application::STATUS_APPROVED]);

        return redirect('/stamp_correction_request/approve/' . $application->id);
    }

    /**
     * 管理者用：フォームの内容で勤怠記録と休憩を直接書き換える
     */
    private function updateRecordDirectly(ApplicationRequest $request, string $id): AttendanceRecord
    {
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

        return $record;
    }

    /**
     * 一般ユーザー用：フォームの内容で修正申請（と休憩の修正案）を作成する
     * 対象の勤怠記録を返す
     */
    private function createApplication(ApplicationRequest $request, User $user, string $id): AttendanceRecord
    {
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

        return $record;
    }
}
