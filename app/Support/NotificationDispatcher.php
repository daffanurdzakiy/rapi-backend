<?php

namespace App\Support;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Notification;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Centralizes creation of in-app notifications for bookings and facility complaints
 * across Web and API controllers, ensuring consistent recipient routing (PIC, Applicants, Admins).
 */
class NotificationDispatcher
{
    /**
     * Notify room PIC(s) and Admins when a new pending room booking is submitted.
     *
     * @param  list<Event>  $createdEvents
     * @param  list<int>  $roomIds
     * @param  Collection<int, Room>  $roomsById
     */
    public static function notifyBookingSubmitted(
        array $createdEvents,
        array $roomIds,
        Collection $roomsById,
        User $applicant,
        string $title,
        string $dateSummary,
    ): void {
        $adminIds = self::adminUserIds(excludeUserId: $applicant->id);
        $eventsByRoom = collect($createdEvents)->groupBy('room_id');

        foreach ($roomIds as $roomId) {
            $room = $roomsById->get($roomId);
            if (! $room) {
                continue;
            }

            /** @var Event|null $firstRoomEvent */
            $firstRoomEvent = $eventsByRoom->get($roomId)?->first();
            $message = "Pengajuan peminjaman ruangan {$room->room_name} ({$dateSummary}) dari {$applicant->name}: {$title}.";

            $recipientIds = collect([$room->pic_id])
                ->merge($adminIds)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === (int) $applicant->id)
                ->unique();

            foreach ($recipientIds as $recipientId) {
                Notification::create([
                    'event_id' => $firstRoomEvent?->id,
                    'user_id' => $recipientId,
                    'message' => $message,
                ]);
            }
        }
    }

    /**
     * Notify the booking applicant (and room PIC if processed by Admin) about approval or rejection.
     */
    public static function notifyBookingDecided(Event $event, User $decider, string $status, ?string $reason = null): void
    {
        $event->loadMissing('room');

        if ($status === 'Ditolak' && ! blank($reason)) {
            $applicantMessage = "Pengajuan agenda {$event->title} ditolak. Alasan: {$reason}";
        } elseif ($status === 'Disetujui') {
            $applicantMessage = "Pengajuan agenda {$event->title} di {$event->room->room_name} telah disetujui.";
        } else {
            return;
        }

        Notification::create([
            'event_id' => $event->id,
            'user_id' => $event->user_id,
            'message' => $applicantMessage,
        ]);

        // Inform the room's PIC when an Admin directly decides a booking on their room.
        $picId = $event->room?->pic_id;
        if ($picId && (int) $picId !== (int) $decider->id && (int) $picId !== (int) $event->user_id) {
            $verb = $status === 'Disetujui' ? 'disetujui' : 'ditolak';
            Notification::create([
                'event_id' => $event->id,
                'user_id' => $picId,
                'message' => "Agenda {$event->title} di {$event->room->room_name} telah {$verb} oleh Admin ({$decider->name}).",
            ]);
        }
    }

    /**
     * Notify the applicant (and room PIC if applicable) when an Admin updates a booking's room or time.
     */
    public static function notifyBookingUpdated(
        Event $event,
        User $editor,
        bool $roomChanged,
        ?string $moveReason = null,
    ): void {
        $event->loadMissing('room');

        $message = "Agenda {$event->title} diperbarui: {$event->room->room_name}, "
            .Carbon::parse($event->start_time)->format('H:i').'-'
            .Carbon::parse($event->end_time)->format('H:i').'.';

        if ($roomChanged && ! blank($moveReason)) {
            $message .= ' Alasan: '.$moveReason;
        }

        $recipientIds = collect([$event->user_id, $event->room?->pic_id])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $editor->id && $id !== (int) $event->user_id)
            ->unique();

        foreach ($recipientIds as $recipientId) {
            Notification::create([
                'event_id' => $event->id,
                'user_id' => $recipientId,
                'message' => $message,
            ]);
        }
    }

    /**
     * Notify the room's PIC and Admins when a new facility complaint is submitted.
     */
    public static function notifyComplaintSubmitted(Complaint $complaint, Room $room, User $reporter): void
    {
        $message = "Pengaduan baru di {$room->room_name} dari {$reporter->name}: {$complaint->description}";

        $recipientIds = collect([$room->pic_id])
            ->merge(self::adminUserIds(excludeUserId: $reporter->id))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $reporter->id)
            ->unique();

        foreach ($recipientIds as $recipientId) {
            Notification::create([
                'complaint_id' => $complaint->id,
                'user_id' => $recipientId,
                'message' => $message,
            ]);
        }
    }

    /**
     * Notify the complaint reporter (and Admins when completed) about repair status updates.
     */
    public static function notifyComplaintStatusUpdated(
        Complaint $complaint,
        User $updater,
        string $newStatus,
        ?string $repairNote = null,
    ): void {
        $complaint->loadMissing('room');

        $noteSnippet = ! empty($repairNote) ? " Keterangan: {$repairNote}" : '';
        $message = "Status perbaikan pengaduan di {$complaint->room->room_name} diperbarui menjadi: {$newStatus}.{$noteSnippet}";

        $recipientIds = collect([$complaint->reporter_id]);
        if ($newStatus === 'Sudah Diperbaiki') {
            $recipientIds = $recipientIds->merge(self::adminUserIds(excludeUserId: $updater->id));
        }

        $recipientIds
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->each(function (int $recipientId) use ($complaint, $message): void {
                Notification::create([
                    'complaint_id' => $complaint->id,
                    'user_id' => $recipientId,
                    'message' => $message,
                ]);
            });
    }

    /**
     * @return Collection<int, int>
     */
    private static function adminUserIds(?int $excludeUserId = null): Collection
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('role_name', 'Admin'))
            ->when($excludeUserId, fn ($q) => $q->whereKeyNot($excludeUserId))
            ->pluck('id');
    }
}

