<?php

use App\Http\Controllers\AttendanceController;
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

Route::get('/attendance', [AttendanceController::class, 'index'])->middleware('auth');
Route::post('/attendance', [AttendanceController::class, 'store'])->middleware('auth');
Route::get('/attendance/list', [AttendanceController::class, 'list'])->middleware('auth');
