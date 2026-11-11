<?php

namespace App\Support;

use App\Models\Complaint;
use App\Models\Event;
use App\Models\Notification;
use Carbon\Carbon;

/**
 * Formats in-app notifications into a structured, human-readable payload
 * for the Web Desktop notification dropdown, notification center, and live toasts.
 */
class NotificationPresenter
{
    /**
     * Transform a Notification model into a structured display array.
     *
     * @return array<string, mixed>
     */
    public static function present(Notification $notification): array
    {
        $createdAt = ($notification->created_at ?? now('Asia/Jakarta'))
            ->copy()
            ->timezone('Asia/Jakarta');

        $base = [
            'id' => $notification->id,
            'is_read' => (bool) $notification->is_read,
            'message' => (string) $notification->message,
            'time_ago' => $createdAt->copy()->locale('id')->diffForHumans(),
            'timestamp' => $createdAt->copy()->locale('id')->translatedFormat('d M Y, H:i').' WIB',
            'open_url' => route('notifications.open', $notification, false),
            'read_url' => route('notifications.read', $notification, false),
            'meta' => [],
            'note_label' => null,
            'note_text' => null,
        ];

        if ($notification->event) {
            return array_merge($base, self::presentEvent($notification, $notification->event));
        }

        if ($notification->complaint) {
            return array_merge($base, self::presentComplaint($notification, $notification->complaint));
        }

        return array_merge($base, [
            'category' => 'system',
            'category_label' => 'Informasi Sistem',
            'title' => 'Pemberitahuan Sistem',
            'badge_label' => 'Informasi',
            'tone' => 'info',
            'summary' => (string) $notification->message,
            'action_label' => 'Buka Dashboard',
        ]);
    }

    /**
     * Present a room-booking notification with schedule, room, and reason context.
     *
     * @return array<string, mixed>
     */
    private static function presentEvent(Notification $notification, Event $event): array
    {
        $message = (string) $notification->message;
        $lower = mb_strtolower($message);
        $roomName = $event->room?->room_name;
        $applicantName = $event->user?->name;
        $approverName = $event->approver?->name;

        $dateStr = $event->event_date
            ? Carbon::parse($event->event_date)->locale('id')->translatedFormat('d M Y')
            : null;
        $timeRange = ($event->start_time && $event->end_time)
            ? substr((string) $event->start_time, 0, 5).'–'.substr((string) $event->end_time, 0, 5).' WIB'
            : null;
        $schedule = implode(' · ', array_filter([$dateStr, $timeRange]));

        $meta = array_values(array_filter([
            $roomName ? ['label' => 'Ruangan', 'value' => $roomName] : null,
            $schedule !== '' ? ['label' => 'Jadwal', 'value' => $schedule] : null,
            $event->title ? ['label' => 'Agenda', 'value' => $event->title] : null,
        ]));

        if (str_contains($lower, 'pengajuan peminjaman ruangan')) {
            if ($applicantName) {
                $meta[] = ['label' => 'Pemohon', 'value' => $applicantName];
            }

            return [
                'category' => 'booking',
                'category_label' => 'Peminjaman Ruang',
                'title' => 'Permohonan Peminjaman Ruangan Baru',
                'badge_label' => $event->status === 'Pending' ? 'Menunggu Persetujuan' : $event->execution_status,
                'tone' => $event->status === 'Pending' ? 'warning' : 'info',
                'summary' => $message,
                'meta' => $meta,
                'note_label' => null,
                'note_text' => null,
                'action_label' => $event->status === 'Pending' ? 'Tinjau Pengajuan' : 'Lihat Agenda',
            ];
        }

        if (str_contains($lower, 'ditolak')) {
            $reason = $event->rejection_reason ?: self::extractAfterMarker($message, 'Alasan:');
            if ($approverName) {
                $meta[] = ['label' => 'Diproses oleh', 'value' => $approverName];
            }

            return [
                'category' => 'booking',
                'category_label' => 'Peminjaman Ruang',
                'title' => 'Pengajuan Peminjaman Ditolak',
                'badge_label' => 'Ditolak',
                'tone' => 'danger',
                'summary' => self::stripAfterMarker($message, 'Alasan:'),
                'meta' => $meta,
                'note_label' => $reason ? 'Alasan Penolakan' : null,
                'note_text' => $reason,
                'action_label' => 'Lihat Agenda',
            ];
        }

        if (str_contains($lower, 'diperbarui')) {
            $reason = $event->move_reason ?: self::extractAfterMarker($message, 'Alasan:');

            return [
                'category' => 'booking',
                'category_label' => 'Peminjaman Ruang',
                'title' => 'Jadwal atau Ruangan Agenda Diperbarui',
                'badge_label' => 'Jadwal Diubah',
                'tone' => 'info',
                'summary' => self::stripAfterMarker($message, 'Alasan:'),
                'meta' => $meta,
                'note_label' => $reason ? 'Alasan Perubahan' : null,
                'note_text' => $reason,
                'action_label' => 'Lihat Agenda',
            ];
        }

        if ($approverName) {
            $meta[] = ['label' => 'Disetujui oleh', 'value' => $approverName];
        }

        return [
            'category' => 'booking',
            'category_label' => 'Peminjaman Ruang',
            'title' => 'Pengajuan Peminjaman Disetujui',
            'badge_label' => 'Disetujui',
            'tone' => 'success',
            'summary' => $message,
            'meta' => $meta,
            'note_label' => null,
            'note_text' => null,
            'action_label' => 'Lihat Agenda',
        ];
    }

    /**
     * Present a facility complaint notification with room, reporter/PIC, and repair note context.
     *
     * @return array<string, mixed>
     */
    private static function presentComplaint(Notification $notification, Complaint $complaint): array
    {
        $message = (string) $notification->message;
        $lower = mb_strtolower($message);
        $roomName = $complaint->room?->room_name;
        $reporterName = $complaint->reporter?->name;
        $picName = $complaint->room?->pic?->name;

        $meta = array_values(array_filter([
            $roomName ? ['label' => 'Ruangan', 'value' => $roomName] : null,
            $reporterName ? ['label' => 'Pelapor', 'value' => $reporterName] : null,
            $picName ? ['label' => 'PIC Ruangan', 'value' => $picName] : null,
            ['label' => 'Status Saat Ini', 'value' => $complaint->status_repair],
        ]));

        if (str_starts_with($lower, 'pengaduan baru')) {
            return [
                'category' => 'complaint',
                'category_label' => 'Pengaduan Ruang',
                'title' => 'Laporan Kerusakan Fasilitas Baru',
                'badge_label' => $complaint->status_repair === 'Belum Diperbaiki' ? 'Perlu Tindak Lanjut' : $complaint->status_repair,
                'tone' => $complaint->status_repair === 'Sudah Diperbaiki' ? 'success' : ($complaint->status_repair === 'Menunggu Spare Part' ? 'warning' : 'danger'),
                'summary' => $complaint->description ?: $message,
                'meta' => $meta,
                'note_label' => null,
                'note_text' => null,
                'action_label' => 'Lihat & Tindak Lanjuti',
            ];
        }

        $tone = 'info';
        $badge = $complaint->status_repair;
        $title = 'Status Perbaikan Fasilitas Diperbarui';

        if (str_contains($lower, 'sudah diperbaiki')) {
            $tone = 'success';
            $badge = 'Sudah Diperbaiki';
            $title = 'Perbaikan Fasilitas Telah Selesai';
        } elseif (str_contains($lower, 'menunggu spare part')) {
            $tone = 'warning';
            $badge = 'Menunggu Spare Part';
            $title = 'Perbaikan Menunggu Spare Part';
        } elseif (str_contains($lower, 'belum diperbaiki')) {
            $tone = 'danger';
            $badge = 'Belum Diperbaiki';
        }

        $note = self::extractAfterMarker($message, 'Keterangan:')
            ?: ($badge === 'Sudah Diperbaiki' ? $complaint->repair_note : null);

        return [
            'category' => 'complaint',
            'category_label' => 'Pengaduan Ruang',
            'title' => $title,
            'badge_label' => $badge,
            'tone' => $tone,
            'summary' => $complaint->description
                ? 'Keluhan: '.$complaint->description
                : self::stripAfterMarker($message, 'Keterangan:'),
            'meta' => $meta,
            'note_label' => $note ? 'Keterangan Perbaikan' : null,
            'note_text' => $note,
            'action_label' => 'Lihat Pengaduan',
        ];
    }

    private static function extractAfterMarker(string $text, string $marker): ?string
    {
        $pos = mb_stripos($text, $marker);
        if ($pos === false) {
            return null;
        }

        $value = trim(mb_substr($text, $pos + mb_strlen($marker)));

        return $value !== '' ? $value : null;
    }

    private static function stripAfterMarker(string $text, string $marker): string
    {
        $pos = mb_stripos($text, $marker);
        if ($pos === false) {
            return $text;
        }

        return trim(mb_substr($text, 0, $pos));
    }
}
