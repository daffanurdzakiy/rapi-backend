<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Support\NotificationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manages the Web Desktop notification center, navbar dropdown feed, and read-state actions.
 */
class NotificationController extends Controller
{
    private const RELATIONS = [
        'event.room',
        'event.user',
        'event.approver',
        'complaint.room.pic',
        'complaint.reporter',
    ];

    /** Display the full Notification Center page with category/read filters. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = (string) $request->query('filter', 'semua');
        if (! in_array($filter, ['semua', 'unread', 'booking', 'complaint'], true)) {
            $filter = 'semua';
        }

        $baseQuery = Notification::visibleTo($user);

        $counts = [
            'semua' => (clone $baseQuery)->count(),
            'unread' => (clone $baseQuery)->where('is_read', false)->count(),
            'booking' => (clone $baseQuery)->whereNotNull('event_id')->count(),
            'complaint' => (clone $baseQuery)->whereNotNull('complaint_id')->count(),
        ];

        $query = (clone $baseQuery)->with(self::RELATIONS)->latest();

        match ($filter) {
            'unread' => $query->where('is_read', false),
            'booking' => $query->whereNotNull('event_id'),
            'complaint' => $query->whereNotNull('complaint_id'),
            default => null,
        };

        $notifications = $query->paginate(15)->withQueryString();
        $items = $notifications->getCollection()->map(
            fn (Notification $n) => NotificationPresenter::present($n)
        );

        return view('notifications.index', [
            'notifications' => $notifications,
            'items' => $items,
            'counts' => $counts,
            'currentFilter' => $filter,
        ]);
    }

    /** Return lightweight JSON feed for the navbar bell dropdown and periodic polling. */
    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();
        $onlyUnread = $request->boolean('only_unread');

        $baseQuery = Notification::visibleTo($user);
        $unreadCount = (clone $baseQuery)->where('is_read', false)->count();
        $latestUnreadId = (clone $baseQuery)->where('is_read', false)->max('id') ?? 0;

        $items = (clone $baseQuery)
            ->when($onlyUnread, fn ($q) => $q->where('is_read', false))
            ->with(self::RELATIONS)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (Notification $n) => NotificationPresenter::present($n))
            ->values();

        return response()->json([
            'success' => true,
            'unread_count' => $unreadCount,
            'latest_unread_id' => (int) $latestUnreadId,
            'items' => $items,
        ]);
    }

    /** Mark a notification as read and redirect straight to the related booking or complaint. */
    public function open(Request $request, Notification $notification): RedirectResponse
    {
        $user = $request->user();
        $this->ensureVisible($user, $notification);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        $notification->load(self::RELATIONS);

        if ($notification->event) {
            $event = $notification->event;
            if ($event->isExpiredFromView()) {
                return redirect()->route('events.index')
                    ->with('success', 'Agenda terkait telah melewati masa tampil aktif dan tercatat di arsip laporan.');
            }

            $statusFilter = match ($event->status) {
                'Pending' => 'menunggu_persetujuan',
                'Ditolak' => 'ditolak',
                default => 'semua',
            };

            return redirect()->to(
                route('events.index', ['status' => $statusFilter, 'highlight' => $event->id]).'#event-'.$event->id
            );
        }

        if ($notification->complaint) {
            $complaint = $notification->complaint;
            $isArchived = $complaint->status_repair === 'Sudah Diperbaiki'
                && $complaint->updated_at
                && $complaint->updated_at->lt(now()->subHours(24));

            if ($isArchived) {
                if ($user->role?->role_name === 'PIC') {
                    return redirect()->route('pic.complaint-reports.index')
                        ->with('success', 'Pengaduan terkait sudah selesai lebih dari 24 jam dan berada di arsip laporan PIC.');
                }

                return redirect()->route('complaints.index')
                    ->with('success', 'Pengaduan terkait sudah selesai lebih dari 24 jam yang lalu dan diarsipkan.');
            }

            return redirect()->to(
                route('complaints.index', ['status' => 'semua', 'highlight' => $complaint->id]).'#complaint-'.$complaint->id
            );
        }

        return redirect()->route('dashboard');
    }

    /** Mark a single notification as read (supports both AJAX JSON and form POST/PATCH). */
    public function markAsRead(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $this->ensureVisible($user, $notification);

        if (! $notification->is_read) {
            $notification->update(['is_read' => true]);
        }

        if ($request->expectsJson()) {
            $unreadCount = Notification::visibleTo($user)->where('is_read', false)->count();

            return response()->json([
                'success' => true,
                'unread_count' => $unreadCount,
            ]);
        }

        return back()->with('success', 'Notifikasi ditandai sudah dibaca.');
    }

    /** Mark all visible unread notifications for the current user as read. */
    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();

        Notification::visibleTo($user)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unread_count' => 0,
            ]);
        }

        return back()->with('success', 'Semua notifikasi telah ditandai sudah dibaca.');
    }

    private function ensureVisible(User $user, Notification $notification): void
    {
        $visible = Notification::visibleTo($user)->whereKey($notification->id)->exists();
        abort_unless($visible, 404);
    }
}

