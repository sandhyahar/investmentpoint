@extends('layouts.app')

@section('title', 'Create Blog | Investment Point')
@section('meta_description', 'Write for Investment Point: submit your stock market or personal finance article. It is published after review by our editors.')

@section('content')
<main class="np-page" data-live="off">{{-- a form: never swapped while someone is typing --}}
    <div class="np-wrap">
        <section>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('experts-view.index') }}#latest-blogs">Blogs</a><i>›</i>Create Blog
            </div>

            <div class="np-card-box bc-card">
                <div class="bc-head">
                    <div>
                        <h1>Create Blog</h1>
                        <p>Write your article and submit it for review. It will appear on the site once approved.</p>
                    </div>
                </div>

                @if(session('success'))
                    <div class="bc-alert success">✓ {{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('blog.store') }}" class="bc-form" enctype="multipart/form-data">
                    @csrf
                    {{-- Honeypot: hidden from people, bots fill it in and get ignored --}}
                    <div class="bc-hp" aria-hidden="true">
                        <label for="website">Website</label>
                        <input id="website" type="text" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="bc-writer">
                        <div class="bc-photo">
                            <img id="photoPreview" src="{{ \App\Models\Setting::logoIconUrl() }}" data-default="{{ \App\Models\Setting::logoIconUrl() }}" alt="Writer photo preview" class="is-default">
                        </div>
                        <div class="bc-writer-fields">
                            <div class="bc-field">
                                <label for="author_name">Expert's Name <span class="bc-required" aria-hidden="true">*</span></label>
                                <input id="author_name" type="text" name="author_name" value="{{ old('author_name') }}" required maxlength="100" minlength="2"
                                       placeholder="Enter the expert's name (shown on the blog)" autocomplete="name" @class(['is-invalid' => $errors->has('author_name')])>
                                @error('author_name')<div class="bc-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="bc-field">
                                <label for="author_email">Email <span class="bc-required" aria-hidden="true">*</span></label>
                                <input id="author_email" type="email" name="author_email" value="{{ old('author_email') }}" required maxlength="150"
                                       placeholder="name@example.com" autocomplete="email" @class(['is-invalid' => $errors->has('author_email')])>
                                <small class="bc-hint">Only our team sees this, to contact you about your blog. It is not shown on the website.</small>
                                @error('author_email')<div class="bc-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="bc-row">
                                <div class="bc-field">
                                    <label for="author_designation">Designation <span class="bc-optional">(optional)</span></label>
                                    <input id="author_designation" type="text" name="author_designation" value="{{ old('author_designation') }}" maxlength="100"
                                           placeholder="e.g. SEBI Research Analyst" autocomplete="organization-title" @class(['is-invalid' => $errors->has('author_designation')])>
                                    @error('author_designation')<div class="bc-error">{{ $message }}</div>@enderror
                                </div>
                                <div class="bc-field">
                                    <label for="author_reg_no">RA / IA / CA No. <span class="bc-optional">(optional)</span></label>
                                    <input id="author_reg_no" type="text" name="author_reg_no" value="{{ old('author_reg_no') }}" maxlength="50"
                                           placeholder="e.g. INH000012345" autocomplete="off" translate="no" @class(['notranslate', 'is-invalid' => $errors->has('author_reg_no')])>
                                    @error('author_reg_no')<div class="bc-error">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="bc-field">
                                <label for="author_website">Website link <span class="bc-optional">(optional)</span></label>
                                <input id="author_website" type="text" inputmode="url" name="author_website" value="{{ old('author_website') }}" maxlength="255"
                                       placeholder="e.g. www.yourwebsite.com" autocomplete="url" translate="no" @class(['notranslate', 'is-invalid' => $errors->has('author_website')])>
                                <small class="bc-hint">Paste your website, blog or profile link. It is shown with your name on the blog.</small>
                                @error('author_website')<div class="bc-error">{{ $message }}</div>@enderror
                            </div>
                            <div class="bc-field bc-field-photo">
                                <label for="author_photo">Profile picture <span class="bc-optional">(optional)</span></label>
                                <div class="bc-file">
                                    <input id="author_photo" type="file" name="author_photo" accept="image/jpeg,image/png,image/webp">
                                    <label for="author_photo" class="bc-file-btn">Choose photo</label>
                                    <span class="bc-file-name" id="photoName">No photo chosen: our logo will be shown</span>
                                    <button type="button" class="bc-file-clear" id="photoClear" hidden>Remove</button>
                                </div>
                                <small class="bc-hint">JPG, PNG or WEBP · max 2MB</small>
                                @error('author_photo')<div class="bc-error">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>

                    <div class="bc-field">
                        <label for="title">Blog title <span class="bc-required" aria-hidden="true">*</span></label>
                        <input id="title" type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                               @class(['is-invalid' => $errors->has('title')])>
                        @error('title')<div class="bc-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="bc-field">
                        <div class="bc-label-row">
                            <label for="content">Blog description <span class="bc-required" aria-hidden="true">*</span></label>
                            <span class="bc-count" id="wordCount">0 words</span>
                        </div>
                        {{-- Pictures go in with the form; "[Image 1]" marks where each one is shown --}}
                        <div class="bc-toolbar">
                            <button type="button" class="bc-tool" id="addImage"><i class="bi bi-image"></i> Add image</button>
                            <button type="button" class="bc-tool" data-wrap="**" title="Bold: select words first"><b>B</b> Bold</button>
                            <button type="button" class="bc-tool" data-prefix="- " title="Bullet point"><i class="bi bi-list-ul"></i> List</button>
                            <span class="bc-tool-hint">Just write: it is formatted automatically when you submit.</span>
                            <input type="file" id="imagePicker" accept="image/jpeg,image/png,image/webp" multiple hidden>
                        </div>
                        <textarea id="content" name="content" rows="16" required
                                  placeholder="Start writing your blog here…&#10;&#10;Leave an empty line between paragraphs. A short line on its own becomes a heading."
                                  @class(['is-invalid' => $errors->has('content')])>{{ old('content') }}</textarea>
                        <div class="bc-images" id="blogImages" hidden></div>
                        <input type="file" name="images[]" id="imagesField" multiple hidden>
                        <div id="imageNumbers"></div>
                        @error('content')<div class="bc-error">{{ $message }}</div>@enderror
                        @if($errors->has('images') || $errors->has('images.*'))<div class="bc-error">{{ $errors->first('images') ?: $errors->first('images.*') }}</div>@endif
                    </div>

                    {{-- The writer's declaration: the blog can only be sent once it is ticked --}}
                    <div class="bc-declare">
                        <label for="declaration">
                            <input id="declaration" type="checkbox" name="declaration" value="1" required @checked(old('declaration'))>
                            <span>The views expressed in this article are based solely on my personal observations and experience. Neither Investment Point nor Nikhil Bhatt has any involvement in its preparation or content. Read the full <a href="{{ route('disclaimer') }}" target="_blank" rel="noopener">Disclaimer</a>.</span>
                        </label>
                        @error('declaration')<div class="bc-error">{{ $message }}</div>@enderror
                    </div>

                    <div class="bc-actions">
                        <button type="submit" class="bc-submit" id="blogSubmit">Submit for Approval →</button>
                        <a href="{{ route('experts-view.index') }}#latest-blogs" class="bc-cancel">Cancel</a>
                    </div>
                </form>
            </div>
        </section>

        <aside class="np-sidebar">
            <div class="np-card-box np-widget">
                <h3 class="np-title">How it works</h3>
                <ol class="bc-steps">
                    <li><b>Write</b><span>Add a clear title and your full article.</span></li>
                    <li><b>Submit</b><span>Your blog is sent to our team for review.</span></li>
                    <li><b>Published</b><span>Once approved, it appears on the Blogs page.</span></li>
                </ol>
            </div>

            <div class="np-card-box np-widget">
                <h3 class="np-title">Writing tips</h3>
                <ul class="bc-tips">
                    <li>Keep the title short and specific.</li>
                    <li>Put a short line on its own to make it a heading.</li>
                    <li>Start lines with "-" for bullet points; put **stars** around words to make them bold.</li>
                    <li>Use "Add image" to put charts or pictures (up to 6) where your cursor is.</li>
                    <li>Avoid buy/sell recommendations.</li>
                </ul>
            </div>
        </aside>
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
{{-- Raw CSS: layouts.app yields extra-css inside its own <style> tag --}}
.bc-card{padding:26px 28px}
.bc-head{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;padding-bottom:18px;margin-bottom:20px;border-bottom:1px solid var(--line)}
.bc-head h1{font-size:clamp(24px,3vw,30px);font-weight:800;color:var(--navy);margin:0 0 4px}
.bc-head p{font-size:13px;color:var(--muted);margin:0;max-width:460px}
.bc-hp{position:absolute!important;left:-9999px;width:1px;height:1px;overflow:hidden}
.bc-writer{display:flex;gap:20px;align-items:flex-start;background:var(--bg);border:1px solid var(--line);border-radius:12px;padding:18px;margin-bottom:20px}
.bc-photo{flex-shrink:0;width:96px;height:96px;border-radius:50%;background:#fff;border:3px solid #fff;box-shadow:0 0 0 2px var(--blue2),0 6px 16px rgba(27,42,107,.12);overflow:hidden;display:flex;align-items:center;justify-content:center}
.bc-photo img{width:100%;height:100%;object-fit:cover}
.bc-photo img.is-default{object-fit:contain;padding:8px}
.bc-writer-fields{flex:1;min-width:0}
.bc-writer-fields>.bc-field:last-child{margin-bottom:0}
.bc-row{display:grid;grid-template-columns:1fr 1fr;gap:0 14px}
.bc-optional{font-weight:600;color:var(--muted);font-size:12px}
.bc-required{color:#dc2626;font-weight:800}
.bc-file{display:flex;align-items:center;flex-wrap:wrap;gap:8px 12px}
.bc-file input[type=file]{position:absolute;width:1px;height:1px;opacity:0;pointer-events:none}
.bc-file-btn{display:inline-block!important;margin:0!important;background:#fff;border:1px solid var(--blue);color:var(--blue)!important;border-radius:8px;padding:8px 14px;font-size:13px!important;font-weight:700;cursor:pointer}
.bc-file-btn:hover{background:var(--blue);color:#fff!important}
.bc-file input:focus-visible+.bc-file-btn{box-shadow:0 0 0 3px var(--blue2)}
.bc-file-name{font-size:12px;color:var(--muted);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%}
.bc-file-clear{border:0;background:none;color:var(--red);font-size:12px;font-weight:700;cursor:pointer;padding:0}
.bc-hint{display:block;font-size:11px;color:var(--muted);margin-top:6px}
.bc-alert{border-radius:10px;padding:12px 14px;font-size:13px;font-weight:700;margin-bottom:18px}
.bc-alert.success{background:#ecfdf3;border:1px solid #bbf0d0;color:#067a4c}
.bc-field{margin-bottom:18px}
.bc-field label{display:block;font-size:13px;font-weight:800;color:var(--text);margin-bottom:6px}
.bc-label-row{display:flex;justify-content:space-between;align-items:baseline}
.bc-count{font-size:11px;color:var(--muted);font-weight:700}
.bc-field input,.bc-field textarea{width:100%;border:1px solid var(--line);border-radius:10px;padding:12px 14px;font:inherit;font-size:15px;color:var(--text);background:#fbfdff;transition:border-color .15s ease,box-shadow .15s ease}
.bc-field textarea{line-height:1.7;resize:vertical;min-height:320px}
.bc-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-bottom:8px}
.bc-tool{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;font:inherit;font-size:13px;font-weight:700;color:var(--navy);cursor:pointer;transition:border-color .15s,color .15s,background .15s}
.bc-tool:hover{border-color:var(--blue);color:var(--blue);background:var(--blue2)}
.bc-tool#addImage{background:var(--blue);border-color:var(--blue);color:#fff}
.bc-tool#addImage:hover{background:var(--navy2)}
.bc-tool-hint{font-size:12px;color:var(--muted);margin-left:4px}
.bc-images{display:flex;flex-wrap:wrap;gap:10px;margin-top:10px}
.bc-image{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px;width:96px;font-size:12px;font-weight:700;color:var(--muted)}
.bc-image img{width:96px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--line)}
.bc-image button{position:absolute;top:-7px;right:-7px;width:22px;height:22px;border-radius:50%;border:0;background:var(--red);color:#fff;font-size:15px;line-height:22px;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.2)}
.bc-field input::placeholder,.bc-field textarea::placeholder{color:#9aabbb}
.bc-field input:focus,.bc-field textarea:focus{outline:0;background:#fff;border-color:var(--blue);box-shadow:0 0 0 3px var(--blue2)}
.bc-field .is-invalid{border-color:var(--red)}
.bc-error{color:var(--red);font-size:12px;font-weight:600;margin-top:5px}
.bc-declare{background:#fff8eb;border:1px solid #f5d9a8;border-radius:10px;padding:14px 16px;margin-bottom:18px}
.bc-declare label{display:flex;gap:10px;align-items:flex-start;cursor:pointer;margin:0}
.bc-declare input{flex-shrink:0;width:18px;height:18px;margin-top:2px;accent-color:var(--blue);cursor:pointer}
.bc-declare span{font-size:13px;font-weight:600;line-height:1.6;color:var(--text)}
.bc-declare a{color:var(--blue);font-weight:800;text-decoration:underline;text-underline-offset:2px}
.bc-actions{display:flex;align-items:center;gap:14px}
.bc-submit:disabled{background:#9aa6c9;cursor:not-allowed}
.bc-submit{border:0;border-radius:10px;padding:12px 22px;background:var(--blue);color:#fff;font-size:14px;font-weight:800;cursor:pointer;transition:background .15s ease}
.bc-submit:hover{background:#22327f}
.bc-cancel{font-size:13px;font-weight:700;color:var(--muted)}
.bc-cancel:hover{color:var(--text)}
.bc-steps{list-style:none;counter-reset:step;margin:0;padding:0}
.bc-steps li{counter-increment:step;display:grid;grid-template-columns:30px 1fr;column-gap:10px;padding:8px 0}
.bc-steps li:before{content:counter(step);grid-row:span 2;width:26px;height:26px;border-radius:50%;background:var(--blue2);color:var(--blue);font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center}
.bc-steps b{font-size:13px;color:var(--text)}
.bc-steps span{font-size:12px;color:var(--muted)}
.bc-tips{margin:0;padding-left:18px}
.bc-tips li{font-size:12px;color:var(--muted);padding:4px 0}
@media(max-width:600px){
  .bc-card{padding:20px 16px}
  .bc-head{flex-direction:column}
  .bc-writer{flex-direction:column;align-items:center;text-align:left;padding:16px}
  .bc-writer-fields{width:100%}
  .bc-row{grid-template-columns:1fr}
  .bc-actions{flex-direction:column;align-items:stretch;text-align:center}
}
@endsection

@section('extra-js')
<script>
(function () {
    const box = document.getElementById('content');
    const out = document.getElementById('wordCount');
    function count() {
        const text = box.value.replace(/\[\s*image\s*\d+\s*\]/gi, '').trim();
        const words = text ? text.split(/\s+/).length : 0;
        out.textContent = words + (words === 1 ? ' word' : ' words');
    }
    box.addEventListener('input', count);
    count();

    // Toolbar: put text at the cursor (on its own line when asked)
    function insertAtCursor(text, ownLine) {
        const start = box.selectionStart, end = box.selectionEnd, v = box.value;
        let before = v.slice(0, start), after = v.slice(end);
        if (ownLine) {
            if (before && !/\n\n$/.test(before)) before += /\n$/.test(before) ? '\n' : '\n\n';
            if (!/^\n\n/.test(after)) after = (/^\n/.test(after) ? '\n' : '\n\n') + after;
        }
        box.value = before + text + after;
        const pos = (before + text).length + (ownLine ? 2 : 0);
        box.focus();
        box.setSelectionRange(pos, pos);
        box.dispatchEvent(new Event('input'));
    }
    document.querySelectorAll('.bc-tool[data-wrap]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const s = box.selectionStart, e = box.selectionEnd, w = btn.dataset.wrap;
            const picked = box.value.slice(s, e) || 'bold text';
            box.setRangeText(w + picked + w, s, e, 'end');
            box.focus();
            box.dispatchEvent(new Event('input'));
        });
    });
    document.querySelectorAll('.bc-tool[data-prefix]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const s = box.selectionStart, lineStart = box.value.lastIndexOf('\n', s - 1) + 1;
            box.setRangeText(btn.dataset.prefix, lineStart, lineStart, 'end');
            box.focus();
            box.dispatchEvent(new Event('input'));
        });
    });

    // Pictures: "Add image" puts "[Image N]" at the cursor and keeps the file; they are sent with the form
    const MAX_IMAGES = 6;
    const picker = document.getElementById('imagePicker');
    const list = document.getElementById('blogImages');
    const field = document.getElementById('imagesField');
    const numbers = document.getElementById('imageNumbers');
    let images = [];   // [{num, file, url}]
    let lastNum = 0;

    // Big phone photos are made web-sized first (max 1600 px), so the upload is quick
    function shrink(file) {
        return new Promise(function (resolve) {
            const img = new Image(), url = URL.createObjectURL(file);
            img.onload = function () {
                URL.revokeObjectURL(url);
                const scale = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
                if (scale === 1 && file.size < 1024 * 1024) return resolve(file);
                const c = document.createElement('canvas');
                c.width = Math.round(img.naturalWidth * scale);
                c.height = Math.round(img.naturalHeight * scale);
                const g = c.getContext('2d');
                g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
                g.drawImage(img, 0, 0, c.width, c.height);
                c.toBlob(function (blob) {
                    resolve(blob && blob.size < file.size ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
                }, 'image/jpeg', 0.85);
            };
            img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
            img.src = url;
        });
    }

    function render() {
        list.hidden = !images.length;
        list.innerHTML = '';
        images.forEach(function (item) {
            const chip = document.createElement('div');
            chip.className = 'bc-image';
            chip.innerHTML = '<img alt=""><span></span><button type="button" aria-label="Remove picture">&times;</button>';
            chip.querySelector('img').src = item.url;
            chip.querySelector('span').textContent = 'Image ' + item.num;
            chip.querySelector('button').addEventListener('click', function () {
                images = images.filter(function (i) { return i !== item; });
                box.value = box.value.replace(new RegExp('\\n*\\[\\s*image\\s*' + item.num + '\\s*\\]\\n*', 'i'), '\n\n').replace(/^\n+/, '');
                box.dispatchEvent(new Event('input'));
                render();
            });
            list.appendChild(chip);
        });
    }

    document.getElementById('addImage').addEventListener('click', function () {
        if (images.length >= MAX_IMAGES) return alert('You can add up to ' + MAX_IMAGES + ' pictures.');
        picker.click();
    });
    picker.addEventListener('change', async function () {
        const files = Array.from(picker.files).filter(function (f) { return /^image\/(jpeg|png|webp)$/.test(f.type); });
        picker.value = '';
        for (const original of files.slice(0, MAX_IMAGES - images.length)) {
            const file = await shrink(original);
            if (file.size > 5 * 1024 * 1024) { alert(original.name + ' is too big (max 5 MB).'); continue; }
            const num = ++lastNum;
            images.push({ num: num, file: file, url: URL.createObjectURL(file) });
            insertAtCursor('[Image ' + num + ']', true);
        }
        render();
    });

    // On submit: send only the pictures still marked in the text, each with its number
    box.form.addEventListener('submit', function () {
        const dt = new DataTransfer();
        numbers.innerHTML = '';
        images.forEach(function (item) {
            if (!new RegExp('\\[\\s*image\\s*' + item.num + '\\s*\\]', 'i').test(box.value)) return;
            dt.items.add(item.file);
            const hidden = document.createElement('input');
            hidden.type = 'hidden'; hidden.name = 'image_numbers[]'; hidden.value = item.num;
            numbers.appendChild(hidden);
        });
        field.files = dt.files;
    });

    // Profile picture: live preview, falls back to the company logo
    const input = document.getElementById('author_photo');
    const img = document.getElementById('photoPreview');
    const name = document.getElementById('photoName');
    const clear = document.getElementById('photoClear');
    function reset() {
        input.value = '';
        img.src = img.dataset.default;
        img.classList.add('is-default');
        name.textContent = 'No photo chosen: our logo will be shown';
        clear.hidden = true;
    }
    input.addEventListener('change', function () {
        const file = input.files && input.files[0];
        if (!file) return reset();
        img.src = URL.createObjectURL(file);
        img.classList.remove('is-default');
        name.textContent = file.name;
        clear.hidden = false;
    });
    clear.addEventListener('click', reset);

    // Submit only after the declaration is ticked
    const declaration = document.getElementById('declaration');
    const submit = document.getElementById('blogSubmit');
    const sync = () => { submit.disabled = !declaration.checked; };
    declaration.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
