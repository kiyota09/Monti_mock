<?php

namespace App\Http\Controllers\Sec;

use App\Models\Sec\SecretaryDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SecretaryDocumentController extends SecretaryController
{
    public function index(Request $request)
    {
        $query = SecretaryDocument::with('creator')->latest();

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('ref_no LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('subject LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('sender LIKE ?', ["%{$search}%"])
                  ->orWhereRaw('recipient LIKE ?', ["%{$search}%"]);
            });
        }
        if ($request->direction) {
            $query->where('direction', $request->direction);
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Dashboard/SEC/Documents', [
            'documents' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'direction', 'status'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'direction' => 'required|in:incoming,outgoing',
            'sender' => 'nullable|string|max:255',
            'recipient' => 'nullable|string|max:255',
            'subject' => 'required|string|max:255',
            'concerned_module' => 'nullable|string|max:16',
            'received_date' => 'required|date',
            'deadline' => 'nullable|date|after_or_equal:received_date',
            'remarks' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png|max:10240',
        ]);

        $path = null;
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('secretary-documents', 'public');
        }

        SecretaryDocument::create([
            ...$validated,
            'ref_no' => $this->refNo('SEC-DOC', SecretaryDocument::class),
            'status' => 'logged',
            'file_path' => $path,
            'created_by' => auth()->id(),
        ]);

        return back()->with('message', 'Document logged successfully.');
    }

    public function updateStatus(Request $request, SecretaryDocument $document)
    {
        $validated = $request->validate([
            'status' => 'required|in:logged,forwarded,in_progress,filed,closed',
            'remarks' => 'nullable|string',
        ]);

        $document->update($validated);

        return back()->with('message', "Document {$document->ref_no} marked as {$validated['status']}.");
    }

    public function destroy(SecretaryDocument $document)
    {
        $user = auth()->user();
        $isOwner = $document->created_by === $user->id;
        $isManager = in_array($user->position, ['manager', 'general_manager', 'special_officer', 'secretary'], true);

        abort_unless($isOwner || $isManager, 403);

        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
        $document->delete();

        return back()->with('message', 'Document record removed.');
    }
}
