<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        // Compresse avant la validation ('max:1024' ci-dessous) plutôt que de rejeter une
        // photo trop lourde — voir App\Services\ImageCompressionService.
        if (($input['photo'] ?? null) instanceof UploadedFile) {
            $input['photo'] = app(ImageCompressionService::class)->compresserSiNecessaire($input['photo'], 1024 * 1024);
        }

        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            'date_naissance' => ['nullable', 'date'],
            'ville' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
        ])->validateWithBag('updateProfileInformation');

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        $donneesComplementaires = [
            'date_naissance' => $input['date_naissance'] ?? null,
            'ville' => $input['ville'] ?? null,
            'telephone' => $input['telephone'] ?? null,
            'whatsapp' => $input['whatsapp'] ?? null,
        ];

        if ($input['email'] !== $user->email &&
            $user instanceof MustVerifyEmail) {
            $this->updateVerifiedUser($user, $input);
            $user->forceFill($donneesComplementaires)->save();
        } else {
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                ...$donneesComplementaires,
            ])->save();
        }
    }

    /**
     * Update the given verified user's profile information.
     *
     * @param  array<string, string>  $input
     */
    protected function updateVerifiedUser(User $user, array $input): void
    {
        $user->forceFill([
            'name' => $input['name'],
            'email' => $input['email'],
            'email_verified_at' => null,
        ])->save();

        $user->sendEmailVerificationNotification();
    }
}
