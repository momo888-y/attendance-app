<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    use HasFactory;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function AttendanceRecord()
    {
        return $this->belongsTo(Application::class);
    }

    public function proposalBreaks()
    {
        return $this->hasMany(ProposalBreak::class);
    }
}
