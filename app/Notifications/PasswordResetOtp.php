<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// =========================================================================
// NOTIFIKASI EMAIL: PASSWORD RESET OTP
// =========================================================================
// Kelas ini mengatur format dan isi pesan email ketika pengguna meminta kode OTP untuk Lupa Password.
// Jika ingin mengubah atau mengatur perubahan subjek email, teks pesan, atau masa berlaku di teks email,
// ubah isi method `toMail()` di bawah ini.
class PasswordResetOtp extends Notification
{
    use Queueable;

    // Menerima parameter kode OTP 6 digit saat kelas ini diinstansiasi (`new PasswordResetOtp($otp)`)
    public function __construct(public readonly string $otp)
    {
    }

    // Menentukan saluran (channel) pengiriman notifikasi, yaitu melalui email ('mail')
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    // Menyusun tampilan pesan email yang akan dikirimkan ke alamat email pengguna
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Kode OTP untuk Reset Password RAPI-Sekolah')
            ->greeting('Permintaan Reset Password')
            ->line('Gunakan kode OTP berikut untuk membuat password baru:')
            ->line($this->otp)
            ->line('Kode ini hanya berlaku selama 10 menit dan hanya dapat digunakan satu kali.')
            ->line('Jika Anda tidak meminta reset password, abaikan email ini.');

        // Memaksa penggunaan driver mailer 'smtp' sesuai konfigurasi di file .env
        $message->mailer = 'smtp';

        return $message;
    }
}
