<?php

namespace DigitSoft\LaravelTokenAuth\Tests\Unit;

use DigitSoft\LaravelTokenAuth\AccessToken;
use DigitSoft\LaravelTokenAuth\Storage\Redis;
use DigitSoft\LaravelTokenAuth\Tests\TestCase;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Class RedisStoreTest
 * @covers \DigitSoft\LaravelTokenAuth\Storage\Redis
 * @covers \DigitSoft\LaravelTokenAuth\Storage\StorageHelpers
 */
class RedisStoreTest extends TestCase
{
    protected $storage;

    /**
     * Connection success test
     * @coversNothing
     */
    public function testConnectionSuccess()
    {
        $connection = $this->getStorageConnection();
        $this->assertTrue($connection instanceof Connection);
        $this->assertNotNull($connection->keys('*'));
    }

    /**
     * Token insertion test
     */
    public function testTokenInsertAndRead()
    {
        $token = $this->createToken();
        $tokenNoTtl = $this->createToken(null, str_random(60));
        $tokenNoUser = $this->createToken(10, str_random(60));
        $tokenNoUser->user_id = \DigitSoft\LaravelTokenAuth\Contracts\AccessToken::USER_ID_GUEST;
        $this->getStorage()->setManager(app('redis'));
        $this->getStorage()->setToken($token);
        $this->getStorage()->setToken($tokenNoTtl);
        $this->getStorage()->setToken($tokenNoUser);
        $tokenRead = $this->getStorage()->getToken($token->token);
        $tokenNoTtlRead = $this->getStorage()->getToken($tokenNoTtl->token);
        $tokenNoUserRead = $this->getStorage()->getToken($tokenNoUser->token);
        $tokenExists = $this->getStorage()->tokenExists($token);
        $tokenReadEmpty = $this->getStorage()->getToken($token->token . "qwerty");
        $tokenReadMultipleEmpty = $this->getStorage()->getTokens([]);
        $this->assertEmpty($tokenReadEmpty, 'False token not found');
        $this->assertEmpty($tokenReadMultipleEmpty, 'Empty array passed to ::getTokens()');
        $this->assertInstanceOf(AccessToken::class, $tokenRead, 'Token is an instance of AccessToken');
        $this->assertInstanceOf(AccessToken::class, $tokenNoTtlRead, 'Token (without TTL) is an instance of AccessToken');
        $this->assertInstanceOf(AccessToken::class, $tokenNoUserRead, 'Token (without user) is an instance of AccessToken');
        $this->assertTrue($tokenExists, 'Token exists in storage');
        $this->assertEquals($token->token, $tokenRead->token, 'Token read successfully');
        $this->assertEquals($tokenNoTtl->token, $tokenNoTtlRead->token, 'Token without TTL read successfully');
        $this->assertEquals($tokenNoUser->token, $tokenNoUserRead->token, 'Token without user read successfully');
        $this->assertNull($tokenNoTtl->ttl, 'TTL in token is null');
        $this->assertTrue($tokenNoUserRead->isGuest(), 'Token without user is valid guest token');
        $this->getStorage()->removeToken($tokenNoTtl);
    }

    /**
     * Token insertion test
     */
    public function testTokenUserAssign()
    {
        $token = $this->createToken();
        $tokenExpired = $this->createToken(0, str_random(60));
        $tokenExpiring = $this->createToken(1, str_random(60));
        $this->getStorage()->setToken($token);
        $this->getStorage()->setToken($tokenExpired);
        $this->getStorage()->setToken($tokenExpiring);
        sleep(2);
        $tokens = $this->getStorage()->getUserTokens($this->token_user_id);
        $tokensByIds = $this->getStorage()->getTokens([$token->token, $tokenExpired->token, $tokenExpiring->token]);
        $tokensLoaded = $this->getStorage()->getUserTokens($this->token_user_id, true);
        $tokensEmpty = $this->getStorage()->getUserTokens(0);
        $this->assertTrue(isset($tokensLoaded[$token->token]), 'User tokens [loaded] contains given token');
        $this->assertFalse(isset($tokensLoaded[$tokenExpiring->token]), 'User tokens [loaded] does not contain expiring token');
        $this->assertNotEmpty($tokensLoaded, 'User tokens [loaded] not empty');
        $this->assertNotEmpty($tokens, 'User tokens not empty');
        $this->assertEmpty($tokensEmpty, 'Not existent user tokens are empty');
        $this->assertContains($token->token, $tokens, 'User tokens not empty');
        $this->assertNotContains($tokenExpired->token, $tokens, 'User tokens not contain expired token');
        $this->assertNotContains($tokenExpired->token, $tokensByIds, 'Tokens got by IDs does not contain expired token');
        $this->assertNotContains($tokenExpiring->token, $tokensByIds, 'Tokens got by IDs does not contain expiring token');
    }

    public function testRemoveTokenFromUser()
    {
        $token = $this->createToken();
        $this->getStorage()->setToken($token);
        $this->getStorage()->removeToken($token);
        $tokens = $this->getStorage()->getUserTokens($this->token_user_id, false);
        $this->getStorage()->setUserTokens($this->token_user_id, []);
        $tokensEmpty = $this->getStorage()->getUserTokens($this->token_user_id, false);
        $this->assertNotContains($token->token, $tokens, 'Token was removed from user assigns');
        $this->assertEmpty($tokensEmpty, 'User tokens assigns clear');
    }

    public function testUserMassiveTokenAssign()
    {
        /** @var \DigitSoft\LaravelTokenAuth\Contracts\AccessToken[] $tokens */
        $tokens = [];
        $tokens[] = $this->createToken(false, str_random(60));
        $tokens[] = $this->createToken(false, str_random(60));
        $tokens[] = $this->createToken(false, str_random(60));
        foreach ($tokens as $token) {
            $token->save();
        }
        $tokensFirstRead = $this->getStorage()->getUserTokens($this->token_user_id);
        $this->assertNotEmpty($tokensFirstRead, 'First user tokens list read success');
        $this->getStorage()->setUserTokens($this->token_user_id, $tokens);
        $tokensSecondRead = $this->getStorage()->getUserTokens($this->token_user_id);
        $this->assertNotEmpty($tokensSecondRead, 'Second user tokens list read success');
        $this->assertEquals($tokensFirstRead, $tokensSecondRead, 'First data and second data are equals');
    }

    protected function getStorage()
    {
        if (!isset($this->storage)) {
            $this->storage = new Redis(config());
        }
        return $this->storage;
    }

    protected function getStorageConnection()
    {
        return $this->getStorage()->getConnection();
    }
}
