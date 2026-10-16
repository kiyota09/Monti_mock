<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Crm\FabricSampleRequest;
use App\Models\Eco\ConversationAttachment;
use App\Models\Eco\ConversationMessage;
use App\Models\Eco\Inquiry;
use App\Models\Eco\EcoQuotation;
use App\Support\ConversationRealtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ClientConversationController extends Controller
{
    public function index()
    {
        $inquiries = Inquiry::where('client_id', Auth::guard('client')->id())
            ->with('product')
            ->latest()
            ->get();

        return Inertia::render('Client/ConversationList', ['inquiries' => $inquiries]);
    }

    public function show(Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        // Lab→CRM handoffs stay hidden until the CRM team forwards them.
        $inquiry->load(['messages' => function ($query) {
            $query->with(['attachments', 'sampleRequest'])->where('visible_to_client', true)->oldest();
        }, 'product']);

        $quotations = EcoQuotation::with('items')
            ->where('inquiry_id', $inquiry->id)
            ->latest()
            ->get();

        return Inertia::render('Client/ConversationShow', [
            'inquiry' => $inquiry,
            'quotations' => $quotations,
        ]);
    }

    public function sendMessage(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        $request->validate([
            'message' => 'required_without:files|nullable|string',
            'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,zip|max:10240',
        ]);

        $message = ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => $request->message ?? '',
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('eco_attachments', 'public');
                ConversationAttachment::create([
                    'conversation_message_id' => $message->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                ]);
            }
        }

        $inquiry->update(['last_message_at' => now()]);

        // Client just sent something → no longer "typing".
        ConversationRealtime::clearTyping($inquiry->id, 'client');

        $message->load('attachments');

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message], 201);
        }

        return back()->with('success', 'Message sent.');
    }

    /**
     * Real-time polling feed for the client side.
     * GET ?after=<lastMessageId> → only newer messages are returned.
     * Also reports whether the ECO team is currently typing.
     */
    public function feed(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        $after = (int) $request->query('after', 0);
        $messages = ConversationRealtime::feed($inquiry->id, $after, true);

        return response()->json([
            'messages' => $messages,
            'typing'   => ConversationRealtime::isTyping($inquiry->id, 'eco'),
        ]);
    }

    /**
     * Typing heartbeat for the client side. Called (debounced) while typing.
     */
    public function typing(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        ConversationRealtime::markTyping($inquiry->id, 'client');

        return response()->json(['ok' => true]);
    }

    /**
     * Send one or more Purchase Order files to the conversation.
     */
    public function sendPO(Request $request, Inquiry $inquiry)
    {
        $this->authorizeClient($inquiry);

        $request->validate([
            'po_files' => 'required|array|min:1',
            'po_files.*' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240',
            'notes' => 'nullable|string|max:500',
        ]);

        $message = ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => "📄 **Purchase Order uploaded**\n".($request->notes ? 'Notes: '.$request->notes : ''),
            'is_system_event' => true,
        ]);

        foreach ($request->file('po_files') as $file) {
            $path = $file->store('client_po', 'public');
            ConversationAttachment::create([
                'conversation_message_id' => $message->id,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'is_po' => true, // Mark as Purchase Order
            ]);
        }

        $inquiry->update(['last_message_at' => now()]);

        return back()->with('success', 'Purchase Order(s) sent.');
    }

    /**
     * Approve an attachment sent by ECO (or any attachment in the conversation).
     * This is called by the client when they approve a file.
     */
    public function approveAttachment(ConversationAttachment $attachment)
    {
        $inquiry = $attachment->message->inquiry;
        $this->authorizeClient($inquiry);

        $attachment->update(['approved_by_client' => true]);

        // Create a system message to notify ECO
        ConversationMessage::create([
            'inquiry_id' => $inquiry->id,
            'sender_type' => 'client',
            'message' => "✅ Client approved attachment: {$attachment->file_name}",
            'is_system_event' => true,
        ]);

        return back()->with('success', 'Attachment approved.');
    }

    /**
     * Approve a forwarded fabric sample. The client may now send a P.O.
     * through the existing purchase-order flow.
     */
    public function approveSample(Request $request, FabricSampleRequest $sampleRequest)
    {
        $inquiry = $sampleRequest->inquiry;
        abort_unless($inquiry, 404);
        $this->authorizeClient($inquiry);

        if ($sampleRequest->status !== FabricSampleRequest::STATUS_FORWARDED) {
            return back()->withErrors(['error' => 'This sample is no longer awaiting approval.']);
        }

        DB::transaction(function () use ($sampleRequest, $inquiry) {
            $sampleRequest->update(['status' => FabricSampleRequest::STATUS_APPROVED]);

            ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'client',
                'message' => "✅ Fabric sample approved ({$sampleRequest->code}): {$sampleRequest->fabric_name}. Ready for P.O.",
                'is_system_event' => true,
                'sample_request_id' => $sampleRequest->id,
            ]);

            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', 'Fabric sample approved. You may now send your P.O.');
    }

    /**
     * Request a color adjustment on a forwarded sample. The CRM team sees
     * this and loops back with a new sample request round.
     */
    public function adjustSample(Request $request, FabricSampleRequest $sampleRequest)
    {
        $inquiry = $sampleRequest->inquiry;
        abort_unless($inquiry, 404);
        $this->authorizeClient($inquiry);

        $validated = $request->validate([
            'notes' => 'required|string|max:2000',
        ]);

        if ($sampleRequest->status !== FabricSampleRequest::STATUS_FORWARDED) {
            return back()->withErrors(['error' => 'This sample is no longer awaiting approval.']);
        }

        DB::transaction(function () use ($sampleRequest, $inquiry, $validated) {
            // Kept on the request itself so the chemist reads it on the
            // shade page (the thread is invisible to the lab).
            $sampleRequest->update([
                'status' => FabricSampleRequest::STATUS_ADJUSTMENT_REQUESTED,
                'adjustment_notes' => $validated['notes'],
            ]);

            ConversationMessage::create([
                'inquiry_id' => $inquiry->id,
                'sender_type' => 'client',
                'message' => "🎨 Color adjustment requested ({$sampleRequest->code}): {$sampleRequest->fabric_name}\n"
                    . "Notes: {$validated['notes']}",
                'is_system_event' => true,
                'sample_request_id' => $sampleRequest->id,
            ]);

            $inquiry->update(['last_message_at' => now()]);
        });

        return back()->with('success', 'Adjustment requested. The CRM team will prepare a new sample.');
    }

    /**
     * Accept a quotation (price agreement only, no order created yet).
     */
    public function acceptQuotation(Request $request, EcoQuotation $quotation)
    {
        // Only allow if the quotation belongs to this client and is still 'sent'
        if ($quotation->client_id !== Auth::guard('client')->id() || $quotation->status !== 'sent') {
            abort(403);
        }

        // Update quotation status
        $quotation->update(['status' => 'accepted']);

        // System message
        $messageText = "Quotation {$quotation->quotation_number} has been ACCEPTED by the client.";
        if ($request->notes) {
            $messageText .= "\n\nNotes from client: " . $request->notes;
        }

        $message = ConversationMessage::create([
            'inquiry_id' => $quotation->inquiry_id,
            'sender_type' => 'client',
            'message' => $messageText,
            'is_system_event' => true,
        ]);

        // Handle any attached files (e.g., proof of payment)
        if ($request->hasFile('files')) {
            $request->validate([
                'files.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,zip|max:10240',
            ]);
            foreach ($request->file('files') as $file) {
                $path = $file->store('eco_attachments', 'public');
                ConversationAttachment::create([
                    'conversation_message_id' => $message->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                ]);
            }
        }

        return back()->with('success', 'Quotation accepted successfully.');
    }

    /**
     * Trash an accepted quotation the client changed their mind on.
     * Flags it for a fresh quotation — CRM sees the trash tab entry plus a
     * thread message and issues a new quotation via the normal flow.
     */
    public function trashQuotation(Request $request, EcoQuotation $quotation)
    {
        if ($quotation->client_id !== Auth::guard('client')->id() || $quotation->status !== 'accepted') {
            abort(403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($quotation, $validated) {
            $quotation->update([
                'status' => 'trashed',
                'request_new_quote' => true,
            ]);

            $msg = "Quotation {$quotation->quotation_number} moved to TRASH by the client. A new quotation was requested.";
            if (! empty($validated['notes'])) {
                $msg .= "\n\nClient notes: " . $validated['notes'];
            }

            ConversationMessage::create([
                'inquiry_id' => $quotation->inquiry_id,
                'sender_type' => 'client',
                'message' => $msg,
                'is_system_event' => true,
            ]);
        });

        return back()->with('success', 'Quotation trashed. The CRM team will issue a new one.');
    }

    public function rejectQuotation(Request $request, EcoQuotation $quotation)
    {
        if ($quotation->client_id !== Auth::guard('client')->id() || $quotation->status !== 'sent') {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
            'request_new' => 'boolean'
        ]);

        $quotation->update([
            'status' => 'rejected',
            'reject_reason' => $request->reason,
            'request_new_quote' => $request->request_new ?? false,
        ]);

        $msg = "Quotation {$quotation->quotation_number} REJECTED. Reason: {$request->reason}";
        if ($request->request_new) {
            $msg .= " | Client requested a revised quotation.";
        }

        ConversationMessage::create([
            'inquiry_id' => $quotation->inquiry_id,
            'sender_type' => 'client',
            'message' => $msg,
            'is_system_event' => true,
        ]);

        return back()->with('success', 'Quotation rejected.');
    }

    /**
     * Download an accepted quotation as a PDF.
     */
    public function downloadQuotation(EcoQuotation $quotation)
    {
        if ($quotation->client_id !== Auth::guard('client')->id()) {
            abort(403);
        }

        if ($quotation->status !== 'accepted') {
            abort(403, 'Only accepted quotations can be downloaded.');
        }

        $quotation->load(['items', 'inquiry.product']);

        return response()->json([
            'quotation' => $quotation,
            'generated_at' => now()->toDateTimeString(),
        ]);
    }

    public function destroyAttachment(ConversationAttachment $attachment)
    {
        if ($attachment->message->inquiry->client_id !== Auth::guard('client')->id()) {
            abort(403);
        }
        Storage::disk('public')->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('success', 'File removed.');
    }

    private function authorizeClient(Inquiry $inquiry)
    {
        if ($inquiry->client_id !== Auth::guard('client')->id()) {
            abort(403);
        }
    }
}