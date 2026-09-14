const path = require('path');
const fs = require('fs');
const pino = require('pino');
const QRCode = require('qrcode');
const { default: makeWASocket, useMultiFileAuthState, DisconnectReason } = require('@whiskeysockets/baileys');
const { notifyWebhook } = require('./webhook');

const SESSIONS_DIR = path.join(__dirname, '..', 'sessions');

// agentId (string) -> { sock, status, qr, numero, saveCreds }
const sessions = new Map();

function sessionPath(agentId) {
    return path.join(SESSIONS_DIR, String(agentId));
}

function publicState(agentId) {
    const entry = sessions.get(String(agentId));
    if (!entry) {
        return { statut: 'deconnecte', qr: null, numero: null };
    }
    return { statut: entry.status, qr: entry.qr, numero: entry.numero };
}

async function startSession(agentId) {
    const id = String(agentId);
    const existing = sessions.get(id);
    if (existing && ['connexion', 'qr', 'connecte'].includes(existing.status)) {
        return publicState(id);
    }

    const dir = sessionPath(id);
    fs.mkdirSync(dir, { recursive: true });
    const { state, saveCreds } = await useMultiFileAuthState(dir);

    const logger = pino({ level: 'silent' });
    const sock = makeWASocket({ auth: state, logger });

    sessions.set(id, { sock, status: 'connexion', qr: null, numero: null, saveCreds });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const entry = sessions.get(id);
        if (!entry) return;

        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            entry.qr = await QRCode.toDataURL(qr);
            entry.status = 'qr';
            notifyWebhook({ agentId: id, statut: 'qr', numero: null }).catch(() => {});
        }

        if (connection === 'open') {
            entry.status = 'connecte';
            entry.qr = null;
            entry.numero = sock.user?.id ? sock.user.id.split(':')[0] : null;
            notifyWebhook({ agentId: id, statut: 'connecte', numero: entry.numero }).catch(() => {});
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const loggedOut = statusCode === DisconnectReason.loggedOut;

            if (loggedOut) {
                sessions.delete(id);
                fs.rmSync(dir, { recursive: true, force: true });
                notifyWebhook({ agentId: id, statut: 'deconnecte', numero: null }).catch(() => {});
            } else {
                // Fermeture involontaire (perte réseau, redémarrage du téléphone...) : on
                // relance une session fraîche, jamais un simple changement de statut sur
                // l'entrée existante (elle référence un socket déjà mort).
                sessions.delete(id);
                startSession(id).catch(() => {});
            }
        }
    });

    return publicState(id);
}

async function stopSession(agentId) {
    const id = String(agentId);
    const entry = sessions.get(id);

    if (entry?.sock) {
        try {
            await entry.sock.logout();
        } catch (error) {
            // Déjà déconnecté ou socket fermé — sans conséquence, le nettoyage suit quand même.
        }
    }

    sessions.delete(id);
    fs.rmSync(sessionPath(id), { recursive: true, force: true });
    await notifyWebhook({ agentId: id, statut: 'deconnecte', numero: null }).catch(() => {});

    return { statut: 'deconnecte', qr: null, numero: null };
}

module.exports = { startSession, stopSession, getStatus: publicState };
