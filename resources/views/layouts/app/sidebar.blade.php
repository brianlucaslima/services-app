<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @PwaHead
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="calendar" :href="route('agenda')" :current="request()->routeIs('agenda')" wire:navigate>
                        {{ __('Agenda') }}
                    </flux:sidebar.item>

                    @if(auth()->user()->role === 'management')
                        <flux:sidebar.item icon="banknotes" :href="route('invoices')" :current="request()->routeIs('invoices')" wire:navigate>
                            {{ __('Invoices') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="briefcase" :href="route('service-types')" :current="request()->routeIs('service-types')" wire:navigate>
                            {{ __('Services') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="users" :href="route('collaborators')" :current="request()->routeIs('collaborators')" wire:navigate>
                            {{ __('Collaborators') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="document-chart-bar" :href="route('reports')" :current="request()->routeIs('reports')" wire:navigate>
                            {{ __('Payout Reports') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item icon="building-office" :href="route('company.edit')" :current="request()->routeIs('company.edit')" wire:navigate>
                            {{ __('Company Settings') }}
                        </flux:sidebar.item>

                        <flux:sidebar.item
                            :href="route('customers')"
                            :current="request()->routeIs('customers')"
                            wire:navigate
                            icon="users"
                        >
                            {{ __('Customers') }}
                        </flux:sidebar.item>
                    @endif
                    @if(auth()->user()->role === 'superadmin')
                        <flux:sidebar.item icon="shield-check" :href="route('superadmin')" :current="request()->routeIs('superadmin')" wire:navigate>
                            {{ __('Superadmin') }}
                        </flux:sidebar.item>
                    @endif
                </flux:sidebar.group>
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    @if(auth()->user()->companies->count() > 1)
                        <flux:menu.heading>{{ __('Switch Company') }}</flux:menu.heading>
                        @foreach(auth()->user()->companies as $c)
                            <flux:menu.item :href="route('company.switch', ['id' => $c->id])" icon="{{ $c->id === auth()->user()->company_id ? 'check' : 'building-office' }}">
                                {{ $c->name }}
                            </flux:menu.item>
                        @endforeach
                        <flux:menu.separator />
                    @endif

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
