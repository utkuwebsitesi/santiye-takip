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
        return view('notifications.index', [
            'notifications' => SystemNotification::where('user_id', $request->user()->id)
                ->latest()->paginate(30),
        ]);
    }

    public function readAll(Request $request): RedirectResponse
    {
        SystemNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'Bildirimlerin tamamı okundu olarak işaretlendi.');
    }

    public function open(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => $notification->read_at ?: now()]);

        return redirect()->to($notification->link ?: route('notifications.index'));
    }
}
