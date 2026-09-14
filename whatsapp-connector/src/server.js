require('dotenv').config();
const express = require('express');
const { startSession, stopSession, getStatus } = require('./sessionManager');

const app = express();
app.use(express.json());

function requireSecret(req, res, next) {
    const secret = req.header('X-Connector-Secret');
    if (!secret || secret !== process.env.CONNECTOR_SECRET) {
        return res.status(401).json({ error: 'unauthorized' });
    }
    next();
}

app.get('/health', (req, res) => res.json({ ok: true }));

app.post('/sessions/:agentId/start', requireSecret, async (req, res) => {
    try {
        const state = await startSession(req.params.agentId);
        res.json(state);
    } catch (error) {
        res.status(500).json({ error: 'start_failed', message: error.message });
    }
});

app.get('/sessions/:agentId/status', requireSecret, (req, res) => {
    res.json(getStatus(req.params.agentId));
});

app.post('/sessions/:agentId/stop', requireSecret, async (req, res) => {
    try {
        const state = await stopSession(req.params.agentId);
        res.json(state);
    } catch (error) {
        res.status(500).json({ error: 'stop_failed', message: error.message });
    }
});

const port = process.env.PORT || 3001;
app.listen(port, () => {
    console.log(`whatsapp-connector listening on port ${port}`);
});
