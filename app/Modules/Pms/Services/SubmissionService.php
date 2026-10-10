<?php

namespace App\Modules\Pms\Services;

use App\Modules\Pms\Models\Submission;
use Illuminate\Support\Facades\Storage;
use App\Modules\Pms\Services\PmsAuth as Auth;

class SubmissionService
{
    public function createSubmission($submittable, array $data, $file = null)
    {
        $validated = validator($data, [
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'status' => 'in:pending,approved,rejected',
            'file' => 'nullable',
        ])->validate();

        $attachmentUrl = null;
        if ($file) {
            // Upload file
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '.' . $extension;
            $attachmentPath = $file->storeAs('projects', $filename, 'public');
            $attachmentUrl = asset('storage/' . $attachmentPath);
        }
        
        return $submittable->submissions()->create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'],
            'file_path' => $attachmentUrl,
            'status' => $validated['status'] ?? 'pending',
        ]);
    }

    public function updateSubmission(Submission $submission, array $data, $file = null)
    {
        $validated = validator($data, [
            'description' => 'nullable|string',
            'status' => 'in:pending,approved,rejected',
        ])->validate();

        if ($file) {
            // Delete old file if exists
            if ($submission->file_path) {
                Storage::disk('public')->delete($submission->file_path);
            }
            $path = $file->store('submissions', 'public');
            $validated['file_path'] = $path;
        }

        $submission->update($validated);
        return $submission;
    }
}
