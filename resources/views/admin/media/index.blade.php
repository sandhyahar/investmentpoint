@extends('admin.layouts.app')

@section('title', 'Media Library')
@section('page-title', 'Media Library')

@section('content')
<style>
.media-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;margin-bottom:14px}
.media-toolbar .search{flex:1;min-width:200px;max-width:500px}
.media-toolbar .search input{border-radius:8px;font-size:13px}
.media-sort{width:auto;flex-shrink:0;border-radius:8px;font-size:13px;font-weight:600;color:var(--ink)}
.media-tile{position:relative;aspect-ratio:1;border-radius:10px;overflow:hidden;background:#eef1f7;box-shadow:0 0 0 1px var(--line);cursor:pointer;transition:box-shadow .15s,transform .15s}
.media-tile:hover{box-shadow:0 0 0 2px var(--blue-2);transform:translateY(-1px)}
.media-tile img{width:100%;height:100%;object-fit:cover;display:block}
.media-tile .pick{position:absolute;top:8px;left:8px;width:20px;height:20px;margin:0;cursor:pointer;accent-color:var(--blue);box-shadow:0 0 0 2px #fff;border-radius:4px}
.media-tile:has(.pick:checked){box-shadow:0 0 0 3px var(--blue)}
.media-tile .cap{position:absolute;left:0;right:0;bottom:0;padding:16px 8px 6px;font-size:11px;font-weight:600;color:#fff;background:linear-gradient(transparent,rgba(0,0,0,.65));white-space:nowrap;overflow:hidden;text-overflow:ellipsis;opacity:0;transition:opacity .15s}
.media-tile:hover .cap{opacity:1}
.media-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px}
@media(max-width:575.98px){.media-grid{grid-template-columns:repeat(3,1fr);gap:8px}.media-tile .cap{display:none}}
</style>

<div class="media-toolbar">
  <button type="button" class="btn btn-primary btn-sm" style="border-radius:8px;font-weight:600" data-bs-toggle="collapse" data-bs-target="#mediaUpload" aria-expanded="{{ $media->total() ? 'false' : 'true' }}"><i class="bi bi-cloud-arrow-up me-1"></i> Add New</button>
  <form class="search d-flex gap-2" method="GET" action="{{ route('admin.media.index') }}" role="search">
    <input type="search" name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search pictures by name…" aria-label="Search pictures">
    {{-- Sort: newest / oldest / name A-Z / Z-A (changes the list at once) --}}
    <select name="sort" class="form-select form-select-sm media-sort" aria-label="Sort pictures" onchange="this.form.submit()">
      @foreach($sorts as $key => $label)
      <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
      @endforeach
    </select>
  </form>
  <span class="text-muted" style="font-size:13px">{{ $media->total() }} {{ $media->total() === 1 ? 'picture' : 'pictures' }}</span>
  <form id="bulk-delete-form" action="{{ route('admin.media.bulk-delete') }}" method="POST" data-bulk-form class="ms-auto d-flex align-items-center gap-2 m-0">
    @csrf
    @if($media->count())
    <label class="d-flex align-items-center gap-1 m-0" style="font-size:13px;cursor:pointer"><input type="checkbox" data-select-all> Select all</label>
    @endif
    <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-submit disabled><i class="bi bi-trash me-1"></i> Delete Selected</button>
  </form>
</div>

{{-- Upload area (open when the library is empty) --}}
<div class="collapse {{ $media->total() ? '' : 'show' }} mb-3" id="mediaUpload">
  <div class="form-card" style="padding:18px">
    @include('admin.media._dropzone', ['id' => 'pageDrop'])
  </div>
</div>

@if($media->count())
<div class="media-grid">
  @foreach($media as $m)
  <div class="media-tile" tabindex="0" role="button" aria-label="Open {{ $m->name }}"
       data-media="{{ json_encode($m->details()) }}">
    <img src="{{ $m->src }}" alt="{{ $m->name }}" loading="lazy">
    <input type="checkbox" class="pick" name="ids[]" value="{{ $m->id }}" form="bulk-delete-form" data-select-item aria-label="Select {{ $m->name }}">
    <span class="cap">{{ $m->name }}</span>
  </div>
  @endforeach
</div>
<div class="mt-3">{{ $media->links() }}</div>
@else
<div class="form-card text-center text-muted" style="padding:40px">
  <i class="bi bi-images" style="font-size:34px;color:#b5bfd6"></i>
  <p class="mb-0 mt-2" style="font-size:13.5px">{{ request('q') ? 'No picture matches "' . request('q') . '".' : 'No pictures yet. Upload your pictures above: they can then be picked for any image field with the "Media Library" button.' }}</p>
</div>
@endif

{{-- Picture details (click a picture) --}}
<div class="modal fade" id="mediaDetail" tabindex="-1" aria-labelledby="mediaDetailTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header">
        <h5 class="modal-title" id="mediaDetailTitle" style="font-weight:800;font-size:16px">Picture details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-lg-8 d-flex align-items-center justify-content-center" style="background:#f3f5fb;border-radius:10px;min-height:260px">
            <img id="mdImg" alt="" style="max-width:100%;max-height:62vh;border-radius:6px">
          </div>
          <div class="col-lg-4" style="font-size:13px">
            <dl class="mb-3" style="display:grid;grid-template-columns:auto 1fr;gap:4px 12px">
              <dt class="text-muted fw-normal">Uploaded</dt><dd class="m-0" id="mdDate"></dd>
              <dt class="text-muted fw-normal">Size</dt><dd class="m-0" id="mdSize"></dd>
              <dt class="text-muted fw-normal">Dimensions</dt><dd class="m-0" id="mdDims"></dd>
            </dl>
            <form id="mdRename" class="mb-3">
              <label class="form-label fw-semibold mb-1" for="mdName" style="font-size:12.5px">Name</label>
              <div class="input-group input-group-sm">
                <input type="text" class="form-control" id="mdName" maxlength="200" required>
                <button class="btn btn-outline-primary" type="submit">Save</button>
              </div>
            </form>
            <label class="form-label fw-semibold mb-1" for="mdUrl" style="font-size:12.5px">File URL</label>
            <div class="input-group input-group-sm mb-3">
              <input type="text" class="form-control" id="mdUrl" readonly>
              <button class="btn btn-outline-secondary" type="button" id="mdCopy"><i class="bi bi-clipboard"></i> Copy</button>
            </div>
            <div class="d-flex flex-wrap gap-2">
              <a class="btn btn-sm btn-outline-secondary" id="mdOpen" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1"></i> Open</a>
              <button type="button" class="btn btn-sm btn-outline-danger" id="mdDelete"><i class="bi bi-trash me-1"></i> Delete permanently</button>
            </div>
            <p class="text-muted mt-3 mb-0" style="font-size:12px">Articles that already use this picture keep their own copy, so deleting it here does not remove it from the website.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection

@section('scripts')
<script>
(function () {
  var csrf = @json(csrf_token());
  // After an upload on this page: reload so the new pictures show first. Not right away when some files
  // were refused (a name already in the library): the warning stays to be read; the list updates on closing it
  var drop = document.getElementById('pageDrop');
  var reloadUrl = @json(route('admin.media.index'));
  drop.addEventListener('media:uploaded', function (e) { if (!e.detail.refused) window.location = reloadUrl; else drop.dataset.reload = '1'; });
  drop.addEventListener('media:dismissed', function () { if (drop.dataset.reload) window.location = reloadUrl; });
  // "Show it": the picture that already has the name
  drop.addEventListener('media:show-existing', function (e) { window.location = reloadUrl + '?q=' + encodeURIComponent(e.detail.name); });

  var modalEl = document.getElementById('mediaDetail');
  var modal = new bootstrap.Modal(modalEl);
  var current = null, currentTile = null;
  function $(id) { return document.getElementById(id); }

  function open(tile) {
    current = JSON.parse(tile.dataset.media);
    currentTile = tile;
    $('mdImg').src = current.src;
    $('mdImg').alt = current.name;
    $('mdName').value = current.name;
    $('mdUrl').value = current.url;
    $('mdOpen').href = current.url;
    $('mdDate').textContent = current.date || '';
    $('mdSize').textContent = current.size;
    $('mdDims').textContent = current.width ? current.width + ' × ' + current.height + ' px' : '-';
    modal.show();
  }
  document.querySelectorAll('.media-tile').forEach(function (tile) {
    tile.addEventListener('click', function (e) { if (!e.target.classList.contains('pick')) open(tile); });
    tile.addEventListener('keydown', function (e) { if ((e.key === 'Enter' || e.key === ' ') && e.target === tile) { e.preventDefault(); open(tile); } });
  });

  $('mdCopy').addEventListener('click', function () {
    var btn = this;
    (navigator.clipboard ? navigator.clipboard.writeText(current.url) : Promise.reject()).catch(function () { $('mdUrl').select(); document.execCommand('copy'); })
      .finally(function () { btn.innerHTML = '<i class="bi bi-check-lg"></i> Copied'; setTimeout(function () { btn.innerHTML = '<i class="bi bi-clipboard"></i> Copy'; }, 1500); });
  });

  function send(method, url, body) {
    return fetch(url, { method: method, headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }, body: body ? JSON.stringify(body) : null })
      .then(function (res) { return res.json().then(function (data) { if (!res.ok) throw new Error(data.message || 'Something went wrong. Please try again.'); return data; }); });
  }

  $('mdRename').addEventListener('submit', function (e) {
    e.preventDefault();
    var name = $('mdName').value.trim();
    if (!name) return;
    send('PUT', current.update_url, { name: name }).then(function () {
      current.name = name;
      currentTile.dataset.media = JSON.stringify(current);
      currentTile.querySelector('.cap').textContent = name;
      modal.hide();
    }).catch(function (err) { alert(err.message); });
  });

  $('mdDelete').addEventListener('click', function () {
    if (!confirm('Delete "' + current.name + '" from the Media Library? This cannot be undone.')) return;
    send('DELETE', current.delete_url).then(function () { window.location.reload(); }).catch(function (err) { alert(err.message); });
  });
})();
</script>
@endsection
