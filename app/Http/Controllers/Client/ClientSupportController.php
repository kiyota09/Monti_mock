<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmFeedback;
use App\Traits\StoresFeedbackAttachments;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ClientSupportController extends Controller
{
    use StoresFeedbackAttachments;

    public function index()
    {
        return Inertia::render('Client/Support');
    }

    public function storeComplaint(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
            // The Support form posts a single file input (JPG/PNG/PDF, 5MB);
            // attachments[] is accepted too for forward compatibility.
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);
        $feedback = CrmFeedback::create([
            'client_id' => Auth::guard('client')->id(),
            'type' => $request->type === 'feedback' ? 'feedback' : 'complaint',
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'open',
        ]);
        // Previously the uploaded file was silently dropped — it is now stored.
        $this->storeFeedbackFiles($feedback, $this->collectFeedbackFiles($validated));

        return back()->with('success', 'Your feedback has been sent to CRM.');
    }
}