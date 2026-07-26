<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;

class AppController extends Controller
{
    /**
     * Alterna o idioma preferido do usuário e da sessão ativa.
     */
    public function switchLanguage(string $locale)
    {
        if (in_array($locale, ['en_GB', 'pt_BR'])) {
            session(['locale' => $locale]);

            if (Auth::check()) {
                Auth::user()->update(['locale' => $locale]);
            }
        }

        return redirect()->back();
    }

    /**
     * Alterna a empresa/área de trabalho ativa do usuário.
     */
    public function switchCompany(int $id)
    {
        $user = Auth::user();

        if ($user->companies()->where('companies.id', $id)->exists()) {
            $user->update(['company_id' => $id]);
        }

        return redirect()->route('dashboard');
    }
}
