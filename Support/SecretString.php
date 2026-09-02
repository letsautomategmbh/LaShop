<?php

namespace Modules\LaStore\Support;

/**
 * Ein Wert, der nicht in Protokollen landen soll.
 *
 * PHP stellt in einem Stacktrace die tatsaechlichen Argumente dar - ein
 * Lizenzschluessel, der als Zeichenkette durch mehrere Aufrufe gereicht wird,
 * steht bei jeder ungefangenen Ausnahme vollstaendig im laravel.log. Und
 * dieses Log wird im Support herumgereicht.
 *
 * Objekte erscheinen in getTraceAsString() dagegen nur als
 * "Object(Modules\LaStore\Support\SecretString)". Der Wert wird erst dort
 * ausgepackt, wo er wirklich gebraucht wird - in der Kopfzeile und im Rumpf
 * der HTTP-Anfrage.
 */
class SecretString
{
    /** @var string */
    protected $value;

    public function __construct($value)
    {
        $this->value = (string) $value;
    }

    /**
     * @param mixed $value
     *
     * @return self
     */
    public static function wrap($value)
    {
        return $value instanceof self ? $value : new self($value);
    }

    /** @return string */
    public function reveal()
    {
        return $this->value;
    }

    /**
     * Ein ungefaehrlicher Anhaltspunkt fuer Meldungen und Protokolle.
     *
     * @return string
     */
    public function hint()
    {
        return $this->value === '' ? '' : LicenseKey::prefix($this->value);
    }

    /**
     * Absichtlich NICHT der Wert: ein versehentliches Einbetten in eine
     * Zeichenkette soll den Schluessel nicht ausgeben.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->hint().'…';
    }

    /** @return array */
    public function __debugInfo()
    {
        return array('value' => $this->hint().'…');
    }
}
