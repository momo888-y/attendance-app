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
     * 休憩時間の合計を「HH:MM」形式で返す（終了していない休憩は数えない）
     */
    public function getTotalBreakTimeAttribute(): string
    {
        $minutes = $this->breaks
            ->filter(fn($break) => $break->break_out)
            ->sum(fn($break) => Carbon::parse($break->break_in)->diffInMinutes(Carbon::parse($break->break_out)));

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 勤務時間の合計（出勤〜退勤から休憩を引いた時間）を「HH:MM」形式で返す
     * まだ退勤していない場合は null を返す
     */
    public function getTotalTimeAttribute(): ?string
    {
        if (! $this->clock_out) {
            return null;
        }

        [$hours, $minutes] = explode(':', $this->total_break_time);
        $breakMinutes = $hours * 60 + $minutes;

        $workMinutes = Carbon::parse($this->clock_in)->diffInMinutes(Carbon::parse($this->clock_out)) - $breakMinutes;

        return sprintf('%02d:%02d', intdiv($workMinutes, 60), $workMinutes % 60);
    }
}
