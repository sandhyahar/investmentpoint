{{-- "Ask anything" box under the Disclaimer (saved as a Reader Query in the admin) --}}
<section id="ask" class="dc-ask">
    <div class="dc-ask-head">
        <span><i class="bi bi-chat-dots-fill"></i></span>
        <div>
            <h2>Have a question? Ask us</h2>
            <p>Ask anything about this disclaimer or our publication. You can attach documents or screenshots.</p>
        </div>
    </div>

    @if(session('query_success'))
        <div class="dc-done"><i class="bi bi-check-circle-fill"></i> Thank you! Your question has been sent. We will get back to you soon.</div>
    @endif

    <form action="{{ route('queries.store') }}" method="POST" enctype="multipart/form-data" class="dc-form" novalidate>
        @csrf
        {{-- Honeypot: hidden from people, bots fill it --}}
        <input type="text" name="website" tabindex="-1" autocomplete="off" class="dc-hp" aria-hidden="true">

        <div class="dc-row">
            <div class="dc-field">
                <label for="q_name">Your name</label>
                <input id="q_name" type="text" name="name" value="{{ old('name') }}" maxlength="100" placeholder="Enter your name" autocomplete="name" @class(['is-invalid' => $errors->has('name')])>
                @error('name')<small class="dc-err">{{ $message }}</small>@enderror
            </div>
            <div class="dc-field">
                <label for="q_contact">Mobile or email</label>
                <input id="q_contact" type="text" name="contact" value="{{ old('contact') }}" maxlength="150" placeholder="So we can reply to you" @class(['is-invalid' => $errors->has('contact')])>
                @error('contact')<small class="dc-err">{{ $message }}</small>@enderror
            </div>
        </div>

        <div class="dc-field">
            <label for="q_message">Your question</label>
            <textarea id="q_message" name="message" rows="6" maxlength="5000" placeholder="Ask anything" @class(['is-invalid' => $errors->has('message')])>{{ old('message') }}</textarea>
            @error('message')<small class="dc-err">{{ $message }}</small>@enderror
        </div>

        <div class="dc-field">
            <label>Attach files <span>(optional)</span></label>
            <label class="dc-drop" for="q_files">
                <i class="bi bi-cloud-arrow-up"></i>
                <b>Choose files</b> or drag them here
                <small>Images, PDF, Word, Excel or text · up to 5 files · 5MB each</small>
                <input id="q_files" type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
            </label>
            <ul class="dc-files" id="q_file_list"></ul>
            @error('files')<small class="dc-err">{{ $message }}</small>@enderror
            @foreach($errors->get('files.*') as $messages)<small class="dc-err">{{ $messages[0] }}</small>@endforeach
        </div>

        <button type="submit" class="dc-send"><i class="bi bi-send-fill"></i> Send question</button>
    </form>
</section>

<script>
// Show the chosen file names under the upload box, and support drag & drop
(function () {
    const input = document.getElementById('q_files');
    const list = document.getElementById('q_file_list');
    const drop = input && input.closest('.dc-drop');
    if (!input) return;
    function render() {
        list.innerHTML = '';
        Array.from(input.files).forEach(function (f) {
            const li = document.createElement('li');
            li.innerHTML = '<i class="bi bi-file-earmark"></i> <span></span> <small></small>';
            li.querySelector('span').textContent = f.name;
            li.querySelector('small').textContent = Math.max(1, Math.round(f.size / 1024)) + ' KB';
            list.appendChild(li);
        });
    }
    input.addEventListener('change', render);
    ['dragenter', 'dragover'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.add('over'); }));
    ['dragleave', 'drop'].forEach(e => drop.addEventListener(e, () => drop.classList.remove('over')));
    drop.addEventListener('drop', function (ev) {
        ev.preventDefault();
        if (ev.dataTransfer && ev.dataTransfer.files.length) { input.files = ev.dataTransfer.files; render(); }
    });
})();
</script>
