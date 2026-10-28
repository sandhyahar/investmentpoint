{{-- Add / edit form for Latest News / Most Popular News. $item (unsaved on create), $routePrefix, $labels,
     $inbox: the News Inbox story it is written from (or null) --}}
@extends('admin.layouts.app')

@section('page-title', ($item->exists ? 'Edit ' : 'Add ') . $labels['singular'])

@section('content')
<div class="mb-3">
  <a href="{{ route($routePrefix . '.index') }}" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;font-size:12px"><i class="bi bi-arrow-left me-1"></i> Back to {{ $labels['title'] }}</a>
</div>

<div class="form-card" style="max-width:760px">
  <form action="{{ $item->exists ? route($routePrefix . '.update', $item->id) : route($routePrefix . '.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($item->exists) @method('PUT') @endif
    @if($inbox)<input type="hidden" name="inbox_id" value="{{ $inbox->id }}">@endif

    {{-- Free AI draft: an original 200-250 word report from the story's facts, to check before saving --}}
    <div class="mb-4 p-3" style="background:#f4f6fd;border:1px solid #dfe5f5;border-radius:12px" id="aiWriter"
         data-url="{{ route('admin.news-writer') }}"
         data-headline="{{ $inbox?->title }}" data-summary="{{ $inbox?->summary }}" data-source="{{ $inbox?->source }}">
      @if($inbox)
        <div style="font-size:12px;color:#66798d;font-weight:700;text-transform:uppercase;letter-spacing:.5px">Writing from · {{ $inbox->source }}</div>
        <div style="font-size:14px;font-weight:700;color:#141f55;margin:4px 0">{{ $inbox->title }}</div>
        @if($inbox->summary)<div style="font-size:13px;color:#4a5a6e">{{ $inbox->summary }}</div>@endif
        <a href="{{ $inbox->url }}" target="_blank" rel="noopener noreferrer" style="font-size:12px">Read original <i class="bi bi-box-arrow-up-right"></i></a>
      @else
        <div style="font-size:13px;color:#4a5a6e">Type a headline below, then let AI write the article.</div>
      @endif
      <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
        <button type="button" class="btn btn-sm btn-primary" id="aiWriteBtn" style="border-radius:8px;font-weight:600"><i class="bi bi-stars me-1"></i> Write with AI (free)</button>
        <small class="text-muted" id="aiWriteStatus">Writes a new headline and a 200–250 word article in your own words. Always check names and numbers before saving.</small>
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="title">Title *</label>
      <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $item->title) }}" required maxlength="255">
      @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
      @include('admin.partials.title-suggest', ['type' => 'news'])
      @include('admin.partials.auto-english', ['fields' => ['title' => 'text', 'content' => 'html']])
    </div>


    <div class="mb-3">
      <label class="form-label" for="keywords">SEO Keywords</label>
      <input type="text" class="form-control @error('keywords') is-invalid @enderror" id="keywords" name="keywords" value="{{ old('keywords', $item->keywords) }}" maxlength="500" placeholder="e.g. Nalco share price, metal stocks, Nifty Metal, NSE">
      <small class="text-muted">Comma separated. Used for Google and shown as topic tags under the article.</small>
      @error('keywords')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
      <label class="form-label" for="content">Full Article</label>
      <textarea class="form-control rich-editor @error('content') is-invalid @enderror" id="content" name="content" rows="12">{{ old('content', $item->content) }}</textarea>
      <small class="text-muted">Shown on the article page. Its first lines are also the short text in the news list. · <b id="wordCount">0</b> words (aim for 200–250)</small>
      @error('content')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
      <label class="form-label" for="image_file">Image (jpg, png, webp — max 4MB)</label>
      @if($item->image)
      <div class="mb-2"><img src="{{ $item->image_url }}" style="width:120px;height:80px;object-fit:cover;border-radius:8px" alt="Current image"></div>
      @endif
      <input type="file" data-ai-image="16:9" data-ai-prompt-from="title" class="form-control @error('image_file') is-invalid @enderror" id="image_file" name="image_file" accept="image/jpeg,image/png,image/webp">
      @error('image_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
      <input type="url" class="form-control mt-2 @error('image_url') is-invalid @enderror" id="image_url" name="image_url" value="{{ old('image_url') }}" placeholder="…or paste an image link (https://…)" aria-label="Image link">
      @error('image_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
      @if($item->exists)<small class="text-muted">Leave both empty to keep the current image</small>@endif
    </div>

    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="source">Source <small class="text-muted">(records only)</small></label>
        <input type="text" class="form-control @error('source') is-invalid @enderror" id="source" name="source" value="{{ old('source', $item->source) }}" maxlength="120" placeholder="e.g. Investment Point">
        @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="published_at">Publish Date &amp; Time</label>
        <input type="datetime-local" class="form-control @error('published_at') is-invalid @enderror" id="published_at" name="published_at" value="{{ old('published_at', $item->published_at?->format('Y-m-d\TH:i')) }}">
        @error('published_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>

    <div class="mb-3">
      <label class="form-label" for="source_url">Source Link</label>
      <input type="url" class="form-control @error('source_url') is-invalid @enderror" id="source_url" name="source_url" value="{{ old('source_url', $item->source_url) }}" maxlength="1000" placeholder="https://… (the original report)">
      <small class="text-muted">For your records only (not shown on the website): the report this article was written from.</small>
      @error('source_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="row">
      @if($labels['ranked'])
      <div class="col-md-6 mb-3">
        <label class="form-label" for="sort_order">Position</label>
        <input type="number" min="0" class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}">
        <small class="text-muted">1 = pinned at the top of "Most Popular"; 0 = sorted by date with the rest</small>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
      @endif
      <div class="col-md-6 mb-3">
        <label class="form-label" for="status">Status *</label>
        <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
          <option value="active" {{ old('status', $item->status) == 'active' ? 'selected' : '' }}>Active</option>
          <option value="inactive" {{ old('status', $item->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="border-radius:8px;font-weight:600"><i class="bi bi-check-lg me-1"></i> {{ $item->exists ? 'Update' : 'Create' }} {{ $labels['singular'] }}</button>
  </form>
</div>
@endsection

@section('scripts')
<script>
(function () {
  const box = document.getElementById('aiWriter');
  const btn = document.getElementById('aiWriteBtn');
  const status = document.getElementById('aiWriteStatus');
  const $ = id => document.getElementById(id);
  const editor = () => window.tinymce && tinymce.get('content');
  const plain = html => { const d = document.createElement('div'); d.innerHTML = html; return d.textContent || ''; };

  // Word count of the full article (TinyMCE or the plain textarea)
  function countWords() {
    const text = editor() ? editor().getContent({ format: 'text' }) : plain($('content').value);
    $('wordCount').textContent = (text.trim().match(/\S+/g) || []).length;
  }
  countWords();
  const hook = setInterval(() => { if (editor()) { editor().on('input change SetContent', countWords); countWords(); clearInterval(hook); } }, 500);
  $('content').addEventListener('input', countWords);

  btn.addEventListener('click', async function () {
    // Facts: the inbox story, or the title the admin typed
    const headline = box.dataset.headline || $('title').value.trim();
    const summary = box.dataset.summary || '';
    if (!headline) { status.textContent = 'Type a headline first.'; $('title').focus(); return; }
    if ((editor() ? editor().getContent({ format: 'text' }).trim() : $('content').value.trim()) && !confirm('Replace the current title and article with a new AI draft?')) return;

    btn.disabled = true;
    const label = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Writing… (up to a minute)';
    status.textContent = '';
    try {
      const res = await fetch(box.dataset.url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) },
        body: JSON.stringify({ headline, summary, source: box.dataset.source || $('source').value }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok) throw new Error(data.message || 'The AI could not write the article. Please try again.');

      if (data.title) $('title').value = data.title;
      if (data.keywords) $('keywords').value = data.keywords;
      if (editor()) editor().setContent(data.content); else $('content').value = data.content;
      countWords();
      status.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle"></i> Draft ready.</span> Check every name and number against the original, edit if needed, add an image, then save.';
    } catch (e) {
      status.innerHTML = '<span class="text-danger">' + e.message.replace(/</g, '&lt;') + '</span>';
    } finally {
      btn.disabled = false;
      btn.innerHTML = label;
    }
  });
})();
</script>
@endsection
