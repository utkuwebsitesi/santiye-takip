<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = AuditLog::with('user')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')))
            ->when($request->filled('record_type'), fn ($query) => $query->where('auditable_type', $request->string('record_type')))
            ->latest('created_at')->paginate(30)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'users' => User::orderBy('name')->get(),
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'recordTypes' => AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type'),
        ]);
    }
}
