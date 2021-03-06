<?php

namespace DigitSoft\LaravelTokenAuth;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use DigitSoft\LaravelTokenAuth\Contracts\Storage;
use Illuminate\Config\Repository as ConfigRepository;
use DigitSoft\LaravelTokenAuth\Events\AccessTokenCreated;
use DigitSoft\LaravelTokenAuth\Contracts\AccessToken as AccessTokenContract;

class AccessTokenHelper
{
    /**
     * @var ConfigRepository
     */
    protected $config;

    public function __construct(ConfigRepository $config)
    {
        $this->config = $config;
    }

    /**
     * Get last added user token.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string                                     $client_id
     * @param  Contracts\Storage|null                     $storage
     * @return Contracts\AccessToken|null
     */
    public function getFirstFor(Authenticatable $user, $client_id = null, Storage $storage = null)
    {
        if ($client_id === null) {
            $client_id = $this->getDefaultClientId();
        }
        if ($storage === null) {
            $storage = app()->make(Storage::class);
        }
        $userId = $user->getAuthIdentifier();
        $list = $storage->getUserTokens($userId, true);
        if (empty($list)) {
            return null;
        }
        foreach ($list as $token) {
            if ($token->client_id === $client_id) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Create new token for user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     * @param  string                                     $client_id
     * @param  bool                                       $autoTtl
     * @return Contracts\AccessToken
     */
    public function createFor(Authenticatable $user, $client_id = null, $autoTtl = true)
    {
        if ($client_id === null) {
            $client_id = $this->getDefaultClientId();
        }
        $data = [
            'user_id' => $user->getAuthIdentifier(),
            'client_id' => $client_id,
        ];
        $token = $this->createFromData($data);
        $token->ensureUniqueness();
        if ($autoTtl) {
            $token->setTtl(config('auth-token.ttl'));
        }
        AccessTokenCreated::dispatch($token);

        return $token;
    }

    /**
     * Create new token for guest.
     *
     * @param  string|null $client_id
     * @param  bool        $autoTtl
     * @return Contracts\AccessToken
     */
    public function createForGuest($client_id = null, $autoTtl = true)
    {
        if ($client_id === null) {
            $client_id = $this->getDefaultClientId();
        }
        $data = [
            'user_id' => AccessTokenContract::USER_ID_GUEST,
            'client_id' => $client_id,
        ];
        $token = $this->createFromData($data);
        $token->ensureUniqueness();
        if ($autoTtl) {
            $token->setTtl(config('auth-token.ttl_guest'));
        }
        AccessTokenCreated::dispatch($token);

        return $token;
    }

    /**
     * Create token instance from data array.
     *
     * @param  array $data
     * @param  bool  $fromStorage
     * @return Contracts\AccessToken
     * @throws null
     */
    public function createFromData($data = [], $fromStorage = false)
    {
        return app()->make(AccessTokenContract::class, ['config' => $data, 'fromStorage' => $fromStorage]);
    }

    /**
     * Remove all tokens for a user.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable $user
     */
    public function removeAllFor(Authenticatable $user)
    {
        if (($userId = $user->getAuthIdentifier()) === null) {
            return;
        }
        /** @var Storage $storage */
        $storage = app()->make(Storage::class);
        $list = $storage->getUserTokens($userId, true);
        if (empty($list)) {
            return;
        }
        foreach ($list as $token) {
            $storage->removeToken($token);
        }
    }

    /**
     * Get default client ID
     *
     * @return string
     */
    public function getDefaultClientId()
    {
        return $this->config->get('auth-token.client_id_default', 'api');
    }

    /**
     * Get client ID from request.
     *
     * @param  \Illuminate\Http\Request $request
     * @return string
     */
    public function getClientIdFromRequest(Request $request)
    {
        if (($clientId = $request->input(AccessTokenContract::REQUEST_CLIENT_ID_PARAM)) !== null && $this->validateClientId($clientId)) {
            return $clientId;
        }
        if (($clientId = $request->header(AccessTokenContract::REQUEST_CLIENT_ID_HEADER)) !== null && $this->validateClientId($clientId)) {
            return $clientId;
        }

        return $this->getDefaultClientId();
    }

    /**
     * Generate random token string.
     *
     * @return string
     * @throws null
     */
    public function generateTokenStr()
    {
        $randLength = config('auth-token.token_length', 60);
        $randomStr = Str::random($randLength);
        $hash = hash('sha256', $randomStr);
        $hashLn = 64; //for sha256 (256/4)
        for ($i = 0; $i < $hashLn; $i++) {
            if (! is_numeric($hash[$i]) && random_int(0, 1) % 2) {
                $hash[$i] = strtoupper($hash[$i]);
            }
        }
        $pos = ceil($randLength / 2);

        return substr($randomStr, 0, $pos) . $hash . substr($randomStr, $pos);
    }

    /**
     * Validate token string.
     *
     * @param  string $token
     * @return bool
     */
    public function validateTokenStr(string $token)
    {
        $randLength = config('auth-token.token_length', 60);
        $hashLn = 64; //for sha256 (256/4)
        if (strlen($token) !== ($randLength + $hashLn)) {
            return false;
        }
        $pos = ceil($randLength / 2);
        $randStr = substr($token, 0, $pos) . substr($token, -($randLength - $pos));
        $hash = strtolower(substr($token, $pos, $hashLn));

        return hash('sha256', $randStr) === $hash;
    }

    /**
     * Check that client ID is valid.
     *
     * @param  string $client_id
     * @return bool
     */
    protected function validateClientId($client_id)
    {
        $ids = $this->config->get('auth-token.client_ids', [$this->getDefaultClientId()]);

        return in_array($client_id, $ids, true);
    }
}
