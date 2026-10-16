<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Core\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:HRM,SCM,FIN,MAN,INV,ORD,WAR,CRM,ECO,PRO,PROJ,IT',
            'position' => 'required|in:manager,staff,trainee',
        ]);

        $role = $request->role;
        $userCount = User::where('role', $role)->count() + 1;
        $employeeId = 'MTX-'.strtoupper($role).'-'.str_pad($userCount, 4, '0', STR_PAD_LEFT);

        $departmentName = match ($request->role) {
            'HRM' => 'Human Resource Management',
            'SCM' => 'Supply Chain Management',
            'FIN' => 'Finance',
            'MAN' => 'Manufacturing',
            'INV' => 'Inventory',
            'ORD' => 'Order Management',
            'WAR' => 'Warehouse',
            'CRM' => 'Customer Relationship Management',
            'ECO' => 'E-commerce',
            default => $request->role,
        };

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'position' => $request->position,
            // 'employee_id' => $employeeId,
            'department' => $departmentName,
            'join_date' => now(),
            // New self-registrations stay inactive until HR approves them.
            // Otherwise anyone could self-grant manager/staff access to any
            // module. LoginRequest/EmployeeLoginController already reject
            // inactive accounts, so no session can be obtained before approval.
            'is_active' => false,
        ]);

        // event(new Registered($user)); // <--- Commented out to stop verification email

        return redirect(route('login'))->with('status', 'Registration submitted. Please wait for HR approval before signing in.');
    }
}
