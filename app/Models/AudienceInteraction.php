<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudienceInteraction extends Model
{
    use BelongsToBoutique;

    public const LIKE = 'LIKE';

    public const MESSAGE = 'MESSAGE';

    public const WHATSAPP_RELANCE_OPENED = 'WHATSAPP_RELANCE_OPENED';

    public const MESSAGE_RELANCE_ENVOYE = 'MESSAGE_RELANCE_ENVOYE';

    public $timestamps = false;

    protected $fillable = [
        'boutique_id',
        'audience_membre_id',
        'produit_id',
        'type',
        'metadata',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }

    public function audienceMembre(): BelongsTo
    {
        return $this->belongsTo(AudienceMembre::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
