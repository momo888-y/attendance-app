<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    /**
     * ログイン後のリダイレクト先を決める
     * 管理者なら勤怠一覧（管理者用）、一般ユーザーなら打刻画面へ
     *
     * 引数の型は、親インターフェースが型なしのため付けられない（付けるとエラーになる）
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        if ($request->user()->admin_status) {
            return redirect('/admin/attendance/list');
        }

        return redirect('/attendance');
    }
}
