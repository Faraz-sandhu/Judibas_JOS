<?php

namespace App\Http\Controllers;

use App\Services\PortalContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PortalController extends Controller
{
    public function index(Request $request, ?string $slug = null)
    {
        if ($request->is('admin') && ! $request->session()->get('judibas_admin')) {
            return redirect('/login');
        }
        if ($request->is('login') && $request->session()->get('judibas_admin')) {
            return redirect('/admin');
        }
        if ($request->is('login') && Auth::check()) {
            return redirect('/');
        }
        $products = PortalContent::products();
        $product = $slug ? collect($products)->firstWhere('slug', $slug) : null;
        if ($slug && ! $product) {
            abort(404);
        }
        $disk = Storage::disk('local');
        $settings = $disk->exists('judibas-settings.json') ? json_decode($disk->get('judibas-settings.json'), true) : [];

        $pages = PortalContent::pages();
        $page = $pages[$request->path()] ?? null;
        if ($request->is('blog/*')) {
            $post = DB::table('blog_posts')->where('slug', $request->route('article'))->where('published', true)->first();
            abort_unless($post, 404);
            $page = ['title' => $post->title, 'intro' => $post->excerpt, 'body' => $post->body];
        }
        $logo = empty($settings['logo']) ? null : '/branding/'.basename(parse_url($settings['logo'], PHP_URL_PATH));

        return view('portal', ['portal' => [
            'name' => $settings['name'] ?? 'Judibas', 'tagline' => $settings['tagline'] ?? 'We build tools for the way our company works.', 'logo' => $logo,
            'logoIcon' => empty($settings['logo_icon']) ? null : '/branding/'.basename(parse_url($settings['logo_icon'], PHP_URL_PATH)),
            'products' => $products, 'product' => $product,
            'productsPage' => $request->is('products'),
            'page' => $page,
            'posts' => $request->is('blog') ? DB::table('blog_posts')->where('published', true)->orderByDesc('updated_at')->get() : [],
            'announcement' => PortalContent::announcement(),
            'employee' => Auth::check() ? ['name' => Auth::user()->name, 'email' => Auth::user()->email] : null,
            'loginPage' => $request->is('login'),
            'admin' => (bool) $request->session()->get('judibas_admin'), 'settingsPage' => $request->is('admin*'),
            'error' => $request->session()->get('error'), 'success' => $request->session()->get('success'),
            'validationErrors' => $request->session()->get('errors')?->all() ?? [],
        ]]);
    }

    public function article(Request $request)
    {
        return $this->index($request);
    }

    public function login(Request $request)
    {
        return app(EmployeeController::class)->login($request);
    }

    public function save(Request $request)
    {
        abort_unless($request->session()->get('judibas_admin'), 403);
        $v = $request->validate(['name' => 'required|string|max:80', 'tagline' => 'required|string|max:160', 'logo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048']);
        $disk = Storage::disk('local');
        $previous = $disk->exists('judibas-settings.json') ? json_decode($disk->get('judibas-settings.json'), true) : [];
        $settings = ['name' => $v['name'], 'tagline' => $v['tagline'], 'logo' => $previous['logo'] ?? null, 'logo_icon' => $previous['logo_icon'] ?? null];
        if ($request->hasFile('logo')) {
            $settings['logo_icon'] = null;
            $settings['logo'] = '/branding/'.basename($request->file('logo')->store('branding', 'public'));
        }
        $disk->put('judibas-settings.json', json_encode($settings, JSON_PRETTY_PRINT));

        return back()->with('success', 'Branding saved. Your website has been updated.');
    }

    public function logo(string $file)
    {
        abort_unless(preg_match('/^[a-zA-Z0-9_-]+\\.(png|jpe?g|webp)$/i', $file), 404);
        abort_unless(Storage::disk('public')->exists('branding/'.$file), 404);

        return Storage::disk('public')->response('branding/'.$file);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
