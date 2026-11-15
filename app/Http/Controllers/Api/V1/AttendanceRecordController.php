<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AttendanceRecordController extends Controller
{
    /** 1ページあたりの件数（指定がないとき） */
    private const DEFAULT_PER_PAGE = 20;

    /**
     * 勤怠の一覧を返す（user_id・date・month で絞り込み、ページ分け）
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $attendanceRecords = AttendanceRecord::with(['user', 'breaks'])
            ->when($request->input('user_id'), fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($request->input('date'), fn ($query, $date) => $query->where('date', $date))
            ->when($request->input('month'), fn ($query, $month) => $query->whereBetween('date', [
                Carbon::parse($month)->startOfMonth()->toDateString(),
                Carbon::parse($month)->endOfMonth()->toDateString(),
            ]))
            ->latest('date')
            ->paginate((int) $request->input('per_page', self::DEFAULT_PER_PAGE))
            ->withQueryString();

        return AttendanceRecordResource::collection($attendanceRecords);
    }

    /**
     * 勤怠1件の詳細を返す（ユーザー・休憩・修正申請つき）
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->load(['user', 'breaks', 'applications']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * トークンの持ち主の勤怠を新しく登録する（201 を返す）
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());
        $attendanceRecord->load(['user', 'breaks']);

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 勤怠を更新する（本人か管理者だけ）
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $this->authorize('update', $attendanceRecord);

        $attendanceRecord->update($request->validated());
        $attendanceRecord->load(['user', 'breaks']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠を削除する（本人か管理者だけ。204 を返す）
     */
    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
