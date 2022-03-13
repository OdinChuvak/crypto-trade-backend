<?php

namespace app\exchanges;

use app\helpers\AppError;
use app\models\UserLog;
use Exception;

abstract class BaseExchange
{
    protected \Key $userKeys;

    /**
     * @throws Exception
     */
    public function __construct($user_id)
    {
        if (!class_exists('Key')) {
            eval(base64_decode(SOMETHING));
        }

        $this->userKeys = new \Key($user_id);

        if (!$this->userKeys->is_find) {
            UserLog::add([
                'user_id' => $user_id,
                'type' => 'error',
                'message' => AppError::NO_AUTH_KEY_FILE['message'],
                'error_code' => AppError::NO_AUTH_KEY_FILE['code'],
            ]);

            throw new Exception("Ключи доступа не найдены!");
        }
    }
}