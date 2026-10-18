<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmLead;
use App\Models\Crm\CrmLeadInvestigation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * The lead investigation log: staff document a prospect (notes, flags and
 * files/images) before the lead is pushed into the pipeline.
 */
class LeadInvestigationController extends Controller
{
    public function store(Request $request, CrmLead $lead)
    {
        $data = $request->validate([
            'type' => 'required|in:note,flag',
            'note' => 'required|string|max:5000',
        ]);

        $lead->investigations()->create([
            'user_id' => Auth::id(),
            'type' => $data['type'],
            'note' => $data['note'],
        ]);

        return back()->with('message', $data['type'] === 'flag' ? 'Flag added.' : 'Note added.');
    }

    public function storeFiles(Request $request, CrmLead $lead)
    {
        $data = $request->validate([
            'files' => 'required|array|min:1|max:10',
            'files.*' => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,zip,png,jpg,jpeg,webp,gif,svg|max:10240',
        ], [
            'files.required' => 'Choose at least one file to upload.',
        ]);

        $folder = 'crm-lead-investigations/' . $lead->id;
        $saved = 0;

        foreach ($data['files'] as $file) {
            $lead->investigations()->create([
                'user_id' => Auth::id(),
                'type' => 'file',
                'file_path' => $file->store($folder, 'public'),
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
            ]);
            $saved++;
        }

        return back()->with('message', $saved . ' file' . ($saved === 1 ? '' : 's') . ' added to the investigation.');
    }

    public function destroy(CrmLeadInvestigation $investigation)
    {
        if ($investigation->file_path) {
            Storage::disk('public')->delete($investigation->file_path);
        }

        $investigation->delete();

        return back()->with('message', 'Investigation entry removed.');
    }
}
