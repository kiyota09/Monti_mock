<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\Core\PagePermission;
use App\Models\Core\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItAccessController extends Controller
{
    /**
     * Manage per-page IT grants (stored in page_permissions, module = IT).
     * Native IT managers/staff pass CheckPagePermission automatically;
     * these rows cover everyone else (secretary, supervisors, cross-posts).
     */
    public function index()
    {
        $pages = config('module_pages.it', []);

        $users = User::whereIn('position', ['secretary', 'special_officer', 'manager', 'supervisor', 'staff'])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'position']);

        $grants = PagePermission::where('module', 'IT')->get()->groupBy('user_id');

        $permissions = [];
        foreach ($users as $user) {
            $row = [];
            foreach (array_keys($pages) as $page) {
                $row[$page] = $grants[$user->id]
                    ? $grants[$user->id]->firstWhere('page', $page) !== null
                    : false;
            }
            $permissions[$user->id] = $row;
        }

        return Inertia::render('Dashboard/IT/Manager/Access', [
            'users' => $users,
            'pages' => $pages,
            'permissions' => $permissions,
        ]);
    }

    public function update(Request $request)
    {
        $pages = array_keys(config('module_pages.it', []));

        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permissions' => 'required|array',
        ]);

        $user = User::findOrFail($data['user_id']);

        foreach ($pages as $page) {
            $granted = (bool) ($data['permissions'][$page] ?? false);

            if ($granted) {
                PagePermission::updateOrCreate(
                    ['user_id' => $user->id, 'module' => 'IT', 'page' => $page],
                    ['permission_level' => 'edit']
                );
            } else {
                PagePermission::where('user_id', $user->id)
                    ->where('module', 'IT')
                    ->where('page', $page)
                    ->delete();
            }
        }

        return redirect()->back()->with('success', "IT access updated for {$user->name}.");
    }
}
