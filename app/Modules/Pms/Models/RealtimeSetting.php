<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class RealtimeSetting extends Model
{
    protected $hidden = ['app_secret'];
    protected $table='pms_realtime_settings';
    protected $fillable = ['enabled', 'app_id', 'app_key', 'app_secret', 'cluster'];

    protected $casts = [
        'enabled' => 'boolean',
        'app_secret' => 'encrypted',
    ];

    public static function current(): self
    {
        return cache()->rememberForever('pms.realtime.settings', fn () => self::query()->firstOrCreate([], [
            'enabled' => in_array(config('broadcasting.default'), ['pusher','reverb'], true),
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

    public function useReverb():bool {return blank($this->app_id)&&blank($this->app_key)&&blank($this->app_secret);}
}
