@extends('layouts.marketing')

@section('title', 'TaskFlow · Plan, Organize, Get Things Done')

@section('content')
    <section class="relative isolate overflow-hidden">
        <div aria-hidden="true" class="absolute -right-40 -top-44 -z-10 h-[34rem] w-[34rem] rounded-full bg-indigo-200/50 blur-3xl dark:bg-indigo-600/10"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-2 lg:gap-12 lg:px-10 lg:py-24">
            <div class="max-w-xl">
                <span class="inline-flex items-center gap-2 rounded-full border border-indigo-100 bg-white/80 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm dark:border-indigo-400/20 dark:bg-slate-900/80 dark:text-indigo-300">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    Your work, in a calmer flow
                </span>
                <h1 class="mt-6 text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl dark:text-white">
                    Organize your tasks.<br>
                    <span class="bg-gradient-to-r from-indigo-600 to-blue-500 bg-clip-text text-transparent">Achieve your goals.</span>
                </h1>
                <p class="mt-6 max-w-lg text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300">
                    TaskFlow helps you manage projects, organize tasks, and see what needs attention with a flexible board built around the way you work.
                </p>
                <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:-translate-y-0.5 hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">
                        Get started free
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                    </a>
                    <a href="{{ route('features') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white/80 px-5 py-3 text-sm font-semibold text-slate-700 transition hover:border-slate-400 hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-200 dark:hover:bg-slate-900 dark:focus-visible:ring-offset-slate-950">Explore features</a>
                </div>
                <div class="mt-8 flex items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <div class="flex -space-x-2" aria-hidden="true">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-slate-50 bg-blue-100 text-xs font-bold text-blue-700 dark:border-slate-950 dark:bg-blue-400/20 dark:text-blue-200">P</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-slate-50 bg-violet-100 text-xs font-bold text-violet-700 dark:border-slate-950 dark:bg-violet-400/20 dark:text-violet-200">T</span>
                        <span class="flex h-8 w-8 items-center justify-center rounded-full border-2 border-slate-50 bg-emerald-100 text-xs font-bold text-emerald-700 dark:border-slate-950 dark:bg-emerald-400/20 dark:text-emerald-200">✓</span>
                    </div>
                    <span>A simple place to move good work forward.</span>
                </div>
            </div>

            <div class="relative mx-auto w-full max-w-xl" aria-label="Illustration of a person planning tasks at a desk">
                <div class="absolute inset-10 rounded-full bg-indigo-200/70 blur-3xl dark:bg-indigo-500/15"></div>
                <svg class="relative w-full drop-shadow-xl" viewBox="0 0 600 450" role="img" aria-labelledby="hero-art-title">
                    <title id="hero-art-title">A person organizing tasks on a board</title>
                    <defs>
                        <linearGradient id="board" x1="120" y1="70" x2="440" y2="370" gradientUnits="userSpaceOnUse"><stop stop-color="#fff"/><stop offset="1" stop-color="#eef2ff"/></linearGradient>
                        <linearGradient id="screen" x1="280" y1="280" x2="450" y2="370" gradientUnits="userSpaceOnUse"><stop stop-color="#6366f1"/><stop offset="1" stop-color="#2563eb"/></linearGradient>
                        <linearGradient id="shirt" x1="400" y1="280" x2="520" y2="390" gradientUnits="userSpaceOnUse"><stop stop-color="#818cf8"/><stop offset="1" stop-color="#4f46e5"/></linearGradient>
                    </defs>
                    <ellipse cx="304" cy="402" rx="235" ry="22" fill="#c7d2fe" opacity=".48"/>
                    <circle cx="302" cy="212" r="171" fill="#eef2ff" opacity=".72"/>
                    <path d="M102 189c-22-47-7-89 23-106 30 21 43 64 21 106-12 22-29 36-38 43-3-15-3-29-6-43Z" fill="#bfdbfe"/>
                    <path d="M142 202c-6-51 20-89 57-95 19 33 11 77-25 108-18 15-38 21-50 24 5-14 12-25 18-37Z" fill="#93c5fd"/>
                    <path d="M120 257c-8-49-34-73-69-74-12 34 5 69 42 88 19 10 37 12 50 12-8-10-16-17-23-26Z" fill="#a5b4fc"/>
                    <path d="M124 174v163" stroke="#22a06b" stroke-width="5" stroke-linecap="round"/><path d="m124 254-24-41m24 16 27-38m-27 82-29-20" stroke="#22a06b" stroke-width="4" stroke-linecap="round"/>
                    <path d="M82 327h88l-9 57h-70l-9-57Z" fill="#f59e0b"/><path d="M76 327h100c-2 12-12 20-25 20h-50c-13 0-23-8-25-20Z" fill="#fbbf24"/>
                    <rect x="201" y="65" width="246" height="242" rx="22" fill="url(#board)" stroke="#dbe3f6" stroke-width="3"/>
                    <rect x="222" y="91" width="204" height="35" rx="10" fill="#eef2ff"/><circle cx="241" cy="108" r="7" fill="#4f46e5"/><path d="M258 108h102" stroke="#a5b4fc" stroke-width="8" stroke-linecap="round"/><circle cx="400" cy="108" r="5" fill="#c7d2fe"/>
                    <rect x="225" y="147" width="25" height="25" rx="7" fill="#e0e7ff" stroke="#818cf8" stroke-width="2"/><path d="m231 159 5 5 9-11" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M266 157h131" stroke="#94a3b8" stroke-width="7" stroke-linecap="round"/>
                    <rect x="225" y="190" width="25" height="25" rx="7" fill="#e0e7ff" stroke="#818cf8" stroke-width="2"/><path d="m231 202 5 5 9-11" stroke="#4f46e5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M266 200h113" stroke="#94a3b8" stroke-width="7" stroke-linecap="round"/>
                    <rect x="225" y="233" width="25" height="25" rx="7" fill="#fff" stroke="#cbd5e1" stroke-width="2"/><path d="M266 243h94" stroke="#cbd5e1" stroke-width="7" stroke-linecap="round"/>
                    <rect x="315" y="49" width="58" height="31" rx="10" fill="#4f46e5"/><circle cx="344" cy="49" r="7" fill="#818cf8"/><path d="M335 64h18" stroke="white" stroke-width="4" stroke-linecap="round"/>
                    <path d="M402 303c6-39 28-61 61-61 36 0 65 25 71 63l9 44H386l16-46Z" fill="url(#shirt)"/>
                    <path d="M453 236c-17-5-29-22-27-44 2-23 20-39 42-37 23 2 38 21 35 43l-3 17c-4 22-24 31-47 21Z" fill="#f6c6a8"/>
                    <path d="M425 193c-3-28 16-49 44-49 24 0 42 17 41 42-12-3-22-10-29-20-11 14-29 23-56 27Z" fill="#1e293b"/>
                    <path d="M433 213c-8-10-18-11-24-4-6 8-2 22 8 28l14 5 2-29Zm57 1c8-9 18-8 23 0 5 9-1 22-11 27l-13 3 1-30Z" fill="#f6c6a8"/>
                    <path d="M444 250c7 8 16 12 26 12 10 0 20-4 28-12l10 39-40 31-38-31 14-39Z" fill="#f6c6a8"/>
                    <path d="M465 281c14 14 27 17 41 13l27 26-14 39h-99l-11-43 29-23 27 18 27-20" fill="url(#shirt)"/>
                    <path d="M274 327h205l36 46H233l41-46Z" fill="url(#screen)"/><path d="M233 373h282l-17 17H250l-17-17Z" fill="#334155"/><path d="M335 382h78" stroke="#94a3b8" stroke-width="4" stroke-linecap="round"/>
                    <circle cx="494" cy="103" r="23" fill="#fff"/><path d="m484 103 7 7 14-16" stroke="#22a06b" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                    <circle cx="184" cy="276" r="10" fill="#818cf8"/><circle cx="472" cy="195" r="7" fill="#60a5fa"/><path d="m474 95 5-12m-11 6 12 5" stroke="#f59e0b" stroke-width="4" stroke-linecap="round"/>
                </svg>
                <div class="absolute bottom-7 left-3 rounded-xl border border-slate-200 bg-white/95 px-4 py-3 shadow-lg dark:border-slate-700 dark:bg-slate-900/95 sm:left-0">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg></span>
                        <span><span class="block text-xs text-slate-500 dark:text-slate-400">Progress made</span><span class="block text-sm font-semibold text-slate-900 dark:text-white">One step at a time</span></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="border-y border-slate-200 bg-white py-12 dark:border-slate-800 dark:bg-slate-900/50">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-[.16em] text-indigo-600 dark:text-indigo-300">A clearer way to work</p>
                <h2 class="mt-3 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl dark:text-white">Everything you need to keep moving</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base dark:text-slate-300">Bring your projects and tasks together, then shape your workflow as your plans change.</p>
            </div>
            <div class="mt-10 grid gap-5 md:grid-cols-3">
                @foreach ([['Projects with purpose', 'Keep related work in one place with project details and a clear overview.', 'folder'], ['A board that fits your flow', 'Group tasks into your own workflow columns and move work forward.', 'board'], ['Know what comes next', 'Use priorities and due dates to keep important work visible.', 'calendar']] as [$title, $description, $icon])
                    <article class="rounded-2xl border border-slate-200 bg-slate-50 p-6 transition hover:-translate-y-1 hover:border-indigo-200 hover:shadow-lg hover:shadow-indigo-950/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-400/30">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-100 text-indigo-700 dark:bg-indigo-400/10 dark:text-indigo-300">
                            @if ($icon === 'folder')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7a2 2 0 0 1 2-2h5l2 2h7a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                            @elseif ($icon === 'board')<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M9 4v16m6-16v16M5.5 8h1m5 0h1m5 0h1"/></svg>
                            @else<svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path stroke-linecap="round" d="M7 3v4m10-4v4M3 10h18m-12 5 2 2 4-4"/></svg>@endif
                        </span>
                        <h3 class="mt-5 text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $description }}</p>
                    </article>
                @endforeach
            </div>
            <div class="mt-8 text-center"><a href="{{ route('features') }}" class="text-sm font-semibold text-indigo-600 hover:text-indigo-700 dark:text-indigo-300 dark:hover:text-indigo-200">See all features <span aria-hidden="true">→</span></a></div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-16 lg:px-10">
        <div class="flex flex-col gap-6 rounded-3xl bg-gradient-to-r from-indigo-600 to-blue-600 px-6 py-9 text-white shadow-xl shadow-indigo-900/15 sm:flex-row sm:items-center sm:justify-between sm:px-10">
            <div><h2 class="text-2xl font-bold tracking-tight">Ready to find your flow?</h2><p class="mt-2 max-w-xl text-sm leading-6 text-indigo-100">Create your workspace and start turning plans into progress.</p></div>
            <a href="{{ route('register') }}" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-white px-5 py-3 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-indigo-600">Create your free account</a>
        </div>
    </section>
@endsection
