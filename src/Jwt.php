<?php
/*
 * Copyright (c) 2023.
 * @author David Xu <david.xu.uts@163.com>
 * All rights reserved.
 */

namespace davidxu\jwt;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Hmac\Sha384;
use Lcobucci\JWT\Signer\Hmac\Sha512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Token;
use Lcobucci\JWT\Validation\Constraint\HasClaimWithValue;
use Lcobucci\JWT\Validation\Constraint\IssuedBy;
use Lcobucci\JWT\Validation\Validator;
use yii\base\Component;
use yii\helpers\Url;

use yii\web\BadRequestHttpException;
use Yii;

class Jwt extends Component
{
    public ?string $privateKey = null;
    public ?string $publicKey = null;
    public string $algorithm = "RS256";
    /**
     * @var int|string $expire_time <p>A date/time string. Valid formats are explained in
     * {@link https://secure.php.net/manual/en/datetime.formats.php Date and Time Formats}.</p>
     */
    public int|string $expire_time = '+2 hour';

    /**
     * @var array Supported algorithms
     */
    public array $supportedAlgorithms = [
        'HS256' => Sha256::class,
        'HS384' => Sha384::class,
        'HS512' => Sha512::class,
        'ES256' => Signer\Ecdsa\Sha256::class,
        'ES384' => Signer\Ecdsa\Sha384::class,
        'ES512' => Signer\Ecdsa\Sha512::class,
        'RS256' => Signer\Rsa\Sha256::class,
        'RS384' => Signer\Rsa\Sha384::class,
        'RS512' => Signer\Rsa\Sha512::class,
    ];

    /**
     * @param array $claims key/value such as ['uid' => 'uid', 'app_id' => 'app-id']
     * @return Token
     * @throws BadRequestHttpException
     */
    public function getToken(array $claims): Token
    {
        $now = (new DateTimeImmutable())->setTimezone(new DateTimeZone(Yii::$app->getTimeZone()));
        $config = $this->getConfiguration();
        $builder = $config->builder()
            ->issuedBy(Url::home(true))
            ->issuedAt($now)
            ->expiresAt($now->modify($this->expire_time));
        foreach ($claims as $name => $value) {
            $builder->withClaim($name, $value);
        }

        return $builder->getToken($config->signer(), $config->signingKey());
    }

    /**
     * @param Token $token
     * @param bool $validate_expires
     * @param array $claims
     * @param ?string $data
     * @return mixed
     */
    public function validateToken(Token $token, bool $validate_expires = true, array $claims = [], ?string $data = 'uid'): mixed
    {
        $expired = false;
        if ($validate_expires) {
            $expired = $this->tokenExpired($token);
        }
        if ($expired && $validate_expires) {
            return false;
        }

        $validator = new Validator();
        $result = assert($token instanceof Token);
        $result = $result && $validator->validate($token, new issuedBy(Url::home(true)));
        if ($claims) {
            foreach ($claims as $claim => $value) {
                $result = $result && $validator->validate($token, new HasClaimWithValue($claim, $value));
            }
        }
        return $data === null ? $result : $token->claims()->get($data);
    }

    /**
     * @param Token $token
     * @return bool
     */
    public function tokenExpired(Token $token): bool
    {
        return $token->isExpired((new DateTimeImmutable())->setTimezone(new DateTimeZone(Yii::$app->getTimeZone())));
    }

    /**
     * @param string $token
     * @return Token
     * @throws BadRequestHttpException
     */
    public function parseToken(string $token): Token
    {
        $config = $this->getConfiguration();
        return $config->parser()->parse($token);
    }

    /**
     * @return Configuration
     * @throws BadRequestHttpException
     * @throws Exception
     */
    private function getConfiguration(): Configuration
    {
        return empty($this->privateKey) || empty($this->publicKey)
            ? Configuration::forSymmetricSigner(
                new Signer\Rsa\Sha256(),
                InMemory::base64Encoded(random_bytes(32))
            )
            : Configuration::forAsymmetricSigner(
                $this->getSigner(),
                InMemory::file($this->privateKey),
                InMemory::file($this->publicKey),
            );
    }

    /**
     * @return Signer
     * @throws BadRequestHttpException
     */
    private function getSigner(): Signer
    {
        $algorithm = strtoupper($this->algorithm);
        if (empty($this->algorithm) || !in_array($algorithm, array_keys($this->supportedAlgorithms))) {
            throw new BadRequestHttpException('No correct algorithm found');
        }

        $class = $this->supportedAlgorithms[$algorithm];
        return new $class();
    }
}
