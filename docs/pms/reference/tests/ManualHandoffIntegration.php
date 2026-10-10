<?php

use App\Http\Controllers\TaskHandoffController;
use App\Models\Department;
use App\Models\Projects;
use App\Models\Tasks;
use App\Models\User;
use App\Notifications\IssueActivityNotification;
use App\Services\EmailDeliveryService;
use App\Services\IssueNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

Notification::fake();
$email = new class extends EmailDeliveryService {
    public function send(object $recipient, Illuminate\Notifications\Notification $notification, string $type, ?App\Models\Invitation $invitation = null): array
    {
        return ['allowed' => true, 'status' => 'test', 'message' => 'Suppressed during integration test.'];
    }
};
$controller = new TaskHandoffController(new IssueNotificationService($email));

DB::beginTransaction();
try {
    $admin = User::whereHas('roles', fn ($query) => $query->where('role_key', 'admin'))->firstOrFail();
    Auth::login($admin);
    $source = Tasks::whereNotNull('project_id')->whereNotNull('department_id')->whereDoesntHave('outgoingHandoffs')->firstOrFail();
    $sourceDepartment = $source->project->departments()->firstOrFail();
    $destinationDepartment = Department::where('status', 1)->whereKeyNot($sourceDepartment->id)
        ->whereHas('users', fn ($query) => $query->where('users.status', 1)->whereKeyNot($admin->id))
        ->whereHas('projects', fn ($query) => $query->where('approval', 'approved'))->firstOrFail();

    $options = $controller->options(Request::create('/options', 'GET', [
        'source_department_id' => $sourceDepartment->id,
    ]), $source)->getData(true)['departments'];
    $assert(! collect($options)->contains('id', $sourceDepartment->id), 'Source department was offered as a destination.');
    $assert(collect($options)->contains('id', $destinationDepartment->id), 'Valid destination department was missing.');

    $sourceStatus = $source->status;
    $response = $controller->store(Request::create('/handoff', 'POST', [
        'source_department_id' => $sourceDepartment->id,
        'destination_department_id' => $destinationDepartment->id,
        'notes' => 'Continue implementation using the approved handoff specifications.',
    ]), $source);
    $payload = $response->getData(true);
    $destination = Tasks::findOrFail($payload['destination_task_id']);
    $source->refresh();

    $assert($response->getStatusCode() === 201, 'Handoff did not return HTTP 201.');
    $assert($source->status === 'completed', "Source task remained {$sourceStatus}.");
    $assert($destination->project_id === null, 'Department handoff unexpectedly selected a project.');
    $assert((int) $destination->department_id === (int) $destinationDepartment->id, 'Destination department is incorrect.');
    $assert($destination->status === 'pending' && $destination->sprint_id === null, 'Destination task is not in backlog.');
    $linkedProjectIds = $destinationDepartment->projects()->pluck('projects.id');
    $visibleOnDefaultDepartmentBoard = Tasks::whereKey($destination->id)
        ->where(fn ($query) => $query->whereIn('project_id', $linkedProjectIds)
            ->orWhere(fn ($backlog) => $backlog->whereNull('project_id')->where('department_id', $destinationDepartment->id)))
        ->exists();
    $assert($visibleOnDefaultDepartmentBoard, 'Destination task is missing from the default destination department board.');
    $assert($destination->title === $source->title && (int) $destination->priority === (int) $source->priority, 'Task details were not copied correctly.');
    $assert($destination->description === $source->description, 'Handoff metadata was incorrectly appended to the destination description.');
    $assert($source->outgoingHandoffs()->where('destination_task_id', $destination->id)->exists(), 'Handoff audit record is missing.');
    $assert((int) $source->outgoingHandoffs()->first()->source_department_id === (int) $sourceDepartment->id, 'Handoff stored the task department instead of the initiating board department.');

    $recipients = User::where('status', 1)->whereHas('departments', fn ($query) => $query->whereKey($destinationDepartment->id))->whereKeyNot($admin->id)->get();
    $assert($recipients->isNotEmpty(), 'Notification test did not find a receiving department member.');
    foreach ($recipients as $recipient) {
        Notification::assertSentTo($recipient, IssueActivityNotification::class, fn ($notification) =>
            str_contains($notification->data['url'] ?? '', 'issue='.$destination->id)
        );
    }

    $duplicateRejected = false;
    try {
        $controller->store(Request::create('/handoff', 'POST', [
            'source_department_id' => $sourceDepartment->id,
            'destination_department_id' => $destinationDepartment->id,
        ]), $source);
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        $duplicateRejected = $exception->getStatusCode() === 422;
    }
    $assert($duplicateRejected, 'Duplicate handoff was not rejected.');

    $project = Projects::where('approval', 'approved')->whereHas('departments', fn ($query) => $query->whereKey($destinationDepartment->id))->firstOrFail();
    $placeResponse = $controller->place(Request::create('/place', 'POST', ['project_id' => $project->id]), $destination);
    $destination->refresh();
    $assert($placeResponse->getStatusCode() === 200, 'Placement did not return HTTP 200.');
    $assert((int) $destination->project_id === (int) $project->id && $destination->sprint_id === null, 'Destination task was not placed in the selected project backlog.');
    $assert($destination->status === 'pending', 'Placed task did not remain pending.');

    echo json_encode([
        'result' => 'PASS',
        'source_task' => $source->id,
        'source_department' => $sourceDepartment->dept_name,
        'destination_department' => $destinationDepartment->dept_name,
        'destination_project' => $project->name,
        'notifications_checked' => $recipients->count(),
        'database_changes' => 'rolled back',
    ], JSON_PRETTY_PRINT).PHP_EOL;
} finally {
    DB::rollBack();
}
