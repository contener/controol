<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMessage extends Model
{
    public const EXPEDITEUR_BOUTIQUE = 'boutique';

    public const EXPEDITEUR_VISITEUR = 'visiteur';

    protected $fillable = ['conversation_id', 'expediteur', 'contenu'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
