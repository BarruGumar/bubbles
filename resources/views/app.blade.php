<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title inertia></title>
        <link rel="icon" type="image/png" href="{{ asset('images/aero-icon.png') }}">

        <!-- Rounded favicon -->
        <script nonce="{{ $cspNonce }}">(function(){var link=document.querySelector("link[rel='icon']");if(!link)return;var img=new Image();img.onload=function(){var c=document.createElement('canvas');c.width=64;c.height=64;var ctx=c.getContext('2d');var r=16;ctx.beginPath();ctx.moveTo(r,0);ctx.lineTo(64-r,0);ctx.quadraticCurveTo(64,0,64,r);ctx.lineTo(64,64-r);ctx.quadraticCurveTo(64,64,64-r,64);ctx.lineTo(r,64);ctx.quadraticCurveTo(0,64,0,64-r);ctx.lineTo(0,r);ctx.quadraticCurveTo(0,0,r,0);ctx.closePath();ctx.clip();ctx.drawImage(img,0,0,64,64);link.href=c.toDataURL('image/png');};img.src=link.href;})();</script>

        <!-- Anti-FOUC: apply theme before paint -->
        <script nonce="{{ $cspNonce }}">(function(){try{if(localStorage.getItem('bubbles_theme')==='dark')document.documentElement.classList.add('dark');}catch(e){}})();</script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes(['nonce' => $cspNonce])
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
