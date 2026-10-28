<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * "My Account": the Super Admin changes their OWN password here: only the new one, twice. The current password is
 * not asked for (the owner's choice, 12 and 22 Oct 2026): being signed in is enough.
 * Team users (User & Role Management) cannot change theirs: they use the password the Super Admin gave them, and
 * only the Super Admin changes it, in User & Role Management (owner, 23 Oct 2026).
 */
class AccountController extends Controller
{
    public function edit()
    {
        return view('admin.account.edit', ['user' => Auth::user()]);
    }

    public function updatePassword(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Your password is set by the Super Admin.');

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
        ]);

        // Saving the new password signs out every other browser/device using this account: the "auth.session"
        // middleware logs out sessions that still hold the old password's hash, and gives this one the new hash.
        // (Not Auth::logoutOtherDevices(): that only re-saves the same password.)
        $request->user()->forceFill(['password' => Hash::make($validated['password']), 'must_change_password' => false])->save();
        $request->session()->regenerate();
        // warning level: the live site only keeps warnings and errors, and this change should leave a trace
        Log::warning('Admin password changed', ['username' => Auth::user()->username, 'ip' => $request->ip()]);

        return redirect()->route('admin.account.edit')->with('success', 'Password changed. Any other device signed in to this account has been logged out.');
    }
}
