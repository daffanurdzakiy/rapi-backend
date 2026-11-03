<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\PasswordResetOtp;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

// Controller untuk autentikasi Web: Login, Logout, dan Lupa Password menggunakan kode OTP 6 digit via Email.
class AuthController extends Controller
{
    // Menampilkan halaman form Login (jika sudah login, langsung diarahkan ke Dashboard).
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /** Authenticate a user by email and password. */
    // Memproses form Login dengan mencocokkan email dan password.
    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Jika email/password salah, kembalikan ke halaman login beserta pesan error.
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email atau password tidak benar.'])->onlyInput('email');
        }

        // Regenerasi session ID untuk keamanan setelah berhasil login.
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    // Menampilkan halaman Lupa Password (input email atau input kode OTP).
    public function requestPasswordReset(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.forgot-password', [
            'otpEmail' => session('password_reset_otp_email'),
        ]);
    }

    // Mereset sesi email OTP jika user ingin mengganti alamat email saat proses lupa password.
    public function changePasswordResetEmail(Request $request): RedirectResponse
    {
        $email = $request->session()->pull('password_reset_otp_email');

        if ($email) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        $request->session()->forget('_old_input');

        return redirect()->route('password.request');
    }

    // Mengirim kode OTP 6 angka ke email pengguna yang terdaftar.
    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            // Membuat kode OTP acak 6 digit (100000 s/d 999999).
            $otp = (string) random_int(100000, 999999);

            try {
                // Mengirim email berisi kode OTP melalui notifikasi PasswordResetOtp.
                $user->notify(new PasswordResetOtp($otp));
            } catch (TransportExceptionInterface $exception) {
                Log::error('Password reset OTP email could not be sent.', [
                    'exception' => $exception::class,
                ]);

                return back()
                    ->withInput(['email' => $data['email']])
                    ->with('mail_error', 'Email OTP gagal dikirim. Periksa konfigurasi email server atau coba lagi nanti.');
            }

            // Menyimpan hash OTP ke tabel password_reset_tokens beserta waktu pembuatannya.
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($otp), 'created_at' => now()],
            );

            $request->session()->put('password_reset_otp_email', $data['email']);

            return back()
                ->withInput(['email' => $data['email']])
                ->with('otp_sent', true)
                ->with('status', 'OTP berhasil dikirim ke email Anda. Periksa kotak masuk atau folder spam.');
        }

        $request->session()->forget('password_reset_otp_email');

        return back()
            ->withErrors(['email' => 'Email tidak terdaftar di database.'])
            ->withInput(['email' => $data['email']]);
    }

    // Memverifikasi kode OTP 6 digit yang dimasukkan oleh user.
    public function resetPasswordWithOtp(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'otp' => ['required', 'digits:6'],
        ], [
            'otp.required' => 'Masukkan kode OTP yang dikirim ke email.',
            'otp.digits' => 'Kode OTP harus terdiri dari 6 angka.',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->with('otp_sent', true)
                ->withInput($request->only('email', 'otp'));
        }

        $data = $validator->validated();
        $resetToken = Str::random(64);
        $verified = DB::transaction(function () use ($data, $resetToken): bool {
            $token = DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->lockForUpdate()
                ->first();

            // Mengecek apakah OTP valid dan belum lewat dari 10 menit (addMinutes(10)).
            if (
                ! $token
                || ! $token->created_at
                || now()->greaterThan(Carbon::parse($token->created_at)->addMinutes(10))
                || ! Hash::check($data['otp'], $token->token)
            ) {
                return false;
            }

            // Jika OTP cocok, ganti token dengan resetToken untuk dipakai di form pembuatan password baru.
            DB::table('password_reset_tokens')
                ->where('email', $data['email'])
                ->update([
                    'token' => Hash::make($resetToken),
                    'created_at' => now(),
                ]);

            return true;
        });

        if ($verified) {
            $request->session()->forget('password_reset_otp_email');

            return redirect()->route('password.reset', [
                'token' => $resetToken,
                'email' => $data['email'],
            ])->with('status', 'Kode OTP berhasil diverifikasi. Silakan buat password baru.');
        }

        return back()
            ->withErrors(['otp' => 'Kode OTP tidak valid atau sudah kedaluwarsa. Silakan minta kode baru.'])
            ->with('otp_sent', true)
            ->withInput($request->only('email'));
    }

    // Menampilkan halaman form pembuatan password baru setelah OTP terverifikasi.
    public function resetPasswordForm(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    // Menyimpan password baru ke database setelah user mengisi form Reset Password.
    public function resetPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            event(new PasswordReset($user));
        });

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', 'Password berhasil diubah. Silakan masuk dengan password baru.');
        }

        return back()->withErrors([
            'email' => 'Tautan reset password tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru.',
        ]);
    }

    /** End the active session and return the user to the login screen. */
    // Fungsi Logout: menghapus sesi login aktif dan mengarahkan kembali ke halaman login.
    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
