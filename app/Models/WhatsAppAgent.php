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

    // Reflètent les défauts de la migration en mémoire dès l'instanciation : sans ça,
    // firstOrCreate()/new WhatsAppAgent() laisse ces attributs à null tant que le modèle
    // n'a pas été relu depuis la base après un INSERT qui s'appuie sur le défaut SQL.
    protected $attributes = [
        'nom' => 'Assistant',
        'actif' => false,
        'langue' => 'fr',
    ];

    protected function casts(): array
    {
        return [
            'actif' => 'boolean',
        ];
    }
}
