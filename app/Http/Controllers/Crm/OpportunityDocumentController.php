<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmOpportunityDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * Files/images uploaded from a pipeline card. Only offered on stages with
 * `allows_uploads`; each file is linked to the deal and to its client.
 */
class OpportunityDocumentController extends Controller
{
    public function store(Request $request, CrmOpportunity $opportunity)
    {
        $data = $request->validate([
            'files' => 'required|array|max:10',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,png,jpg,jpeg,webp,gif,svg|max:10240',
        ]);

        $folder = 'crm-card-documents/' . $opportunity->id;
        $saved = 0;

        foreach ($data['files'] as $file) {
            $path = $file->store($folder, 'public');

            $opportunity->documents()->create([
                'client_id' => $opportunity->client_id,
                'stage_id' => $opportunity->stage_id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => Auth::id(),
            ]);

            $saved++;
        }

        return back()->with('message', $saved . ' file' . ($saved === 1 ? '' : 's') . ' uploaded.');
    }

    public function destroy(CrmOpportunityDocument $document)
    {
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return back()->with('message', 'File removed.');
    }
}
