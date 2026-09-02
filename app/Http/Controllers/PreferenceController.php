<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use Illuminate\Http\RedirectResponse;

class PreferenceController extends Controller
{
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('flash_success', 'Préférences mises à jour.');
    }
}
