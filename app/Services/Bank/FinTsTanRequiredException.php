<?php

namespace App\Services\Bank;

use RuntimeException;

/**
 * Wird geworfen, wenn die Bank eine TAN verlangt. Trägt den vollständigen
 * Zustand, um den Vorgang nach TAN-Eingabe fortzusetzen:
 *  - persist:          serialisierte FinTS-Dialog-Instanz ($fints->persist())
 *  - serializedAction: serialisierte FinTS-Aktion (Login/Konten/Umsätze)
 *  - flow:             'discover' (Kontenabruf) | 'fetch' (Umsatzabruf)
 *  - stage:            'login' | 'accounts' | 'statement'
 *  - context:          IDs/Zeitraum zur Fortsetzung
 */
class FinTsTanRequiredException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $persist,
        public readonly string $serializedAction,
        public readonly string $flow,
        public readonly string $stage,
        public readonly array $context,
        public readonly string $challenge,
    ) {
        parent::__construct('TAN erforderlich: ' . $challenge);
    }
}
