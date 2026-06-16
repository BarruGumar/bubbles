<div align="center">

# bubbles

### Uma rede social onde as ideias flutuam.

![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?style=flat-square&logo=vuedotjs&logoColor=white)
![Inertia.js](https://img.shields.io/badge/Inertia.js-2.0-9553E9?style=flat-square&logo=inertia&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql&logoColor=white)
![Cloudinary](https://img.shields.io/badge/Cloudinary-3.0-3448C5?style=flat-square&logo=cloudinary&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green?style=flat-square)

</div>

---

## Sobre o projeto

**Bubbles** é uma rede social onde as comunidades não se chamam "grupos" ou "páginas" — chamam-se **bolhas**. Cada bolha flutua livremente num canvas com física simulada, podendo ser criada e habitada por qualquer pessoa em torno de um tema, interesse ou piada interna. Dentro de cada bolha existe um feed próprio, com posts, comentários, reações e moderação dedicada — para além do feed pessoal, do chat privado em tempo real e do sistema de amizades que ligam toda a plataforma.

A inspiração visual é assumidamente nostálgica: o estilo **Frutiger Aero** — superfícies translúcidas, brilhos, gradientes suaves e motivos aquáticos/orgânicos — cruzado com a estética luminosa e arredondada da interface da **Wii U**. O resultado é uma plataforma que parece leve e tangível, onde a própria UI lembra bolhas de sabão a flutuar.

O projeto nasceu com outro nome — **NapeBook** — antes de a metáfora das bolhas (e o canvas físico que as anima) se tornar o verdadeiro coração da aplicação. Foi então renomeado para **Bubbles**, e a identidade visual e funcional foi reconstruída em torno desse conceito.

---

## Funcionalidades principais

- **Canvas com física simulada** — as bolhas/comunidades flutuam, colidem e reagem ao rato no ecrã principal
- **Comunidades (bolhas)** — criação, adesão, posts dedicados e moderação por comunidade
- **Feed social com paginação por cursor** — scroll infinito eficiente em perfis, comunidades e notificações
- **Chat em tempo real (WebSockets)** — mensagens, indicadores de "a escrever", leitura e edição instantâneas
- **Sistema de amizades** — pedidos, aceitação, rejeição e bloqueio de utilizadores
- **Notificações em tempo real** — badge de contagem e eventos entregues via WebSocket
- **Moderação e painel de administração** — denúncias, avisos/silenciamentos/banimentos, logs de auditoria e anúncios da plataforma
- **Pesquisa com score de relevância** — resultados de utilizadores e comunidades ordenados por relevância ao termo pesquisado
- **Login com Google (OAuth 2.0)** — registo e autenticação sem password via Google
- **Tema claro / escuro** — preferência guardada por conta

---

## Stack tecnológica

### Backend

| Tecnologia | Versão | Uso |
|---|---|---|
| Laravel | 12 | Framework principal (rotas, ORM, eventos, filas) |
| MySQL | 8.0+ | Base de dados relacional |
| Laravel Reverb / Pusher | 1.0 / — | WebSockets para chat e notificações em tempo real |
| Laravel Sanctum | 4.0 | Autenticação stateful e proteção CSRF |
| Cloudinary | 3.0 | Armazenamento, transformação e entrega de imagens/vídeos |
| Brevo | — | Envio de emails transacionais em produção |

### Frontend

| Tecnologia | Versão | Uso |
|---|---|---|
| Vue 3 | 3.4 | Framework reativo (Composition API) |
| Inertia.js | 2.0 | Ponte SPA entre Laravel e Vue, sem API REST intermédia |
| Tailwind CSS | 3.x | Estilização utility-first |
| Vite | 7 | Build tool e servidor de desenvolvimento com HMR |
| Laravel Echo | 2.3 | Cliente WebSocket no browser |
| Axios | 1.11 | Cliente HTTP para chamadas assíncronas |

---

## Arquitectura resumida

```
┌──────────────────────────────────────────────────────────────┐
│                            Browser                              │
│      Vue 3  +  Inertia.js  +  Tailwind CSS  +  Laravel Echo     │
└───────────────────┬─────────────────────────────┬───────────────┘
                     │ HTTP (Inertia)                │ WebSocket
                     ▼                                ▼
┌──────────────────────────────────────────────────────────────┐
│                        Laravel 12 (PHP)                         │
│     Controllers · Policies · Events · Jobs · Broadcasting       │
└──────────┬───────────────────┬───────────────────┬──────────────┘
           │                   │                   │
           ▼                   ▼                   ▼
     ┌──────────┐        ┌───────────┐       ┌─────────────┐
     │  MySQL   │        │ Cloudinary │       │ Brevo / SMTP │
     │ (dados)  │        │  (média)   │       │   (email)    │
     └──────────┘        └───────────┘       └─────────────┘
```

---

## Instalação

Para colocar o projeto a correr localmente (ou em produção), segue o guia passo a passo em **[INSTALL.md](INSTALL.md)**.

---

## Licença

Distribuído sob a licença MIT.
