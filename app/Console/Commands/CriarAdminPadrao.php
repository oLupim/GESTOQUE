<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Cria o administrador padrão a partir do .env (ADMIN_NOME, ADMIN_EMAIL, ADMIN_SENHA).
 * Pode rodar em todo deploy: se já existe um administrador, não faz nada.
 */
class CriarAdminPadrao extends Command
{
    protected $signature = 'gestoque:admin-padrao';

    protected $description = 'Cria o administrador padrão definido no .env, se ainda não existir nenhum administrador';

    public function handle(): int
    {
        if (User::where('perfil', 'administrador')->exists()) {
            $this->info('Já existe um administrador. Nada a fazer.');

            return self::SUCCESS;
        }

        $dados = [
            'name' => config('gestoque.admin.nome'),
            'email' => config('gestoque.admin.email'),
            'password' => config('gestoque.admin.senha'),
        ];

        if (blank($dados['email']) || blank($dados['password'])) {
            $this->warn('ADMIN_EMAIL e ADMIN_SENHA não estão definidos. Nenhuma conta foi criada.');

            return self::SUCCESS;
        }

        $validador = Validator::make($dados, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ], [
            'email.unique' => 'Já existe uma conta (não administradora) com o ADMIN_EMAIL.',
            'password.min' => 'ADMIN_SENHA precisa ter pelo menos 8 caracteres.',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        // O cast 'hashed' do User criptografa a senha.
        $user = User::create($dados + ['perfil' => 'administrador', 'ativo' => true]);

        $this->info("Administrador criado: {$user->name} <{$user->email}>");

        return self::SUCCESS;
    }
}
