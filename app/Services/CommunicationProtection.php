<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunicationProtection
{
    public static function participant(Request $r, object $conversation): bool
    {
        if ($r->filled('view_user') || $r->input('mode') === 'review') return false;
        return $conversation->kind === 'direct' && $conversation->super_participant &&
            (CommunicationAccess::personal($r) || (!CommunicationAccess::super($r) && DB::table('communication_members')->where('conversation_id', $conversation->id)->where('user_id', auth()->id())->exists()));
    }

    public static function validateEnvelope(array $value): void
    {
        foreach (['iv' => [12, 12], 'ciphertext' => [16, 100000]] as $key => [$min, $max]) {
            $decoded = base64_decode($value[$key] ?? '', true);
            abort_unless($decoded !== false && strlen($decoded) >= $min && strlen($decoded) <= $max, 422, 'Invalid encrypted message envelope.');
        }
    }
}
