<?php

namespace App\Http\Controllers;

use App\Models\BrandingSetting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class BrandingSettingController extends Controller
{
    use AuthorizesRequests;

    public function edit(): View
    {
        $this->authorize('branding-settings');

        return view('dashboard.settings.branding', ['branding' => BrandingSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('branding-settings');
        $validated = $request->validate([
            'sidebar_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'sidebar_logo_text' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'sidebar_logo_text_dark' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'login_logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
            'login_logo_dark' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:3072'],
            'login_cover' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'login_cover_dark' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:5120'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp', 'max:1024'],
        ]);

        $branding = BrandingSetting::current();
        foreach (array_keys($validated) as $field) {
            if (! $request->hasFile($field)) continue;
            $oldPath = $branding->{$field};
            $branding->{$field} = $request->file($field)->store('branding', 'public');
            if ($oldPath && str_starts_with($oldPath, 'branding/')) Storage::disk('public')->delete($oldPath);
        }
        $branding->save();
        cache()->forget('branding.settings');

        return back()->with('success', 'Branding settings updated successfully.');
    }
}
