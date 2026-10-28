<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the admin panel login. Safe to run on a live database:
 *   php artisan db:seed --class=AdminUserSeeder
 *
 * No password is written in this file (anyone who saw the code would know it):
 * - ADMIN_PASSWORD set in .env  -> the admin password is set to it (also a way to reset a forgotten password;
 *                                  remove it from .env again afterwards)
 * - not set, admin doesn't exist -> a random password is created and shown once in the terminal
 * - not set, admin exists        -> the password is left unchanged
 */
class AdminUserSeeder extends Seeder
{
    public function run()
    {
        $user = User::firstOrNew(['username' => 'nikhilbhatt']);
        $user->fill([
            'name' => $user->name ?: 'Administrator',
            'email' => $user->email ?: 'nikhilbhatt@investmentpoint.in',
            'role' => 'admin',
        ]);

        $password = env('ADMIN_PASSWORD');
        if ($password) {
            $user->password = Hash::make($password);
            $this->command?->info('Admin password set from ADMIN_PASSWORD. Remove ADMIN_PASSWORD from .env now.');
        } elseif (!$user->exists) {
            $password = Str::random(12) . '@' . random_int(10, 99) . 'Aa'; // letters, number and symbol
            $user->password = Hash::make($password);
            $this->command?->warn("Admin created - username: nikhilbhatt  password: {$password}  (save it now, it is not shown again; change it in Admin -> My Account)");
        }
        $user->save();

        User::where('username', 'admin')->where('username', '!=', 'nikhilbhatt')->delete();
    }
}
