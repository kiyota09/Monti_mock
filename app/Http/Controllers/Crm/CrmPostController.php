<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class CrmPostController extends Controller
{
    private function publicMedia(array $media): array
    {
        return collect($media)->map(function ($item) {
            $url = (string) ($item['url'] ?? '');
            $path = parse_url($url, PHP_URL_PATH);

            // Keep local uploads host-relative so they work under localhost,
            // LAN addresses, and the configured production domain alike.
            if (is_string($path) && str_starts_with($path, '/storage/')) {
                $item['url'] = url(ltrim($path, '/'));
                $item['path'] = ltrim(substr($path, strlen('/storage/')), '/');
            }

            return $item;
        })->values()->all();
    }

    public function index()
    {
        return Inertia::render('Dashboard/CRM/Posts', [
            'posts' => CrmPost::with('creator:id,name')->latest()->get()->map(fn (CrmPost $post) => [
                'id' => $post->id,
                'caption' => $post->caption,
                'media' => $this->publicMedia($post->media ?? []),
                'created_at' => $post->created_at?->toIso8601String(),
                'archived_at' => $post->archived_at?->toIso8601String(),
                'creator' => $post->creator?->name ?? 'Monti Textile',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'caption' => ['required', 'string', 'max:5000'],
            'media' => ['nullable', 'array', 'max:8'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov', 'max:51200'],
        ]);

        $media = collect($request->file('media', []))->map(function ($file) {
            $path = $file->store('crm/posts', 'public');

            return [
                'url' => url('storage/' . ltrim($path, '/')),
                'path' => $path,
                'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
            ];
        })->values()->all();

        CrmPost::create([
            'caption' => $data['caption'],
            'media' => $media,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Your post is now live on the welcome page.');
    }

    public function update(Request $request, CrmPost $post)
    {
        $data = $request->validate([
            'caption' => ['required', 'string', 'max:5000'],
            'media' => ['nullable', 'array', 'max:8'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov', 'max:51200'],
            'remove_media' => ['nullable', 'array', 'max:8'],
            'remove_media.*' => ['string', 'max:500'],
        ]);

        $currentMedia = collect($post->media ?? []);
        $removePaths = collect($data['remove_media'] ?? [])->unique()->values();
        $remainingMedia = $currentMedia->reject(fn ($item) => $removePaths->contains($this->storedMediaPath($item)))->values();
        $newFiles = $request->file('media', []);

        if ($remainingMedia->count() + count($newFiles) > 8) {
            return back()->withErrors(['media' => 'A post can contain up to 8 images or videos.'])->withInput();
        }

        foreach ($currentMedia->reject(fn ($item) => !$removePaths->contains($this->storedMediaPath($item))) as $item) {
            $path = $this->storedMediaPath($item);
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        foreach ($newFiles as $file) {
            $path = $file->store('crm/posts', 'public');
            $remainingMedia->push([
                'url' => url('storage/' . ltrim($path, '/')),
                'path' => $path,
                'type' => str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image',
            ]);
        }

        $post->update([
            'caption' => $data['caption'],
            'media' => $remainingMedia->values()->all(),
        ]);

        return back()->with('success', 'Post updated.');
    }

    public function archive(CrmPost $post)
    {
        $post->update(['archived_at' => now()]);

        return back()->with('success', 'Post moved to the archive.');
    }

    public function restore(CrmPost $post)
    {
        $post->update(['archived_at' => null]);

        return back()->with('success', 'Post restored to the welcome page.');
    }

    private function storedMediaPath(array $media): ?string
    {
        $path = (string) ($media['path'] ?? '');
        if ($path === '') {
            $urlPath = parse_url((string) ($media['url'] ?? ''), PHP_URL_PATH);
            $path = is_string($urlPath) ? str_replace('/storage/', '', $urlPath) : '';
        }

        $path = ltrim($path, '/');

        return str_starts_with($path, 'crm/posts/') ? $path : null;
    }

    public function destroy(CrmPost $post)
    {
        foreach ($post->media ?? [] as $media) {
            $path = $this->storedMediaPath($media);
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }

        $post->delete();

        return back()->with('success', 'Post removed.');
    }
}
