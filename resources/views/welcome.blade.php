<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Welcome') }} - {{ config('app.name', 'Invo Ease') }}</title>

        <link rel="icon" href="/favicon.png" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-50 dark:bg-zinc-950 text-zinc-900 dark:text-zinc-100 min-h-screen flex flex-col font-sans antialiased selection:bg-zinc-900 selection:text-white dark:selection:bg-white dark:selection:text-zinc-900">

        <!-- Header -->
        <header class="border-b border-zinc-200/80 dark:border-zinc-800/50 bg-white/80 dark:bg-zinc-900/80 backdrop-blur sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <x-app-logo-icon-text-white class="h-10 w-auto" />
                </div>

                <nav class="flex items-center gap-4">
                    <!-- Language Switcher -->
                    <div class="flex items-center border border-zinc-200 dark:border-zinc-800 rounded-xl overflow-hidden bg-zinc-100 dark:bg-zinc-900/60 p-0.5 shadow-sm">
                        <a href="{{ route('lang.switch', ['locale' => 'en_GB']) }}" class="px-2 py-1 text-xs font-bold rounded-lg transition-all {{ app()->getLocale() === 'en_GB' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white' }}">
                            🇬🇧 EN
                        </a>
                        <a href="{{ route('lang.switch', ['locale' => 'pt_BR']) }}" class="px-2 py-1 text-xs font-bold rounded-lg transition-all {{ app()->getLocale() === 'pt_BR' ? 'bg-white dark:bg-zinc-800 text-zinc-900 dark:text-white shadow-sm' : 'text-zinc-500 hover:text-zinc-900 dark:hover:text-white' }}">
                            🇧🇷 PT
                        </a>
                    </div>

                    @auth
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                            {{ app()->getLocale() === 'pt_BR' ? 'Ir para o Painel' : 'Go to Dashboard' }}
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-semibold text-zinc-600 dark:text-zinc-400 hover:text-zinc-900 dark:hover:text-white transition">
                            {{ app()->getLocale() === 'pt_BR' ? 'Entrar' : 'Log in' }}
                        </a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                                {{ app()->getLocale() === 'pt_BR' ? 'Começar Grátis' : 'Start Free' }}
                            </a>
                        @endif
                    @endauth
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <section class="relative overflow-hidden py-20 lg:py-32 border-b border-zinc-200/50 dark:border-zinc-800/30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center space-y-6">
                @if(app()->getLocale() === 'pt_BR')
                    <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-900 px-3 py-1 text-xs font-semibold text-zinc-600 dark:text-zinc-400 border dark:border-zinc-800">
                        ⚡ Nova Versão Multi-Idioma Disponível
                    </span>
                    <h1 class="text-4xl sm:text-6xl font-black tracking-tight text-zinc-900 dark:text-white max-w-4xl mx-auto leading-tight">
                        Gerencie seu Negócio de Serviços e Equipes com <span class="bg-gradient-to-r from-emerald-600 to-teal-500 bg-clip-text text-transparent">Facilidade Absoluta</span>
                    </h1>
                    <p class="text-base sm:text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto leading-relaxed">
                        A plataforma unificada para agendamento recorrente, rateio de horas para colaboradores, emissão de invoices profissionais em libras e acompanhamento financeiro semanal.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-6 py-3 text-base font-bold text-white shadow-md transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                                Ir para o Painel de Controle
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-6 py-3 text-base font-bold text-white shadow-md transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                                Cadastrar Empresa Gratuitamente
                            </a>
                            <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-white dark:bg-zinc-900 px-6 py-3 text-base font-bold text-zinc-900 dark:text-white border border-zinc-200 dark:border-zinc-800 shadow-sm transition hover:bg-zinc-50 dark:hover:bg-zinc-800/80">
                                Entrar na Conta
                            </a>
                        @endauth
                    </div>
                @else
                    <span class="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-900 px-3 py-1 text-xs font-semibold text-zinc-600 dark:text-zinc-400 border dark:border-zinc-800">
                        ⚡ New Multi-Language Version Available
                    </span>
                    <h1 class="text-4xl sm:text-6xl font-black tracking-tight text-zinc-900 dark:text-white max-w-4xl mx-auto leading-tight">
                        Manage Your Service Business & Teams with <span class="bg-gradient-to-r from-emerald-600 to-teal-500 bg-clip-text text-transparent">Absolute Ease</span>
                    </h1>
                    <p class="text-base sm:text-lg text-zinc-600 dark:text-zinc-400 max-w-2xl mx-auto leading-relaxed">
                        The unified platform for recurring scheduling, team hour-split, professional British invoice generation, and weekly financial tracking.
                    </p>
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-6 py-3 text-base font-bold text-white shadow-md transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                                Go to Dashboard Area
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-zinc-900 px-6 py-3 text-base font-bold text-white shadow-md transition hover:bg-zinc-800 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100">
                                Register Your Business Free
                            </a>
                            <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-white dark:bg-zinc-900 px-6 py-3 text-base font-bold text-zinc-900 dark:text-white border border-zinc-200 dark:border-zinc-800 shadow-sm transition hover:bg-zinc-50 dark:hover:bg-zinc-800/80">
                                Log in to Account
                            </a>
                        @endauth
                    </div>
                @endif
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-20 lg:py-32 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-16">
            <div class="text-center space-y-4">
                @if(app()->getLocale() === 'pt_BR')
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                        Tudo que você precisa para decolar sua operação
                    </h2>
                    <p class="text-zinc-500 dark:text-zinc-400 max-w-2xl mx-auto text-sm sm:text-base">
                        Esqueça as planilhas e anotações em papel. Tenha controle absoluto sobre clientes, equipes e faturamentos.
                    </p>
                @else
                    <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-zinc-900 dark:text-white">
                        Everything you need to scale your operation
                    </h2>
                    <p class="text-zinc-500 dark:text-zinc-400 max-w-2xl mx-auto text-sm sm:text-base">
                        Forget spreadsheets and paper notes. Have absolute control over customers, teams, and invoicing.
                    </p>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Card 1 -->
                <div class="bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 hover:border-zinc-900 dark:hover:border-white transition-all duration-300 shadow-sm space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 flex items-center justify-center font-bold">
                        📅
                    </div>
                    @if(app()->getLocale() === 'pt_BR')
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Agenda Semanal Inteligente</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Controle de calendário compacto por semana. Navegue, reagende serviços ou marque ausências/pulos temporários com 1 clique, sem afetar o cronograma padrão do endereço do cliente.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Smart Weekly Schedule</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Compact calendar tracking by week. Navigate, reschedule services, or skip occurrences on the fly without breaking the address's master recurring pattern.
                        </p>
                    @endif
                </div>

                <!-- Card 2 -->
                <div class="bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 hover:border-zinc-900 dark:hover:border-white transition-all duration-300 shadow-sm space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 flex items-center justify-center font-bold">
                        📍
                    </div>
                    @if(app()->getLocale() === 'pt_BR')
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Locais e Calendários Dinâmicos</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Cada cliente pode ter múltiplos locais de serviço. Gerencie e crie calendários e categorias de locais customizados (Residencial, Comercial, Industrial, etc.) e defina taxas horárias de equipe personalizadas de acordo com cada tipo.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Multi-Location & Dynamic Calendars</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Each customer can have multiple service addresses. Manage and create custom calendar categories (House, Office, Industrial, etc.) and configure specific collaborator hourly rates dynamically for each calendar type.
                        </p>
                    @endif
                </div>

                <!-- Card 3 -->
                <div class="bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 hover:border-zinc-900 dark:hover:border-white transition-all duration-300 shadow-sm space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 flex items-center justify-center font-bold">
                        🤝
                    </div>
                    @if(app()->getLocale() === 'pt_BR')
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Rateio e Equipes Designadas</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Designe múltiplos colaboradores para um serviço. O sistema rateia as horas do local igualmente entre a equipe e calcula automaticamente o pagamento individual com base no valor de hora de cada um.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Team Allocation & Hour Split</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Assign multiple collaborators to a job. The system splits the total duration equally among the team and calculates individual payouts based on their personal hourly rate.
                        </p>
                    @endif
                </div>

                <!-- Card 4 -->
                <div class="bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 hover:border-zinc-900 dark:hover:border-white transition-all duration-300 shadow-sm space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 flex items-center justify-center font-bold">
                        🧾
                    </div>
                    @if(app()->getLocale() === 'pt_BR')
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Cotações, Faturas & PDF Lifecycle</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Monte cotações profissionais detalhadas com anotações por item, envie-as por e-mail e converta-as em faturas com um clique. Baixe rascunhos de faturas, envie documentos finais com o e-mail da sua empresa em destaque e controle pagamentos recebidos de forma simples.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Quotes, Invoices & PDF Lifecycle</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Create detailed professional quotes and estimates with per-item notes, send them by email, and convert them to invoice drafts with a single click. Download draft invoices, send final documents with your company email highlighted, and easily track paid status.
                        </p>
                    @endif
                </div>

                <!-- Card 5 -->
                <div class="bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 hover:border-zinc-900 dark:hover:border-white transition-all duration-300 shadow-sm space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 flex items-center justify-center font-bold">
                        🎨
                    </div>
                    @if(app()->getLocale() === 'pt_BR')
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Identidade Visual Customizada</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Personalize sua empresa definindo seu logotipo, dados bancários de recebimento, mensagem padrão e uma **Cor de Marca** que se aplica instantaneamente a todas as faturas e relatórios emitidos.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Custom Brand Colors</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Customize your business profile by uploading a logo, defining bank transfer details, default invoice messages, and a **Primary Brand Color** applied instantly to all generated PDFs.
                        </p>
                    @endif
                </div>

                <!-- Card 6 -->
                <div class="bg-white dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-800 rounded-3xl p-6 hover:border-zinc-900 dark:hover:border-white transition-all duration-300 shadow-sm space-y-4">
                    <div class="w-10 h-10 rounded-xl bg-zinc-950 dark:bg-white text-white dark:text-zinc-950 flex items-center justify-center font-bold">
                        🔒
                    </div>
                    @if(app()->getLocale() === 'pt_BR')
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Dois Níveis de Acesso</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Gestores possuem acesso completo para gerenciar toda a empresa. Colaboradores comuns possuem acesso básico e restrito, visualizando apenas seus serviços designados e seu faturamento líquido a receber.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Two Levels of Access</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Managers get full system control. Regular collaborators get basic and restricted views, displaying only their assigned jobs and personal weekly payout reports.
                        </p>
                    @endif
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="mt-auto border-t border-zinc-200 dark:border-zinc-800 bg-white dark:bg-zinc-900/30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                <p>&copy; {{ date('Y') }} Invo Ease. {{ app()->getLocale() === 'pt_BR' ? 'Todos os direitos reservados.' : 'All rights reserved.' }}</p>
                <div class="flex items-center gap-3">
                    <span>{{ app()->getLocale() === 'pt_BR' ? 'Idioma' : 'Language' }}:</span>
                    <strong class="text-zinc-900 dark:text-white uppercase">{{ app()->getLocale() }}</strong>
                </div>
            </div>
        </footer>

    </body>
</html>
