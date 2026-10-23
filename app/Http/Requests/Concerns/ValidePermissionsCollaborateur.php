<?php

namespace App\Http\Requests\Concerns;

use App\Models\Boutique;

/**
 * Règles de validation des 7 permissions (voir Boutique::PERMISSIONS_COLLABORATEUR),
 * partagées entre StoreCollaborateurRequest (création) et
 * UpdateCollaborateurPermissionsRequest (modification) -- jamais dupliquées.
 */
trait ValidePermissionsCollaborateur
{
    protected function reglesPermissions(): array
    {
        return array_fill_keys(Boutique::PERMISSIONS_COLLABORATEUR, ['boolean']);
    }
}
