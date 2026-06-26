@php
    $title = $seo['title'] ?? 'ND WORLD';
    $description = $seo['description'] ?? '';
    $canonical = $seo['canonical'] ?? null;
    $image = $seo['image'] ?? null;
    $keywords = $seo['keywords'] ?? '';
    $type = $seo['type'] ?? 'website';
    $heading = $seo['heading'] ?? $title;
    $body = $seo['body'] ?? $description;
    $price = $seo['price'] ?? null;
    $category = $seo['category_name'] ?? null;
    $inStock = $seo['in_stock'] ?? null;
    $schema = $seo['schema'] ?? null;
@endphp
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link rel="icon" type="image/jpeg" href="https://nd-world.site/ND-world.jpeg">
    <link rel="apple-touch-icon" href="https://nd-world.site/ND-world.jpeg">
    <meta name="description" content="{{ $description }}">
    @if($keywords)<meta name="keywords" content="{{ is_array($keywords) ? implode(', ', $keywords) : $keywords }}">@endif
    <meta name="robots" content="index,follow,max-image-preview:large">
    @if($canonical)<link rel="canonical" href="{{ $canonical }}">@endif

    <meta property="og:type" content="{{ $type === 'product' ? 'product' : 'website' }}">
    @if($canonical)<meta property="og:url" content="{{ $canonical }}">@endif
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    @if($image)<meta property="og:image" content="{{ $image }}">@endif
    <meta property="og:site_name" content="ND WORLD">
    <meta property="og:locale" content="fr_FR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description }}">
    @if($image)<meta name="twitter:image" content="{{ $image }}">@endif

    @if($schema)
    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
</head>
<body>
    <main>
        <h1>{{ $heading }}</h1>
        @if($category)<p><strong>Catégorie :</strong> {{ $category }}</p>@endif
        @if($price)<p><strong>Prix :</strong> {{ $price }}</p>@endif
        @if($inStock !== null)<p>{{ $inStock ? 'En stock' : 'Rupture de stock' }}</p>@endif
        @if($image)<img src="{{ $image }}" alt="{{ $heading }}" width="600">@endif
        @if($body)<p>{{ $body }}</p>@endif
        @if($canonical)<p><a href="{{ $canonical }}">Voir sur ND WORLD</a></p>@endif

        @if(!empty($seo['products']))
        <section>
            <h2>Nos produits</h2>
            <ul>
                @foreach($seo['products'] as $p)
                <li><a href="{{ $p['url'] }}">{{ $p['nom'] }}</a> — {{ $p['price'] }}</li>
                @endforeach
            </ul>
        </section>
        @endif
    </main>
</body>
</html>
