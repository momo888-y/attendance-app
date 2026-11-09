<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * このユーザーが出した修正申請一覧を取得する
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * このユーザーの勤怠記録一覧を取得する
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * 今日の勤務状態（勤務外・出勤中・休憩中・退勤済）を返す
     */
    public function getAttendanceStatusAttribute(): string
    {
        $record = $this->attendanceRecords()
            ->where('date', today()->toDateString())
            ->first();

        if (! $record) {
            return '勤務外';
        }

        if ($record->clock_out) {
            return '退勤済';
        }

        if ($record->breaks()->whereNull('break_out')->exists()) {
            return '休憩中';
        }

        return '出勤中';
    }
}
