<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class EmailLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_log_in_with_email_and_password(): void
    {
        $user = User::factory()->create([
            'email' => 'person@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'person@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_user_cannot_log_in_with_an_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'person@example.com',
            'password' => 'password',
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'person@example.com',
            'password' => 'incorrect-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_user_can_reset_password_using_the_email_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'person@example.com',
            'password' => 'old-password',
        ]);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Atur ulang kata sandi')
            ->assertSee('Kirim Kode OTP');

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('status', 'OTP berhasil dikirim ke email Anda. Periksa kotak masuk atau folder spam.')
            ->assertSessionHas('otp_sent');

        $otp = null;
        Notification::assertSentTo($user, PasswordResetOtp::class, function (PasswordResetOtp $notification) use (&$otp): bool {
            $otp = $notification->otp;

            return true;
        });
        $this->assertNotNull($otp);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Verifikasi Kode')
            ->assertSee('name="otp"', false)
            ->assertSee('Ganti email')
            ->assertSee(route('password.change-email'))
            ->assertDontSee('name="password"', false);

        $verification = $this->post(route('password.otp'), [
            'email' => $user->email,
            'otp' => $otp,
        ]);
        $verification->assertRedirectContains('/reset-password/');
        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));

        $resetUrl = $verification->getTargetUrl();
        $resetPath = parse_url($resetUrl, PHP_URL_PATH);
        $resetToken = basename($resetPath);
        $this->get($resetUrl)
            ->assertOk()
            ->assertSee('Buat Password Baru')
            ->assertSee('Konfirmasi Password')
            ->assertDontSee('Kode Verifikasi');

        $this->post(route('password.update'), [
            'token' => $resetToken,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_password_reset_otp_uses_configured_smtp_instead_of_log_mailer(): void
    {
        $notification = new PasswordResetOtp('123456');
        $message = $notification->toMail(new User);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertSame('smtp', $message->mailer);
    }

    public function test_password_reset_request_rejects_unknown_emails_without_sending_or_storing_an_otp(): void
    {
        Notification::fake();

        $this->followingRedirects()
            ->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'unknown@example.com'])
            ->assertOk()
            ->assertSee('Email tidak terdaftar di database.')
            ->assertDontSee('Verifikasi Kode')
            ->assertDontSee('name="otp"', false)
            ->assertSessionMissing('otp_sent')
            ->assertSessionMissing('password_reset_otp_email')
            ->assertSessionMissing('status');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'unknown@example.com']);
    }

    public function test_changing_email_clears_the_previous_otp_and_allows_requesting_an_otp_for_another_email(): void
    {
        Notification::fake();
        $previousUser = User::factory()->create(['email' => 'previous@example.com']);
        $newUser = User::factory()->create(['email' => 'new@example.com']);

        $this->post(route('password.email'), ['email' => $previousUser->email]);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $previousUser->email]);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Ganti email')
            ->assertSee('value="'.$previousUser->email.'"', false);

        $this->get(route('password.change-email'))
            ->assertRedirect(route('password.request'))
            ->assertSessionMissing('password_reset_otp_email');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $previousUser->email]);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Atur ulang kata sandi')
            ->assertDontSee('Verifikasi Kode')
            ->assertDontSee('value="'.$previousUser->email.'"', false);

        $this->post(route('password.email'), ['email' => $newUser->email])
            ->assertRedirect()
            ->assertSessionHas('otp_sent')
            ->assertSessionHas('password_reset_otp_email', $newUser->email);

        Notification::assertSentTo($newUser, PasswordResetOtp::class);
        Notification::assertSentTo($previousUser, PasswordResetOtp::class);
    }

    public function test_failed_otp_email_does_not_show_success_or_store_a_new_otp(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        Mail::shouldReceive('mailer')
            ->once()
            ->andThrow(new TransportException('SMTP connection failed'));

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('mail_error', 'Email OTP gagal dikirim. Periksa konfigurasi email server atau coba lagi nanti.')
            ->assertSessionMissing('otp_sent')
            ->assertSessionMissing('status');

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);

        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Email OTP gagal dikirim.')
            ->assertDontSee('OTP berhasil dikirim');
    }

    public function test_password_reset_rejects_an_invalid_or_expired_otp(): void
    {
        Notification::fake();
        $user = User::factory()->create([
            'email' => 'person@example.com',
            'password' => 'old-password',
        ]);

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->from(route('password.request'))
            ->post(route('password.otp'), [
                'email' => $user->email,
                'otp' => '000000',
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('otp')
            ->assertSessionHas('otp_sent');

        $otp = null;
        Notification::assertSentTo($user, PasswordResetOtp::class, function (PasswordResetOtp $notification) use (&$otp): bool {
            $otp = $notification->otp;

            return true;
        });

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->update(['created_at' => now()->subMinutes(11)]);

        $this->from(route('password.request'))
            ->post(route('password.otp'), [
                'email' => $user->email,
                'otp' => $otp,
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('otp');

        $this->assertTrue(Hash::check('old-password', $user->fresh()->password));
    }

    public function test_password_reset_rejects_invalid_tokens_and_unconfirmed_passwords(): void
    {
        $user = User::factory()->create(['email' => 'person@example.com']);

        $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertSessionHasErrors(['password']);

        $this->from(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'invalid-token', 'email' => $user->email]))
            ->assertSessionHasErrors(['email']);
    }
}
