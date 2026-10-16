---
title: Les commandes (assistant IA)
icon: heroicon-o-command-line
order: 110
---

# Les commandes (assistant IA)

Quatre commandes permettent à un développeur, ou à un assistant IA comme Claude, de consulter les tickets et les
tâches et d'y reporter son travail, depuis le terminal de l'une ou l'autre application.

```bash
php artisan tma:list [--type=tickets|tasks|all] [--state=in_progress] [--app=Desapp] [--project=] [--mine] [--json]
php artisan tma:show DES-0042 [--json]
php artisan tma:log DES-0042 --hours=2 --summary="…" [--date=2026-10-07] [--non-billable] [--internal]
php artisan tma:log DES-0042 --minutes=30 --entry=01k…   # sur un message ou une note qui existe déjà
php artisan tma:comment DES-0042 --body="…" [--note]
```

Exemple : « Ajoute deux heures à DES-0042 avec un résumé de ce que j'ai fait » devient
`tma:log DES-0042 --hours=2 --summary-file=resume.md`. La **durée** est enregistrée comme une saisie de temps, le
**résumé** comme un message dans l'historique (`--internal` en fait une note sur le ticket), et le temps porte sur ce
message. Pour du temps passé sur un message ou une note qui existe déjà, `--entry` donne son ulid (affiché par
`tma:show`), sans résumé. `tma:comment --note` écrit une note sur le ticket entier. Les notes sont lues de tous, comme
les messages.

## La protection

- Chaque commande exige le **jeton personnel** d'un utilisateur : `--token`, ou `TMA_CLI_TOKEN` dans `.env`. Ce
  jeton ne doit jamais être versionné.
- La commande a **exactement les droits** de cet utilisateur, et tout ce qu'elle fait est enregistré avec l'origine
  « commande » (voir [L'audit](#modal-tma.audit-nettoyage)).

## Depuis une application cliente

Le temps ne se saisit que chez le Support : depuis une application cliente, les commandes agissent **chez le
Support**, avec le jeton d'un utilisateur du Support. `--local` les fait agir plutôt sur les tickets de l'application
(ceux de l'équipe du client), avec un jeton de l'application.

## Les notifications

Deux commandes sans jeton, pour le planificateur et l'administrateur : elles n'écrivent rien dans les tickets, elles
envoient ce que le module a décidé. Voir [Rappels et notifications](#modal-tma.notifications).

```bash
php artisan tma:notify --stale [--dry-run]     # rappelle à qui a la main ce qui ne bouge plus
php artisan tma:notify --grouped [--dry-run]   # envoie maintenant les notifications en attente
php artisan tma:digest [--pending] [--dry-run] # le récapitulatif (--pending : seulement qui a des nouveautés)
```
