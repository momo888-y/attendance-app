<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplicationRequest extends FormRequest
{
    /**
     * このリクエストを許可するかどうか（ログイン確認はルートのミドルウェアで行うため常に許可）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 勤怠修正フォームのバリデーションルールを返す
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'date_format:H:i', 'before:new_clock_out'],
            'new_clock_out' => ['required', 'date_format:H:i'],
            'new_break_in.*' => ['nullable', 'required_with:new_break_out.*', 'date_format:H:i', 'after_or_equal:new_clock_in', 'before_or_equal:new_clock_out'],
            'new_break_out.*' => ['nullable', 'required_with:new_break_in.*', 'date_format:H:i', 'after:new_break_in.*', 'before_or_equal:new_clock_out'],
            'comment' => ['required', 'max:255'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージを返す
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.before' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.date_format' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.*.required_with' => '休憩時間が不適切な値です',
            'new_break_in.*.date_format' => '休憩時間が不適切な値です',
            'new_break_in.*.after_or_equal' => '休憩時間が不適切な値です',
            'new_break_in.*.before_or_equal' => '休憩時間が不適切な値です',
            'new_break_out.*.required_with' => '休憩時間が不適切な値です',
            'new_break_out.*.date_format' => '休憩時間が不適切な値です',
            'new_break_out.*.after' => '休憩時間が不適切な値です',
            'new_break_out.*.before_or_equal' => '休憩時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }
}
