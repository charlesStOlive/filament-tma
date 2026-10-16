<?php

namespace CharlesStOlive\FilamentTma\Support\Demo;

use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Support\Addressables;
use CharlesStOlive\FilamentTma\Support\Tma;
use CharlesStOlive\FilamentTma\Sync\MasterClient;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Recipients;
use CharlesStOlive\FilamentTma\Workflow\TicketWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Le jeu d'exemples d'une application cliente, avec ses vrais utilisateurs : les correspondants du Support (le droit
 * `tma.ticket.correspond`) et le membre du Support relié ici (`Agent`), qui traite depuis ici (dans le maître).
 *
 * - Des demandes au Support dans tous les états : brouillon, nouvelle, retirée, en attente du demandeur, reprise par
 *   un autre destinataire (« Prendre la main ») puis répondue, en cours (un point d'étape en note, une note pour soi,
 *   du temps), en pause, résolue, close, close d'office (« Forcer la clôture »). Le contact au Support est le membre
 *   relié ici, d'office : le Support répond tout de suite, sans « prise en charge ».
 * - La participation aux tâches publiées : messages, note, réponse du Support ; un ticket qui part de l'une d'elles.
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
        [$correspondents, $agent] = $this->people();
        // Les rôles du récit (les mêmes personnes reviennent s'il y a moins de monde).
        [$leo, $benoit, $claire] = [$correspondents[0], $correspondents[1 % $correspondents->count()], $correspondents[2 % $correspondents->count()]];
        $this->teller = $leo;

        $this->at(1, '16:20', fn () => $this->draft($leo, 'Ajouter un filtre par pays sur la liste des parrains',
            'Sur la liste des parrains, pouvoir n’afficher que ceux d’un pays donné.', 'low'));
        $this->say('Brouillon : « Ajouter un filtre par pays… »');

        if ($agent === null) {
            $this->say('Pas de membre du Support relié ici : demandes au Support et tâches passées.');

            return;
        }

        $this->supportTickets($leo, $benoit, $claire, $agent);
        $this->tasks($leo, $benoit, $agent, $correspondents);
    }

    /** Les demandes que les correspondants transmettent au Support, traitées depuis ici par le membre du Support. */
    private function supportTickets(Authenticatable $leo, Authenticatable $benoit, Authenticatable $claire, Authenticatable $agent): void
    {
        $this->transmitted($claire, 'Ajouter un export des parrains par pays', 'Un export Excel des parrains, regroupés par pays.', 'low');
        $this->say('Nouveau : « Ajouter un export des parrains par pays »');

        $duplicate = $this->transmitted($leo, 'La photo de l’enfant ne s’affiche pas', 'Sur la fiche d’un enfant, la photo reste blanche.');
        $this->move($duplicate, 'cancel', $leo, ['reason' => 'Doublon d’une demande déjà faite.']);
        $this->say('Retiré par son demandeur tant qu’il était nouveau : « La photo de l’enfant ne s’affiche pas »');

        $error = $this->transmitted($leo, 'Erreur 500 à l’enregistrement d’un parrainage',
            'En enregistrant un nouveau parrainage, la page affiche « Erreur 500 ».', 'urgent');
        $this->move($error, 'request-answer', $agent, ['question' => self::html('Pour quel parrain ? Pouvez-vous donner son numéro et l’heure de l’erreur ?')]);
        $this->say('En attente du demandeur : « Erreur 500 à l’enregistrement d’un parrainage »');

        // Léo est absent : Benoît, destinataire principal, prend la main et répond à sa place.
        $transfers = $this->transmitted($leo, 'Les dons par virement n’apparaissent plus', 'Les virements de la semaine ne remontent pas dans la liste des dons.');
        app(Recipients::class)->update($transfers->refresh(), ['u:'.$benoit->getAuthIdentifier()], [], Context::ui($leo));
        $this->move($transfers, 'request-answer', $agent, ['question' => self::html('Quelle banque, et depuis quelle date ?')]);
        $this->move($transfers, 'take-over', $benoit);
        $this->move($transfers, 'answer', $benoit, ['answer' => self::html('Le Crédit Mutuel, depuis lundi dernier.')]);
        $this->say('Repris par un autre destinataire (« Prendre la main »), qui a répondu : « Les dons par virement n’apparaissent plus »');

        $twice = $this->transmitted($benoit, 'Les relances de dons partent en double',
            'Certains parrains reçoivent deux fois le même courriel de relance.', 'high');
        $this->move($twice, 'request-answer', $agent, ['question' => self::html('Toutes les relances, ou seulement celles du lundi ?')]);
        $this->move($twice, 'answer', $benoit, ['answer' => self::html('Surtout le lundi matin.')]);
        $looking = $this->timeline->add($twice, EntryType::Note, self::html('Je regarde les tâches planifiées de lundi.'), Context::ui($agent));
        $this->timeline->add($twice, EntryType::Note, self::html('Piste : la relance tourne deux fois (planificateur et file).'), Context::ui($agent), private: true);
        $this->agentTime($twice, $agent, 90, $looking);
        $this->say('En cours, après la réponse, avec un point d’étape en note, une note pour soi et du temps : « Les relances de dons partent en double »');

        $cards = $this->transmitted($claire, 'Les paiements par carte échouent', 'Plusieurs parrains signalent un refus de paiement par carte.', 'high');
        $this->move($cards, 'pause', $agent, ['until' => now()->addDays(4)->setTime(9, 0), 'reason' => 'En attente du correctif du prestataire de paiement.']);
        $this->say('En pause : « Les paiements par carte échouent »');

        $pdf = $this->transmitted($benoit, 'Le PDF du reçu fiscal est vide', 'Le reçu fiscal téléchargé est une page blanche.');
        $this->move($pdf, 'submit-for-validation', $agent, ['message' => self::html('Corrigé en production : il manquait une police au modèle du reçu.')]);
        $this->say('Résolu, à confirmer : « Le PDF du reçu fiscal est vide »');

        $typo = $this->transmitted($leo, 'Faute de frappe sur le reçu fiscal', 'Le mot « association » est mal écrit en bas du reçu.', 'low');
        $this->move($typo, 'submit-for-validation', $agent, ['message' => self::html('C’est corrigé.')]);
        $this->move($typo, 'validate', $leo, ['message' => self::html('Parfait, merci !')]);
        $this->say('Clos : « Faute de frappe sur le reçu fiscal »');

        $sleeping = $this->transmitted($claire, 'Le bouton Exporter ne répond plus', 'Depuis ce matin, le bouton Exporter de la liste des dons ne fait plus rien.');
        $this->move($sleeping, 'request-answer', $agent, ['question' => self::html('Le problème est-il toujours là ?')]);
        $this->move($sleeping, 'force-close', $agent, ['reason' => 'Sans nouvelles : le correctif de la semaine dernière l’a sans doute réglé.']);
        $this->say('Clos d’office (« Forcer la clôture ») : « Le bouton Exporter ne répond plus »');
    }

    /** La participation aux tâches publiées, et un ticket qui part de l'une d'elles. */
    /** @param  Collection<int, Authenticatable>  $correspondents */
    private function tasks(Authenticatable $leo, Authenticatable $benoit, Authenticatable $agent, Collection $correspondents): void
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
        $this->say("Tâche {$mockups->number} : une question du client, la réponse du Support");

        // Une note, par qui peut en écrire sur une tâche.
        $noter = $correspondents->first(fn (Authenticatable $user): bool => $this->timeline->canAdd($mockups, EntryType::Note, Context::ui($user)));

        if ($noter !== null) {
            $this->timeline->add($mockups, EntryType::Note, self::html('La charte graphique 2026 est dans le Drive › Communication.'), Context::ui($noter), $question);
            $this->say("Tâche {$mockups->number} : une note du client sur la question");
        }

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

    /** Une demande qu'un correspondant transmet au Support (le Support fait autorité dès lors). */
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

    /** Le membre du Support saisit son temps, dans le maître, sur une de ses notes ; les totaux reviennent. */
    private function agentTime(Ticket $ticket, Authenticatable $user, int $minutes, Entry $on): void
    {
        $context = Context::ui($user)->forSubject($ticket->refresh());
        $snapshot = app(MasterClient::class)->agentTime($context->agent, $ticket, $minutes, now()->toDateString(), true, entryUlid: $on->ulid);
        DB::transaction(fn () => Mirror::snapshot($snapshot));
    }

    /**
     * Les correspondants du Support et le membre du Support relié ici.
     *
     * @return array{0: Collection<int, Authenticatable>, 1: Authenticatable|null}
     */
    private function people(): array
    {
        $model = Tma::userModel();
        $agent = Agent::query()->with('user')->get()->first(fn (Agent $agent): bool => $agent->user !== null)?->user;
        $correspondents = $model::query()->orderBy('id')->get()
            ->filter(fn ($user): bool => Addressables::isCorrespondent($user)
                && (string) $user->getAuthIdentifier() !== (string) Addressables::gateway()?->getAuthIdentifier())
            ->values();

        if ($correspondents->isEmpty()) {
            throw new RuntimeException(__('Personne ici n’est correspondant du Support (droit tma.ticket.correspond).'));
        }

        return [$correspondents, $agent];
    }
}
