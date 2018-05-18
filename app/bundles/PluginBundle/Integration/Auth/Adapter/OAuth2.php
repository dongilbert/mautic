<?php

namespace Mautic\PluginBundle\Integration\Auth\Adapter;

class OAuth2
{
    /**
     * @var string
     */
    private $clientIdKey = 'client_id';

    /**
     * @var string
     */
    private $clientSecretKey = 'client_secret';

    /**
     * @var string
     */
    private $authTokenKey = 'access_token';

    /**
     * @param string $key
     */
    public function setClientIdKey($key)
    {
        $this->clientIdKey = $key;
    }

    /**
     * @return string
     */
    public function getClientIdKey()
    {
        return $this->clientIdKey;
    }

    /**
     * @param string $key
     */
    public function setClientSecretKey($key)
    {
        $this->clientSecretKey = $key;
    }

    /**
     * @return string
     */
    public function getClientSecretKey()
    {
        return $this->clientSecretKey;
    }

    /**
     * @param string $key
     */
    public function setAuthTokenKey($key)
    {
        $this->authTokenKey = $key;
    }

    /**
     * @return string
     */
    public function getAuthTokenKey()
    {
        return $this->authTokenKey;
    }

    /**
     * @return array
     */
    public function getRequiredKeyFields()
    {
        return [
            $this->clientIdKey,
            $this->clientSecretKey,
        ];
    }
}
