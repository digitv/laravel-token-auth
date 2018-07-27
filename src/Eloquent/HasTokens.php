<?php

namespace DigitSoft\LaravelTokenAuth\Eloquent;

use DigitSoft\LaravelTokenAuth\AccessToken;
use DigitSoft\LaravelTokenAuth\Contracts\Storage;

trait HasTokens
{
    /**
     * Get user active access tokens
     * @return AccessToken[]
     */
    public function getTokens()
    {
        $id = $this->getAuthIdentifier();
        return $this->getTokensStorage()->getUserTokens($id, true);
    }

    /**
     * Get active token
     * @param string|null $client_id
     * @return AccessToken
     */
    public function getToken($client_id = null)
    {
        $client_id = $client_id ?? AccessToken::CLIENT_ID_DEFAULT;
        $token = AccessToken::getFirstFor($this, $client_id);
        $token = $token ?? $this->createToken($client_id);
        return $token;
    }

    /**
     * Create new access token and save it to storage
     * @param string|null $client_id
     * @param int|null    $ttl
     * @return AccessToken
     */
    public function createToken($client_id = null, $ttl = 0)
    {
        $token = AccessToken::createFor($this, $client_id);
        if ($ttl !== 0) {
            $token->setTtl($ttl);
        }
        $token->save();
        return $token;
    }

    /**
     * Get token storage instance
     * @return Storage
     */
    protected function getTokensStorage()
    {
        return app('auth.tokencached.storage');
    }
}