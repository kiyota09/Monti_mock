<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmMessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Per-user, per-page message templates for the floating Template widget.
 * A template saved on one CRM page is intentionally invisible on another.
 */
class CrmMessageTemplateController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'page' => ['required', 'string', Rule::in(CrmMessageTemplate::PAGE_KEYS)],
        ]);

        $templates = CrmMessageTemplate::query()
            ->where('user_id', Auth::id())
            ->where('page_key', $data['page'])
            ->latest('id')
            ->get(['id', 'title', 'body', 'created_at'])
            ->map(fn (CrmMessageTemplate $template) => [
                'id' => $template->id,
                'title' => $template->title,
                'body' => $template->body,
                'created_at' => $template->created_at?->toIso8601String(),
            ])
            ->values();

        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'page_key' => ['required', 'string', Rule::in(CrmMessageTemplate::PAGE_KEYS)],
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
        ]);

        $template = CrmMessageTemplate::create([
            'user_id' => Auth::id(),
            'page_key' => $data['page_key'],
            'title' => trim($data['title']),
            'body' => $data['body'],
        ]);

        return response()->json([
            'template' => [
                'id' => $template->id,
                'title' => $template->title,
                'body' => $template->body,
                'created_at' => $template->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function update(Request $request, CrmMessageTemplate $template)
    {
        if ((int) $template->user_id !== (int) Auth::id()) {
            abort(403);
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:20000',
        ]);

        $template->update([
            'title' => trim($data['title']),
            'body' => $data['body'],
        ]);

        return response()->json([
            'template' => [
                'id' => $template->id,
                'title' => $template->title,
                'body' => $template->body,
                'created_at' => $template->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function destroy(CrmMessageTemplate $template)
    {
        if ((int) $template->user_id !== (int) Auth::id()) {
            abort(403);
        }

        $template->delete();

        return response()->json(['ok' => true]);
    }
}
