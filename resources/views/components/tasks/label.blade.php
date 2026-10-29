@props(['label'])

<span @class([
    'inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold',
    'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' => $label->color === 'slate',
    'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300' => $label->color === 'blue',
    'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $label->color === 'emerald',
    'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $label->color === 'amber',
    'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' => $label->color === 'red',
    'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300' => $label->color === 'violet',
    'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $label->color === 'rose',
])>{{ $label->name }}</span>
