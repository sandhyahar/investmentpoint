{{-- Optional writer details under the writer name: "Designation · RA / IA / CA No. X". Styles: blogs.partials.styles (.bl-cred) --}}
@if($blog->author_designation || $blog->author_reg_no)
    <span class="bl-cred">
        @if($blog->author_designation)<span>{{ $blog->author_designation }}</span>@endif
        @if($blog->author_reg_no)<span>RA / IA / CA No. <b class="notranslate" translate="no">{{ $blog->author_reg_no }}</b></span>@endif
    </span>
@endif
