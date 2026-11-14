<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 管理者は、すべての操作を許可する
     */
    public function before(User $user): ?bool
    {
        return $user->admin_status ? true : null;
    }

    /**
     * 本人の勤怠だけ更新できる
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 本人の勤怠だけ削除できる
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
