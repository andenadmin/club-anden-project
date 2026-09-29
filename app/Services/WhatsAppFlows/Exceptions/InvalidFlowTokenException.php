<?php

namespace App\Services\WhatsAppFlows\Exceptions;

/** Meta espera HTTP 427 cuando el flow_token no es válido o venció. */
class InvalidFlowTokenException extends \RuntimeException {}
