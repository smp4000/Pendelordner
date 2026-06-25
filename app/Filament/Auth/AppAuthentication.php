<?php

namespace App\Filament\Auth;

use Filament\Auth\MultiFactor\App\AppAuthentication as BaseAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Facades\Filament;
use SensitiveParameter;

/**
 * Filament-App-Authentifizierung (TOTP) mit korrigierter QR-Code-Erzeugung.
 *
 * pragmarx/google2fa-qrcode v4 liefert bei getQRCodeInline() bereits eine
 * vollständige data:-URI (SVG ohne Imagick, PNG mit Imagick). Filaments
 * Standard-Fallback base64-kodiert diese ohne Imagick jedoch ein zweites Mal,
 * wodurch ein kaputtes Bild entsteht. Hier geben wir die fertige URI direkt
 * zurück.
 */
class AppAuthentication extends BaseAppAuthentication
{
    public function generateQrCodeDataUri(#[SensitiveParameter] string $secret): string
    {
        /** @var HasAppAuthentication $user */
        $user = Filament::auth()->user();

        return $this->google2FA->getQRCodeInline(
            $this->getBrandName(),
            $this->getHolderName($user),
            $secret,
        );
    }
}
