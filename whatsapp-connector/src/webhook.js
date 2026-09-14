async function notifyWebhook({ agentId, statut, numero }) {
    const url = process.env.LARAVEL_WEBHOOK_URL;
    if (!url) return;

    await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Connector-Secret': process.env.CONNECTOR_SECRET || '',
        },
        body: JSON.stringify({ agentId, statut, numero }),
    });
}

module.exports = { notifyWebhook };
