<x-layouts.auth.card>
    <div class="space-y-6">
        <div class="text-center space-y-2">
            <h1 class="text-2xl font-bold tracking-tight text-zinc-900 dark:text-white">
                {{ __('Subscription Expired') }}
            </h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Your trial period or subscription has expired.') }}
            </p>
        </div>

        <div class="bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-2xl p-6 text-center space-y-4">
            <p class="text-sm text-zinc-600 dark:text-zinc-400 leading-relaxed">
                {{ __('To reactivate your account and regain access to all features of invoease.co.uk, please contact our support team to activate your manual subscription.') }}
            </p>
            @if(auth()->user()->company)
                <div class="text-xs text-zinc-400 mt-2">
                    <strong>{{ __('Company') }}:</strong> {{ auth()->user()->company->name }}<br>
                    <strong>{{ __('Expired on') }}:</strong> {{ auth()->user()->company->subscription_ends_at ? auth()->user()->company->subscription_ends_at->format('d/m/Y H:i') : __('N/A') }}
                </div>
            @endif
        </div>

        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:button type="submit" variant="primary" class="w-full">
                {{ __('Log out') }}
            </flux:button>
        </form>
    </div>
</x-layouts.auth.card>
