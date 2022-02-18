<?php

namespace app\components;

use Yii;
use sizeg\jwt\JwtValidationData;
use Lcobucci\JWT\Token;

class Jwt extends JwtValidationData
{
    const TTL = 86400;
    const ISSUER = 'http://fit-be.com';
    const AUDIENCE = 'http://fit-be.com';
    const JID = '4f1g23a12aa';

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->validationData->setIssuer(self::ISSUER);
        $this->validationData->setAudience(self::AUDIENCE);
        $this->validationData->setId(self::JID);
        parent::init();
    }

    /**
     * Returns Token object
     * @param string $user_id
     * @return mixed
     */
    public static function generate(string $user_id): Token
    {
        $jwt = Yii::$app->jwt;
        $time = time();
        return $jwt->getBuilder()
            ->issuedBy(self::ISSUER)// Configures the issuer (iss claim)
            ->permittedFor(self::AUDIENCE)// Configures the audience (aud claim)
            ->identifiedBy(self::JID, true)// Configures the id (jti claim), replicating as a header item
            ->issuedAt($time)// Configures the time that the token was issue (iat claim)
            ->expiresAt($time + self::TTL)// Configures the expiration time of the token (exp claim)
            ->withClaim('uid', $user_id)// Configures a new claim, called "uid"
            ->getToken($jwt->getSigner('HS256'), $jwt->getKey()); // Retrieves the generated token
    }
}
