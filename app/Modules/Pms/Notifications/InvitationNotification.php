<?php
namespace App\Modules\Pms\Notifications;
use Illuminate\Notifications\Notification;
class InvitationNotification extends Notification {
 public function __construct(public $invitation){}
 public function via($user){return ['database','broadcast'];}
 public function toArray($user){return ['subject'=>'Workspace invitation','message'=>'You have been invited to '.($this->invitation->invitable->name??$this->invitation->invitable->title??'a project'),'url'=>url('/pms?section=invitations'),'invitation_id'=>$this->invitation->id];}
}
