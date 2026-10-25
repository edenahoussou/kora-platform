<?php

namespace Tests\Support\AppDistribution;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use ZipArchive;

/**
 * Fabrique de faux packages Android pour les tests MEZ-150 : une vraie
 * archive ZIP contenant un `AndroidManifest.xml`, suffisante pour passer la
 * validation de contenu de StoreAppReleaseRequest sans binaire réel.
 */
final class FakeApk
{
    public static function make(string $name = 'app-release.apk', string $payload = 'kora-ops-mobile'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kora-apk-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary APK file.');
        }

        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::OVERWRITE);
        $archive->addFromString('AndroidManifest.xml', '<manifest package="com.koraexpress.ops_mobile" />');
        $archive->addFromString('classes.dex', str_repeat($payload, 64));
        $archive->close();

        return new UploadedFile($path, $name, 'application/vnd.android.package-archive', null, true);
    }

    /**
     * Fichier portant l'extension .apk mais qui n'est pas une archive.
     */
    public static function notAnArchive(string $name = 'fake.apk'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kora-apk-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        file_put_contents($path, 'this is not an android package');

        return new UploadedFile($path, $name, 'application/octet-stream', null, true);
    }

    /**
     * Archive ZIP valide mais sans manifeste Android.
     */
    public static function zipWithoutManifest(string $name = 'archive.apk'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'kora-apk-');

        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary file.');
        }

        $archive = new ZipArchive;
        $archive->open($path, ZipArchive::OVERWRITE);
        $archive->addFromString('readme.txt', 'no manifest here');
        $archive->close();

        return new UploadedFile($path, $name, 'application/zip', null, true);
    }
}
