<?php

return [
    'oficina' => env('OFICINA_NOME', 'Moto Mecânica'),
    'whatsapp' => env('OFICINA_WHATSAPP'),
    'endereco' => env('OFICINA_ENDERECO'),

    // Disco das imagens públicas (logos e, no futuro, fotos de produtos).
    // Local: 'public' (storage/app/public). Na nuvem: o bucket público "imagens".
    'disco_imagens' => env('IMAGENS_DISK', 'public'),

    // Disco dos arquivos fiscais (XML e DANFE), que são privados.
    // Local: 'local' (storage/app/private). Na nuvem: o bucket privado padrão.
    'disco_fiscal' => env('FISCAL_DISK', env('FILESYSTEM_DISK', 'local')),

    // Administrador padrão, criado por "php artisan gestoque:admin-padrao"
    // somente se ainda não existir nenhum administrador.
    'admin' => [
        'nome' => env('ADMIN_NOME', 'Administrador'),
        'email' => env('ADMIN_EMAIL'),
        'senha' => env('ADMIN_SENHA'),
    ],
];