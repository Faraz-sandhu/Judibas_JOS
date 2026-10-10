<?php
namespace App\Services;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
class CommunicationMessagePolicy
{
    public static function enforce(Request $request, ?string $body): void
    {
        if (CommunicationAccess::super($request) || !$body) return;
        $text = strtr($body, array_combine(
            preg_split('//u', '٠١٢٣٤٥٦٧٨٩۰۱۲۳۴۵۶۷۸۹０１２３４５６７８９', -1, PREG_SPLIT_NO_EMPTY),
            str_split(str_repeat('0123456789', 3))
        ));
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', $text);
        preg_match_all('/(?<![0-9])\+?[0-9](?:[\s().-]*[0-9]){6,14}(?![0-9])/u', $text, $matches);
        foreach ($matches[0] as $candidate) {
            $candidate = trim($candidate);
            if (preg_match('/^(?:[0-9]{4}-[0-9]{2}-[0-9]{2}|[0-9]{2}-[0-9]{2}-[0-9]{4})$/', $candidate)) continue;
            if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) continue;
            throw ValidationException::withMessages(['body' => 'Only Super Admin can share phone numbers in chats. Remove the phone number and try again.']);
        }
    }
}
