<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Illuminate\Http\Request;

class MailLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = MailLog::orderByDesc('created_at')->paginate(30)->withQueryString();

        return view('admin.maillog', ['logs' => $logs]);
    }
}
