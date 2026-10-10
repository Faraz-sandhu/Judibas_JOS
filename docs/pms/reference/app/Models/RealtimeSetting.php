<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeSetting extends Model
{
    protected $fillable = ['enabled', 'app_id', 'app_key', 'app_secret', 'cluster'];

    protected $casts = [
        'enabled' => 'boolean',
        'app_secret' => 'encrypted',
    ];

    public static function current(): self
    {
        return cache()->rememberForever('realtime.settings', fn () => self::query()->firstOrCreate([], [
            'enabled' => config('broadcasting.default') === 'pusher',
        ]));
    }

    public function effectiveAppId(): ?string
    {
        return $this->app_id ?: config('broadcasting.connections.pusher.app_id');
    }

    public function effectiveKey(): ?string
    {
        return $this->app_key ?: config('broadcasting.connections.pusher.key');
    }

    public function effectiveSecret(): ?string
    {
        return $this->app_secret ?: config('broadcasting.connections.pusher.secret');
    }

    public function effectiveCluster(): ?string
    {
        return $this->cluster ?: config('broadcasting.connections.pusher.options.cluster');
    }

    public function isComplete(): bool
    {
        return filled($this->effectiveAppId())
            && filled($this->effectiveKey())
            && filled($this->effectiveSecret())
            && filled($this->effectiveCluster());
    }

    public function applyToConfig(): void
    {
        config([
            'broadcasting.default' => $this->enabled ? 'pusher' : 'null',
            'broadcasting.connections.pusher.app_id' => $this->effectiveAppId(),
            'broadcasting.connections.pusher.key' => $this->effectiveKey(),
            'broadcasting.connections.pusher.secret' => $this->effectiveSecret(),
            'broadcasting.connections.pusher.options.cluster' => $this->effectiveCluster(),
        ]);
    }
}
