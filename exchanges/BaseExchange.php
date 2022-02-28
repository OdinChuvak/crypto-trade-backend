<?php

namespace app\exchanges;

use app\helpers\AppError;

abstract class BaseExchange
{
    protected \Key $userKeys;

    public function __construct($user_id)
    {
        if (!class_exists('Key')) {
            eval(base64_decode(SOMETHING));
        }

        $this->userKeys = new \Key($user_id);

        if (!$this->userKeys->is_find) {
            return AppError::NO_AUTH_KEY_FILE;
        }

        return $this;
    }
}