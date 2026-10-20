<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $formattedDate = now()->isoFormat('YYYY年M月D日(ddd)');
        $formattedTime = now()->format('H:i');

        return view('user.attendance-register', compact('user', 'formattedDate', 'formattedTime'));
    }
}
