<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

use function activity;

class UpdateUserAvatarAction
{
    /**
     * Remplace l'avatar de l'utilisateur (collection singleFile).
     */
    public function execute(User $user, UploadedFile $avatar): Media
    {
        unset($user->media);

        $media = $user->addMedia($avatar)->toMediaCollection('avatar');

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['media_uuid' => $media->uuid])
            ->log('avatar_updated');

        return $media;
    }
}
