<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
class CommunicationManagementNavigationTest extends TestCase {
 use DatabaseTransactions;
 public function test_old_management_links_redirect_to_central_administration():void {
  $this->withSession(['judibas_admin'=>true]);
  foreach(['users'=>'users','invitations'=>'invitations'] as $view=>$tab)$this->get('/products/communication?manage='.$view)->assertRedirect('/admin#'.$tab);
  $this->get('/products/communication?manage=communities')->assertRedirect('/admin#communication');
  $this->get('/products/communication?manage=overview')->assertRedirect('/products/communication');
  $this->withoutVite()->get('/products/communication')->assertOk();
 }
}
