<?php

namespace App\Http\Controllers\Applicants\Portal;

use App\Http\Controllers\Controller;
use App\Models\Hrm\ApplicantNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Official APPLICANTS notifications center
 * (Dashboard/APPLICANTS/Notifications/index.vue).
 * List tabs are JSON (native fetch); the page shell is Inertia.
 */
class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        $applicant = $request->user('applicant');
        $page = $this->page($request, $applicant, 'all');

        return Inertia::render('Dashboard/APPLICANTS/Notifications/index', [
            'notifications' => $page['data'],
            'pagination' => $page['pagination'],
            'unreadCount' => ApplicantNotification::where('applicant_id', $applicant->id)
                ->whereNull('read_at')->count(),
        ]);
    }

    public function tab(Request $request, string $tab)
    {
        abort_unless(in_array($tab, ['all', 'unread', 'read'], true), 404);

        return response()->json($this->page($request, $request->user('applicant'), $tab));
    }

    /**
     * Unread count badge (ported from super_monti's count endpoint).
     */
    public function count(Request $request)
    {
        return response()->json([
            'count' => ApplicantNotification::where('applicant_id', $request->user('applicant')->id)
                ->whereNull('read_at')->count(),
        ]);
    }

    /**
     * Single notification fetch (ported from super_monti's show endpoint).
     */
    public function show(Request $request, ApplicantNotification $notification)
    {
        $this->owned($request, $notification);

        return response()->json($notification->toPortalArray());
    }

    /**
     * Full details incl. payload data (ported from super_monti's details endpoint).
     */
    public function details(Request $request, ApplicantNotification $notification)
    {
        $this->owned($request, $notification);
        $arr = $notification->toPortalArray();
        $arr['data'] = $notification->data;
        $arr['is_read'] = $notification->read_at !== null;

        return response()->json($arr);
    }

    public function markRead(Request $request, ApplicantNotification $notification)
    {
        $this->owned($request, $notification);
        $notification->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        ApplicantNotification::where('applicant_id', $request->user('applicant')->id)
            ->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, ApplicantNotification $notification)
    {
        $this->owned($request, $notification);
        $notification->delete();

        return response()->json(['ok' => true]);
    }

    public function destroyAll(Request $request)
    {
        ApplicantNotification::where('applicant_id', $request->user('applicant')->id)->delete();

        return response()->json(['ok' => true]);
    }

    private function page(Request $request, $applicant, string $tab): array
    {
        $q = ApplicantNotification::where('applicant_id', $applicant->id)->latest();
        if ($tab === 'unread') {
            $q->whereNull('read_at');
        } elseif ($tab === 'read') {
            $q->whereNotNull('read_at');
        }
        $paginated = $q->paginate(10);

        return [
            'data' => collect($paginated->items())->map(fn ($n) => $n->toPortalArray())->values()->all(),
            'pagination' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    private function owned(Request $request, ApplicantNotification $notification): void
    {
        abort_unless($notification->applicant_id === $request->user('applicant')->id, 404);
    }
}
