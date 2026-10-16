<?php

namespace App\Http\Controllers\Suppliers;

use App\Http\Controllers\Controller;
use App\Models\SupplierMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * Supplier-portal side of the ECO ↔ supplier thread.
 * Same supplier_messages table ECO writes to, with sender_type=supplier,
 * so the ECO conversation poll picks replies up in real time.
 */
class SupplierChatController extends Controller
{
    /**
     * Supplier messaging page (Vendor Hub → Messages).
     * Same supplier_messages thread ECO sees — supplier's own view.
     */
    public function index()
    {
        $supplier = auth('supplier')->user();

        $messages = SupplierMessage::where('supplier_id', $supplier->id)
            ->orderBy('id', 'asc')
            ->limit(500)
            ->get()
            ->map(fn ($m) => $this->present($m));

        return Inertia::render('Supplier/supplierMessages', [
            'auth' => [
                'user' => $supplier,
                'supplier' => $supplier,
            ],
            'messages' => $messages,
        ]);
    }

    public function feed(Request $request)
    {
        $supplier = auth('supplier')->user();
        $afterId = (int) $request->query('after', 0);

        $messages = SupplierMessage::where('supplier_id', $supplier->id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id', 'asc')
            ->limit(200)
            ->get()
            ->map(fn ($m) => $this->present($m));

        return response()->json(['messages' => $messages]);
    }

    public function send(Request $request)
    {
        $supplier = auth('supplier')->user();

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,jpg,jpeg,png,zip|max:10240',
        ]);

        $path = $request->hasFile('attachment')
            ? $request->file('attachment')->store('supplier-messages', 'public')
            : null;

        $created = SupplierMessage::create([
            'supplier_id' => $supplier->id,
            'sender_type' => 'supplier',
            'sender_id' => null,
            'message' => $validated['message'],
            'attachment' => $path,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['message' => $this->present($created)], 201);
        }

        return back()->with('success', 'Message sent.');
    }

    protected function present(SupplierMessage $msg): array
    {
        $arr = $msg->toArray();
        $arr['attachment_url'] = !empty($arr['attachment'])
            ? (str_starts_with($arr['attachment'], 'http') || str_starts_with($arr['attachment'], '/storage')
                ? $arr['attachment']
                : Storage::url($arr['attachment']))
            : null;
        return $arr;
    }
}
