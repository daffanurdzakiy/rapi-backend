<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventCancellationService
{
    public function request(Event $event, User $requester, string $reason): Event
    {
        return DB::transaction(function () use ($event, $requester, $reason): Event {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);

            if (
                $event->user_id !== $requester->id
                || ! in_array($requester->role?->role_name, ['Guru', 'Organisasi'], true)
            ) {
                abort(403);
            }

            if ($event->status !== 'Disetujui' || $event->execution_status !== 'Belum Terlaksana') {
                throw ValidationException::withMessages([
                    'cancellation_reason' => 'Pembatalan hanya dapat diajukan untuk agenda yang telah disetujui dan belum dimulai.',
                ]);
            }

            $event->update([
                'status' => 'Menunggu Pembatalan',
                'cancellation_reason' => $reason,
                'cancellation_decision_reason' => null,
            ]);

            $recipientIds = User::query()
                ->whereHas('role', fn ($query) => $query->where('role_name', 'Admin'))
                ->pluck('id')
                ->filter()
                ->unique();

            foreach ($recipientIds as $recipientId) {
                Notification::create([
                    'event_id' => $event->id,
                    'user_id' => $recipientId,
                    'message' => "Permintaan pembatalan agenda {$event->title} diajukan oleh {$requester->name}. Alasan: {$reason}",
                ]);
            }

            return $event->fresh(['room.pic', 'user', 'approver', 'cancellationReviewer']);
        });
    }

    public function decide(Event $event, User $reviewer, string $decision, ?string $reason = null): Event
    {
        return DB::transaction(function () use ($event, $reviewer, $decision, $reason): Event {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $role = $reviewer->role?->role_name;

            if ($role !== 'Admin') {
                abort(403);
            }

            if ($event->status !== 'Menunggu Pembatalan') {
                throw ValidationException::withMessages([
                    'status' => 'Permintaan pembatalan agenda ini sudah tidak menunggu keputusan.',
                ]);
            }

            if (! in_array($decision, ['Dibatalkan', 'Disetujui'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Keputusan pembatalan tidak valid.',
                ]);
            }

            if (
                $decision === 'Dibatalkan'
                && Carbon::parse($event->event_date->format('Y-m-d').' '.$event->start_time, 'Asia/Jakarta')
                    ->lte(now('Asia/Jakarta'))
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Permintaan pembatalan tidak dapat disetujui setelah agenda dimulai.',
                ]);
            }

            $reason = trim((string) $reason);
            if ($decision === 'Disetujui' && $reason === '') {
                throw ValidationException::withMessages([
                    'cancellation_decision_reason' => 'Alasan penolakan pembatalan wajib diisi.',
                ]);
            }

            $event->update([
                'status' => $decision,
                'cancellation_decision_reason' => $reason !== '' ? $reason : null,
                'cancellation_reviewed_by' => $reviewer->id,
            ]);

            $message = $decision === 'Dibatalkan'
                ? "Permintaan pembatalan agenda {$event->title} disetujui oleh {$reviewer->name}."
                : "Permintaan pembatalan agenda {$event->title} ditolak oleh {$reviewer->name}. Alasan: {$reason}";

            Notification::create([
                'event_id' => $event->id,
                'user_id' => $event->user_id,
                'message' => $message,
            ]);

            return $event->fresh(['room.pic', 'user', 'approver', 'cancellationReviewer']);
        });
    }
}
