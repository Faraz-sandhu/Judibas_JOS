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
        $v = $r->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:200', 'redirect_to' => ['sometimes', 'string', 'max:2048', 'regex:~^/products(?:\?[^\r\n#]*)?$~']]);
        if ($r->expectsJson() && isset($v['redirect_to'])) {
            $r->session()->put('url.intended', $v['redirect_to']);
        }
        $email = strtolower(trim($v['email']));
        if ($email === strtolower((string) config('judibas.admin_email'))) {
            $hash = config('judibas.admin_password_hash');
            if (! $hash || ! Hash::check($v['password'], $hash)) {
                return $this->loginFailed($r);
            }
            $intended = $r->session()->get('url.intended');
            Auth::logout();
            $r->session()->invalidate();
            $r->session()->regenerateToken();
            $r->session()->put('judibas_admin', true);
            if ($intended) {
                $r->session()->put('url.intended', $intended);
            }

            return $this->loginComplete($r, '/admin');
        }
        $u = User::where('email', $email)->first();
        if (! $u || ! $u->is_active || ! Hash::check($v['password'], $u->password)) {
            return $this->loginFailed($r);
        }
        $r->session()->forget('judibas_admin');
        Auth::login($u);
        $r->session()->regenerate();

        if(\App\Services\PlatformAccess::administrator($r))return $this->loginComplete($r, '/admin');
        return $this->loginComplete($r, DB::table('user_product_access')->where('user_id', Auth::id())->where('product_slug', 'communication')->exists() ? '/communication' : '/');
    }

    private function loginFailed(Request $r)
    {
        $message = 'The email or password is incorrect, or your account is inactive.';

        return $r->expectsJson() ? response()->json(['message' => $message], 422) : back()->with('error', $message);
    }

    private function loginComplete(Request $r, string $fallback)
    {
        if ($r->expectsJson()) {
            return response()->json(['redirect' => $r->session()->pull('url.intended', $fallback)]);
        }

        return redirect()->intended($fallback);
    }

    public function workspace(Request $r, string $slug)
    {
        abort_unless(Auth::check() && Auth::user()->is_active, 403);
        abort_unless(($r->user()->is_active&&$r->user()->judibas_administrator)||DB::table('user_product_access')->where('user_id',Auth::id())->where('product_slug',$slug)->exists(),403);

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
