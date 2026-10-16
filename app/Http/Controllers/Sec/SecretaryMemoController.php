<?php

namespace App\Http\Controllers\Sec;

use App\Models\Sec\SecretaryMemo;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecretaryMemoController extends SecretaryController
{
    public function index(Request $request)
    {
        $query = SecretaryMemo::with('creator')->latest();

        if ($search = $request->search) {
            $query->where(fn ($q) => $q
                ->where('ref_no', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%"));
        }
        if ($request->status) {
            $query->where('status', $request->status);
        }

        return Inertia::render('Dashboard/SEC/Memos', [
            'memos' => $query->paginate(15)->withQueryString(),
            'filters' => $request->only('search', 'status'),
            'employees' => \App\Models\Core\User::where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'role', 'position']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'audience' => ['required', 'string', 'max:255', function ($attr, $value, $fail) {
                $v = strtolower(trim($value));
                if (in_array($v, SecretaryMemo::AUDIENCES, true)) {
                    return;
                }
                $ids = SecretaryMemo::audienceUserIds($value);
                if (! empty($ids) && \App\Models\Core\User::whereIn('id', $ids)->where('is_active', true)->count() === count($ids)) {
                    return;
                }
                $fail('Choose a valid audience or at least one active employee.');
            }],
            'priority' => 'required|in:normal,urgent',
        ]);

        SecretaryMemo::create([
            ...$validated,
            'ref_no' => $this->refNo('SEC-MEMO', SecretaryMemo::class),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        return back()->with('message', 'Memo drafted successfully.');
    }

    public function publish(SecretaryMemo $memo)
    {
        $memo->update(['status' => 'published', 'published_at' => now()]);

        // Fan out to the memo audience — CEO and COO are always included
        // (CEO executive inbox untouched). Names resolved so the "To:"
        // line reads "Juan Cruz", never "Employee #12".
        $names = \App\Models\Core\User::whereIn(
            'id', SecretaryMemo::audienceUserIds($memo->audience)
        )->pluck('name', 'id')->all();

        foreach ($memo->recipientIds() as $uid) {
            \App\Models\Core\Notification::notify(
                $uid, 'memo', 'New memo: '.$memo->title,
                mb_substr($memo->body ?? '', 0, 140).' — To: '.$memo->audienceLabel($names),
                null, auth()->id(), $memo->id
            );
        }

        return back()->with('message', "Memo {$memo->ref_no} published.");
    }

    public function archive(SecretaryMemo $memo)
    {
        $memo->update(['status' => 'archived']);

        return back()->with('message', "Memo {$memo->ref_no} archived.");
    }

    public function destroy(SecretaryMemo $memo)
    {
        $memo->delete();

        return back()->with('message', 'Memo removed.');
    }
}
