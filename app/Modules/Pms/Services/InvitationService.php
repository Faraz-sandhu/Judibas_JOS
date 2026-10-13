<?php


// app/Services/InvitationService.php
namespace App\Modules\Pms\Services;

use App\Modules\Pms\Models\Invitation;
use App\Modules\Pms\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InvitationService
{
    public function sendInvitation(User $sender, User $recipient, Model $invitable, string $role = null, int $expiryDays = 7)
    {
        // Check if user can send invitation (using your permission system)
        if (!$sender->can('project-assign', $invitable)) {
            throw new \Exception('User not authorized to send invitations');
        }

        return Invitation::create([
            'sender_id' => $sender->id,
            'recipient_id' => $recipient->id,
            'invitable_type' => get_class($invitable),
            'invitable_id' => $invitable->id,
            'role' => $role,
            'token' => Str::random(32),
            'expires_at' => now()->addDays(7),
        ]);
    }
    

    
public function acceptInvitation(Invitation $invitation)
    {
        if (!$invitation->isPending()) {
            Log::warning('Invitation is not pending', ['invitation_id' => $invitation->id]);
            return false;
        }

        try {
            DB::transaction(function () use ($invitation) {
                // The recipient may not yet have task visibility. Resolve only the signed invitation's target.
                $types=[\App\Modules\Pms\Models\Projects::class,\App\Modules\Pms\Models\Tasks::class,\App\Modules\Pms\Models\Subtasks::class];
                if(!in_array($invitation->invitable_type,$types,true))throw new \Exception('Invalid invitation target.');
                $target=$invitation->invitable_type::withoutGlobalScope('visibility')->findOrFail($invitation->invitable_id);
                $project=$target instanceof \App\Modules\Pms\Models\Projects?$target:($target instanceof \App\Modules\Pms\Models\Tasks?\App\Modules\Pms\Models\Projects::withoutGlobalScope('visibility')->find($target->project_id):\App\Modules\Pms\Models\Projects::withoutGlobalScope('visibility')->find(\App\Modules\Pms\Models\Tasks::withoutGlobalScope('visibility')->findOrFail($target->task_id)->project_id));
                if($project?->company_id){$platform=DB::table('pms_companies')->where('id',$project->company_id)->value('platform_company_id');$recipient=User::withoutGlobalScopes()->findOrFail($invitation->recipient_id);if(!$recipient->judibas_administrator&&!$recipient->hasRole('admin')&&$recipient->company_id!=$platform&&!DB::table('communication_company_memberships')->where('company_id',$platform)->where('user_id',$recipient->id)->exists())throw new \Exception('The recipient needs access to this company first.');}
                if($target instanceof \App\Modules\Pms\Models\Tasks)$target->setRelation('project',$project);
                if($target instanceof \App\Modules\Pms\Models\Subtasks){$parent=\App\Modules\Pms\Models\Tasks::withoutGlobalScope('visibility')->findOrFail($target->task_id);$parent->setRelation('project',$project);$target->setRelation('task',$parent);}
                $invitation->setRelation('invitable',$target);
                // Mark invitation as accepted
                $invitation->update(['accepted_at' => now()]);

                // Ensure invitable is loaded
                if (!$invitation->invitable) {
                    Log::error('Invitable model not loaded', [
                        'invitation_id' => $invitation->id,
                        'invitable_type' => $invitation->invitable_type,
                        'invitable_id' => $invitation->invitable_id,
                    ]);
                    throw new \Exception('Invitable model not loaded');
                }

                switch ($invitation->invitable_type) {
                    case 'App\Modules\Pms\Models\Projects':
                        $invitation->invitable->users()->syncWithoutDetaching([
                            $invitation->recipient_id => ['assigned_by' => $invitation->sender_id],
                        ]);
                        Log::info('User assigned to project', [
                            'user_id' => $invitation->recipient_id,
                            'project_id' => $invitation->invitable->id,
                        ]);
                        break;

                    case 'App\Modules\Pms\Models\Tasks':
                        // Invitations add another collaborator without removing
                        // employees who are already working on this task.
                        $invitation->invitable->users()->syncWithoutDetaching([
                            $invitation->recipient_id => ['assigned_by' => $invitation->sender_id],
                        ]);
                        Log::info('User assigned to task', [
                            'user_id' => $invitation->recipient_id,
                            'task_id' => $invitation->invitable->id,
                        ]);

                        // Ensure user is assigned to parent project
                        if ($invitation->invitable->project && !$invitation->invitable->project->users()->where('user_id', $invitation->recipient_id)->exists()) {
                            $invitation->invitable->project->users()->syncWithoutDetaching([
                                $invitation->recipient_id => ['assigned_by' => $invitation->sender_id],
                            ]);
                            Log::info('User assigned to parent project', [
                                'user_id' => $invitation->recipient_id,
                                'project_id' => $invitation->invitable->project->id,
                            ]);
                        }
                        break;

                    case 'App\Modules\Pms\Models\Subtasks':
                        $invitation->invitable->users()->syncWithoutDetaching([
                            $invitation->recipient_id => ['assigned_by' => $invitation->sender_id],
                        ]);
                        Log::info('User assigned to subtask', [
                            'user_id' => $invitation->recipient_id,
                            'subtask_id' => $invitation->invitable->id, // Fixed missing quote and key name
                        ]);

                        // Ensure user is assigned to parent task
                        if (!$invitation->invitable->task->users()->where('user_id', $invitation->recipient_id)->exists()) {
                            $invitation->invitable->task->users()->syncWithoutDetaching([
                                $invitation->recipient_id => ['assigned_by' => $invitation->sender_id],
                            ]);
                            Log::info('User assigned to parent task', [
                                'user_id' => $invitation->recipient_id,
                                'task_id' => $invitation->invitable->task->id,
                            ]);
                        }

                        // Ensure user is assigned to parent project
                        if ($invitation->invitable->task->project && !$invitation->invitable->task->project->users()->where('user_id', $invitation->recipient_id)->exists()) {
                            $invitation->invitable->task->project->users()->syncWithoutDetaching([
                                $invitation->recipient_id => ['assigned_by' => $invitation->sender_id],
                            ]);
                            Log::info('User assigned to parent project', [
                                'user_id' => $invitation->recipient_id,
                                'project_id' => $invitation->invitable->task->project->id,
                            ]);
                        }
                        break;

                    default:
                        Log::error('Unknown invitable_type', [
                            'invitation_id' => $invitation->id,
                            'invitable_type' => $invitation->invitable_type,
                        ]);
                        throw new \Exception('Invalid invitable type');
                }
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to accept invitation', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }
    
   

    public function declineInvitation(Invitation $invitation)
    {
        if (!$invitation->isPending()) {
            return false;
        }

        $invitation->update(['declined_at' => now()]);
        return true;
    }
}
