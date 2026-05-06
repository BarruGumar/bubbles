<?php

namespace Database\Seeders;

use App\Models\Bubble;
use App\Models\User;
use Illuminate\Database\Seeder;

class BubbleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user || Bubble::count() > 0) return;

        $communities = [
            [
                'label'                  => '#futebol',
                'color'                  => '#009ac7',
                'size'                   => 100,
                'members'                => 312,
                'x'                      => 500,
                'y'                      => 200,
                'community_title'        => 'Futebol',
                'community_tagline'      => 'O desporto mais bonito do mundo',
                'community_description'  => 'Comunidade dedicada ao futebol. Resultados, análises, transferências e discussão dos melhores jogos.',
                'community_cover_color'  => '#007ba0',
                'community_guidelines'   => [
                    'Respeita os outros membros.',
                    'Evita spoilers sem aviso.',
                    'Conteúdo relacionado com futebol apenas.',
                ],
            ],
            [
                'label'                  => '#ps5',
                'color'                  => '#4ebcff',
                'size'                   => 80,
                'members'                => 88,
                'x'                      => 780,
                'y'                      => 160,
                'community_title'        => 'PlayStation',
                'community_tagline'      => 'Jogos, notícias e muito mais',
                'community_description'  => 'Tudo sobre PlayStation 5: jogos exclusivos, dicas, análises e novidades da Sony.',
                'community_cover_color'  => '#0060a0',
                'community_guidelines'   => [
                    'Sem spam de links externos.',
                    'Partilha spoilers com aviso.',
                    'Sem publicidade não autorizada.',
                ],
            ],
            [
                'label'                  => '#xbox',
                'color'                  => '#2ea87e',
                'size'                   => 80,
                'members'                => 54,
                'x'                      => 900,
                'y'                      => 340,
                'community_title'        => 'Xbox',
                'community_tagline'      => 'Game Pass e tudo sobre Xbox',
                'community_description'  => 'Comunidade Xbox: Game Pass, exclusivos, hardware e tudo sobre o ecossistema Microsoft Gaming.',
                'community_cover_color'  => '#1a7a5a',
                'community_guidelines'   => [
                    'Respeita fãs de outras plataformas.',
                    'Sem comparações agressivas.',
                    'Conteúdo sobre Xbox e jogos.',
                ],
            ],
            [
                'label'                  => '#música',
                'color'                  => '#e07b4a',
                'size'                   => 90,
                'members'                => 141,
                'x'                      => 250,
                'y'                      => 370,
                'community_title'        => 'Música',
                'community_tagline'      => 'Partilha o que estás a ouvir',
                'community_description'  => 'Para todos os apaixonados por música. Playlists, artistas, álbuns, concertos e descobertas musicais.',
                'community_cover_color'  => '#c0602a',
                'community_guidelines'   => [
                    'Partilha músicas de todos os géneros.',
                    'Respeita os gostos dos outros.',
                    'Sem pirataria ou links ilegais.',
                ],
            ],
        ];

        foreach ($communities as $data) {
            Bubble::create(array_merge($data, ['user_id' => $user->id]));
        }
    }
}
