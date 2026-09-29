<?php

namespace App\Services\WhatsAppFlows\Exceptions;

/** Meta espera HTTP 421 cuando el request no se puede descifrar (fuerza a re-descargar la clave pública). */
class FlowDecryptionException extends \RuntimeException {}
