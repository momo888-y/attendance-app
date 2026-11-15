<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * 管理者だけを通す。一般ユーザーは打刻画面へリダイレクトする
     *
     * @param  Request  $request  リクエスト
     * @param  Closure(Request): (Response)  $next  次の処理
     * @return Response 次の処理のレスポンス、または打刻画面へのリダイレクト
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->admin_status) {
            return redirect('/attendance');
        }

        return $next($request);
    }
}
