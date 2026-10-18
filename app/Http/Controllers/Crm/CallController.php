<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use App\Models\Crm\CrmCall;
use App\Models\Crm\CrmCallSignal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Internal CRM calls. Any user who can reach CRM → Messaging can call any
 * other such user by hashed/plain id. The callee's browser polls `incoming`
 * from anywhere in the dashboard and shows a floating call button.
 */
class CallController extends Controller
{
    protected const RING_TIMEOUT = 55; // seconds before an unanswered ring is missed

    public function contacts()
    {
        $me = (int) Auth::id();

        $candidates = User::query()
            ->where('id', '!=', $me)
            ->where(function ($query) {
                $query->whereIn('role', ['CRM', 'CEO', 'COO'])
                    ->orWhereIn('position', ['secretary', 'vice_president'])
                    ->orWhereHas('pagePermissions', function ($q) {
                        $q->whereIn('module', ['CRM', 'crm', 'Crm'])
                            ->whereRaw('LOWER(page) = ?', ['messaging']);
                    });
            })
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'position', 'profile_photo_path']);

        $contacts = $candidates
            ->filter(fn (User $user) => $user->canAccessCrmMessaging())
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'position' => $user->position,
                'avatar' => $user->profile_photo_path,
            ])
            ->values();

        return response()->json(['contacts' => $contacts]);
    }

    public function incoming()
    {
        $me = (int) Auth::id();

        $this->expireStale();

        $calls = CrmCall::with(['caller:id,name', 'callee:id,name'])
            ->where('callee_id', $me)
            ->where('status', 'ringing')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CrmCall $call) => $this->present($call, $me));

        return response()->json([
            'eligible' => Auth::user()?->canAccessCrmMessaging() ?? false,
            'calls' => $calls,
        ]);
    }

    public function history()
    {
        $me = (int) Auth::id();

        $calls = CrmCall::with(['caller:id,name', 'callee:id,name'])
            ->where(fn ($q) => $q->where('caller_id', $me)->orWhere('callee_id', $me))
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (CrmCall $call) => $this->present($call, $me));

        return response()->json(['calls' => $calls]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:audio,video',
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        $me = (int) Auth::id();

        if ((int) $data['user_id'] === $me) {
            return response()->json(['message' => 'You cannot call yourself.'], 422);
        }

        $callee = User::find($data['user_id']);
        if (! $callee || ! $callee->canAccessCrmMessaging()) {
            return response()->json(['message' => 'That user cannot receive calls.'], 403);
        }

        $call = CrmCall::create([
            'caller_id' => $me,
            'callee_id' => $callee->id,
            'client_id' => $data['client_id'] ?? null,
            'type' => $data['type'],
            'status' => 'ringing',
            'started_at' => now(),
        ]);

        $call->load(['caller:id,name', 'callee:id,name']);

        return response()->json(['call' => $this->present($call, $me)], 201);
    }

    public function show(CrmCall $call)
    {
        $me = (int) Auth::id();
        $this->authorizeParticipant($call, $me);
        $this->expireSingle($call);

        return response()->json(['call' => $this->present($call->fresh(['caller:id,name', 'callee:id,name']), $me)]);
    }

    public function answer(CrmCall $call)
    {
        $me = (int) Auth::id();

        if ((int) $call->callee_id !== $me) {
            return response()->json(['message' => 'Only the callee can answer.'], 403);
        }

        if ($call->status === 'ringing') {
            $call->update(['status' => 'answered', 'answered_at' => now()]);
        }

        $call->load(['caller:id,name', 'callee:id,name']);

        return response()->json(['call' => $this->present($call, $me)]);
    }

    public function decline(CrmCall $call)
    {
        $me = (int) Auth::id();
        $this->authorizeParticipant($call, $me);

        if (in_array($call->status, ['ringing', 'answered'], true)) {
            $call->update(['status' => 'declined', 'ended_at' => now()]);
        }

        $call->load(['caller:id,name', 'callee:id,name']);

        return response()->json(['call' => $this->present($call, $me)]);
    }

    public function end(Request $request, CrmCall $call)
    {
        $me = (int) Auth::id();
        $this->authorizeParticipant($call, $me);

        $data = $request->validate([
            'duration' => 'nullable|integer|min:0',
            'status' => 'nullable|in:completed,failed,missed,declined',
        ]);

        $call->update([
            'status' => $data['status'] ?? 'completed',
            'duration' => $data['duration'] ?? 0,
            'ended_at' => now(),
        ]);
        $call->load(['caller:id,name', 'callee:id,name']);

        return response()->json(['call' => $this->present($call, $me)]);
    }

    /**
     * WebRTC signalling relay. Both peers poll for rows written by the other
     * party; SDP offers/answers and ICE candidates are exchanged this way so no
     * dedicated signalling server is needed.
     */
    public function signal(Request $request, CrmCall $call)
    {
        $me = (int) Auth::id();
        $this->authorizeParticipant($call, $me);

        $data = $request->validate([
            'kind' => 'required|in:offer,answer,candidate',
            'payload' => 'required|array',
        ]);

        $signal = CrmCallSignal::create([
            'crm_call_id' => $call->id,
            'sender_id' => $me,
            'kind' => $data['kind'],
            'payload' => $data['payload'],
        ]);

        return response()->json(['id' => $signal->id], 201);
    }

    public function signals(Request $request, CrmCall $call)
    {
        $me = (int) Auth::id();
        $this->authorizeParticipant($call, $me);
        $this->expireSingle($call);

        $after = (int) $request->query('after', 0);

        $signals = CrmCallSignal::query()
            ->where('crm_call_id', $call->id)
            ->where('sender_id', '!=', $me)
            ->where('id', '>', $after)
            ->orderBy('id')
            ->get(['id', 'kind', 'payload']);

        return response()->json([
            'signals' => $signals,
            'last_id' => (int) ($signals->last()->id ?? $after),
            'call' => $this->present($call->fresh(['caller:id,name', 'callee:id,name']), $me),
        ]);
    }

    protected function expireStale(): void
    {
        CrmCall::where('status', 'ringing')
            ->where('started_at', '<', now()->subSeconds(self::RING_TIMEOUT))
            ->update(['status' => 'missed', 'ended_at' => now()]);
    }

    protected function expireSingle(CrmCall $call): void
    {
        if ($call->status === 'ringing' && $call->started_at && $call->started_at->lt(now()->subSeconds(self::RING_TIMEOUT))) {
            $call->update(['status' => 'missed', 'ended_at' => now()]);
        }
    }

    protected function authorizeParticipant(CrmCall $call, int $me): void
    {
        abort_unless(in_array($me, [(int) $call->caller_id, (int) $call->callee_id], true), 403);
    }

    protected function present(CrmCall $call, int $me): array
    {
        return [
            'id' => $call->id,
            'type' => $call->type,
            'status' => $call->status,
            'direction' => (int) $call->callee_id === $me ? 'incoming' : 'outgoing',
            'started_at' => $call->started_at,
            'answered_at' => $call->answered_at,
            'ended_at' => $call->ended_at,
            'duration' => (int) $call->duration,
            'client_id' => $call->client_id,
            'caller' => ['id' => $call->caller_id, 'name' => $call->caller?->name],
            'callee' => ['id' => $call->callee_id, 'name' => $call->callee?->name],
        ];
    }
}
