<?php

use App\Http\Controllers\AdminAttendanceController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', function () {
        return view('admin.admin-login');
    });
    Route::post('/admin/login', [AuthenticatedSessionController::class, 'store']);
});

Route::post('/admin/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth');

Route::get('/attendance', [AttendanceController::class, 'index'])->middleware(['auth', 'verified']);
Route::post('/attendance', [AttendanceController::class, 'store'])->middleware(['auth', 'verified']);
Route::get('/attendance/list', [AttendanceController::class, 'list'])->middleware(['auth', 'verified']);
Route::get('/attendance/report', [ReportController::class, 'index'])->middleware(['auth', 'verified']);
Route::get('/attendance/{id}', [AttendanceController::class, 'show'])->middleware(['auth', 'verified']);
Route::post('/attendance/{id}', [ApplicationController::class, 'store'])->middleware(['auth', 'verified']);
Route::get('/stamp_correction_request/list', [ApplicationController::class, 'index'])->middleware(['auth', 'verified']);
Route::get('/application/{id}', [ApplicationController::class, 'show'])->middleware(['auth', 'verified']);

Route::get('/admin/attendance/list', [AdminAttendanceController::class, 'index'])->middleware(['auth', 'admin']);
Route::get('/admin/staff/list', [AdminAttendanceController::class, 'staffList'])->middleware(['auth', 'admin']);
Route::get('/admin/attendance/staff/{id}', [AdminAttendanceController::class, 'staffAttendance'])->middleware(['auth', 'admin']);
Route::get('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApplicationController::class, 'approveShow'])->middleware(['auth', 'admin']);
Route::post('/stamp_correction_request/approve/{attendance_correct_request_id}', [ApplicationController::class, 'approve'])->middleware(['auth', 'admin']);
Route::post('/export', [AdminAttendanceController::class, 'export'])->middleware(['auth', 'admin']);
