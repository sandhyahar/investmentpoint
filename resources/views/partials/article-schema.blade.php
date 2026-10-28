{{--
    Google structured data for one article page (NewsArticle): lets Google show the headline, picture,
    date and publisher in results and Top Stories.
    Params: $headline, $description, $image, $published (Carbon|null), $modified (Carbon|null), $author (?string), $section (?string), $keywords (?string)
--}}
<script type="application/ld+json">{!! json_encode(array_filter([
    '@' . 'context' => 'https://schema.org', // written in two parts: Blade reads the whole word as one of its own directives
    '@type' => 'NewsArticle',
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => url()->current()],
    'headline' => \Illuminate\Support\Str::limit(strip_tags($headline), 110, ''),
    'description' => isset($description) ? \Illuminate\Support\Str::limit(\App\Support\Html::plain($description), 300) : null,
    'image' => !empty($image) ? [$image] : [asset(config('seo.default_image'))],
    'datePublished' => optional($published ?? null)->toIso8601String(),
    'dateModified' => optional($modified ?? $published ?? null)->toIso8601String(),
    'articleSection' => $section ?? null,
    'keywords' => $keywords ?? null,
    'inLanguage' => 'en-IN',
    'author' => !empty($author)
        ? ['@type' => 'Person', 'name' => $author]
        : ['@type' => 'Organization', 'name' => config('seo.site_name'), 'url' => url('/')],
    'publisher' => [
        '@type' => 'NewsMediaOrganization',
        'name' => config('seo.site_name'),
        'url' => url('/'),
        'logo' => ['@type' => 'ImageObject', 'url' => asset(config('seo.default_image'))],
    ],
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
