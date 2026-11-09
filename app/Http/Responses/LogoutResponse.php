<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * ログアウト後のリダイレクト先を決める
     * 管理者画面（admin/*）からなら管理者ログイン画面、それ以外は一般ログイン画面へ
     *
     * 引数の型は、親インターフェースが型なしのため付けられない（付けるとエラーになる）
     *
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        if ($request->is('admin/*')) {
            return redirect('/admin/login');
        }

        return redirect('/login');
    }
}
