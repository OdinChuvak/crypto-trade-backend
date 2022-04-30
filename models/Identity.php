<?php

namespace app\models;

use yii\web\IdentityInterface;

class Identity extends User implements IdentityInterface
{
    public static function findIdentity($id): Identity|IdentityInterface|null
    {
        return self::findOne(['id' => $id]);
    }

    public static function findIdentityByAccessToken($token, $type = null): Identity|IdentityInterface|null
    {
        return self::findOne(['access_token' => $token]);
    }

    public function getId()
    {
        return $this->id;
    }

    public function getAuthKey()
    {
        return $this->access_token;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->access_token === $authKey;
    }
}