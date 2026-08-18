@extends('layouts.app')

@section('title', 'BPS AI Assistant — Asisten Statistik Publik')

@section('body')
<!-- Header -->
<header class="w-full flex justify-between items-center h-16 px-md md:px-lg max-w-container-max mx-auto bg-surface border-b border-border-default z-10 sticky top-0">
    <div class="flex items-center gap-sm">
        <div class="w-10 h-10 rounded-full bg-primary-container flex items-center justify-center shadow-sm">
            <span class="material-symbols-outlined text-on-primary-container" style="font-variation-settings: 'FILL' 1;">analytics</span>
        </div>
        <div class="flex flex-col justify-center">
            <h1 class="font-headline text-primary tracking-tight leading-none" style="font-size:18px;font-weight:700;">BPS AI Assistant</h1>
            <span class="text-on-surface-variant" style="font-size:12px;">Asisten Statistik Publik</span>
        </div>
    </div>
    <div class="flex items-center gap-md">
        <nav class="hidden md:flex gap-md">
            <a class="text-on-surface-variant hover:text-primary transition-colors" style="font-size:14px;" href="#">Tentang</a>
            <a class="text-on-surface-variant hover:text-primary transition-colors" style="font-size:14px;" href="#">Bantuan</a>
        </nav>
        <span class="bg-surface-blue text-brand-blue-deep px-sm py-base rounded-full border border-primary-fixed" style="font-size:12px;">Prototype / Demo</span>
    </div>
</header>

<!-- Main Content -->
<main class="flex-grow flex flex-col w-full max-w-reading-max mx-auto px-md md:px-lg py-lg relative">

    <!-- Welcome state -->
    <div id="welcome" class="flex flex-col items-center text-center max-w-2xl mx-auto mb-lg">
        <div class="w-20 h-20 bg-surface-container-low rounded-xl flex items-center justify-center mb-6 shadow-[0_8px_24px_rgba(15,23,42,0.05)] border border-surface-container">
            <span class="material-symbols-outlined text-primary" style="font-variation-settings: 'FILL' 1; font-size: 40px;">smart_toy</span>
        </div>
        <h2 class="font-headline text-on-surface mb-sm" style="font-size:26px;font-weight:700;letter-spacing:-0.02em;line-height:1.25;">Halo, ada yang bisa saya bantu?</h2>
        <p class="text-on-surface-variant" style="font-size:16px;line-height:1.6;">Tanyakan data, istilah statistik, publikasi, metodologi, atau informasi layanan BPS.</p>

        <!-- Suggested questions -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-sm w-full max-w-3xl mt-lg">
            <button type="button" class="bps-suggest bg-surface-container-lowest border border-border-default rounded-xl p-md text-left hover:bg-surface-container-low transition-colors duration-200 group" data-q="Apa itu inflasi?">
                <p class="text-on-surface group-hover:text-primary transition-colors" style="font-size:14px;font-weight:600;">Apa itu inflasi?</p>
                <p class="text-on-surface-variant mt-base" style="font-size:12px;">Penjelasan ringkas konsep inflasi.</p>
            </button>
            <button type="button" class="bps-suggest bg-surface-container-lowest border border-border-default rounded-xl p-md text-left hover:bg-surface-container-low transition-colors duration-200 group" data-q="Apa itu PDRB?">
                <p class="text-on-surface group-hover:text-primary transition-colors" style="font-size:14px;font-weight:600;">Apa itu PDRB?</p>
                <p class="text-on-surface-variant mt-base" style="font-size:12px;">Definisi dan komponen PDRB daerah.</p>
            </button>
            <button type="button" class="bps-suggest bg-surface-container-lowest border border-border-default rounded-xl p-md text-left hover:bg-surface-container-low transition-colors duration-200 group" data-q="Bagaimana mencari publikasi BPS?">
                <p class="text-on-surface group-hover:text-primary transition-colors" style="font-size:14px;font-weight:600;">Bagaimana mencari publikasi BPS?</p>
                <p class="text-on-surface-variant mt-base" style="font-size:12px;">Panduan akses direktori publikasi.</p>
            </button>
            <button type="button" class="bps-suggest bg-surface-container-lowest border border-border-default rounded-xl p-md text-left hover:bg-surface-container-low transition-colors duration-200 group" data-q="Di mana saya bisa menemukan data penduduk?">
                <p class="text-on-surface group-hover:text-primary transition-colors" style="font-size:14px;font-weight:600;">Di mana saya bisa menemukan data penduduk?</p>
                <p class="text-on-surface-variant mt-base" style="font-size:12px;">Arahkan ke Sensus Penduduk terbaru.</p>
            </button>
        </div>
    </div>

    <!-- Messages list -->
    <div id="messages" class="flex flex-col gap-lg w-full mt-lg" aria-live="polite"></div>

    <!-- Composer -->
    <div class="w-full mt-auto flex flex-col gap-sm pb-lg pt-lg">
        <div class="relative w-full bg-surface-container-lowest rounded-xl shadow-[0_8px_24px_rgba(15,23,42,0.08)] border border-border-default flex items-end p-xs" id="composer-wrap">
            <button type="button" class="p-sm text-on-surface-variant hover:text-primary transition-colors rounded-lg flex-shrink-0" aria-label="Lampiran" tabindex="-1" disabled>
                <span class="material-symbols-outlined" style="opacity:0.4;">attach_file</span>
            </button>
            <textarea id="composer" class="w-full bg-transparent border-none focus:ring-0 resize-none text-on-surface placeholder-on-surface-variant py-sm px-sm max-h-32 min-h-[48px]" placeholder="Tanyakan sesuatu tentang BPS..." rows="1" aria-label="Pesan untuk BPS AI Assistant"></textarea>
            <button type="button" id="send" class="bg-primary-container text-on-primary-container p-sm rounded-lg flex-shrink-0 hover:opacity-90 transition-opacity ml-xs disabled:opacity-40 disabled:cursor-not-allowed" aria-label="Kirim pesan" disabled>
                <span class="material-symbols-outlined" style="font-variation-settings: 'FILL' 1;">send</span>
            </button>
        </div>
        <p class="text-on-surface-variant text-center opacity-80" style="font-size:12px;">BPS AI dapat melakukan kesalahan. Verifikasi informasi melalui sumber yang ditampilkan.</p>
    </div>
</main>

@vite(['resources/js/chat.js'])
@endsection
