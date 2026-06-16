# Guia de Instalação — bubbles

Guia passo a passo para colocar o **bubbles** a correr localmente, do zero. Segue os passos pela ordem indicada.

---

## Pré-requisitos

| Ferramenta | Versão mínima |
|---|---|
| PHP | 8.2+ |
| Composer | 2.x |
| Node.js | 18+ (inclui npm) |
| MySQL | 8.0+ (ou MariaDB 10.6+) |
| Git | qualquer versão recente |

> **Windows:** o [XAMPP](https://www.apachefriends.org/) inclui PHP, MySQL e Apache numa só instalação.

Verifica as versões instaladas:

```bash
php -v
composer -V
node -v
npm -v
```

---

## Passos

### 1. Clonar o repositório

```bash
git clone <url-do-repositorio> bubbles
cd bubbles
```

### 2. Instalar dependências PHP

```bash
composer install
```

### 3. Instalar dependências JavaScript

```bash
npm install
```

### 4. Configurar o ambiente

```bash
cp .env.example .env
php artisan key:generate
```

`php artisan key:generate` gera a `APP_KEY`, usada para encriptar sessões e cookies. Nunca a partilhes.

### 5. Configurar o `.env`

Abre o `.env` e preenche os grupos de variáveis abaixo.

#### Base de dados (`DB_*`)

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bubbles
DB_USERNAME=root
DB_PASSWORD=
```

Cria a base de dados antes de continuar:

```bash
mysql -u root -e "CREATE DATABASE bubbles CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

#### Cloudinary — upload de imagens (`CLOUDINARY_*`)

Todos os uploads de imagens e vídeos (avatares, banners, posts, mensagens) passam pelo Cloudinary.

```env
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
CLOUDINARY_CLOUD_NAME=<cloud_name>
CLOUDINARY_API_KEY=<api_key>
CLOUDINARY_API_SECRET=<api_secret>
```

Cria uma conta gratuita em [cloudinary.com](https://cloudinary.com) e copia os três valores do **Dashboard**. Sem estas credenciais, a aplicação faz *fallback* automático para armazenamento local (`storage/app/public`).

#### WebSockets — chat e notificações em tempo real (`PUSHER_*` ou `REVERB_*`)

O chat, as notificações e os indicadores de "a escrever" são entregues via WebSocket. Escolhe **um** dos dois:

**Opção A — Pusher (recomendado, serviço gerido, mais simples):**

```env
BROADCAST_CONNECTION=pusher

PUSHER_APP_ID=<app_id>
PUSHER_APP_KEY=<app_key>
PUSHER_APP_SECRET=<app_secret>
PUSHER_APP_CLUSTER=<cluster>

VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"
```

Cria uma conta gratuita em [pusher.com](https://pusher.com), cria uma app do tipo **Channels**, e copia as credenciais.

**Opção B — Laravel Reverb (self-hosted, sem dependências externas):**

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<app_id>
REVERB_APP_KEY=<app_key>
REVERB_APP_SECRET=<app_secret>
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

Com Reverb, é necessário correr `php artisan reverb:start` (ver passo 10).

#### Email (`MAIL_*` / `BREVO_*`)

Em desenvolvimento, usa o [Mailtrap](https://mailtrap.io) (gratuito) para capturar emails sem os enviar de verdade:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=<mailtrap_username>
MAIL_PASSWORD=<mailtrap_password>
MAIL_FROM_ADDRESS="bubblessupport@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

Em produção, recomenda-se o [Brevo](https://www.brevo.com) (envio transacional real):

```env
MAIL_MAILER=brevo
BREVO_KEY=<a_tua_chave_brevo>
```

#### Google OAuth — login sem password (`GOOGLE_CLIENT_*`, opcional)

```env
GOOGLE_CLIENT_ID=<client_id>
GOOGLE_CLIENT_SECRET=<client_secret>
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

Cria as credenciais em [console.cloud.google.com](https://console.cloud.google.com) → **APIs & Services → Credentials → OAuth client ID** (tipo *Web application*), e regista o redirect URI acima. Esta variável é opcional — a plataforma funciona por completo sem login Google.

#### Depois de editar o `.env`

```bash
php artisan config:clear
```

### 6. Base de dados

Cria todas as tabelas:

```bash
php artisan migrate
```

Opcionalmente, popula a base de dados com bolhas de exemplo:

```bash
php artisan db:seed
```

### 7. Compilar assets

Em desenvolvimento (com Hot Module Replacement):

```bash
npm run dev
```

Para produção (gera os ficheiros finais em `public/build/`):

```bash
npm run build
```

> Corre sempre `composer install` antes do primeiro `npm run build` — o build depende do Ziggy, gerado pelo Composer.

### 8. Iniciar o servidor

```bash
php artisan serve
```

Abre [http://localhost:8000](http://localhost:8000).

### 9. Iniciar o queue worker (noutro terminal)

```bash
php artisan queue:work
```

Necessário sempre que `QUEUE_CONNECTION` não seja `sync` (por defeito no `.env.example` é `sync`, pelo que este passo pode ser ignorado em desenvolvimento local). Em produção, processa emails e outros jobs assíncronos.

### 10. Iniciar o servidor WebSocket (apenas se usares Reverb)

```bash
php artisan reverb:start
```

Só é necessário se `BROADCAST_CONNECTION=reverb`. Com Pusher (opção recomendada no passo 5), este passo não é necessário — o Pusher é um serviço externo já "sempre ligado".

> **Atalho:** `composer run dev` inicia de uma vez o servidor Laravel, o queue worker, o leitor de logs (`pail`) e o Vite — tudo num só comando, com saída colorida por serviço.

---

## Produção (Railway)

O repositório já inclui tudo o que o [Railway](https://railway.app) precisa para fazer deploy automaticamente:

- **`nixpacks.toml`** — define que o build usa os providers `php` e `node`, corre `composer install --no-dev` + `npm ci` na instalação, e `npm run build` na fase de build.
- **`worker.sh`** — script de arranque que corre automaticamente em cada deploy:
  ```bash
  php artisan migrate --force
  php artisan storage:link
  php artisan queue:work --sleep=3 --tries=3 &   # queue worker em segundo plano
  exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
  ```

Ou seja, **migrations, link de storage e queue worker correm sozinhos a cada deploy** — não precisas de correr nada manualmente além de configurar as variáveis de ambiente no painel do Railway:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=<gerado com php artisan key:generate --show>
APP_URL=https://<o-teu-dominio>.up.railway.app

DB_CONNECTION=mysql
DB_HOST=...
DB_PORT=...
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

CLOUDINARY_URL=...

BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=...
VITE_PUSHER_APP_KEY="${PUSHER_APP_KEY}"
VITE_PUSHER_APP_CLUSTER="${PUSHER_APP_CLUSTER}"

MAIL_MAILER=brevo
BREVO_KEY=...

GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=https://<o-teu-dominio>.up.railway.app/auth/google/callback

QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

> Como o Vite incorpora as variáveis `VITE_*` no momento do **build**, garante que `VITE_PUSHER_APP_KEY`/`VITE_PUSHER_APP_CLUSTER` (ou as equivalentes do Reverb) estão definidas no Railway *antes* de o build correr.

---

## Troubleshooting

### `No application encryption key has been specified`

A `APP_KEY` está vazia. Corre:

```bash
php artisan key:generate
```

### HTTP 500 em todas as páginas

Normalmente é uma variável do `.env` com espaços e sem aspas:

```env
MAIL_FROM_NAME=Bubbles Support   # errado — quebra o parser do .env
MAIL_FROM_NAME="Bubbles Support" # correto
```

Depois de corrigir:

```bash
php artisan config:clear
```

### Erros de permissões em `storage/` ou `bootstrap/cache/` (Linux/macOS)

```bash
chmod -R 775 storage bootstrap/cache
```

### Imagens não fazem upload / erro "Cloudinary não configurado"

Confirma os quatro valores `CLOUDINARY_*` no `.env` contra o teu Dashboard do Cloudinary, depois:

```bash
php artisan config:clear
```

Sem Cloudinary configurado, a aplicação usa armazenamento local como *fallback* — as imagens continuam a funcionar, mas ficam guardadas no servidor em vez da CDN.

### Chat/notificações não chegam em tempo real

Confirma que `BROADCAST_CONNECTION` corresponde às credenciais preenchidas (`PUSHER_*` ou `REVERB_*`), e que `VITE_PUSHER_APP_KEY`/`VITE_PUSHER_APP_CLUSTER` (ou equivalentes Reverb) foram definidas **antes** de correr `npm run build` ou `npm run dev` — são incorporadas no bundle do frontend em tempo de build.

### `npm run build` falha com erro de módulo não encontrado

Faltou correr o Composer primeiro (o build depende do Ziggy, gerado por ele):

```bash
composer install
npm run build
```

### Porta 8000 já em uso

```bash
php artisan serve --port=8080
```

### Erro 419 "Page Expired" ao submeter formulários

Normalmente é `SESSION_DOMAIN` ou `SANCTUM_STATEFUL_DOMAINS` desalinhados com o domínio real de acesso. Confirma que o domínio/porta que usas no browser está incluído em `SANCTUM_STATEFUL_DOMAINS` no `.env`.

---

Voltar ao [README.md](README.md).
