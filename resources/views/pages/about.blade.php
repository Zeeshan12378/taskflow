@extends('layouts.marketing')

@section('title', 'About · TaskFlow')

@section('content')
    <section class="relative isolate overflow-hidden border-b border-slate-200 dark:border-slate-800">
        <div aria-hidden="true" class="absolute -left-32 -top-36 -z-10 h-96 w-96 rounded-full bg-indigo-200/50 blur-3xl dark:bg-indigo-600/10"></div>
        <div class="mx-auto max-w-7xl px-5 py-16 sm:px-8 sm:py-20 lg:px-10">
            <div class="max-w-3xl">
                <span class="text-sm font-semibold uppercase tracking-[.16em] text-indigo-600 dark:text-indigo-300">About TaskFlow</span>
                <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl dark:text-white">Good work starts with a clear next step.</h1>
                <p class="mt-6 text-base leading-7 text-slate-600 sm:text-lg dark:text-slate-300">TaskFlow is a project and task management workspace designed to make planning feel clear, adaptable, and manageable. Bring your projects into one place, shape the workflow around your team, and focus on what moves forward next.</p>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-10 px-5 py-14 sm:px-8 sm:py-16 md:grid-cols-2 lg:px-10">
        <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Our approach</span>
            <h2 class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">Flexible structure, less clutter</h2>
            <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">Every project can follow its own workflow. Keep the details useful, make status visible, and let your task board reflect the way the work actually moves.</p>
        </article>
        <article class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-300">Built as a learning project</span>
            <h2 class="mt-3 text-2xl font-bold text-slate-900 dark:text-white">Made step by step</h2>
            <p class="mt-4 text-sm leading-7 text-slate-600 dark:text-slate-400">TaskFlow is also a hands-on Laravel project. It grows incrementally while exploring Livewire, accessible interfaces, secure authentication, and practical application design.</p>
        </article>
    </section>

    <section class="mx-auto max-w-7xl px-5 pb-16 sm:px-8 lg:px-10">
        <div class="rounded-3xl bg-slate-900 px-6 py-9 text-white sm:px-10">
            <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 class="text-2xl font-bold">See how your work can flow.</h2><p class="mt-2 text-sm text-slate-300">Explore the tools or create your own workspace.</p></div>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('features') }}" class="rounded-lg border border-slate-600 px-5 py-3 text-center text-sm font-semibold text-white hover:bg-slate-800">Explore features</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-indigo-500 px-5 py-3 text-center text-sm font-semibold text-white hover:bg-indigo-400">Get started</a>
                </div>
            </div>
        </div>
    </section>
@endsection
