<?php

namespace Modules\LaStore\Services;

/**
 * Ein Fehler aus dem Protokoll. Traegt den maschinenlesbaren Code aus der
 * Fehlerhuelle mit, damit der Aufrufer entscheiden kann, ohne Text zu lesen.
 */
class StoreException extends \Exception
{
    /** @var string */
    protected $errorCode;

    public function __construct($message, $errorCode = 'transport_error', $httpStatus = 0)
    {
        parent::__construct($message, (int) $httpStatus);

        $this->errorCode = (string) $errorCode;
    }

    /** @return string */
    public function errorCode()
    {
        return $this->errorCode;
    }
}
