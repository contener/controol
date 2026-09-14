<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAgent extends Model
{
    use BelongsToBoutique, HasFactory;

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
