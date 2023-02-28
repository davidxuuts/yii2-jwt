# Yii2 JWT

This extension provides the [JWT](https://github.com/lcobucci/jwt) integration for the [Yii framework 2.0](http://www.yiiframework.com).

## Table of contents

1. [Installation](#installation)
2. [Dependencies](#dependencies)
3. [Basic usage](#basicusage)
   1. [Generating public and private keys](#generating-public-and-private-keys)
   2. [Use as a Yii component](#use-as-a-yii-component)
   3. [Use as a Yii params](#use-as-a-yii-params)
   4. [Creating](#basicusage-creating)
   5. [Parsing from strings](#basicusage-parsing)
   6. [Validating](#basicusage-validating)
4. [Yii2 advanced template example](#yii2advanced-example)

<a name="installation"></a>
## Installation

Package is available on [Packagist](https://packagist.org/packages/davidxu/yii2-jwt),
you can install it using [Composer](http://getcomposer.org).

```shell
composer require davidxu/yii2-jwt
```

<a name="dependencies"></a>
## Dependencies

- PHP 8.0+
- OpenSSL Extension

<a name="basicusage"></a>
## Basic usage
<a name="generating-public-and-private-keys"></a>
### 1. Generating public and private keys
The public/private key pair is used to sign and verify JWTs transmitted.
To generate the private key run this command on the terminal:
```shell
openssl genrsa -out private.key 2048
```
If you want to provide a passphrase for your private key run this command instead:
```shell
openssl genrsa -aes128 -passout pass:_passphrase_ -out private.key 2048
```
then extract the public key from the private key:
```shell
openssl rsa -in private.key -pubout -out public.key
```
or use your passphrase if provided on private key generation:
```shell
openssl rsa -in private.key -passin pass:_passphrase_ -pubout -out public.key
```
The private key must be kept secret (i.e. out of the web-root of the authorization server).

<a name="#use-as-a-yii-component"></a>
### 2.1 Use as a Yii component

Add `jwt` component to your configuration file,

```php
'components' => [
    'jwt' => [
        'class' => \davidxu\jwt\Jwt::class,
        'privateKey' => __DIR__ . '/../private.key',
        'publicKey' => __DIR__ . '/../public.key',
        // A date/time string. Valid formats are explained in
        // [Date and Time Formats](https://secure.php.net/manual/en/datetime.formats.php)
        'expire_time' => '+2 hour'
    ],
],
```
<a name="#use-as-a-yii-params"></a>
### 2.2 Use as a Yii params
Add following params in `params.php`
```php
return [
    //...
    'jwt' => [
        'privateKey' => __DIR__ . '/../private.key',
        'publicKey' => __DIR__ . '/../public.key',
        'expire_time' => '+2 hour'
    ],
    //...
];
```
Configure the `authenticator` behavior as follows.

```php
namespace app\controllers;

class ExampleController extends \yii\rest\Controller
{

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => CompositeAuth::class,
            'authMethods' => [
                [
                    'class' => HttpBearerAuth::class,
                ],
            ]
        ];
        return $behaviors;
    }
}
```

<a name="basicusage-creating"></a>
### 3. Creating

Just use `getToken` to create/issue a new JWT token:

```php
$jwt = new davidxu\jwt\Jwt();
// OR 
// $jwt = Yii::$app->jwt;
$token = $jwt->getToken([
    'uid' => 12345,
    'app_id' => Yii::$app->id,
]);

echo $token->claims()->get('uid'); // will print "12345"
echo $token->toString();
```

<a name="basicusage-parsing"></a>
### Parsing from strings

Use `parseToken` to parse a token from a JWT string (using the previous token as example):

```php
$jwt = new davidxu\jwt\Jwt();
// OR 
// $jwt = Yii::$app->jwt;
$token = $jwt->parseToken($token);
echo $token->claims()->get('uid'); // will print "12345"
```

<a name="basicusage-validating"></a>
### Validating

We can easily validate if the token is valid (using the previous token as example):

```php
$jwt = new davidxu\jwt\Jwt();
// OR 
// $jwt = Yii::$app->jwt;
$valid = $jwt->validateToken($token, true, [
    'app_id' => Yii::$app->id,
    ], 'uid'); // return 12345(uid)
```

<a name="yii2advanced-example"></a>
## Yii2 advanced template example

### Change method `common\models\User::findIdentityByAccessToken()`

```php
public static function findIdentityByAccessToken($token, $type = null): ?Member
{
    // use yii2 components
    $jwt = Yii::$app->jwt;
    // use yii2 params
    $jwt = new \davidxu\jwt\Jwt();
    $jwt->privateKey = Yii::$app->params['jwt']['privateKey'];
    $jwt->publicKey = Yii::$app->params['jwt']['publicKey'];
    $jwt->expire_time = '+2 hour';
    return Member::findOne($jwt->validateToken($jwt->parseToken($token)));
}
```
