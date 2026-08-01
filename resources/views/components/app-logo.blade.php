@props([
    'sidebar' => false,
])

@if($sidebar)
    <a href="{{ $attributes->get('href', '#') }}" {{ $attributes->class([
        'h-10 min-w-0 flex items-center px-2 in-data-flux-sidebar-collapsed-desktop:w-10 in-data-flux-sidebar-collapsed-desktop:px-0 in-data-flux-sidebar-collapsed-desktop:justify-center',
        'in-data-flux-sidebar-collapsed-desktop:in-data-flux-sidebar-active:absolute',
        'in-data-flux-sidebar-collapsed-desktop:in-data-flux-sidebar-active:opacity-0'
    ]) }} data-flux-sidebar-brand>
        <!-- Full logo with text (shown when expanded) -->
        <x-app-logo-icon-text class="h-8 w-auto in-data-flux-sidebar-collapsed-desktop:hidden dark:hidden" />
        <x-app-logo-icon-text-white class="hidden dark:block h-8 w-auto dark:in-data-flux-sidebar-collapsed-desktop:hidden" />

        <!-- Icon only (shown when collapsed on desktop) -->
        <x-app-logo-icon class="hidden in-data-flux-sidebar-collapsed-desktop:block h-7 w-auto" />
    </a>
@else
    <a {{ $attributes->merge(['class' => 'flex items-center']) }}>
        <x-app-logo-icon-text class="h-8 w-auto dark:hidden" />
        <x-app-logo-icon-text-white class="hidden dark:block h-8 w-auto" />
    </a>
@endif
