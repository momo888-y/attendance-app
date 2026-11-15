<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApplicationRequest;
use App\Models\Application;
use App\Models\AttendanceRecord;
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
     *
     * @return View 申請一覧画面（管理者用または一般ユーザー用）
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

        $formattedApplications = $user->applications()
            ->latest()
            ->get()
            ->map(fn ($application) => [
                'id' => $application->id,
                'approval_status' => $application->approval_status,
                'date' => $application->new_date->format('Y/m/d'),
                'comment' => $application->comment,
                'application_date' => Carbon::parse($application->application_date)->format('Y/m/d'),
            ]);

        return view('user.user-application-list', compact('user', 'formattedApplications'));
    }

    /**
     * 勤怠の修正を受け付ける
     * 管理者なら勤怠記録をその場で直接書き換え、一般ユーザーなら修正申請を作成する
     *
     * @param  ApplicationRequest  $request  修正内容（出勤・退勤・休憩・備考）
     * @param  string  $id  勤怠記録の ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function store(ApplicationRequest $request, string $id): RedirectResponse
    {
        $user = Auth::user();

        if ($user->admin_status) {
            $record = $this->updateRecordDirectly($request, $id);

            return redirect('/attendance/'.$record->id);
        }

        $record = $this->createApplication($request, $user, $id);

        return redirect('/attendance/'.$record->id);
    }

    /**
     * 一般ユーザー用：申請に対応する勤怠詳細画面へリダイレクトする
     *
     * @param  string  $id  修正申請の ID
     * @return RedirectResponse 勤怠詳細画面へのリダイレクト
     */
    public function show(string $id): RedirectResponse
    {
        $application = Auth::user()->applications()->findOrFail($id);

        return redirect('/attendance/'.$application->attendance_record_id);
    }

    /**
     * 管理者用：修正申請の承認画面を表示する
     *
     * @param  string  $attendanceCorrectRequestId  修正申請の ID
     * @return View 修正申請承認画面
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
     *
     * @param  string  $attendanceCorrectRequestId  修正申請の ID
     * @return RedirectResponse 修正申請承認画面へのリダイレクト
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

        $record->breaks()->createMany(
            $application->proposalBreaks->map(fn ($proposalBreak) => [
                'break_in' => $proposalBreak->break_in,
                'break_out' => $proposalBreak->break_out,
            ])->all()
        );

        $application->update(['approval_status' => Application::STATUS_APPROVED]);

        return redirect('/stamp_correction_request/approve/'.$application->id);
    }

    /**
     * 管理者用：フォームの内容で勤怠記録と休憩を直接書き換える
     *
     * @param  ApplicationRequest  $request  修正内容（出勤・退勤・休憩・備考）
     * @param  string  $id  勤怠記録の ID
     * @return AttendanceRecord 更新した勤怠記録
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

        $record->breaks()->createMany($this->formatInputBreaks($request));

        return $record;
    }

    /**
     * 一般ユーザー用：フォームの内容で修正申請（と休憩の修正案）を作成する
     *
     * @param  ApplicationRequest  $request  修正内容（出勤・退勤・休憩・備考）
     * @param  User  $user  ログイン中のユーザー
     * @param  string  $id  勤怠記録の ID
     * @return AttendanceRecord 修正対象の勤怠記録
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

        $application->proposalBreaks()->createMany($this->formatInputBreaks($request));

        return $record;
    }

    /**
     * フォームで入力された休憩のうち、開始時刻があるものだけを保存用の形にそろえる
     *
     * @param  ApplicationRequest  $request  休憩（new_break_in・new_break_out）を含むリクエスト
     * @return array 休憩ごとの break_in・break_out の配列
     */
    private function formatInputBreaks(ApplicationRequest $request): array
    {
        return collect($request->input('new_break_in', []))
            ->filter()
            ->map(fn ($breakIn, $index) => [
                'break_in' => $breakIn,
                'break_out' => $request->input('new_break_out.'.$index),
            ])
            ->values()
            ->all();
    }
}
