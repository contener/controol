<?php

namespace App\Models;

use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    public const EXPEDITEUR_BOUTIQUE = 'boutique';

    public const EXPEDITEUR_VISITEUR = 'visiteur';

    protected $fillable = ['conversation_id', 'expediteur', 'contenu'];

    /**
     * recu_le / lu_le : statut de remise façon WhatsApp, renseignés uniquement par
     * MessagerieTempsReelService (hors $fillable -- jamais envoyés par un client).
     */
    protected function casts(): array
    {
        return [
            'recu_le' => 'datetime',
            'lu_le' => 'datetime',
        ];
    }

    /**
     * Notification "nouveau message" branchée ici une seule fois : couvre tous les
     * chemins d'envoi (page publique, lien invité, Mes conversations, messagerie de
     * la boutique, web comme application mobile).
     */
    protected static function booted(): void
    {
        static::created(fn (self $message) => app(NotificationService::class)->nouveauMessage($message));
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
