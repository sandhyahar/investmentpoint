<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Forgotten admin-panel password: asks for a new one (typed hidden, twice) and saves it, and lifts the
 * login lock for that username. php artisan admin:password nikhilbhatt
 */
class ResetAdminPassword extends Command
{
    protected $signature = 'admin:password {username : the admin-panel login to reset}';

    protected $description = 'Set a new password for an admin-panel login (asks for it) and unlock its login';

    public function handle(): int
    {
        $user = User::where('username', $this->argument('username'))->whereIn('role', ['admin', 'staff'])->first();
        if (!$user) {
            $this->error('No admin-panel login with that username. Logins: ' . User::whereIn('role', ['admin', 'staff'])->pluck('username')->implode(', '));

            return self::FAILURE;
        }

        $password = (string) $this->secret('New password (at least 10 characters, upper- and lower-case, a number and a symbol)');
        if ($password !== (string) $this->secret('Repeat the new password')) {
            $this->error('The two passwords are different. Nothing was changed.');

            return self::FAILURE;
        }
        $check = Validator::make(['password' => $password], ['password' => [Password::min(10)->mixedCase()->numbers()->symbols()]]);
        if ($check->fails()) {
            $this->error($check->errors()->first('password') . ' Nothing was changed.');

            return self::FAILURE;
        }

        $user->forceFill(['password' => Hash::make($password), 'must_change_password' => false])->save();
        // The login lock is per username + IP: lift it for this computer (local addresses)
        foreach (['127.0.0.1', '::1'] as $ip) {
            RateLimiter::clear('admin-login|' . strtolower($user->username) . '|' . $ip);
            RateLimiter::clear('admin-login-ip|' . $ip);
        }
        $this->info("New password saved for {$user->username}. Other devices signed in to this login are signed out.");

        return self::SUCCESS;
    }
}
