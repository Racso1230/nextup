<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Dark is the only theme: the "dark" class is permanent and the colours come from app.css. --}}

        @if (request()->routeIs('home'))
            {{-- Public landing page only: everything else is behind sign-in. Server-rendered, so crawlers and link
                 previews see it without running JavaScript. --}}
            @php
                $seoTitle = 'nextup: what should you play tonight?';
                $seoDescription = 'nextup ranks the games you already own on Steam by what you will actually play next, and explains every pick. Try it with a sample library, no account needed.';
                $seoUrl = url('/');
                $seoImage = url('/og-image.png');
                // Built inside @php: in the template body Blade would read "@context" as a directive and drop it.
                $seoSchema = [
                    '@context' => 'https://schema.org',
                    '@type' => 'WebApplication',
                    'name' => 'nextup',
                    'url' => $seoUrl,
                    'description' => $seoDescription,
                    'applicationCategory' => 'GameApplication',
                    'operatingSystem' => 'Any (web browser)',
                    'browserRequirements' => 'Requires JavaScript',
                    'image' => $seoImage,
                    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'GBP'],
                    'creator' => ['@type' => 'Person', 'name' => 'Oscar Freeman', 'url' => 'https://ojfdigital.co.uk'],
                ];
            @endphp
            <meta name="description" content="{{ $seoDescription }}">
            <link rel="canonical" href="{{ $seoUrl }}">
            <meta property="og:type" content="website">
            <meta property="og:site_name" content="nextup">
            <meta property="og:title" content="{{ $seoTitle }}">
            <meta property="og:description" content="{{ $seoDescription }}">
            <meta property="og:url" content="{{ $seoUrl }}">
            <meta property="og:image" content="{{ $seoImage }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta property="og:image:alt" content="The nextup landing page: a top pick with its reason and signal bars">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $seoTitle }}">
            <meta name="twitter:description" content="{{ $seoDescription }}">
            <meta name="twitter:image" content="{{ $seoImage }}">
            <script type="application/ld+json">
                {!! json_encode($seoSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
            </script>
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{--
            Hero animation without a flash of the final state: with JavaScript running and motion allowed, mark
            <html> so app.css hides the animated hero parts until GSAP reveals them (useHeroAnimation.ts). The
            timer is the failsafe: if the app has not revealed them within 1.5s, remove the class so content is
            never left hidden. Without JavaScript, or with reduced motion, the class is never added.
        --}}
        <script>
            (function () {
                try {
                    if (window.matchMedia('(prefers-reduced-motion: no-preference)').matches) {
                        var root = document.documentElement;
                        root.classList.add('js-motion');
                        setTimeout(function () { root.classList.remove('js-motion'); }, 1500);
                    }
                } catch (e) {}
            })();
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
