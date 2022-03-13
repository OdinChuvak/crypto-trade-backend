<?php

namespace app\exceptions;

use JetBrains\PhpStorm\Pure;
use Throwable;

class ApiException extends \Exception
{
    #[Pure] public function __construct($message, $code, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}