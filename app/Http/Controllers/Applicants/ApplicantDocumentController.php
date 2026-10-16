<?php

namespace App\Http\Controllers\Applicants;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Applicant;
use App\Models\Hrm\ApplicantDocument;
use Illuminate\Http\Request;

class ApplicantDocumentController extends Controller
{
    public function store(Request $request, Applicant $applicant)
    {
        $data = $request->validate([
            'type' => 'required|in:resume,id,sss,philhealth,pagibig,certificate,other',
            'file' => 'required|file|max:5120',
        ]);
        $path = $request->file('file')->store('applicants/documents', 'public');
        $doc = ApplicantDocument::create([
            'applicant_id' => $applicant->id,
            'type' => $data['type'],
            'file_path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
            'uploaded_by' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json($doc, 201);
        }

        return back()->with('success', 'Document uploaded.');
    }

    public function verify(Applicant $applicant, ApplicantDocument $document)
    {
        abort_unless($document->applicant_id === $applicant->id, 404);
        $document->update(['verified' => true]);

        return back()->with('success', 'Document verified.');
    }

    public function destroy(Applicant $applicant, ApplicantDocument $document)
    {
        abort_unless($document->applicant_id === $applicant->id, 404);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
