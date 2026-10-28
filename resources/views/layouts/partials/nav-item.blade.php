@php
    $patterns = (array) ($active ?? $route);
    $isActive = request()->routeIs(...$patterns);
    $count = (int) ($navBadge ?? 0);
@endphp
<a href="{{ route($route) }}"
   class="relative flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition
   {{ $isActive ? 'bg-brand-600 text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
    @if($isActive)
        <span class="absolute left-0 top-2 bottom-2 w-1 rounded-r bg-gold-400"></span>
    @endif
    <x-dynamic-component :component="'lucide-' . $icon" class="w-5 h-5" />
    <span class="flex-1">{{ $label }}</span>
    @if($count > 0)
        <span class="min-w-5 h-5 px-1.5 rounded-full bg-danger-500 text-white text-[11px] font-semibold flex items-center justify-center">{{ $count > 99 ? '99+' : $count }}</span>
    @endif
</a>