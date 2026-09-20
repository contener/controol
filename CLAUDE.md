# Instructions pour Claude Code — projet Controool

## Règle prioritaire : tenir `controol.md` à jour

`controol.md` (racine du projet) est le prompt de reconstruction intégrale de
l'application — le filet de sécurité si le code, le dépôt Git ou l'ordinateur de
l'utilisateur venait à disparaître. Il doit toujours refléter l'état réel du projet.

**Après toute fonctionnalité nouvelle, tout changement de comportement, ou toute
décision de conception non triviale, mets `controol.md` à jour dans la même
session, sans que l'utilisateur ait à le demander.** Ajoute une entrée en tête de
la section « Historique des évolutions majeures », et corrige la section concernée
si le comportement décrit a changé. Une petite correction de bug n'a pas besoin
d'une nouvelle section, mais mérite une ligne d'historique si elle change un
comportement documenté.

Ne jamais écrire d'identifiants, mots de passe ou secrets réels dans ce fichier ni
dans `controol.md` — uniquement la structure et les procédures.

## Conventions du projet

Le code, les commentaires, les noms de modèles/méthodes/routes sont en **français** —
ce n'est pas une traduction, c'est la langue native du projet. Respecter cette
convention dans tout nouveau code.

Pour les principes d'architecture établis (statut calculé jamais persisté en double,
modèle "grand livre" pour le financier, champs sensibles hors `$fillable`, isolation
multi-boutique via `BelongsToBoutique`, réutiliser avant de recréer, etc.), voir la
section 3 de `controol.md` — ces règles s'appliquent à tout nouveau développement,
pas seulement au code déjà existant.
