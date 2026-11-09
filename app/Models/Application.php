<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    /** 承認ステータス：承認待ち */
    public const STATUS_PENDING = '承認待ち';

    /** 承認ステータス：承認済み */
    public const STATUS_APPROVED = '承認済み';

    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'new_date',
        'new_clock_in',
        'new_clock_out',
        'comment',
        'approval_status',
        'application_date',
    ];

    protected $casts = [
        'new_date' => 'date',
    ];

    /**
     * 申請したユーザーを取得する
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 修正対象の勤怠記録を取得する
     * （Blade から $application->AttendanceRecord で参照しているため、メソッド名は変更しない）
     */
    public function AttendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 申請に含まれる修正後の休憩時間を取得する
     */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(ProposalBreak::class);
    }

    /**
     * 修正後の出勤時刻を「H:i」形式で返す
     */
    public function getNewClockInAttribute(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }

    /**
     * 修正後の退勤時刻を「H:i」形式で返す
     */
    public function getNewClockOutAttribute(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->format('H:i') : null;
    }
}
