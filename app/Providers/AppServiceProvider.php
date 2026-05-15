<?php

namespace App\Providers;

use App\Models\Bubble;
use App\Models\CommunityPost;
use App\Models\Post;
use App\Policies\BubblePolicy;
use App\Policies\CommunityPostPolicy;
use App\Policies\PostPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::policy(Post::class, PostPolicy::class);
        Gate::policy(CommunityPost::class, CommunityPostPolicy::class);
        Gate::policy(Bubble::class, BubblePolicy::class);

        // Posts & community posts: 15 por minuto por utilizador
        RateLimiter::for('posts', fn (Request $req) => Limit::perMinute(15)->by($req->user()?->id ?: $req->ip())
        );

        // Mensagens privadas: 40 por minuto
        RateLimiter::for('messages', fn (Request $req) => Limit::perMinute(40)->by($req->user()?->id ?: $req->ip())
        );

        // Pedidos de amizade: 20 por minuto
        RateLimiter::for('friends', fn (Request $req) => Limit::perMinute(20)->by($req->user()?->id ?: $req->ip())
        );

        // Likes e comentários: 60 por minuto
        RateLimiter::for('reactions', fn (Request $req) => Limit::perMinute(60)->by($req->user()?->id ?: $req->ip())
        );

        // Criação de bolhas/comunidades: 5 por hora
        RateLimiter::for('create-community', fn (Request $req) => Limit::perHour(5)->by($req->user()?->id ?: $req->ip())
        );
    }
}
