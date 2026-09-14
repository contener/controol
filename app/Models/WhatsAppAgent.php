<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAgent extends Model
{
    use BelongsToBoutique, HasFactory;

    // La convention snake_case d'Eloquent découperait "WhatsAppAgent" en "whats_app_agents"
    // (une majuscule = une coupure) — jamais "whatsapp_agents", d'où ce nom de table explicite.
    protected $table = 'whatsapp_agents';

    protected $fillable = [
        'nom',
        'actif',
        'langue',
        'personnalite',
        'ton',
        'message_accueil',
        'message_hors_horaires',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }
}
