<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandingSetting extends Model
{
    protected $fillable = ['sidebar_logo', 'sidebar_logo_text', 'sidebar_logo_text_dark', 'login_logo', 'login_logo_dark', 'login_cover', 'login_cover_dark', 'favicon'];

    public static function current(): self
    {
        return cache()->rememberForever('branding.settings', fn () => self::query()->firstOrCreate([]));
    }

    public function assetUrl(string $field, string $fallback): string
    {
        return $this->{$field} ? asset('storage/'.$this->{$field}) : asset($fallback);
    }

    public function variantUrl(string $field, string $fallbackField, string $fallback): string
    {
        if ($this->{$field}) return asset('storage/'.$this->{$field});
        if ($this->{$fallbackField}) return asset('storage/'.$this->{$fallbackField});

        return asset($fallback);
    }
}
