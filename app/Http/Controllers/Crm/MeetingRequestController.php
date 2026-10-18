<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmMeetingRequest;
use Illuminate\Http\Request;

/**
 * Public (no-auth) endpoints reached from the meeting-request email. There is a
 * single live page (`show`) that always reflects the request's current status;
 * confirming, declining or rescheduling updates the request and bounces the
 * client straight back to that page.
 */
class MeetingRequestController extends Controller
{
    public function show(string $token)
    {
        return view('meeting-request.respond', ['req' => $this->find($token)]);
    }

    public function confirm(string $token)
    {
        $req = $this->find($token);
        if (! $req->isAnswered()) {
            $req->update(['status' => 'confirmed', 'responded_at' => now()]);
        }

        return redirect()->route('meeting-request.show', $req->token);
    }

    public function decline(string $token)
    {
        $req = $this->find($token);
        if (! $req->isAnswered()) {
            $req->update(['status' => 'declined', 'responded_at' => now()]);
        }

        return redirect()->route('meeting-request.show', $req->token);
    }

    /**
     * Kept for compatibility (old email links). Rescheduling now happens inline
     * on the single request page, so just send the client there.
     */
    public function rescheduleForm(string $token)
    {
        return redirect()->route('meeting-request.show', $this->find($token)->token);
    }

    public function reschedule(Request $request, string $token)
    {
        $req = $this->find($token);

        $data = $request->validate([
            'preferred_at' => 'nullable|date|after_or_equal:today',
            'response_place' => 'nullable|string|max:255',
            'response_message' => 'nullable|string|max:2000',
        ], [
            'preferred_at.after_or_equal' => 'Please choose today\'s date or a future date — past dates cannot be scheduled.',
        ]);

        if (! $req->isAnswered()) {
            $req->update([
                'status' => 'reschedule',
                'preferred_at' => $data['preferred_at'] ?? null,
                'response_place' => $data['response_place'] ?? null,
                'response_message' => $data['response_message'] ?? null,
                'responded_at' => now(),
            ]);
        }

        return redirect()->route('meeting-request.show', $req->token);
    }

    protected function find(string $token): CrmMeetingRequest
    {
        return CrmMeetingRequest::with('opportunity:id,title')->where('token', $token)->firstOrFail();
    }
}
