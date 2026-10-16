<?php

namespace App\Http\Controllers\It;

use App\Http\Controllers\Controller;
use App\Models\It\ItKnowledgeArticle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KnowledgeController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = ItKnowledgeArticle::with('author:id,name');

        // Staff only see published articles (plus their own drafts).
        if ($user->position !== 'manager') {
            $query->where(function ($q) use ($user) {
                $q->where('is_published', true)
                    ->orWhere('author_id', $user->id);
            });
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        return Inertia::render('Dashboard/IT/Manager/Knowledge', [
            'articles' => $query->latest()->paginate(12)->withQueryString(),
            'filters' => $request->only(['category', 'search']),
            'isManager' => $user->position === 'manager',
            'categories' => ItKnowledgeArticle::select('category')->distinct()->pluck('category'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'body' => 'required|string|max:10000',
            'audience' => 'required|in:all,office,plant,it_staff',
            'is_published' => 'sometimes|boolean',
        ]);

        // Staff submissions always start as drafts awaiting manager review.
        if (auth()->user()->position !== 'manager') {
            $data['is_published'] = false;
        }

        ItKnowledgeArticle::create([...$data, 'author_id' => auth()->id()]);

        return redirect()->back()->with('success', 'Knowledge article saved.');
    }

    public function update(Request $request, ItKnowledgeArticle $article)
    {
        $user = auth()->user();

        if ($user->position !== 'manager' && $article->author_id !== $user->id) {
            abort(403, 'You can only edit your own drafts.');
        }

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string|max:100',
            'body' => 'required|string|max:10000',
            'audience' => 'required|in:all,office,plant,it_staff',
            'is_published' => 'sometimes|boolean',
        ]);

        if ($user->position !== 'manager') {
            unset($data['is_published']);
        }

        $article->update($data);

        return redirect()->back()->with('success', 'Knowledge article updated.');
    }

    public function destroy(ItKnowledgeArticle $article)
    {
        $article->delete();

        return redirect()->back()->with('success', 'Knowledge article deleted.');
    }

    public function markRead(ItKnowledgeArticle $article)
    {
        $article->increment('views');

        return redirect()->back();
    }
}
