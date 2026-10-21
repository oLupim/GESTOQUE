<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CriarUsuario extends Command
{
    protected $signature = 'gestoque:usuario {--nome=} {--email=} {--senha=}';

    protected $description = 'Cria a conta de acesso do dono da oficina';

    public function handle(): int
    {
        // Sem as opções, o comando pergunta. A senha não aparece na tela.
        $dados = [
            'name' => $this->option('nome') ?? text('Nome', required: true),
            'email' => $this->option('email') ?? text('E-mail', required: true),
            'password' => $this->option('senha') ?? password('Senha (mínimo 8 caracteres)', required: true),
        ];

        $validador = Validator::make($dados, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
        ], [
            'email.unique' => 'Já existe uma conta com esse e-mail.',
            'password.min' => 'A senha precisa ter pelo menos 8 caracteres.',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $erro) {
                $this->error($erro);
            }

            return self::FAILURE;
        }

        // O cast 'hashed' do User criptografa a senha.
        $user = User::create($dados + ['perfil' => 'administrador', 'ativo' => true]);

        $this->info("Conta criada: {$user->name} <{$user->email}>");

        return self::SUCCESS;
    }
}