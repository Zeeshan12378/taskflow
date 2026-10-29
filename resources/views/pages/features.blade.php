@extends('layouts.marketing')

@section('title', 'Features · TaskFlow')

@section('content')
    <section class="relative isolate overflow-hidden border-b border-slate-200 dark:border-slate-800">
        <div aria-hidden="true" class="absolute -right-32 -top-36 -z-10 h-96 w-96 rounded-full bg-blue-200/50 blur-3xl dark:bg-blue-600/10"></div>
        <div class="mx-auto max-w-7xl px-5 py-16 text-center sm:px-8 sm:py-20 lg:px-10">
            <span class="text-sm font-semibold uppercase tracking-[.16em] text-indigo-600 dark:text-indigo-300">Features</span>
            <h1 class="mx-auto mt-4 max-w-3xl text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl dark:text-white">A flexible workspace for the work ahead</h1>
            <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300">Plan projects, keep tasks organized, and make progress visible with tools that stay simple and focused.</p>
            <a href="{{ route('register') }}" class="mt-8 inline-flex rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-600 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-slate-950">Start using TaskFlow</a>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-14 sm:px-8 sm:py-16 lg:px-10">
        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['Projects that stay organized', 'Create projects with descriptions and keep each body of work together. Find your projects from a single overview.'],
                ['Configurable workflow boards', 'Use project-specific columns, then add, rename, reorder, or remove columns as your process changes.'],
                ['Move tasks through the work', 'View tasks on a visual board and change their status with drag and drop or the accessible status selector.'],
                ['Priorities and due dates', 'Set task priority and due dates so the next important action is easier to spot.'],
                ['A focused task list', 'Review tasks together and use search and filters to find the work you need.'],
                ['A workspace that adapts', 'Use TaskFlow on desktop or mobile, with a light or dark theme to suit your preference.'],
            ] as [$title, $description])
                <article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 dark:bg-indigo-400/10 dark:text-indigo-300"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/><circle cx="12" cy="12" r="10"/></svg></span>
                    <h2 class="mt-5 text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400">{{ $description }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="bg-indigo-50/70 dark:bg-slate-900/70">
        <div class="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-12 sm:px-8 md:flex-row md:items-center md:justify-between lg:px-10">
            <div><h2 class="text-2xl font-bold text-slate-900 dark:text-white">Make your next project easier to follow.</h2><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Start with a project, add your tasks, and choose a workflow that fits.</p></div>
            <a href="{{ route('register') }}" class="inline-flex shrink-0 justify-center rounded-lg bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700">Create an account</a>
        </div>
    </section>
@endsection
