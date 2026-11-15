<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * このリクエストを許可するかどうか（更新できる人の判定は Policy で行うため常に許可）
     *
     * @return bool 常に true
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 更新する勤怠のバリデーションルールを返す（送られてきた項目だけチェックする）
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $attendanceRecord = $this->route('attendanceRecord');

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records')
                    ->ignore($attendanceRecord->id)
                    ->where('user_id', $attendanceRecord->user_id),
            ],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => [
                'sometimes',
                'nullable',
                'date_format:H:i:s',
                function ($attribute, $value, $fail) use ($attendanceRecord) {
                    $clockIn = $this->input('clock_in', $attendanceRecord->clock_in);

                    if ($value && $value <= $clockIn) {
                        $fail('退勤時刻は出勤時刻より後の時刻を指定してください。');
                    }
                },
            ],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージを返す（FN060）
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
