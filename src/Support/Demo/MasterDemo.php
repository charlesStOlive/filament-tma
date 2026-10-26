<?php

namespace CharlesStOlive\FilamentTma\Support\Demo;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Enums\RecipientRole;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Sync\RemoteUsers;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use CharlesStOlive\FilamentTma\Workflow\TimeLog;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Le jeu d'exemples du maître (le Support) : deux projets et leurs tâches dans tous les états (terminée, en cours, en
 * retard, programmée, annulée), du temps (parfois sur un message), et deux tickets préparés pour le client (l'un
 * publié, avec une note, l'autre programmé). Ce qui est publié part tout de suite chez le client.
 */
final class MasterDemo extends Scenario
{
    public function run(): void
    {
        // Les envois au client partent pendant la commande, pas plus tard dans la file.
        config(['queue.default' => 'sync']);

        $instance = Instance::query()->active()->orderBy('id')->first()
            ?? throw new RuntimeException(__('filament-tma::commands.demo.no_instance'));
        $support = $this->teller = $this->supportUser();
        $ctx = Context::ui($support);
        $tasks = app(TaskWorkflow::class);
        // Ses destinataires chez le client, avant la publication : le premier correspondant principal, les autres secondaires.
        $addressed = fn (ProjectTask $task): ProjectTask => $this->addressed($task, $instance);
        $timeline = app(Timeline::class);
        $time = app(TimeLog::class);

        $portal = Project::create([
            'title' => 'Portail parrain', 'target_instance_uid' => $instance->uid,
            'client_type' => $instance->client_type, 'client_id' => $instance->client_id,
            'description' => 'Le nouvel espace en ligne des parrains : suivi de l’enfant parrainé, dons, reçus fiscaux.',
        ]);
        $export = Project::create([
            'title' => 'Export comptable', 'target_instance_uid' => $instance->uid,
            'client_type' => $instance->client_type, 'client_id' => $instance->client_id,
            'description' => 'Les dons de la base vers le logiciel de comptabilité.',
        ]);

        // Terminée : le cadrage, avec son compte rendu et le temps de l'atelier.
        $this->at(20, '09:30', function () use ($tasks, $addressed, $timeline, $time, $ctx, $portal): void {
            $task = $tasks->create(['project_id' => $portal->getKey(), 'title' => 'Cadrage du nouveau portail',
                'description' => self::html('Atelier avec l’équipe EDM : parcours du parrain, pages à reprendre, priorités du lot 1.'),
                'due_on' => now()->addDays(7)->toDateString()], $ctx);
            $tasks->run($addressed($task), 'publish', [], $ctx);
            $this->at(18, '17:10', function () use ($timeline, $time, $task, $ctx): void {
                $report = $timeline->add($task, EntryType::Comment, self::html('Le compte rendu de l’atelier est en pièce de la description. Trois parcours retenus pour le lot 1.'), $ctx);
                $time->add($task, 7 * 60, now(), $ctx, entry: $report);
            });
            $this->at(12, '11:00', fn () => $tasks->run($task, 'complete', ['message' => self::html('Cadrage terminé : le lot 1 démarre avec les maquettes.')], $ctx));
        });
        $this->say('Tâche terminée : « Cadrage du nouveau portail »');

        // En cours : les maquettes, avec une note et du temps sur un message.
        $this->at(10, '10:00', function () use ($tasks, $addressed, $timeline, $time, $ctx, $portal): void {
            $task = $tasks->create(['project_id' => $portal->getKey(), 'title' => 'Maquettes de l’espace parrain',
                'description' => self::html('Maquettes des pages Accueil, Mon filleul et Mes dons, en suivant la charte graphique 2026.'),
                'due_on' => now()->addDays(15)->toDateString()], $ctx);
            $tasks->run($addressed($task), 'publish', [], $ctx);
            $this->at(7, '15:45', function () use ($timeline, $time, $task, $ctx): void {
                $draft = $timeline->add($task, EntryType::Comment, self::html('Première version des maquettes Accueil et Mon filleul : vos retours sont les bienvenus.'), $ctx);
                $time->add($task, 4 * 60, now(), $ctx, entry: $draft);
                $timeline->add($task, EntryType::Note, self::html('Penser au contraste des boutons (accessibilité).'), $ctx, $draft);
            });
        });
        $this->say('Tâche en cours : « Maquettes de l’espace parrain »');

        // Programmée : la recette, demain matin ; rien ne s'y écrit avant.
        $recipe = $tasks->create(['project_id' => $portal->getKey(), 'title' => 'Recette avec les parrains testeurs',
            'description' => self::html('Session de test avec cinq parrains volontaires : parcours, questions, retours.'),
            'publish_at' => $this->in(1, '09:00')], $ctx);
        $addressed($recipe);
        $this->say('Tâche programmée : « Recette avec les parrains testeurs » (demain 9 h)');

        // En retard : l'export des dons, échéance dépassée ; le temps écrit son message.
        $this->at(6, '08:45', function () use ($tasks, $addressed, $time, $ctx, $export): void {
            $task = $tasks->create(['project_id' => $export->getKey(), 'title' => 'Export des dons vers la comptabilité',
                'description' => self::html('Un fichier mensuel des dons, au format du logiciel comptable, déposé sur le serveur partagé.'),
                'due_on' => now()->addDays(4)->toDateString()], $ctx);
            $tasks->run($addressed($task), 'publish', [], $ctx);
            $this->at(5, '14:00', fn () => $time->addWithMessage($task, 90, now(), self::html('Analyse du format attendu : un fichier CSV par mois, une ligne par don.'), $ctx));
        });
        $this->say('Tâche en retard : « Export des dons vers la comptabilité »');

        // Annulée.
        $this->at(15, '16:00', function () use ($tasks, $addressed, $ctx, $export): void {
            $task = $tasks->create(['project_id' => $export->getKey(), 'title' => 'Reprise de l’ancien export CSV',
                'description' => self::html('Remettre en service l’export CSV de l’ancienne base.')], $ctx);
            $tasks->run($addressed($task), 'publish', [], $ctx);
            $this->at(14, '09:20', fn () => $tasks->run($task, 'cancel', ['reason' => 'Remplacé par l’export vers la comptabilité.'], $ctx));
        });
        $this->say('Tâche annulée : « Reprise de l’ancien export CSV »');

        $this->preparedTickets($instance, $ctx);
    }

    /** Deux tickets préparés pour le client : l'un publié, avec une note, l'autre programmé. */
    private function preparedTickets(Instance $instance, Context $ctx): void
    {
        $users = rescue(fn (): array => RemoteUsers::for($instance), [], false);
        $requester = collect($users)->first();

        if ($requester === null) {
            $this->say('Tickets préparés : passés (l’application cliente ne donne pas ses utilisateurs).');

            return;
        }

        $tickets = app(TicketWorkflow::class);
        $timeline = app(Timeline::class);

        $this->at(2, '18:30', function () use ($tickets, $timeline, $instance, $requester, $ctx): void {
            $ticket = $tickets->createForClient($instance, $requester, [
                'title' => 'Mise à jour de sécurité de l’application',
                'description' => self::html('Une mise à jour de sécurité du framework est disponible : nous l’installerons jeudi soir, avec une coupure d’une dizaine de minutes.'),
                'priority' => 'high',
            ], $ctx);
            $tickets->run($ticket, 'publish', [], $ctx);
            $this->at(1, '09:15', function () use ($timeline, $ticket, $ctx): void {
                $timeline->add($ticket, EntryType::Note, self::html('Installation prévue jeudi à 20 h. Rien à faire de votre côté.'), $ctx);
            });
        });
        $this->say('Ticket préparé, publié, avec une note : « Mise à jour de sécurité de l’application »');

        $tickets->createForClient($instance, $requester, [
            'title' => 'Renouvellement du certificat du site',
            'description' => self::html('Le certificat du site arrive à échéance : il sera renouvelé automatiquement, sans coupure.'),
            'publish_at' => $this->in(7, '09:00'),
        ], $ctx);
        $this->say('Ticket préparé, programmé dans une semaine : « Renouvellement du certificat du site »');
    }

    /** Les correspondants du client destinataires d'une tâche : le premier principal, les autres secondaires. */
    private function addressed(ProjectTask $task, Instance $instance): ProjectTask
    {
        foreach (array_values(rescue(fn (): array => RemoteUsers::for($instance), [], false)) as $i => $user) {
            app(Recipients::class)->add($task, Actor::fromInstance($instance->uid, (string) $user['id'], (string) $user['name'], $user['email'] ?? null), $i === 0 ? RecipientRole::Primary : RecipientRole::Secondary);
        }

        return $task;
    }

    /** Un utilisateur du Support qui peut tout faire ici : le premier qui a le droit de publier une tâche. */
    private function supportUser(): Authenticatable
    {
        $model = Tma::userModel();

        return $model::query()->orderBy('id')->get()
            ->first(fn ($user): bool => TmaGate::allows($user, TmaGate::workflowAction('publish'), new ProjectTask) && TmaGate::allows($user, TmaGate::TIME, new ProjectTask))
            ?? throw new RuntimeException(__('filament-tma::commands.demo.no_publisher'));
    }
}
