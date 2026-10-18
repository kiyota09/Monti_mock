<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmPipelineNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shared progress log shown in the floating Notes panel on the Opportunities
 * board. Any CRM editor can post an entry; entries are visible to every user.
 */
class PipelineNoteController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        CrmPipelineNote::create([
            'user_id' => Auth::id(),
            'body' => trim($data['body']),
        ]);

        return back()->with('message', 'Note added to the pipeline log.');
    }

    public function destroy(CrmPipelineNote $note)
    {
        $note->delete();

        return back()->with('message', 'Note removed.');
    }
}
