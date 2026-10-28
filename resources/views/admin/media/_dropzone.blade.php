{{-- Drag-and-drop upload into the Media Library (handled by the uploader script in admin/layouts/app) --}}
<div class="media-drop" id="{{ $id }}" data-media-drop>
  <i class="bi bi-cloud-arrow-up"></i>
  <strong>Drop pictures here to upload</strong>
  <span class="text-muted">or</span>
  <label class="btn btn-outline-primary btn-sm" style="border-radius:8px;font-weight:600">Select Files
    <input type="file" accept="image/jpeg,image/png,image/webp" multiple hidden data-media-skip>
  </label>
  <small class="text-muted">JPG, PNG or WebP, up to 10 MB each. Big photos are made web-sized automatically.</small>
  <div class="media-drop-status" aria-live="polite"></div>
</div>
