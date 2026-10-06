<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:200']);
        $email = strtolower(trim($v['email']));
        if ($email === strtolower((string) config('judibas.admin_email'))) {
            $hash = config('judibas.admin_password_hash');
            if (! $hash || ! Hash::check($v['password'], $hash)) {
                return back()->with('error', 'The email or password is incorrect, or your account is inactive.');
            }
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();
            $r->session()->put('judibas_admin', true);

            return redirect('/admin');
        }
        $u = User::where('email', $email)->first();
        if (! $u || ! $u->is_active || ! Hash::check($v['password'], $u->password)) {
            return back()->with('error', 'The email or password is incorrect, or your account is inactive.');
        }
        $r->session()->forget('judibas_admin');
        Auth::login($u);
        $r->session()->regenerate();

        return redirect(DB::table('user_product_access')->where('user_id', Auth::id())->where('product_slug', 'communication')->exists() ? '/communication' : '/');
    }

    public function workspace(Request $r, string $slug)
    {
        abort_unless(Auth::check() && Auth::user()->is_active, 403);
        abort_unless(DB::table('user_product_access')->where('user_id', Auth::id())->where('product_slug', $slug)->exists(), 403);

        if ($slug === 'communication') {
            return redirect('/communication');
        }

        return app(PortalController::class)->index($r, $slug);
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }
}
