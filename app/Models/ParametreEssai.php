<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParametreEssai extends Model
{
    protected $table = 'parametres_essai';

    protected $fillable = [
        'heure_notification',
        'fuseau',
    ];

    /**
     * Table à une seule ligne — la crée avec les valeurs par défaut de la migration
     * si elle n'existe pas encore (ex. juste après le déploiement de cette phase).
     */
    public static function actuel(): self
    {
        return self::query()->first() ?? self::create([]);
    }
}
