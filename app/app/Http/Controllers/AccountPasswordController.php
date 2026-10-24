<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('changePassword', $request->user());

        return view('account.password');
    }

    public function update(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        $user = DB::transaction(function () use ($request): User {
            $user = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            Gate::authorize('changePassword', $user);
            if (! Hash::check($request->validated('current_password'), $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }
            $user->password = $request->validated('password');
            $user->must_change_password = false;
            $user->remember_token = Str::random(60);
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
            $user->tokens()->delete();

            return $user;
        });
        Auth::guard('web')->setUser($user);
        $request->session()->regenerate(true);

        return redirect()->route('dashboard')->with('status', 'Password updated. Other sessions have been signed out.');
    }
}
