<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModeleNotificationEssai extends Model
{
    protected $table = 'modeles_notification_essai';

    protected $fillable = [
        'jour',
        'titre',
        'message',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'jour' => 'integer',
            'actif' => 'boolean',
        ];
    }
}
