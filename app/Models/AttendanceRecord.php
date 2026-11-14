<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'comment',
    ];

    /**
     * この勤怠記録のユーザーを取得する
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * この勤怠記録の休憩一覧を取得する
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    /**
     * この勤怠記録に対する修正申請一覧を取得する
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 休憩時間の合計を「分」で返す（終了していない休憩は数えない）
     */
    public function getBreakMinutesAttribute(): int
    {
        return $this->breaks
            ->filter(fn($break) => $break->break_out)
            ->sum(fn($break) => Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out)));
    }

    /**
     * 労働時間（出勤〜退勤から休憩を引いた時間）を「分」で返す
     * まだ退勤していない場合は null を返す
     */
    public function getWorkMinutesAttribute(): ?int
    {
        if (! $this->clock_out) {
            return null;
        }

        return Carbon::parse($this->clock_in)->diffInMinutes(Carbon::parse($this->clock_out)) - $this->break_minutes;
    }

    /**
     * 休憩時間の合計を「HH:MM」形式で返す
     */
    public function getTotalBreakTimeAttribute(): string
    {
        return $this->formatMinutes($this->break_minutes);
    }

    /**
     * 労働時間を「HH:MM」形式で返す（まだ退勤していない場合は null）
     */
    public function getTotalTimeAttribute(): ?string
    {
        if ($this->work_minutes === null) {
            return null;
        }

        return $this->formatMinutes($this->work_minutes);
    }

    /**
     * 分を「HH:MM」形式の文字にする
     */
    private function formatMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
