# Publicando o Gestoque no Laravel Cloud

Este guia mostra, passo a passo, como colocar o Gestoque no ar usando o **Laravel Cloud**, com banco **PostgreSQL** e arquivos guardados em **buckets** (Object Storage compatível com S3).

> Os nomes de botões e menus podem mudar um pouco com o tempo. Se algo não estiver exatamente onde o guia diz, procure pelo nome mais parecido na mesma tela.

---

## Antes de começar: por que buckets?

No Laravel Cloud, o disco do servidor é **apagado a cada deploy**. Por isso nenhum arquivo enviado ou gerado pode ficar no disco local. O Gestoque usa dois buckets:

| Bucket | Tipo | Para quê | Como o app encontra |
|---|---|---|---|
| `fiscal` | **Privado** | XML e DANFE das notas | É o disco padrão (`FILESYSTEM_DISK`) |
| `imagens` | **Público** | Logos das empresas (e, no futuro, fotos de produtos) | `IMAGENS_DISK=imagens` |

O XML e o DANFE nunca ficam com link público: o download sempre passa pelo sistema, que confere o login.

---

## 1. Criar a conta e o app

1. Acesse **cloud.laravel.com** e crie uma conta (pode entrar com o GitHub).
2. Crie uma **organização** se ele pedir.
3. Clique em **New application** (Novo app).
4. Conecte o GitHub e escolha o repositório **oLupim/GESTOQUE**, branch **main**.
5. Dê o nome `gestoque` ao app.

## 2. Escolher a região

Na criação do app, escolha a **região mais próxima do Brasil** que aparecer na lista:

- Se existir **São Paulo** (`sa-east-1`), use essa.
- Se não existir, **US East (N. Virginia)** costuma ter a menor latência para o Brasil.

O banco e os buckets devem ficar na **mesma região** do app.

## 3. Conferir versão do PHP e comandos

No ambiente (environment) `main`, abra **Settings** (Configurações) → **Deployments** (ou "Build & Deploy").

- **Versão do PHP:** 8.2 (a mesma usada no PC e no GitHub Actions).
- **Comandos de build** (rodam antes de publicar):

  ```
  composer install --no-dev --optimize-autoloader
  npm ci && npm run build
  ```

- **Comando de deploy** (roda a cada publicação, já com o banco ligado):

  ```
  php artisan migrate --force && php artisan gestoque:admin-padrao
  ```

  O segundo comando cria o administrador padrão (passo 6) **só se ainda não existir nenhum administrador**. Nos deploys seguintes ele não faz nada, então pode ficar aí para sempre.

> Não coloque `php artisan db:seed` aqui. O seeder cria usuários de teste com senha `password` e, por segurança, ele se recusa a rodar em produção.

## 4. Adicionar o banco PostgreSQL

1. No ambiente, clique em **Add resource** (Adicionar recurso) → **Database**.
2. Escolha **PostgreSQL** (Laravel Serverless Postgres).
3. Use o menor tamanho disponível; é suficiente para a oficina.
4. Ligue o banco ao ambiente. O Cloud preenche sozinho `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`. **Não digite essas variáveis à mão.**

> O Postgres serverless pode "hibernar" quando ninguém usa o sistema. O primeiro acesso depois disso demora alguns segundos a mais. É normal.

## 5. Criar os dois buckets

No ambiente, clique em **Add resource** → **Object Storage** (Bucket).

**Bucket 1: `fiscal`**
- Nome: `fiscal`
- Visibilidade: **Private** (privado)
- Marque a opção de usar como **disco padrão** (default disk). Assim o Cloud define `FILESYSTEM_DISK` sozinho.

**Bucket 2: `imagens`**
- Nome: `imagens`
- Visibilidade: **Public** (público)
- **Disk name:** `imagens` (precisa ser exatamente esse nome)
- **Não** marque como disco padrão.

O Cloud cria sozinho a configuração e as chaves de acesso dos dois discos. Você não precisa copiar nenhuma chave.

## 6. Variáveis de ambiente

Em **Settings** → **Environment variables** (Variáveis de ambiente), adicione:

```
APP_NAME=Gestoque
APP_ENV=production
APP_DEBUG=false
APP_LOCALE=pt_BR
OFICINA_NOME="Nome da oficina"
OFICINA_WHATSAPP=55DDDNUMERO
OFICINA_ENDERECO="Cidade/UF"
IMAGENS_DISK=imagens
ADMIN_NOME="Nome do Dono"
ADMIN_EMAIL=email@exemplo.com
ADMIN_SENHA=UmaSenhaForte123
```

Observações:
- `ADMIN_*` é o **administrador padrão**: a primeira conta, criada no deploy. A senha precisa ter pelo menos 8 caracteres. Ela fica só aqui no painel do Cloud, nunca no GitHub.
- **`APP_DEBUG=false` é obrigatório.** Com `true`, uma página de erro mostraria detalhes internos do sistema para qualquer pessoa.
- `APP_KEY` é gerada pelo Cloud. Não troque depois que o sistema estiver em uso: as senhas continuam funcionando, mas o token da Brasil NFe (criptografado com essa chave) deixa de abrir.
- Confira se `APP_URL` está com o endereço `https://...` do app. Se estiver vazio ou diferente, defina com o endereço que aparece no topo do painel.
- `FISCAL_DISK` não precisa ser definida: sem ela, o sistema usa o disco padrão (o bucket `fiscal`).

Depois de salvar as variáveis, clique em **Deploy** para publicar.

## 7. Definir um limite de gastos

Para não ter surpresa na fatura:

1. Abra as configurações da **organização** → **Billing** (Cobrança) / **Usage**.
2. Defina um **limite de gastos** (spend limit) mensal, por exemplo o valor do plano mais uma pequena margem.
3. Ative os avisos por e-mail quando o uso chegar perto do limite.

## 8. Contas de acesso

O sistema não tem cadastro público: ninguém de fora consegue criar conta.

**Primeira conta (administrador padrão).** Ela é criada sozinha no primeiro deploy, com os dados de `ADMIN_*` do passo 6. Se o deploy já tinha rodado antes de você definir essas variáveis, clique em **Deploy** de novo.

Depois do primeiro login:
1. Troque a senha em **Configurações → Senha**.
2. Apague a variável `ADMIN_SENHA` do painel; ela não é mais necessária.

**Contas de funcionários.** Entre como administrador e abra **Usuários** no menu:
- **Novo usuário:** nome, e-mail, perfil (Administrador ou Mecânico) e senha.
- **Editar:** troca de dados, perfil ou senha (senha em branco mantém a atual).
- **Desativar:** desmarque "Conta ativa". A pessoa é desconectada na hora e não consegue mais entrar. Contas não são excluídas porque vendas e movimentações guardam quem fez cada operação.

> Todos os perfis enxergam os mesmos dados da oficina; só o administrador vê o menu **Usuários**.

**Plano B (sem variáveis):** na aba **Commands** do ambiente, rode
`php artisan gestoque:usuario --nome="..." --email="..." --senha="..."` e depois troque a senha pela tela.

## 9. Checklist depois do deploy

Abra o endereço do app e confira, nesta ordem:

- [ ] `https://SEU-APP/up` responde (página de "Application up").
- [ ] **Login** com o administrador padrão (`ADMIN_EMAIL` / `ADMIN_SENHA`).
- [ ] **Usuários:** criar uma conta de mecânico, entrar com ela numa janela anônima e conferir que o menu **Usuários** não aparece.
- [ ] **Configurações → Empresa:** salvar o nome e enviar um logo. O logo aparece no menu e no canto superior direito (vem do bucket `imagens`).
- [ ] **Produtos:** cadastrar um produto.
- [ ] **Entradas:** dar entrada no estoque desse produto.
- [ ] **Vendas → Nova venda:** fazer uma venda com o produto. O saldo diminui em **Estoque**.
- [ ] **Serviços:** abrir uma OS com cliente e moto, adicionar uma peça, finalizar.
- [ ] **Catálogo público:** abrir `/catalogo` numa janela anônima (sem login). O produto aparece sem custo e sem quantidade.
- [ ] **Configurações → Fiscal:** preencher CNPJ, regime, **Ambiente = Homologação** e o token da Brasil NFe.
- [ ] **Emissão de NFC-e em homologação:** emitir a nota da venda de teste. Se a SEFAZ rejeitar, a mensagem aparece na tela Fiscal (isso confirma que a integração está funcionando).
- [ ] **Download de XML e DANFE:** com uma nota autorizada, baixar os dois arquivos pela tela Fiscal (vêm do bucket `fiscal`).
- [ ] Fazer um novo deploy e conferir que o logo e os arquivos fiscais **continuam lá**.

## 10. Ver os logs

Quando algo der erro (por exemplo, "Server Error" na tela):

1. No ambiente, abra a aba **Logs**.
2. Procure a linha vermelha mais recente, perto do horário do erro. Ela diz o arquivo e a linha do problema.
3. Os logs do **deploy** (build e migrations) ficam em **Deployments** → clique no deploy → veja a saída de cada etapa.

## 11. Voltar para o deploy anterior

Se um deploy novo quebrar o sistema:

1. Abra **Deployments**.
2. Encontre o último deploy que funcionava (marcado como sucesso).
3. Use **Redeploy** / **Rollback** nesse deploy.

Atenção: voltar o deploy **não desfaz migrations**. Se o deploy problemático alterou o banco, o código antigo pode não combinar com o banco novo. Por isso:

- Prefira corrigir o código e fazer um novo push.
- Ou desfaça o commit com `git revert <commit>` e faça push: o Cloud publica a versão corrigida automaticamente.

---

## Resumo do que roda onde

| Item | No PC (desenvolvimento) | No Laravel Cloud |
|---|---|---|
| Banco | SQLite (`database/database.sqlite`) | PostgreSQL |
| XML/DANFE | `storage/app/private` (disco `local`) | bucket privado `fiscal` |
| Logos | `storage/app/public` (disco `public`) | bucket público `imagens` |
| Testes | `php.exe artisan test` | GitHub Actions (SQLite **e** PostgreSQL 16) |
