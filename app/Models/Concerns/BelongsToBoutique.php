<?php

namespace App\Models\Concerns;

use App\Models\Boutique;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToBoutique
{
    public static function bootBelongsToBoutique(): void
    {
        static::addGlobalScope('boutique', function (Builder $builder) {
            if (! Auth::check()) {
                return;
            }

            // Repli fermé : si l'utilisateur authentifié n'a pas (encore) de boutique
            // courante, ne jamais laisser la requête remonter des données d'une autre
            // boutique/utilisateur — on renvoie un jeu de résultats vide plutôt que
            // de sauter le filtre.
            $boutiqueId = Auth::user()->currentBoutique?->id ?? 0;

            $builder->where($builder->getModel()->getTable().'.boutique_id', $boutiqueId);
        });

        static::creating(function ($model) {
            if (! $model->boutique_id && Auth::check() && Auth::user()->currentBoutique) {
                $model->boutique_id = Auth::user()->currentBoutique->id;
            }
        });
    }

    public function boutique(): BelongsTo
    {
        return $this->belongsTo(Boutique::class);
    }
}
