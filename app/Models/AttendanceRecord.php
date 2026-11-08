<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function breaks()
    {
        return $this->hasMany(AttendanceBreak::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function getTotalBreakTimeAttribute()
    {
        $minutes = 0;
        foreach ($this->breaks as $break) {
            if ($break->break_out) {
                $minutes += \Carbon\Carbon::parse($break->break_in)->diffInMinutes(\Carbon\Carbon::parse($break->break_out));
            }
        }

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function getTotalTimeAttribute()
    {
        if (! $this->clock_out) {
            return null;
        }

        [$hours, $minutes] = explode(':', $this->total_break_time);
        $breakMinutes = $hours * 60 + $minutes;

        $workMinutes = \Carbon\Carbon::parse($this->clock_in)->diffInMinutes(\Carbon\Carbon::parse($this->clock_out)) - $breakMinutes;

        return sprintf('%02d:%02d', intdiv($workMinutes, 60), $workMinutes % 60);
    }
}
