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
            'is_lost' => 'nullable|boolean',
            'allows_uploads' => 'nullable|boolean',
        ]);

        // Won and Lost are mutually exclusive terminal states.
        $isWon = (bool) ($data['is_won'] ?? false);
        $isLost = (bool) ($data['is_lost'] ?? false) && ! $isWon;

        $stage = CrmStage::create([
            'name' => $data['name'],
            'sequence' => (CrmStage::max('sequence') ?? 0) + 1,
            'default_probability' => $data['default_probability'] ?? 10,
            'is_won' => $isWon,
            'is_lost' => $isLost,
            'allows_uploads' => (bool) ($data['allows_uploads'] ?? false),
            'created_by' => Auth::id(),
        ]);

        return back()->with('message', "Stage “{$stage->name}” added.");
    }

    public function update(Request $request, CrmStage $stage)
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'default_probability' => 'sometimes|required|integer|min:0|max:100',
            'is_folded' => 'sometimes|required|boolean',
            'is_won' => 'sometimes|required|boolean',
            'is_lost' => 'sometimes|required|boolean',
            'allows_uploads' => 'sometimes|required|boolean',
        ]);

        // Keep Won and Lost mutually exclusive: the most recent tick wins.
        if (array_key_exists('is_won', $data) && $data['is_won']) {
            $data['is_lost'] = false;
        } elseif (array_key_exists('is_lost', $data) && $data['is_lost']) {
            $data['is_won'] = false;
        }

        $stage->update($data);

        return back()->with('message', "Stage “{$stage->name}” updated.");
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
