<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    // GET: Fetch all positions for the modal table
    public function index()
    {
        $positions = Position::orderBy('created_at', 'desc')->get();

        return response()->json($positions);
    }

    // POST: Create a new position
    public function store(Request $request)
    {
        // dd($request->all());
        $request->validate([
            // Accepting 'title' from the frontend fetch request
            'position' => 'required|string|max:255',
        ]);

        $position = Position::create([
            'position' => $request->position, // Map frontend 'position' to DB 'position'
            'status' => 'active',
        ]);

        return back()->with('message', 'Position added successfully.');
    }

    // PATCH: Toggle active/inactive status
    public function toggleStatus($id)
    {
        $position = Position::findOrFail($id);

        // Toggle the status
        if ($position->status === 'active') {
            $position->status = 'deactivated';
        } else {
            $position->status = 'active';
        }

        $position->save();

        return response()->json([
            'message' => 'Position status updated successfully.',
            'position' => $position,
        ]);
    }

    // DELETE: Remove a position
    public function destroy(Position $position)
    {
        $position->delete();

        return response()->json(['message' => 'Position deleted successfully']);
    }

    public function getActivePositions()
    {
        // Fetch positions where status is 'active', and order them alphabetically
        $positions = Position::where('status', 'active')
            ->orderBy('position', 'asc')
            ->get();

        return response()->json($positions);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'position' => 'required|string|max:255',
        ]);

        $position = Position::findOrFail($id);
        $position->update(['position' => $request->position]);

        return back()->with('success', 'Position update successful!');
    }
}