<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se a conta for desativada enquanto a pessoa está logada,
 * ela é desconectada na próxima ação (o login já bloqueia contas desativadas).
 */
class BloquearContaDesativada
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Compara com false de propósito: um usuário recém-criado sem o campo carregado (null) não é bloqueado.
        if ($user && $user->ativo === false) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors(['email' => 'Sua conta foi desativada. Fale com o administrador.']);
        }

        return $next($request);
    }
}
