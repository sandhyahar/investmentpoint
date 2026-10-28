@extends('layouts.app')

@section('title', $blog->title . ' | Investment Point')
@section('meta_description', \App\Support\Html::plain($blog->content, 160))
{{-- WhatsApp / Facebook preview: the writer's photo (wide card); without one the site's default picture --}}
@if($blog->share_image_url)
@section('og_image', $blog->share_image_url)
@endif

@section('content')
@include('partials.article-schema', ['headline' => $blog->title, 'description' => $blog->content, 'image' => $blog->share_image_url,'published' => $blog->approved_at, 'modified' => $blog->updated_at, 'author' => $blog->writer_name, 'section' => 'Blog'])
<main class="np-page">
    <div class="np-wrap">
        <div>
            <div class="np-crumbs" aria-label="Breadcrumb">
                <a href="{{ route('home') }}">Home</a><i>›</i>
                <a href="{{ route('experts-view.index') }}#latest-blogs">Blogs</a><i>›</i>
                {{ $blog->title }}
            </div>

            <article class="np-card-box np-article">
                <h1>{{ $blog->title }}</h1>

                @include('partials.author-card', [
                    'photo' => $blog->writer_photo_url,
                    'isLogo' => !$blog->has_writer_photo,
                    'label' => 'Blog',
                    'name' => $blog->writer_name,
                    'roles' => array_values(array_filter([
                        ['text' => $blog->author_designation ?: 'Guest Expert', 'sebi' => false],
                        $blog->author_reg_no ? ['text' => 'RA / IA / CA No. ' . $blog->author_reg_no, 'sebi' => true] : null,
                    ])),
                    'contacts' => $blog->author_website ? [['type' => 'web', 'href' => $blog->author_website, 'text' => $blog->website_label]] : [],
                    'date' => $blog->approved_at ?? $blog->created_at,
                    'views' => \App\Models\PageView::viewsOf(\App\Models\PageView::ARTICLE_ROUTES['blog'], $blog->slug),
                    'reactions' => $reactions + ['url' => route('blog.react', $blog->slug)],
                    'shareUrl' => route('blog.show', $blog->slug),
                    'shareTitle' => $blog->title,
                ])

                @include('partials.article-body', ['text' => $blog->content])

                @include('partials.stock-disclaimer', ['blog' => true])
                <div class="ip-share-row">
                    <b>Liked this blog? Share it with friends</b>
                    @include('partials.share-button', ['url' => route('blog.show', $blog->slug), 'title' => $blog->title, 'label' => 'Share blog'])
                </div>
                @include('partials.share-buttons')
            </article>

            <a class="btn-custom bl-back" href="{{ route('experts-view.index') }}#latest-blogs">← Back to all blogs</a>
        </div>

        @include('blogs.partials.sidebar')
    </div>
</main>
@endsection

@section('extra-css')
@include('news.partials.styles')
@include('blogs.partials.styles')
@include('partials.author-card-styles')
@endsection

@section('extra-js')
<script>
// Like / dislike buttons in the writer card. Delegated on document: the live-update swap may replace the card.
document.addEventListener('click', function (e) {
    var btn = e.target.closest('.ac-react-btn');
    if (!btn) return;
    var box = btn.closest('.ac-react');
    var buttons = box.querySelectorAll('.ac-react-btn');
    buttons.forEach(function (b) { b.disabled = true; });
    fetch(box.dataset.reactUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ reaction: btn.dataset.reaction })
    })
    .then(function (res) { if (!res.ok) throw new Error(res.status); return res.json(); })
    .then(function (data) {
        buttons.forEach(function (b) {
            var type = b.dataset.reaction, on = data.mine === type;
            b.classList.toggle('active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.querySelector('i').className = 'bi bi-hand-thumbs-' + (type === 'like' ? 'up' : 'down') + (on ? '-fill' : '');
            b.querySelector('b').textContent = Number(data[type + 's']).toLocaleString('en-IN');
        });
    })
    .catch(function (err) { alert(err.message === '429' ? 'Too many clicks, please wait a minute.' : 'Could not save your vote. Please try again.'); })
    .finally(function () { buttons.forEach(function (b) { b.disabled = false; }); });
});
</script>
@endsection
