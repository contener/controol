# whatsapp-connector

Service indépendant qui gère les connexions WhatsApp (méthode QR non-officielle, type
WhatsApp Web) pour l'Agent IA WhatsApp de Controool. **Ce dossier n'est pas une app Laravel
ni une app Vue** — c'est un service Node.js à part, qui communique avec controool.fr
uniquement par HTTP (jamais l'inverse, jamais d'accès direct à la base de données Laravel).

## Ce que ce service fait (et ne fait pas)

Il établit et surveille une connexion WhatsApp par "agent" (une boutique) : génère un QR
code à scanner, détecte la connexion, notifie Laravel du statut et du numéro rattaché.

Il ne gère **pas** encore l'envoi/réception de messages ni l'IA — seulement la connexion.

## Lancer en local

```bash
cd whatsapp-connector
npm install
cp .env.example .env   # renseigner CONNECTOR_SECRET (valeur longue et aléatoire)
npm start
```

Le service écoute sur `http://localhost:3001` (configurable via `PORT`). Toutes les routes
sauf `/health` exigent l'en-tête `X-Connector-Secret`.

```
POST /sessions/:agentId/start   -> démarre (ou renvoie l'état d'une session déjà en cours)
GET  /sessions/:agentId/status  -> { statut: 'deconnecte'|'connexion'|'qr'|'connecte', qr, numero }
POST /sessions/:agentId/stop    -> déconnecte et efface la session
```

## Déployer en production — ce qu'il faut savoir avant de commencer

**Ce service ne peut PAS tourner sur un hébergement mutualisé classique** (le type
d'hébergement où vit actuellement controool.fr). Il maintient une connexion WebSocket
permanente à WhatsApp — il doit rester en vie 24/7, pas juste répondre à des requêtes HTTP
ponctuelles comme PHP/Passenger. Il faut un hébergement qui permette un **processus Node.js
de longue durée** :

- Un petit VPS (Hostinger en propose, OVH, DigitalOcean...) avec `pm2` ou un service
  `systemd` pour garder le processus en vie et le relancer automatiquement.
- Ou une plateforme "app Node.js persistante" comme Railway, Render ou Fly.io.

Une fois ce service déployé quelque part et joignable par une URL publique, il suffit de
renseigner **deux variables d'environnement côté Laravel** (`.env` de controool.fr) — aucun
redéploiement de code Laravel n'est nécessaire, la fonctionnalité s'active toute seule :

```
WHATSAPP_CONNECTOR_URL=https://<url-du-service-node>
WHATSAPP_CONNECTOR_SECRET=<même valeur que CONNECTOR_SECRET ci-dessus>
```

Et côté service Node, `LARAVEL_WEBHOOK_URL` doit pointer vers
`https://controool.fr/api/webhooks/whatsapp-connector`.

## Sessions

Chaque agent a son propre dossier `sessions/<agentId>/` (identifiants d'authentification
WhatsApp persistés par Baileys) — jamais commité (`.gitignore`), jamais partagé entre agents.
