<?php

namespace App\Http\Middleware;

use App\Events\CommunicationChanged;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotifyCommunicationChanges
{
    public function handle(Request $r, Closure $next)
    {
        $path = $r->path();
        $notify = $r->is('communication/api*') && in_array($r->method(), ['POST', 'PATCH', 'DELETE']) && ! $r->is('communication/api/review', 'communication/api/stories/*/view');
        $conversation = null;
        $company = null;
        if ($notify && preg_match('~communication/api/(conversations|groups)/(\d+)~', $path, $m)) {
            $conversation = (int) $m[2];
        }
        if ($notify && preg_match('~communication/api/messages/(\d+)~', $path, $m)) {
            $conversation = DB::table('communication_messages')->where('id', $m[1])->value('conversation_id');
        }
        if ($r->is('communication/api/stories*')) {
            $company = preg_match('~/stories/(\d+)$~', $path, $m) ? DB::table('communication_stories')->where('id', $m[1])->value('company_id') : ($r->session()->get('judibas_admin') ? null : $r->user()?->company_id);
        }
        $before = $conversation ? $this->members($conversation) : [];
        $response = $next($r);
        if ($notify && $response->getStatusCode() < 300) {
            if ($r->is('communication/api/conversations')) {
                $conversation = $response->getData()->id ?? null;
            }
            if ($r->is('communication/api/messages/*/forward')) {
                $conversation = $r->integer('conversation_id');
            }
            $messageIds = [];
            if ($r->is('communication/api/announcements')) {
                $messageIds = $response->getData()->ids ?? [];
            } elseif ($r->method() === 'POST' && ($r->is('communication/api/conversations/*/messages') || $r->is('communication/api/messages/*/forward'))) {
                $messageIds = [$response->getData()->id];
            }
            if ($messageIds) {
                try {
                    foreach ($messageIds as $messageId) {
                        $this->publishMessage((int) $messageId);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Communication WebSocket delivery unavailable', ['exception' => get_class($e)]);
                }

                return $response;
            }
            $recipients = $conversation ? array_unique([...$before, ...$this->members($conversation)]) : User::where('is_active', true)->whereIn('id', DB::table('user_product_access')->where('product_slug', 'communication')->select('user_id'))->when($company, fn ($q) => $q->where('company_id', $company))->pluck('id')->all();
            try {
                foreach (array_chunk($recipients ?: [], 99) ?: [[]] as $chunk) {
                    event(new CommunicationChanged($chunk, $r->is('communication/api/stories*') ? 'stories' : 'workspace', $conversation));
                }
            } catch (\Throwable $e) {
                Log::warning('Communication WebSocket delivery unavailable', ['exception' => get_class($e)]);
            }
        }

        return $response;
    }

    private function publishMessage(int $id): void
    {
        $message = DB::table('communication_messages')->where('id', $id)->first();
        if (! $message) {
            return;
        }
        \App\Services\CommunicationAttachmentStorage::queue($message->attachment_path);
        $message->attachment_url = $message->attachment_path ? '/communication/api/attachments/'.$message->id : null;
        unset($message->attachment_path);
        $message->reactions = [];
        $recipients = User::where('is_active', true)->whereIn('id', $this->members($message->conversation_id))->whereIn('id', DB::table('user_product_access')->where('product_slug', 'communication')->select('user_id'))->pluck('id')->all();
        foreach (array_chunk($recipients, 99) ?: [[]] as $chunk) {
            event(new CommunicationChanged($chunk, 'message', (int) $message->conversation_id, (array) $message));
        }
    }

    private function members(int $id): array
    {
        $company = DB::table('communication_conversations')->where('id', $id)->where('kind', 'community')->value('company_id');

        return $company ? User::where('company_id', $company)->where('is_active', true)->pluck('id')->all() : DB::table('communication_members')->where('conversation_id', $id)->pluck('user_id')->all();
    }
}
