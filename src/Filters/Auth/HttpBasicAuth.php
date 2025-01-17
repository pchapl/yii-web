<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace Yiisoft\Web\Filters\Auth;

/**
 * HttpBasicAuth is an action filter that supports the HTTP Basic authentication method.
 *
 * You may use HttpBasicAuth by attaching it as a behavior to a controller or module, like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'basicAuth' => [
 *             '__class' => \Yiisoft\Web\filters\auth\HttpBasicAuth::class,
 *         ],
 *     ];
 * }
 * ```
 *
 * The default implementation of HttpBasicAuth uses the [[\Yiisoft\Web\User::loginByAccessToken()|loginByAccessToken()]]
 * method of the `user` application component and only passes the user name. This implementation is used
 * for authenticating API clients.
 *
 * If you want to authenticate users using username and password, you should provide the [[auth]] function for example like the following:
 *
 * ```php
 * public function behaviors()
 * {
 *     return [
 *         'basicAuth' => [
 *             '__class' => \Yiisoft\Web\filters\auth\HttpBasicAuth::class,
 *             'auth' => function ($username, $password) {
 *                 $user = User::find()->where(['username' => $username])->one();
 *                 if ($user->verifyPassword($password)) {
 *                     return $user;
 *                 }
 *                 return null;
 *             },
 *         ],
 *     ];
 * }
 * ```
 *
 * > Tip: In case authentication does not work like expected, make sure your web server passes
 * username and password to `$_SERVER['PHP_AUTH_USER']` and `$_SERVER['PHP_AUTH_PW']` variables.
 * If you are using Apache with PHP-CGI, you might need to add this line to your `.htaccess` file:
 * ```
 * RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization},L]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class HttpBasicAuth extends AuthMethod
{
    /**
     * @var string the HTTP authentication realm
     */
    public $realm = 'api';
    /**
     * @var callable a PHP callable that will authenticate the user with the HTTP basic auth information.
     * The callable receives a username and a password as its parameters. It should return an identity object
     * that matches the username and password. Null should be returned if there is no such identity.
     * The callable will be called only if current user is not authenticated.
     *
     * The following code is a typical implementation of this callable:
     *
     * ```php
     * function ($username, $password) {
     *     return \app\models\User::findOne([
     *         'username' => $username,
     *         'password' => $password,
     *     ]);
     * }
     * ```
     *
     * If this property is not set, the username information will be considered as an access token
     * while the password information will be ignored. The [[\Yiisoft\Web\User::loginByAccessToken()]]
     * method will be called to authenticate and login the user.
     */
    public $auth;


    /**
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        [$username, $password] = $request->getAuthCredentials();

        if ($this->auth) {
            if ($username !== null || $password !== null) {
                $identity = $user->getIdentity() ?: call_user_func($this->auth, $username, $password);

                if ($identity === null) {
                    $this->handleFailure($response);
                } elseif ($user->getIdentity(false) !== $identity) {
                    $user->switchIdentity($identity);
                }

                return $identity;
            }
        } elseif ($username !== null) {
            $identity = $user->loginByAccessToken($username, get_class($this));
            if ($identity === null) {
                $this->handleFailure($response);
            }

            return $identity;
        }

        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function challenge($response)
    {
        $response->setHeader('WWW-Authenticate', "Basic realm=\"{$this->realm}\"");
    }
}
