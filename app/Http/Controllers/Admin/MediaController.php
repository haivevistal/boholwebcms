<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\ImageProcessor;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Media/Index', [
            'media' => $this->query($request)->paginate(40)->withQueryString()->through(fn ($m) => $this->present($m)),
            'filters' => $request->only(['s', 'type', 'm']),
            'months' => Media::orderByDesc('created_at')->pluck('created_at')->map(fn ($d) => $d?->format('Y-m'))->filter()->unique()->values(),
            'maxUploadKb' => (int) config('cms.media.max_upload_kb'),
            'allowed' => config('cms.media.allowed_mimes'),
        ]);
    }

    /**
     * Used by the media picker modal.
     */
    public function json(Request $request)
    {
        return $this->query($request)->paginate(30)->through(fn ($m) => $this->present($m));
    }

    public function store(Request $request, ImageProcessor $images)
    {
        $allowed = apply_filters('upload_mimes', config('cms.media.allowed_mimes'));
        if (! current_user_can('unfiltered_upload')) {
            // SVG can carry scripts — only trusted users may upload it.
            $allowed = implode(',', array_diff(explode(',', $allowed), ['svg']));
        }

        $request->validate([
            'file' => 'required|file|max:'.(int) config('cms.media.max_upload_kb').'|mimes:'.$allowed,
        ]);

        $file = $request->file('file');
        $disk = config('cms.media.disk', 'public');
        $dir = trim(config('cms.media.directory', 'media'), '/');
        if (get_option('uploads_use_yearmonth_folders', true)) {
            $dir .= '/'.now()->format('Y/m');
        }

        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin');
        $base = Str::slug($original) ?: 'file';
        $name = $base.'.'.$ext;
        $i = 1;
        while (Storage::disk($disk)->exists($dir.'/'.$name)) {
            $name = $base.'-'.$i++.'.'.$ext;
        }

        $path = $file->storeAs($dir, $name, $disk);
        $mime = $file->getMimeType() ?: 'application/octet-stream';

        $meta = $images->process($disk, $path, $mime);

        $media = Media::create(apply_filters('add_attachment_data', [
            'user_id' => current_user_id(),
            'title' => Str::headline($original),
            'filename' => $name,
            'path' => $path,
            'disk' => $disk,
            'mime_type' => $mime,
            'size' => $file->getSize(),
            'width' => $meta['width'],
            'height' => $meta['height'],
            'sizes' => $meta['sizes'],
        ]));

        do_action('add_attachment', $media);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($this->present($media));
        }

        return back()->with('success', 'File uploaded.');
    }

    public function update(Request $request, Media $media)
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'alt' => 'nullable|string|max:255',
            'caption' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        $media->update($data);
        do_action('edit_attachment', $media);

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json($this->present($media));
        }

        return back()->with('success', 'Media updated.');
    }

    public function destroy(Media $media)
    {
        $this->deleteMedia($media);

        return back()->with('success', 'Media file permanently deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer'])['ids'];
        $items = Media::whereIn('id', $ids)->get();
        $items->each(fn ($m) => $this->deleteMedia($m));

        return back()->with('success', $items->count().' file(s) deleted.');
    }

    protected function deleteMedia(Media $media): void
    {
        abort_unless(current_user_can('delete_others_posts') || (int) $media->user_id === current_user_id(), 403);

        do_action('delete_attachment', $media);

        $paths = [$media->path];
        foreach ((array) $media->sizes as $size) {
            $paths[] = $size['path'];
        }
        Storage::disk($media->disk)->delete($paths);
        Post::where('featured_media_id', $media->id)->update(['featured_media_id' => null]);
        $media->delete();
    }

    protected function query(Request $request)
    {
        $q = Media::query()->latest();
        if ($s = trim((string) $request->query('s'))) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('filename', 'like', "%{$s}%")->orWhere('alt', 'like', "%{$s}%"));
        }
        match ($request->query('type')) {
            'image' => $q->where('mime_type', 'like', 'image/%'),
            'video' => $q->where('mime_type', 'like', 'video/%'),
            'audio' => $q->where('mime_type', 'like', 'audio/%'),
            'document' => $q->where('mime_type', 'not like', 'image/%')->where('mime_type', 'not like', 'video/%')->where('mime_type', 'not like', 'audio/%'),
            default => null,
        };
        if ($m = $request->query('m')) {
            [$y, $mo] = array_pad(explode('-', $m), 2, null);
            if ($y && $mo) {
                $q->whereYear('created_at', (int) $y)->whereMonth('created_at', (int) $mo);
            }
        }

        return $q;
    }

    protected function present(Media $m): array
    {
        return [
            'id' => $m->id,
            'title' => $m->title,
            'alt' => $m->alt,
            'caption' => $m->caption,
            'description' => $m->description,
            'filename' => $m->filename,
            'mime_type' => $m->mime_type,
            'is_image' => $m->is_image,
            'url' => $m->url,
            'thumb' => $m->is_image ? $m->sizeUrl('thumbnail') : null,
            'medium' => $m->is_image ? $m->sizeUrl('medium') : null,
            'sizes' => $m->sizeUrls(),
            'width' => $m->width,
            'height' => $m->height,
            'size' => $m->size,
            'size_human' => $this->human($m->size),
            'date' => format_cms_date($m->created_at),
            'uploaded_by' => $m->user?->name,
        ];
    }

    protected function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < 3) {
            $size /= 1024;
            $i++;
        }

        return round($size, $i ? 1 : 0).' '.$units[$i];
    }
}
