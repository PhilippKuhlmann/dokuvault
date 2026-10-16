{{-- On / Off marker in front of a person's name. --}}
<span @class([
    'shrink-0 rounded-sm px-1.5 py-px font-mono text-[10px] font-bold uppercase tracking-wide',
    'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $type === 'onboarding',
    'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $type !== 'onboarding',
])>{{ $type === 'onboarding' ? 'On' : 'Off' }}</span>
