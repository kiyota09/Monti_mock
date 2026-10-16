<?php

namespace App\Traits;

use App\Models\Crm\CrmFeedback;
use Illuminate\Http\UploadedFile;

trait StoresFeedbackAttachments
{
    /**
     * Persist uploaded files as feedback attachments.
     * Used by both the client Support intake and the CRM Investigation page.
     */
    protected function storeFeedbackFiles(CrmFeedback $feedback, array $files, $uploaderId = null): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('feedback-attachments', 'public');
            $feedback->attachments()->create([
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?? 0,
                'uploaded_by' => $uploaderId,
            ]);
        }
    }

    /** Collect single-file + multi-file inputs into one list. */
    protected function collectFeedbackFiles(array $data): array
    {
        $files = $data['attachments'] ?? [];
        if (! empty($data['attachment'])) {
            $files[] = $data['attachment'];
        }

        return array_values(array_filter(is_array($files) ? $files : [$files]));
    }
}
