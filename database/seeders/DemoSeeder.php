<?php

namespace Database\Seeders;

use App\Core\ExpertiseAreas;
use App\Core\Links;
use App\Core\Tags;
use App\Enums\RelationType;
use App\Enums\WorkspaceRole;
use App\Models\CalendarEvent;
use App\Models\Campaign;
use App\Models\ContentItem;
use App\Models\Notice;
use App\Models\Person;
use App\Models\PressRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Seeder;

/**
 * Realistic demonstration data for the "reitoria" workspace (local/demo only;
 * not shipped in dist/). All people and requests are fictional.
 */
class DemoSeeder extends Seeder
{
    public function run(Links $links, Tags $tags, ExpertiseAreas $areas): void
    {
        $workspace = Workspace::where('slug', 'reitoria')->firstOrFail();
        app(WorkspaceContext::class)->set($workspace);

        $team = collect([
            ['Marta Pinto', 'marta@example.com', WorkspaceRole::Editor],
            ['João Ferreira', 'joao@example.com', WorkspaceRole::Member],
            ['Inês Costa', 'ines@example.com', WorkspaceRole::Member],
        ])->map(function (array $row) use ($workspace) {
            $user = User::factory()->create(['name' => $row[0], 'email' => $row[1]]);
            $workspace->members()->attach($user, ['role' => $row[2]]);

            return $user;
        });
        [$marta, $joao, $ines] = $team->all();
        $day = fn (int $days, int $hour = 10) => now()->startOfDay()->addDays($days)->setTime($hour, 0);

        $campaign = Campaign::create([
            'name' => 'Candidaturas 2027', 'status' => 'active', 'start_date' => now()->subWeek(), 'end_date' => now()->addMonths(4),
            'description' => 'Campanha de captação para o concurso nacional de acesso.',
            'objectives' => 'Aumentar as candidaturas em primeira opção.', 'audiences' => ['futuros estudantes', 'famílias', 'escolas secundárias'],
            'channels' => ['instagram', 'tiktok', 'website'],
        ]);
        $campaign->responsibles()->sync([$marta->id, $joao->id]);

        $events = collect([
            ['Receção aos novos estudantes', 'institutional', $day(0, 9), 'Reitoria'],
            ['Conferência de imprensa: ranking internacional', 'institutional', $day(0, 14), 'Salão Nobre'],
            ['Dia Aberto', 'campaign', $day(3, 9), 'Campus da Asprela'],
            ['Noite Europeia dos Investigadores', 'institutional', $day(5, 18), 'Galeria da Biodiversidade'],
            ['Dia Mundial da Alimentação', 'ephemeris', $day(9, 0), null],
            ['Prazo: relatório de atividades', 'deadline', $day(12, 0), null],
        ])->map(fn (array $row) => CalendarEvent::create([
            'title' => $row[0], 'type' => $row[1], 'start_at' => $row[2], 'all_day' => in_array($row[1], ['ephemeris', 'deadline'], true),
            'location' => $row[3], 'priority' => 'normal', 'status' => 'confirmed', 'responsible_user_id' => $row[1] === 'institutional' ? $ines->id : null,
        ]));
        $links->link($events[2], $campaign, RelationType::PartOf);
        $tags->sync($events[3]->record, ['Ciência', 'Investigação']);

        foreach ([
            ['Preparar kit de imprensa do ranking', $ines, -1, 'urgent', 'in_progress'],
            ['Fotografias da receção aos estudantes', $joao, 0, 'high', 'todo'],
            ['Rever texto da newsletter de outubro', $marta, 2, 'normal', 'todo'],
            ['Reservar sala para o Dia Aberto', $joao, 1, 'normal', 'blocked'],
            ['Atualizar página de candidaturas', $ines, 6, 'normal', 'todo'],
        ] as [$title, $user, $days, $priority, $status]) {
            Task::create(['title' => $title, 'assigned_to' => $user->id, 'deadline' => now()->addDays($days), 'priority' => $priority, 'status' => $status]);
        }

        Notice::create(['title' => 'Edifício da Reitoria fechado na sexta-feira à tarde', 'body' => 'Por motivos de manutenção, o edifício fecha às 14h de sexta-feira.', 'published_at' => now()->subDay(), 'priority' => 'high', 'pinned' => true]);
        Notice::create(['title' => 'Novo manual de normas gráficas disponível', 'body' => 'A versão atualizada está na pasta partilhada da equipa.', 'published_at' => now()->subDays(3), 'priority' => 'normal']);

        $press = PressRequest::create(['subject' => 'Posição da U.Porto no ranking internacional', 'journalist' => 'Jornalista de exemplo', 'media_outlet' => 'Público', 'contact' => 'redacao@example.com', 'request' => 'Pedido de declaração do Reitor sobre a subida no ranking.', 'received_at' => now()->subHours(3), 'deadline' => now()->addHours(5), 'responsible_user_id' => $ines->id, 'status' => 'in_progress']);
        PressRequest::create(['subject' => 'Entrevista sobre investigação em saúde pública', 'journalist' => 'Jornalista de exemplo', 'media_outlet' => 'RTP', 'received_at' => now()->subDay(), 'deadline' => now()->addDays(2), 'status' => 'received']);

        foreach ([
            ['Notícia: estudantes internacionais batem recorde', 'news', 'review', ['website', 'newsletter']],
            ['Vídeo do Dia Aberto', 'video', 'preparing', ['youtube', 'instagram']],
            ['Série de publicações "Porquê a U.Porto?"', 'social_post', 'idea', ['instagram', 'tiktok']],
            ['Newsletter de outubro', 'newsletter', 'approved', ['newsletter']],
        ] as [$title, $format, $stage, $channels]) {
            $item = ContentItem::create(['title' => $title, 'format' => $format, 'stage' => $stage, 'channels' => $channels, 'owner_id' => $marta->id, 'due_at' => now()->addDays(4)]);

            if ($format !== 'news') {
                $links->link($item, $campaign, RelationType::PartOf);
            }
        }

        foreach ([
            // Fictional people, marked as examples.
            ['Helena Vasconcelos (exemplo)', 'Professora Associada', 'Faculdade de Ciências', 'Estuda a formação de galáxias e a evolução do Universo.', ['Astronomia', 'Espaço'], 'galáxias, telescópios, exoplanetas, eclipses', "2020 – Professora Associada, Faculdade de Ciências\n2014 – Investigadora no Instituto de Astrofísica\n2012 – Doutoramento em Astronomia", null, null],
            ['Rui Magalhães (exemplo)', 'Investigador', 'Instituto de Saúde Pública', 'Epidemiologista, fala sobre vacinação e doenças infecciosas.', ['Saúde pública'], 'vacinação, gripe, pandemias, saúde global', "2018 – Investigador no Instituto de Saúde Pública\n2015 – Doutoramento em Saúde Pública", null, null],
            ['Joaquim Sarmento (exemplo)', 'Professor Catedrático', 'Faculdade de Engenharia', 'Modelação climática e transição energética.', ['Clima', 'Energia'], 'clima, energias renováveis, hidrogénio', "2005 – Professor Catedrático, Faculdade de Engenharia\n1995 – Doutoramento em Engenharia Mecânica", 'Joaquim Sarmento (1948–2026), professor catedrático da Faculdade de Engenharia, foi uma referência na modelação climática em Portugal. (exemplo)', now()->subDays(3)->toDateString()],
        ] as [$name, $title, $affiliation, $bio, $personAreas, $topics, $career, $obituary, $deceasedOn]) {
            $person = Person::create([
                'name' => $name, 'academic_title' => $title, 'affiliation' => $affiliation, 'short_bio' => $bio,
                'keywords' => $topics, 'career' => $career, 'obituary' => $obituary, 'deceased_on' => $deceasedOn,
                'last_reviewed_at' => now()->subMonths(2),
            ]);
            $areas->sync($person, $personAreas);
        }
        $links->link($press, Person::where('name', 'like', 'Helena%')->firstOrFail());
    }
}
