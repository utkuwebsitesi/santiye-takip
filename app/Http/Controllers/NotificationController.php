<?php

namespace App\Http\Controllers;

use App\Models\SystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $query = SystemNotification::where('user_id', $request->user()->id);
        if (! $request->user()->hasPermission('maintenance.view')) {
            $query->whereNull('maintenance_entry_id');
        }
        return view('notifications.index', [
            'notifications' => $query->latest()->paginate(30),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        $query = SystemNotification::where('user_id', $request->user()->id)->whereNull('read_at');
        if (! $request->user()->hasPermission('maintenance.view')) {
            $query->whereNull('maintenance_entry_id');
        }
        $query->update(['read_at' => now()]);

        return back()->with('success', 'Bildirimlerin tamamı okundu olarak işaretlendi.');
    }

    public function open(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        abort_unless($request->user()->hasPermission('maintenance.view') || $notification->maintenance_entry_id === null, 403);
        $notification->update(['read_at' => $notification->read_at ?: now()]);

        return redirect()->to($notification->link ?: route('notifications.index'));
    }
}
