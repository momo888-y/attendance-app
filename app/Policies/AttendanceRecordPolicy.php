<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;

class AttendanceRecordPolicy
{
    /**
     * 管理者は、すべての操作を許可する
     *
     * @param  User  $user  ログイン中のユーザー
     * @return bool|null 管理者なら true、それ以外は null（各メソッドの判定に進む）
     */
    public function before(User $user): ?bool
    {
        return $user->admin_status ? true : null;
    }

    /**
     * 本人の勤怠だけ更新できる
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceRecord  $attendanceRecord  対象の勤怠記録
     * @return bool 更新してよければ true
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }

    /**
     * 本人の勤怠だけ削除できる
     *
     * @param  User  $user  ログイン中のユーザー
     * @param  AttendanceRecord  $attendanceRecord  対象の勤怠記録
     * @return bool 削除してよければ true
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return $user->id === $attendanceRecord->user_id;
    }
}
