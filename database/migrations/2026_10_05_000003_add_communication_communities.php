<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_companies', function (Blueprint $t) {
            $t->id();
            $t->string('name', 100)->unique();
            $t->timestamps();
        });
        $default = DB::table('communication_companies')->insertGetId(['name' => 'Judibass', 'created_at' => now(), 'updated_at' => now()]);
        Schema::table('users', function (Blueprint $t) use ($default) {
            $t->foreignId('company_id')->default($default)->constrained('communication_companies');
            $t->boolean('communication_admin')->default(false);
        });
        Schema::table('communication_teams', function (Blueprint $t) use ($default) {
            $t->foreignId('company_id')->default($default)->constrained('communication_companies');
        });
        Schema::table('communication_conversations', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->constrained('communication_companies');
            $t->index(['company_id', 'kind']);
        });
        DB::table('communication_conversations')->whereIn('kind', ['group', 'channel'])->update(['company_id' => $default]);
        foreach (DB::table('communication_conversations')->where('kind', 'channel')->get() as $c) {
            foreach (DB::table('communication_team_members')->where('team_id', $c->team_id)->pluck('user_id') as $uid) {
                DB::table('communication_members')->updateOrInsert(['conversation_id' => $c->id, 'user_id' => $uid], []);
            }
        }
        DB::table('communication_conversations')->where('kind', 'channel')->update(['kind' => 'group', 'team_id' => null]);
        DB::table('communication_conversations')->insert(['kind' => 'community', 'name' => 'Judibass announcements', 'company_id' => $default, 'created_at' => now(), 'updated_at' => now()]);
        Schema::create('communication_reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('message_id')->constrained('communication_messages')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('emoji', 16);
            $t->unique(['message_id', 'user_id']);
        });
        Schema::create('communication_invitations', function (Blueprint $t) {
            $t->id();
            $t->string('email', 254);
            $t->foreignId('company_id')->constrained('communication_companies');
            $t->foreignId('inviter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->boolean('invited_by_super')->default(false);
            $t->boolean('communication_admin')->default(false);
            $t->text('group_ids');
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->string('delivery_status', 20)->default('pending');
            $t->timestamps();
            $t->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_invitations');
        Schema::dropIfExists('communication_reactions');
        DB::table('communication_conversations')->where('kind', 'community')->delete();
        Schema::table('communication_conversations', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropIndex(['company_id', 'kind']);
            $t->dropColumn('company_id');
        });
        Schema::table('communication_teams', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropColumn('company_id');
        });
        Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['company_id']);
            $t->dropColumn(['company_id', 'communication_admin']);
        });
        Schema::dropIfExists('communication_companies');
    }
};
