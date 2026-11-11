<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// =========================================================================
// API CONTROLLER: NOTIFICATION CONTROLLER (MANAJEMEN NOTIFIKASI IN-APP)
// =========================================================================
// Mengelola daftar notifikasi milik pengguna yang sedang login, menghitung jumlah
// notifikasi yang belum dibaca (`unread_count`), serta menandai notifikasi telah dibaca.
class NotificationController extends ApiController
{
    /**
     * Get user's notifications list.
     */
    // Endpoint: GET /api/notifications
    // Mengambil daftar notifikasi milik user yang sedang login dengan pagination
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Muat notifikasi beserta relasi agenda+ruangan dan pengaduan+ruangan dari yang paling baru
        $visibleQuery = Notification::visibleTo($user);

        $notifications = (clone $visibleQuery)
            ->with(['event.room', 'complaint.room'])
            ->latest()
            ->paginate($request->input('per_page', 20));

        // Hitung total notifikasi yang belum dibaca (`is_read = false`) untuk ditampilkan pada badge lonceng aplikasi
        $unreadCount = (clone $visibleQuery)->where('is_read', false)->count();

        return $this->sendResponse([
            'unread_count' => $unreadCount,
            'items' => NotificationResource::collection($notifications->items()),
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ], 'Daftar notifikasi berhasil dimuat.');
    }

    /**
     * Mark a specific notification as read.
     */
    // Endpoint: PATCH /api/notifications/{notification}/read
    // Menandai satu notifikasi spesifik sebagai sudah dibaca (`is_read = true`)
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        // Keamanan: Pastikan user hanya bisa menandai notifikasi yang dapat ia akses
        $isVisible = Notification::visibleTo($request->user())
            ->whereKey($notification->id)
            ->exists();

        if (! $isVisible) {
            return $this->sendError('Notifikasi tidak ditemukan.', null, 404);
        }

        $notification->update(['is_read' => true]);

        return $this->sendResponse(
            new NotificationResource($notification->fresh(['event.room', 'complaint.room'])),
            'Notifikasi ditandai sudah dibaca.',
        );
    }

    /**
     * Mark all user's notifications as read.
     */
    // Endpoint: POST /api/notifications/read-all
    // Menandai seluruh notifikasi milik user yang belum dibaca menjadi sudah dibaca sekaligus
    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::visibleTo($request->user())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return $this->sendResponse(null, 'Semua notifikasi telah ditandai sudah dibaca.');
    }
}

