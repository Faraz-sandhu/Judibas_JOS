<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CvBankCareersSettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function settings(array $overrides = []): array
    {
        return array_merge(['theme'=>'editorial','headline'=>'Join our next chapter','intro'=>'Build thoughtful products with our team.'], $overrides);
    }

    private function employee(): User
    {
        $user=User::factory()->create(['is_active'=>true,'judibas_administrator'=>false]);
        DB::table('user_product_access')->insert(['user_id'=>$user->id,'product_slug'=>'cv-bank']);
        return $user;
    }

    public function test_public_visitors_cannot_publish_or_preview_unpublished_themes(): void
    {
        $this->postJson('/cv-bank/settings', $this->settings())->assertForbidden();
        $this->get('/careers?theme=editorial')->assertForbidden();
        $this->get('/careers')->assertOk()->assertSee('Make your introduction');
    }

    public function test_all_six_designs_can_be_previewed_without_changing_the_published_theme(): void
    {
        $before=DB::table('cv_bank_settings')->find(1);
        foreach(config('cv-bank.themes') as $theme) {
            $this->withSession(['judibas_admin'=>true])->get('/careers?theme='.$theme['id'])
                ->assertOk()->assertSee('theme-'.$theme['id'],false)->assertSee('Design preview');
        }
        $this->assertEquals($before, DB::table('cv_bank_settings')->find(1));
    }

    public function test_each_design_renders_its_own_page_structure(): void
    {
        $layouts = [
            'studio'=>'studio-hero',
            'editorial'=>'editorial-lede',
            'midnight'=>'spotlight-hero',
            'minimal'=>'minimal-workspace',
            'atlas'=>'atlas-directory',
            'bloom'=>'bloom-hero',
        ];
        foreach($layouts as $theme=>$marker) {
            $this->withSession(['judibas_admin'=>true])->get('/careers?theme='.$theme)->assertOk()->assertSee($marker,false);
        }
    }

    public function test_admin_can_publish_and_delegate_but_cannot_assign_an_employee_without_product_access(): void
    {
        $editor=$this->employee();
        $other=User::factory()->create(['is_active'=>true]);
        $this->withSession(['judibas_admin'=>true])->postJson('/cv-bank/settings', $this->settings(['career_editors'=>[$editor->id]]))->assertOk();
        $this->assertDatabaseHas('cv_bank_career_editors',['user_id'=>$editor->id]);
        $this->get('/careers')->assertOk()->assertSee('theme-editorial',false)->assertSee('Join our next chapter');
        $this->postJson('/cv-bank/settings', $this->settings(['career_editors'=>[$other->id]]))->assertUnprocessable();
        $this->assertDatabaseHas('cv_bank_career_editors',['user_id'=>$editor->id]);
    }

    public function test_delegated_editor_can_publish_but_cannot_grant_permission(): void
    {
        $user=$this->employee();
        $this->actingAs($user)->postJson('/cv-bank/settings',$this->settings())->assertForbidden();
        DB::table('cv_bank_career_editors')->insert(['user_id'=>$user->id]);
        $this->postJson('/cv-bank/settings',$this->settings(['theme'=>'bloom']))->assertOk();
        $this->get('/careers?theme=studio')->assertOk();
        $this->postJson('/cv-bank/settings',$this->settings(['career_editors'=>[]]))->assertForbidden();
        $this->assertDatabaseHas('cv_bank_career_editors',['user_id'=>$user->id]);
        DB::table('user_product_access')->where('user_id',$user->id)->delete();
        $this->postJson('/cv-bank/settings',$this->settings())->assertForbidden();
        $this->get('/careers?theme=studio')->assertForbidden();
    }

    public function test_invalid_theme_is_rejected_and_career_text_is_escaped(): void
    {
        $this->withSession(['judibas_admin'=>true])->postJson('/cv-bank/settings',$this->settings(['theme'=>'missing']))->assertUnprocessable();
        $headline='<script>alert("test")</script>';
        $this->postJson('/cv-bank/settings',$this->settings(['headline'=>$headline]))->assertOk();
        $this->get('/careers')->assertOk()->assertSee($headline)->assertDontSee($headline,false);
    }

    public function test_header_and_footer_saves_do_not_overwrite_the_theme_or_page_content(): void
    {
        $this->withSession(['judibas_admin'=>true])->postJson('/cv-bank/settings',$this->settings())->assertOk();
        $this->postJson('/cv-bank/settings',[
            'header_brand'=>'Example Careers','header_brand_url'=>'https://example.test',
            'header_label'=>'Our team','header_roles_label'=>'Find a role',
            'header_apply_label'=>'Apply today','header_show_roles'=>false,'header_show_apply'=>true,
        ])->assertOk()->assertJsonPath('settings.theme','editorial')->assertJsonPath('settings.headline','Join our next chapter');
        $this->postJson('/cv-bank/settings',[
            'footer_brand'=>'Example','footer_message'=>'Make a difference.',
            'footer_copyright'=>'Copyright {year} Example',
            'footer_link_label'=>'Privacy','footer_link_url'=>'/privacy','footer_show'=>true,
        ])->assertOk()->assertJsonPath('settings.header_brand','Example Careers');
        foreach(config('cv-bank.themes') as $theme) {
            $this->get('/careers?theme='.$theme['id'])->assertOk()
                ->assertSee('Example Careers')->assertSee('Apply today')->assertDontSee('Find a role')
                ->assertSee('Make a difference.')->assertSee('Copyright '.date('Y').' Example')->assertSee('href="/privacy"',false);
        }
        $this->postJson('/cv-bank/settings',['footer_show'=>false])->assertOk();
        $this->get('/careers')->assertOk()->assertDontSee('class="career-footer"',false);
    }

    public function test_header_and_footer_links_reject_unsafe_destinations(): void
    {
        foreach(['javascript:alert(1)','//example.test','/\\example.test','data:text/html,test'] as $url) {
            $this->withSession(['judibas_admin'=>true])->postJson('/cv-bank/settings',['header_brand_url'=>$url])->assertUnprocessable();
            $this->postJson('/cv-bank/settings',['footer_link_label'=>'Link','footer_link_url'=>$url])->assertUnprocessable();
        }
        $this->postJson('/cv-bank/settings',['footer_link_label'=>'Privacy','footer_link_url'=>null])->assertUnprocessable();
    }

    public function test_delegated_editor_can_edit_page_sections_without_changing_access(): void
    {
        $user=$this->employee();
        DB::table('cv_bank_career_editors')->insert(['user_id'=>$user->id]);
        $this->actingAs($user)->postJson('/cv-bank/settings',['header_brand'=>'HR Careers'])->assertOk();
        $this->postJson('/cv-bank/settings',['jobs_heading'=>'Join a team that cares'])->assertOk();
        $this->get('/careers')->assertOk()->assertSee('HR Careers')->assertSee('Join a team that cares');
        $this->assertDatabaseHas('cv_bank_career_editors',['user_id'=>$user->id]);
    }
}
