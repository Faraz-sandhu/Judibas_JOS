<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunicationOpeningTest extends TestCase
{
    use DatabaseTransactions;

    private function conversation(): array
    {
        $a = User::factory()->create(['is_active' => true]);
        $b = User::factory()->create(['is_active' => true]);
        foreach ([$a, $b] as $u) {
            DB::table('user_product_access')->insert(['user_id' => $u->id, 'product_slug' => 'communication']);
        }
        $id = DB::table('communication_conversations')->insertGetId(['kind' => 'direct', 'direct_key' => $a->id.':'.$b->id, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([$a, $b] as $u) {
            DB::table('communication_members')->insert(['conversation_id' => $id, 'user_id' => $u->id, 'read_at' => now()->subDays(2)]);
        }
        $ids = [];
        for ($i = 0; $i < 145; $i++) {
            $ids[] = DB::table('communication_messages')->insertGetId(['conversation_id' => $id, 'sender_id' => $a->id, 'sender_name' => $a->name, 'body' => 'Message '.$i, 'created_at' => $i < 70 ? now()->subDays(3) : now()->subDay(), 'updated_at' => now()]);
        }

        return [$id, $b, $ids];
    }

    public function test_opening_loads_first_unread_even_beyond_latest_fifty_and_can_load_newer_pages(): void
    {
        [$id,$user,$ids] = $this->conversation();
        $first = $this->actingAs($user)->getJson('/communication/api/conversations/'.$id.'/messages?open=1')->assertOk()->assertJsonPath('first_unread_id', $ids[70])->assertJsonPath('has_more', true)->assertJsonPath('has_newer', true)->assertJsonCount(60, 'messages');
        $this->assertContains($ids[70], array_column($first->json('messages'), 'id'));
        $this->getJson('/communication/api/conversations/'.$id.'/messages?after='.$first->json('next_after'))->assertOk()->assertJsonCount(25, 'messages')->assertJsonPath('has_newer', false)->assertJsonPath('next_after', $ids[144]);
    }

    public function test_read_conversation_opens_at_latest_page_without_unread_marker(): void
    {
        [$id,$user,$ids] = $this->conversation();
        DB::table('communication_members')->where('conversation_id', $id)->where('user_id', $user->id)->update(['read_at' => now()]);
        $this->actingAs($user)->getJson('/communication/api/conversations/'.$id.'/messages?open=1')->assertOk()->assertJsonPath('first_unread_id', null)->assertJsonPath('has_newer', false)->assertJsonCount(50, 'messages')->assertJsonPath('messages.49.id',$ids[144]);
    }
    public function test_opening_bundles_private_call_history_only_when_enabled(): void
    {
        config(['communication_calls.enabled'=>true]);[$id,$user]=$this->conversation();$peer=DB::table('communication_members')->where('conversation_id',$id)->where('user_id','!=',$user->id)->value('user_id');$call=(string)\Illuminate\Support\Str::uuid();
        DB::table('communication_calls')->insert(['id'=>$call,'conversation_id'=>$id,'caller_id'=>$peer,'callee_id'=>$user->id,'caller_seen_at'=>now(),'callee_seen_at'=>now(),'type'=>'audio','status'=>'ended','answered_at'=>now()->subMinute(),'ended_at'=>now(),'created_at'=>now()->subMinutes(2),'updated_at'=>now()]);
        $this->actingAs($user)->getJson('/communication/api/conversations/'.$id.'/messages?open=1')->assertOk()->assertJsonPath('call_history.calls.0.id',$call)->assertJsonCount(60,'messages');
        config(['communication_calls.enabled'=>false]);$this->getJson('/communication/api/conversations/'.$id.'/messages?open=1')->assertOk()->assertJsonPath('call_history',null);
    }
}
