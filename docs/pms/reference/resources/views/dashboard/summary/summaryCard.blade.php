@foreach ($summary as $item)
    @php
        $user = App\Models\User::find($item->user_id)->name;
        $project = App\Models\Projects::find($item->project_id)->name;
        $tasks = json_decode($item->task, true) ?? [];
    @endphp
    <div class="row">
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5 class="card-title">Project Name: {{ $project }}</h5>
                        </div>
                        <div class="col-md-4 text-center">
                            <span class="text-muted">Employee: {{ $user }}</span>
                        </div>
                        <div class="col-md-4 text-end">
                            <p class="text-muted">Date: {{ $item->date }}</p>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        @if (!empty($tasks))
                            @foreach ($tasks as $index => $task)
                                <li class="list-group-item">
                                    <div class="row mb-2">
                                        <div class="col-md-7">
                                            <strong>Task {{ $index + 1 }}:</strong>
                                            <span>{{ $task['task'] }}</span>
                                        </div>
                                        <div class="col-md-2">
                                            <span class="badge bg-label-primary text-uppercase">Completed</span>
                                        </div>
                                        <div class="col-md-3">
                                            <span class="badge bg-label-primary">{{ $task['invest_time'] ?? '0 minutes' }}</span>
                                        </div>
                                    </div>

                                    @if (!empty($task['subtasks']))
                                        <ul class="list-unstyled subtask-list mt-1">
                                            @foreach ($task['subtasks'] as $subtask)
                                                <li class="row mb-2">
                                                    <div class="col-md-9">--  {{ $subtask['title'] }}</div>
                                                    <div class="col-md-3"><span class="badge bg-label-primary">{{ $subtask['invest_time'] ?? '0 minutes' }}</span></div>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </li>
                            @endforeach
                        @else
                            <li class="list-group-item">No tasks available.</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endforeach
@foreach ($summaryPending as $pendingTask)
    @php
        $user = App\Models\User::find($pendingTask->user_id)->name;
        $Task = App\Models\Tasks::find($pendingTask->task_id);
        $title = $Task->title;
        $date = $Task->date;
        $status = $Task->status;
        $investTime = $Task->invest_time;
        $project = App\Models\Projects::find($Task->project_id)->name;
        $subtaskIds = explode(',', $pendingTask->sub_task_ids);
        $subTasks = App\Models\Subtasks::whereIn('id', $subtaskIds)->get();
        $subTaskTitles = $subTasks->toArray();
    @endphp
    <div class="row">
        <div class="col-12 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5 class="card-title">Project Name: {{ $project }}</h5>
                        </div>
                        <div class="col-md-4 text-center">
                            <span class="text-muted">Employee: {{ $user }}</span>
                        </div>
                        <div class="col-md-4 text-end">
                            <p class="text-muted">Date: {{ $pendingTask->date }}</p>
                        </div>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <strong>Task :</strong>
                            <span>{{ $title }}</span>
                            <span class="badge bg-label-warning text-uppercase">{{ $status }}</span>
                            <span>({{ $investTime }})</span>
                            @if (!empty($subTaskTitles))
                                <ul class="list-unstyled subtask-list mt-1">
                                    @foreach ($subTaskTitles as $subtask)
                                        <li>- {{ $subtask['title'] }} ({{ $subtask['invest_time'] }})</li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endforeach
