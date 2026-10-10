<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    protected $fillable = [
        'sender_id',
        'recipient_id',
        'invitee_email',
        'invitee_name',
        'invitable_type',
        'invitable_id',
        'role',
        'purpose',
        'role_id',
        'department_id',
        'token',
        'expires_at',
        'accepted_at',
        'declined_at',
        'delivery_status',
        'emailed_at',
        'email_error',
    ];


    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];



    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function invitable()
    {
        return $this->morphTo();
    }

    public function assignedRole()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function isEmployeeOnboarding(): bool
    {
        return $this->purpose === 'employee_onboarding';
    }

    public function isExpired()
    {
        // Ensure we have a Carbon instance
        if (!$this->expires_at instanceof \Carbon\Carbon) {
            $this->expires_at = \Carbon\Carbon::parse($this->expires_at);
        }

        return $this->expires_at->isPast();
    }

    public function isPending()
    {
        return is_null($this->accepted_at) && is_null($this->declined_at) && !$this->isExpired();
    }

    public function getStatusAttribute()
    {
        if ($this->accepted_at) {
            return 'Accepted';
        }

        if ($this->declined_at) {
            return 'Declined';
        }

        if ($this->isExpired()) {
            return 'Expired';
        }

        return 'Pending';
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'Accepted' => 'label-success',
            'Declined' => 'label-danger',
            'Expired' => 'label-warning',
            default => 'label-primary',
        };
    }

    public function scopePending($query)
    {
        return $query->whereNull('accepted_at')
            ->whereNull('declined_at')
            ->where('expires_at', '>', now());
    }

    public function getRecipientDisplayAttribute(): string
    {
        return $this->recipient?->name ?: ($this->invitee_name ?: $this->invitee_email ?: 'Invited user');
    }
}
