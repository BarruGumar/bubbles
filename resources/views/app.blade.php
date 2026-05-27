<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia></title>
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/BubblesLogo.png') }}">

        <!-- Anti-FOUC: apply theme before paint -->
        <script>(function(){try{if(localStorage.getItem('bubbles_theme')==='dark')document.documentElement.classList.add('dark');}catch(e){}})();</script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
        @if(config('app.debug'))
        <script src="//cdn.jsdelivr.net/npm/eruda"></script>
        <script>eruda.init();</script>
        @endif
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
