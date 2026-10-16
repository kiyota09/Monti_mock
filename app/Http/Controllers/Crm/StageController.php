<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Customizable pipeline stages: create / rename / fold / won-flag / reorder / delete.
 * Delete is blocked while opportunities sit in the stage unless the caller
 * passes mode=move + target_stage_id.
 */
class StageController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'default_probability' => 'nullable|integer|min:0|max:100',
            'is_won' => 'nullable|boolean',
        ]);

        $stage = CrmStage::create([
            'name' => $data['name'],
            'sequence' => (CrmStage::max('sequence') ?? 0) + 1,
            'default_probability' => $data['default_probability'] ?? 10,
            'is_won' => $data['is_won'] ?? false,
            'created_by' => Auth::id(),
        ]);

        return back()->with('message', "Stage “{$stage->name}” added. Drag it anywhere.");
    }

    public function update(Request $request, CrmStage $stage)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'default_probability' => 'sometimes|required|integer|min:0|max:100',
            'is_folded' => 'sometimes|required|boolean',
            'is_won' => 'sometimes|required|boolean',
        ]);

        $stage->update($data);

        return back()->with('message', "Stage “{$stage->name}” updated.");
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'ordered_ids' => 'required|array|min:1',
            'ordered_ids.*' => 'integer|exists:crm_stages,id',
        ]);

        foreach ($data['ordered_ids'] as $i => $id) {
            CrmStage::where('id', $id)->update(['sequence' => $i]);
        }

        return back()->with('message', 'Stage order saved.');
    }

    public function destroy(Request $request, CrmStage $stage)
    {
        $count = CrmOpportunity::where('stage_id', $stage->id)->count();

        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['block', 'move'])],
            'target_stage_id' => 'nullable|integer|exists:crm_stages,id|different:' . $stage->id,
        ]);
        $mode = $data['mode'] ?? 'block';

        if ($count > 0 && $mode !== 'move') {
            return back()->withErrors([
                'error' => "“{$stage->name}” holds {$count} opportunitie(s). Move them first or delete with move mode.",
            ]);
        }

        if ($count > 0) {
            if (empty($data['target_stage_id'])) {
                return back()->withErrors(['error' => 'Choose a target stage to move the opportunities to.']);
            }
            $target = CrmStage::findOrFail($data['target_stage_id']);
            CrmOpportunity::where('stage_id', $stage->id)->update([
                'stage_id' => $target->id,
                'probability' => $target->default_probability,
            ]);
        }

        $name = $stage->name;
        $stage->delete();

        // Compact sequences.
        CrmStage::orderBy('sequence')->get()->each(fn ($s, $i) => $s->update(['sequence' => $i]));

        return back()->with('message', "Stage “{$name}” deleted.");
    }
}
