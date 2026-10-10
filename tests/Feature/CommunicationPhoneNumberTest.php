<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
class CommunicationPhoneNumberTest extends TestCase
{
 use DatabaseTransactions;
 private function employee(bool $admin=false):User {$u=User::factory()->create(['is_active'=>true,'communication_admin'=>$admin]);DB::table('user_product_access')->insert(['user_id'=>$u->id,'product_slug'=>'communication']);return $u;}
 public function test_employee_and_delegated_admin_cannot_send_edit_or_forward_numbers():void {
  Event::fake();$a=$this->employee();$b=$this->employee(true);$chat=$this->actingAs($a)->postJson('/communication/api/conversations',['kind'=>'direct','member_ids'=>[$b->id]])->assertOk()->json('id');
  foreach(['03001234567','+92 300 123 4567','(212) 555-0123','+44 20 7946 0958','tel:5551234','wa.me/923001234567',"\u{0660}\u{0663}\u{0660}\u{0660}\u{0661}\u{0662}\u{0663}\u{0664}\u{0665}\u{0666}\u{0667}","0300\u{200B}1234567"]as $number){$this->postJson('/communication/api/conversations/'.$chat.'/messages',['body'=>'Contact '.$number])->assertUnprocessable()->assertJsonValidationErrors('body');}
  $id=$this->postJson('/communication/api/conversations/'.$chat.'/messages',['body'=>'Meeting on 2026-10-09, budget 1500, server 192.168.10.69'])->assertCreated()->json('id');
  $this->patchJson('/communication/api/messages/'.$id,['body'=>'Call 03001234567'])->assertUnprocessable()->assertJsonValidationErrors('body');
  $this->actingAs($b)->postJson('/communication/api/conversations/'.$chat.'/messages',['body'=>'03001234567'])->assertUnprocessable();
  $old=DB::table('communication_messages')->insertGetId(['conversation_id'=>$chat,'sender_id'=>$a->id,'sender_name'=>$a->name,'body'=>'Legacy phone 03001234567','created_at'=>now(),'updated_at'=>now()]);
  $this->postJson('/communication/api/messages/'.$old.'/forward',['conversation_id'=>$chat])->assertUnprocessable()->assertJsonValidationErrors('body');
 }
 public function test_super_admin_can_send_edit_and_forward_numbers():void {
  Event::fake();$this->withSession(['judibas_admin'=>true]);$chat=DB::table('communication_conversations')->insertGetId(['kind'=>'group','name'=>'Admin phone policy','created_at'=>now(),'updated_at'=>now()]);
  $id=$this->postJson('/communication/api/conversations/'.$chat.'/messages',['body'=>'03001234567'])->assertCreated()->json('id');
  $this->patchJson('/communication/api/messages/'.$id,['body'=>'+92 300 123 4567'])->assertOk();
  $this->postJson('/communication/api/messages/'.$id.'/forward',['conversation_id'=>$chat])->assertCreated();
 }
}
