<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Dark is the only theme: the "dark" class is permanent and the colours come from app.css. --}}

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
