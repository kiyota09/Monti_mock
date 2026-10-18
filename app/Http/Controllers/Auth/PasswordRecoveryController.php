<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordRecoveryMail;
use App\Models\Core\User;
use App\Models\Crm\Client;
use App\Models\Hrm\AgencyUser;
use App\Models\Hrm\Applicant;
use App\Models\Pro\Supplier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Custom "forgot password" recovery for every portal.
 *
 * Instead of emailing a reset link, this generates a brand-new random
 * password, stores its hash, and emails the plaintext password to the
 * account owner with an instruction to change it immediately.
 *
 * The JSON responses are consumed by the shared <ForgotPasswordModal> on
 * each login screen (Resources/js/Components/Auth/ForgotPasswordModal.vue).
 */
class PasswordRecoveryController extends Controller
{
    public function employee(Request $request): JsonResponse
    {
        return $this->recover(
            $request,
            model: User::class,
            portal: 'Employee Portal',
            loginRoute: 'login',
            name: fn (User $u) => (string) ($u->name ?: $u->email),
        );
    }

    public function client(Request $request): JsonResponse
    {
        return $this->recover(
            $request,
            model: Client::class,
            portal: 'Partner Portal',
            loginRoute: 'client.login',
            name: fn (Client $c) => (string) ($c->contact_person ?: $c->company_name ?: $c->email),
        );
    }

    public function applicant(Request $request): JsonResponse
    {
        return $this->recover(
            $request,
            model: Applicant::class,
            portal: 'Applicant Portal',
            loginRoute: 'applicant.login',
            name: fn (Applicant $a) => (string) ($a->name ?: $a->email),
        );
    }

    public function supplier(Request $request): JsonResponse
    {
        return $this->recover(
            $request,
            model: Supplier::class,
            portal: 'Supplier Portal',
            loginRoute: 'supplier.login',
            name: fn (Supplier $s) => (string) ($s->representative_name ?: $s->business_name ?: $s->email),
        );
    }

    public function agency(Request $request): JsonResponse
    {
        return $this->recover(
            $request,
            model: AgencyUser::class,
            portal: 'Agency Portal',
            loginRoute: 'agency.login',
            name: fn (AgencyUser $a) => (string) ($a->name ?: $a->email),
        );
    }

    /**
     * Locate the account, replace its password with a fresh one, and email
     * the plaintext password to the owner.
     *
     * @param  class-string<Model>  $model
     * @param  callable(Model): string  $name
     */
    private function recover(
        Request $request,
        string $model,
        string $portal,
        string $loginRoute,
        callable $name,
    ): JsonResponse {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = trim($validated['email']);

        /** @var Model|null $account */
        $account = $model::query()->where('email', $email)->first();

        if (! $account) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'Sorry, the email you entered does not exist.',
            ], 404);
        }

        $password = $this->generatePassword();

        $account->forceFill([
            'password' => Hash::make($password),
            'must_change_password' => true,
        ])->save();

        Mail::to($account->email)->send(new PasswordRecoveryMail(
            recipientName: $name($account) ?: (string) $account->email,
            newPassword: $password,
            portal: $portal,
            loginUrl: route($loginRoute),
        ));

        return response()->json([
            'status' => 'sent',
            'message' => 'We sent the recovery password to your email. Please check it, then change your password immediately.',
        ]);
    }

    /**
     * A readable 12-character password using an alphabet that excludes
     * easily-confused characters (0/O, 1/l/I).
     */
    private function generatePassword(int $length = 12): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;

        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }
}
