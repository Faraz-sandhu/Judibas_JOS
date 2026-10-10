<?php

namespace App\Modules\Pms\Models;

use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    protected $hidden = ['password'];
    protected $table='pms_mail_settings';
    protected $fillable = [
        'host', 'port', 'scheme', 'username', 'password', 'from_address', 'from_name',
        'notification_emails_enabled', 'invitation_emails_enabled', 'daily_limit', 'warning_threshold',
    ];

    protected $casts = [
        'port' => 'integer',
        'password' => 'encrypted',
        'notification_emails_enabled' => 'boolean',
        'invitation_emails_enabled' => 'boolean',
        'daily_limit' => 'integer',
        'warning_threshold' => 'integer',
    ];

    public static function current(): self
    {
        return cache()->rememberForever('pms.mail.settings', fn () => self::query()->firstOrCreate([], [
            'port' => 587, 'daily_limit' => 300, 'warning_threshold' => 270,
        ]));
    }

    public function applyToConfig(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.scheme' => $this->scheme ?: 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => $this->host,
            'mail.mailers.smtp.port' => $this->port,
            'mail.mailers.smtp.username' => $this->username,
            'mail.mailers.smtp.password' => $this->password,
            'mail.from.address' => $this->from_address,
            'mail.from.name' => $this->from_name ?: config('app.name'),
        ]);
    }
}
