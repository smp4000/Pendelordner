<?php

namespace App\Services\Bank;

use RuntimeException;

/**
 * Wird geworfen, wenn die Bank für den Abruf eine TAN verlangt. Enthält die
 * serialisierte FinTS-Instanz und die TAN-Aufforderung, damit der Vorgang nach
 * TAN-Eingabe fortgesetzt werden kann (interaktiver Flow – Ausbaustufe).
 */
class FinTsTanRequiredException extends RuntimeException
{
    public function __construct(
        public readonly string $persistedInstance,
        public readonly string $challenge,
    ) {
        parent::__construct('Für diesen Bankabruf wird eine TAN benötigt: ' . $challenge);
    }
}
