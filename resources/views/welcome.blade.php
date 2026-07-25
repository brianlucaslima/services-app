<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ __('Welcome') }} - {{ config('app.name', 'Easy Invoices') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
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
                    <div class="w-9 h-9 rounded-xl bg-zinc-900 dark:bg-white flex items-center justify-center text-white dark:text-zinc-900 font-extrabold text-lg shadow-sm">
                        E
                    </div>
                    <span class="font-bold text-lg tracking-tight text-zinc-900 dark:text-white">Easy Invoices</span>
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
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Múltiplos Locais de Serviço</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Cada cliente pode ter múltiplos locais de serviço (Casa, Escritório). Controle de forma independente a duração, o preço por hora, data de início e de fim para cada local.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Multi-Location Customer Booking</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Each customer can have multiple service addresses (House, Office). Manage duration, hourly rates, start, and end dates independently for each location.
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
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Faturamento & PDF Lifecycle</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Gere faturas profissionais em libras a partir dos serviços confirmados. Baixe rascunhos com marca d'água "DRAFT", emita a fatura oficial, envie por e-mail e marque pagamentos recebidos de forma simples.
                        </p>
                    @else
                        <h3 class="text-lg font-bold text-zinc-900 dark:text-white">Billing & PDF Lifecycle</h3>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400 leading-relaxed">
                            Generate professional invoices in British Pounds from completed services. Download draft PDFs with watermarks, issue official documents, send by email, and track paid status easily.
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
                <p>&copy; {{ date('Y') }} Easy Invoices. {{ app()->getLocale() === 'pt_BR' ? 'Todos os direitos reservados.' : 'All rights reserved.' }}</p>
                <div class="flex items-center gap-3">
                    <span>{{ app()->getLocale() === 'pt_BR' ? 'Idioma' : 'Language' }}:</span>
                    <strong class="text-zinc-900 dark:text-white uppercase">{{ app()->getLocale() }}</strong>
                </div>
            </div>
        </footer>

    </body>
</html>
