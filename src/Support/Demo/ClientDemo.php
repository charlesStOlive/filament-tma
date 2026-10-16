<?php

namespace CharlesStOlive\FilamentTma\Support\Demo;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Support\TmaGate;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Le jeu d'exemples d'une application cliente, avec ses vrais utilisateurs : les internes (qui ne font que confier
 * leurs demandes à l'équipe), les correspondants du Support (qui lui écrivent : le droit « Transmettre au Support »),
 * l'équipe qui traite (qui peut prendre en charge) et le membre du Support relié ici (`Agent`).
 *
 * - Des demandes d'internes, traitées par l'équipe, dans tous les états : brouillon, nouvelle, en attente du
 *   demandeur, en pause, résolue, close, annulée.
 * - Des demandes transmises au Support, que le membre du Support traite depuis ici (dans le maître) : question au
 *   demandeur, en cours avec des notes (dont une pour lui seul) et du temps, résolue, nouvelle ; l'une part d'une tâche.
 * - La participation aux tâches publiées : messages, note, réponse du Support.
 *
 * À lancer après le jeu du maître (ses tâches doivent être arrivées).
 */
final class ClientDemo extends Scenario
{
    private TicketWorkflow $tickets;

    private Timeline $timeline;

    public function run(): void
    {
        $this->tickets = app(TicketWorkflow::class);
        $this->timeline = app(Timeline::class);
        [$correspondents, $internals, $team, $agent] = $this->people();
        // Les rôles du récit (les mêmes personnes reviennent s'il y a moins de monde) : trois correspondants du
        // Support, trois demandes d'internes, deux membres de l'équipe qui traite.
        [$leo, $benoit, $claire] = [$correspondents[0], $correspondents[1 % $correspondents->count()], $correspondents[2 % $correspondents->count()]];
        [$guillaume, $helpdesk] = [$team[0], $team[1 % $team->count()]];
        $this->teller = $guillaume;

        if ($internals->isEmpty()) {
            $this->say('Aucun interne (sans le droit « Transmettre au Support ») : demandes internes passées.');
        } else {
            [$mathilde, $paul, $lea] = [$internals[0], $internals[1 % $internals->count()], $internals[2 % $internals->count()]];
            $this->localTickets($mathilde, $paul, $lea, $guillaume, $helpdesk);
        }

        if ($agent === null) {
            $this->say('Pas de membre du Support relié ici : demandes au Support et tâches passées.');

            return;
        }

        $this->supportTickets($leo, $benoit, $claire, $guillaume, $agent, $internals->first());
        $this->tasks($leo, $benoit, $guillaume, $agent);
    }

    /** Les demandes des internes, que l'équipe traite elle-même. */
    private function localTickets(Authenticatable $leo, Authenticatable $mathilde, Authenticatable $benoit, Authenticatable $guillaume, Authenticatable $helpdesk): void
    {
        $this->at(1, '16:20', fn () => $this->draft($leo, 'Ajouter un filtre par pays sur la liste des parrains',
            'Sur la liste des parrains, pouvoir n’afficher que ceux d’un pays donné.', 'low'));
        $this->say('Brouillon : « Ajouter un filtre par pays… »');

        $this->at(2, '10:05', fn () => $this->submitted($mathilde, 'Le bouton Exporter ne répond plus',
            'Depuis ce matin, le bouton Exporter de la liste des dons ne fait plus rien.'));
        $this->say('Nouveau, confié à l’équipe : « Le bouton Exporter ne répond plus »');

        $this->at(6, '09:40', function () use ($benoit, $guillaume): void {
            $ticket = $this->submitted($benoit, 'Impossible d’ouvrir la fiche d’un enfant', 'Un message d’erreur s’affiche en ouvrant certaines fiches enfant.');
            $this->at(5, '11:10', function () use ($ticket, $guillaume): void {
                $this->move($ticket, 'take-charge', $guillaume);
                $this->timeline->add($ticket, EntryType::Note, self::html('Déjà vu le mois dernier sur les fiches sans photo.'), Context::ui($guillaume));
                $this->move($ticket, 'request-answer', $guillaume, ['question' => self::html('Sur quelles fiches exactement ? Pouvez-vous joindre une capture de l’erreur ?')]);
            });
        });
        $this->say('En attente du demandeur : « Impossible d’ouvrir la fiche d’un enfant »');

        $this->at(9, '14:00', function () use ($leo, $helpdesk): void {
            $ticket = $this->submitted($leo, 'Les paiements par carte échouent', 'Plusieurs parrains signalent un refus de paiement par carte.', 'high');
            $this->at(8, '09:30', function () use ($ticket, $helpdesk): void {
                $this->move($ticket, 'take-charge', $helpdesk);
                $this->timeline->add($ticket, EntryType::Comment, self::html('Le prestataire de paiement confirme un incident de son côté.'), Context::ui($helpdesk));
                $this->move($ticket, 'pause', $helpdesk, ['until' => $this->in(4, '09:00'), 'reason' => 'En attente du correctif du prestataire de paiement.']);
            });
        });
        $this->say('En pause : « Les paiements par carte échouent »');

        $this->at(4, '15:30', function () use ($mathilde, $guillaume): void {
            $ticket = $this->submitted($mathilde, 'Les exports de dons sont vides', 'Le fichier exporté ne contient que les en-têtes.');
            $this->at(4, '16:00', fn () => $this->move($ticket, 'take-charge', $guillaume));
            $this->at(1, '10:15', fn () => $this->move($ticket, 'submit-for-validation', $guillaume, ['message' => self::html('Corrigé : le cache des exports était plein. Pouvez-vous vérifier ?')]));
        });
        $this->say('Résolu, à confirmer : « Les exports de dons sont vides »');

        $this->at(12, '11:00', function () use ($benoit, $guillaume): void {
            $ticket = $this->submitted($benoit, 'Faute de frappe sur le reçu fiscal', 'Le mot « association » est mal écrit en bas du reçu.', 'low');
            $this->at(12, '14:00', fn () => $this->move($ticket, 'take-charge', $guillaume));
            $this->at(10, '09:00', fn () => $this->move($ticket, 'submit-for-validation', $guillaume, ['message' => self::html('C’est corrigé.')]));
            $this->at(9, '17:45', fn () => $this->move($ticket, 'validate', $benoit, ['message' => self::html('Parfait, merci !')]));
        });
        $this->say('Clos : « Faute de frappe sur le reçu fiscal »');

        $this->at(3, '08:50', function () use ($leo): void {
            $ticket = $this->submitted($leo, 'La photo de l’enfant ne s’affiche pas', 'Sur la fiche d’un enfant, la photo reste blanche.');
            $this->move($ticket, 'cancel', $leo, ['reason' => 'Doublon d’une demande déjà faite.']);
        });
        $this->say('Annulé : « La photo de l’enfant ne s’affiche pas »');
    }

    /** Les demandes que les correspondants transmettent au Support, traitées depuis ici par le membre du Support. */
    private function supportTickets(Authenticatable $leo, Authenticatable $mathilde, Authenticatable $benoit, Authenticatable $guillaume, Authenticatable $agent, ?Authenticatable $internal): void
    {
        // La demande d'une interne, que l'équipe transmet : le Support pose sa question à l'équipe, pas à elle.
        if ($internal !== null) {
            $escalated = $this->submitted($internal, 'Les dons par virement n’apparaissent plus', 'Les virements de la semaine ne remontent pas dans la liste des dons.');
            $this->move($escalated, 'take-charge', $guillaume);
            $this->move($escalated, 'transmit', $guillaume);
            $this->move($escalated, 'take-charge', $agent);
            $this->move($escalated, 'request-answer', $agent, ['question' => self::html('Quelle banque, et depuis quelle date ?')]);
            $this->say('Demande d’une interne transmise par l’équipe, le Support attend l’équipe : « Les dons par virement n’apparaissent plus »');
        }

        $error = $this->transmitted($leo, 'Erreur 500 à l’enregistrement d’un parrainage',
            'En enregistrant un nouveau parrainage, la page affiche « Erreur 500 ».', 'urgent');
        $this->move($error, 'take-charge', $agent);
        $this->move($error, 'request-answer', $agent, ['question' => self::html('Pour quel parrain ? Pouvez-vous donner son numéro et l’heure de l’erreur ?')]);
        $this->say('Au Support, en attente du demandeur : « Erreur 500 à l’enregistrement d’un parrainage »');

        $twice = $this->transmitted($mathilde, 'Les relances de dons partent en double',
            'Certains parrains reçoivent deux fois le même courriel de relance.', 'high');
        $this->move($twice, 'take-charge', $agent);
        $this->timeline->add($twice, EntryType::Note, self::html('Constaté surtout le lundi matin.'), Context::ui($guillaume));
        $looking = $this->timeline->add($twice, EntryType::Comment, self::html('Je regarde les tâches planifiées de lundi.'), Context::ui($agent));
        $this->timeline->add($twice, EntryType::Note, self::html('Piste : la relance tourne deux fois (planificateur et file).'), Context::ui($agent), private: true);
        $this->agentTime($twice, $agent, 90, $looking);
        $this->say('Au Support, en cours, avec une note pour soi et du temps : « Les relances de dons partent en double »');

        $pdf = $this->transmitted($benoit, 'Le PDF du reçu fiscal est vide', 'Le reçu fiscal téléchargé est une page blanche.');
        $this->move($pdf, 'take-charge', $agent);
        $this->move($pdf, 'submit-for-validation', $agent, ['message' => self::html('Corrigé en production : il manquait une police au modèle du reçu.')]);
        $this->say('Au Support, résolu : « Le PDF du reçu fiscal est vide »');

        $this->transmitted($guillaume, 'Ajouter un export des parrains par pays', 'Un export Excel des parrains, regroupés par pays.', 'low');
        $this->say('Au Support, nouveau : « Ajouter un export des parrains par pays »');
    }

    /** La participation aux tâches publiées, et un ticket qui part de l'une d'elles. */
    private function tasks(Authenticatable $leo, Authenticatable $benoit, Authenticatable $guillaume, Authenticatable $agent): void
    {
        $tasks = ProjectTask::query()->whereNot('state', 'draft')->orderBy('number')->get();
        $done = $tasks->first(fn (ProjectTask $task): bool => $task->state::getMorphClass() === 'done');
        $mockups = $tasks->first(fn (ProjectTask $task): bool => $task->state::getMorphClass() === 'in_progress');

        if ($mockups === null) {
            $this->say('Aucune tâche reçue du Support : lancez d’abord le jeu du maître.');

            return;
        }

        $question = $this->timeline->add($mockups, EntryType::Comment, self::html('Peut-on prévoir une version mobile de l’espace parrain ?'), Context::ui($benoit));
        $this->timeline->add($mockups, EntryType::Comment, self::html('Oui : la version mobile arrive avec les maquettes de la semaine prochaine.'), Context::ui($agent));
        $this->timeline->add($mockups, EntryType::Note, self::html('La charte graphique 2026 est dans le Drive › Communication.'), Context::ui($guillaume), $question);
        $this->say("Tâche {$mockups->number} : une question du client, la réponse du Support, une note de l’équipe");

        if ($done !== null) {
            $this->timeline->add($done, EntryType::Comment, self::html('Compte rendu validé de notre côté, merci.'), Context::ui($leo));
            $this->say("Tâche terminée {$done->number} : un message du client");
        }

        $fromTask = $this->tickets->create([
            'title' => 'Les maquettes ne s’affichent pas sur mobile',
            'description' => self::html('Sur téléphone, les maquettes partagées s’ouvrent en tout petit.'),
            'priority' => 'normal',
            'task_id' => $mockups->getKey(),
        ], Context::ui($benoit));
        $this->move($fromTask, 'transmit', $benoit);
        $this->say("Ticket ouvert à partir de la tâche {$mockups->number}, transmis au Support");
    }

    private function draft(Authenticatable $user, string $title, string $text, string $priority = 'normal'): Ticket
    {
        return $this->tickets->create(['title' => $title, 'description' => self::html($text), 'priority' => $priority], Context::ui($user));
    }

    /** Une demande confiée à l'équipe de l'application. */
    private function submitted(Authenticatable $user, string $title, string $text, string $priority = 'normal'): Ticket
    {
        $ticket = $this->draft($user, $title, $text, $priority);
        $this->move($ticket, 'submit', $user);

        return $ticket;
    }

    /** Une demande qu'un correspondant transmet lui-même au Support (le Support fait autorité dès lors). */
    private function transmitted(Authenticatable $user, string $title, string $text, string $priority = 'normal'): Ticket
    {
        $ticket = $this->draft($user, $title, $text, $priority);
        $this->move($ticket, 'transmit', $user);

        return $ticket;
    }

    /** @param  array<string, mixed>  $data */
    private function move(Ticket $ticket, string $move, Authenticatable $user, array $data = []): void
    {
        $this->tickets->run($ticket->refresh(), $move, $data, Context::ui($user));
    }

    /** Le membre du Support saisit son temps, dans le maître, sur un de ses messages ; les totaux reviennent. */
    private function agentTime(Ticket $ticket, Authenticatable $user, int $minutes, Entry $on): void
    {
        $context = Context::ui($user)->forSubject($ticket->refresh());
        $snapshot = app(MasterClient::class)->agentTime($context->agent, $ticket, $minutes, now()->toDateString(), true, entryUlid: $on->ulid);
        DB::transaction(fn () => Mirror::snapshot($snapshot));
    }

    /**
     * Les correspondants du Support (le droit de lui écrire), les internes (qui ne font que confier), l'équipe qui
     * traite (qui peut prendre en charge) et le membre du Support relié ici.
     *
     * @return array{0: Collection<int, Authenticatable>, 1: Collection<int, Authenticatable>, 2: Collection<int, Authenticatable>, 3: Authenticatable|null}
     */
    private function people(): array
    {
        $model = Tma::userModel();
        $agent = Agent::query()->with('user')->get()->first(fn (Agent $agent): bool => $agent->user !== null)?->user;
        $users = $model::query()->orderBy('id')->get()
            ->reject(fn ($user): bool => (bool) $agent?->is($user))
            // Ceux qui peuvent créer une demande (les comptes de service, sans rôle, ne le peuvent pas).
            ->filter(fn ($user): bool => Addressables::mayRequest($user));

        $correspondents = $users->filter(fn ($user): bool => Addressables::isCorrespondent($user))->values();
        $internals = $users->reject(fn ($user): bool => Addressables::isCorrespondent($user))->values();
        $team = $users->filter(fn ($user): bool => TmaGate::allows($user, TmaGate::workflowAction('take-charge')))->values();

        if ($correspondents->isEmpty()) {
            throw new RuntimeException(__('Personne ici n’est correspondant du Support (droit tma.ticket.workflow.transmit).'));
        }

        if ($team->isEmpty()) {
            throw new RuntimeException(__('Personne ici ne peut prendre en charge une demande (droit tma.ticket.workflow.take-charge).'));
        }

        return [$correspondents, $internals, $team, $agent];
    }
}
