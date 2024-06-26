<?php

namespace app\exchanges;

use app\enums\AppError;
use app\models\Notice;
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
        
        $exchange_class_name = (new \ReflectionClass($this))->getShortName();

        $this->userKeys = new \Key($user_id, $exchange_class_name);

        if (!$this->userKeys->is_find) {
            Notice::add([
                'user_id' => $user_id,
                'reference' => 'user',
                'reference_id' => $user_id,
                'type' => 'error',
                'message' => AppError::NO_AUTH_KEY_FILE['message'],
                'error_code' => AppError::NO_AUTH_KEY_FILE['code'],
            ]);

            throw new Exception("Ключи доступа не найдены!");
        }
    }
}