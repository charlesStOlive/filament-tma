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
php artisan tma:comment DES-0042 --body="…" [--note]
```

Exemple : « Ajoute deux heures à DES-0042 avec un résumé de ce que j'ai fait » devient
`tma:log DES-0042 --hours=2 --summary-file=resume.md`. La **durée** est enregistrée comme une saisie de temps, le
**résumé** comme un « Résumé d'intervention » dans la chronologie, partagé avec le client (`--internal` en fait une
note interne).

## La protection

- Chaque commande exige le **jeton personnel** d'un utilisateur : `--token`, ou `TMA_CLI_TOKEN` dans `.env`. Ce
  jeton ne doit jamais être versionné.
- La commande a **exactement les droits** de cet utilisateur, et tout ce qu'elle fait est enregistré avec l'origine
  « commande » (voir [L'audit](#modal-tma.audit-nettoyage)).

## Depuis une application cliente

Le temps ne se saisit que dans le centre de service : depuis une application cliente, les commandes agissent **dans
le centre de service**, avec le jeton d'un utilisateur du centre de service. `--local` les fait agir plutôt sur les
tickets locaux de l'application, avec un jeton de l'application.
