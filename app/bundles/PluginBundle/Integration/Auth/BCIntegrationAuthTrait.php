<?php

namespace Mautic\PluginBundle\Integration\Auth;

use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Event\PluginIntegrationAuthCallbackUrlEvent;
use Mautic\PluginBundle\Exception\ApiErrorException;
use Mautic\PluginBundle\PluginEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @DEPRECATED: To be removed in 3.0
 */
trait BCIntegrationAuthTrait
{
    /**
     * Get the type of authentication required for this API.  Values can be none, key, oauth2 or callback
     * (will call $this->authenticationTypeCallback).
     *
     * @return string
     */
    abstract public function getAuthenticationType();

    /**
     * Get the array key for clientId.
     *
     * @return string
     */
    public function getClientIdKey()
    {
        switch ($this->getAuthenticationType()) {
            case 'oauth1a':
                return 'consumer_id';
            case 'oauth2':
                return 'client_id';
            case 'key':
                return 'key';
            default:
                return '';
        }
    }

    /**
     * Get the array key for client secret.
     *
     * @return string
     */
    public function getClientSecretKey()
    {
        switch ($this->getAuthenticationType()) {
            case 'oauth1a':
                return 'consumer_secret';
            case 'oauth2':
                return 'client_secret';
            case 'basic':
                return 'password';
            default:
                return '';
        }
    }

    /**
     * Array of keys to mask in the config form.
     *
     * @return array
     */
    public function getSecretKeys()
    {
        return [$this->getClientSecretKey()];
    }

    /**
     * Get the array key for the auth token.
     *
     * @return string
     */
    public function getAuthTokenKey()
    {
        switch ($this->getAuthenticationType()) {
            case 'oauth2':
                return 'access_token';
            case 'oauth1a':
                return 'oauth_token';
            default:
                return '';
        }
    }

    /**
     * Get the keys for the refresh token and expiry.
     *
     * @return array
     */
    public function getRefreshTokenKeys()
    {
        return [];
    }

    /**
     * Get a list of keys required to make an API call.  Examples are key, clientId, clientSecret.
     *
     * @return array
     */
    public function getRequiredKeyFields()
    {
        switch ($this->getAuthenticationType()) {
            case 'oauth1a':
                return [
                    'consumer_id'     => 'mautic.integration.keyfield.consumerid',
                    'consumer_secret' => 'mautic.integration.keyfield.consumersecret',
                ];
            case 'oauth2':
                return [
                    'client_id'     => 'mautic.integration.keyfield.clientid',
                    'client_secret' => 'mautic.integration.keyfield.clientsecret',
                ];
            case 'key':
                return [
                    'key' => 'mautic.integration.keyfield.api',
                ];
            case 'basic':
                return [
                    'username' => 'mautic.integration.keyfield.username',
                    'password' => 'mautic.integration.keyfield.password',
                ];
            default:
                return [];
        }
    }

    /**
     * Method to prepare the request parameters. Builds array of headers and parameters.
     *
     * @param $url
     * @param $parameters
     * @param $method
     * @param $settings
     * @param $authType
     *
     * @return array
     */
    public function prepareRequest($url, $parameters, $method, $settings, $authType)
    {
        /*
         * @deprecated: 2.14 to be removed in 3.0. If you're setting the
         * 'authorize_session' setting, call authorizeSession() directly.
         */
        if (!empty($settings['authorize_session']) && in_array()) {
            return $this->authorizeSession($url, $parameters, $method, $settings, $authType);
        }

        $headers         = [];
        $clientSecretKey = $this->getClientSecretKey();
        $authTokenKey    = $this->getAuthTokenKey();
        $authToken       = '';

        if (isset($settings['override_auth_token'])) {
            $authToken = $settings['override_auth_token'];
        } elseif (isset($this->keys[$authTokenKey])) {
            $authToken = $this->keys[$authTokenKey];
        }

        // Override token parameter key if neede
        if (!empty($settings[$authTokenKey])) {
            $authTokenKey = $settings[$authTokenKey];
        }

        switch ($authType) {
            case 'basic':
                return [
                    $parameters,
                    [
                        'Authorization' => 'Basic '.base64_encode($this->keys['username'].':'.$this->keys['password']),
                    ],
                ];
            case 'oauth1a':
                $oauthHelper = new oAuthHelper($this, $this->request, $settings);
                // $parameters is potentially modified in this next call :(
                $headers     = $oauthHelper->getAuthorizationHeader($url, $parameters, $method);

                return [
                    $parameters,
                    $headers,
                ];
            case 'oauth2':
                if ($bearerToken = $this->getBearerToken()) {
                    return [
                        $parameters,
                        [
                            "Authorization: Bearer {$bearerToken}",
                        ],
                    ];
                } else {
                    if (!empty($settings['append_auth_token'])) {
                        // Workaround because $settings cannot be manipulated here
                        $parameters['append_to_query'] = [
                            $authTokenKey => $authToken,
                        ];
                    } else {
                        $parameters[$authTokenKey] = $authToken;
                    }

                    return [
                        $parameters,
                        [
                            "oauth-token: $authTokenKey",
                            "Authorization: OAuth {$authToken}",
                        ],
                    ];
                }
                break;
            case 'key':
                $parameters[$authTokenKey] = $authToken;

                return [$parameters, $headers];
        }

        return [$parameters, $headers];
    }

    /**
     * Authorize the session. For OAuth based integrations only.
     * Can only be called from within the integration class.
     *
     * @param $url
     * @param $parameters
     * @param $method
     * @param $settings
     * @param $authType
     *
     * @return array The modified parameters & headers arrays
     */
    protected function authorizeSession($url, $parameters, $method, $settings, $authType)
    {
        $headers         = [];
        $clientIdKey     = $this->getClientIdKey();
        $clientSecretKey = $this->getClientSecretKey();

        if ($authType === 'oauth1a') {
            $requestTokenUrl = $this->getRequestTokenUrl();

            if (!array_key_exists('append_callback', $settings) && !empty($requestTokenUrl)) {
                $settings['append_callback'] = false;
            }

            $oauthHelper = new oAuthHelper($this, $this->request, $settings);
            // $parameters is potentially modified in this next call :(
            $headers     = $oauthHelper->getAuthorizationHeader($url, $parameters, $method);

            return [$parameters, $headers];
        }

        if ($authType === 'oauth2') {
            if ($bearerToken = $this->getBearerToken(true)) {
                $headers = [
                    "Authorization: Basic {$bearerToken}",
                    'Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
                ];
                $parameters['grant_type'] = 'client_credentials';

                return [$parameters, $headers];
            } else {
                $defaultGrantType = !empty($settings['refresh_token']) ? 'refresh_token'
                    : 'authorization_code';
                $grantType = !isset($settings['grant_type']) ? $defaultGrantType
                    : $settings['grant_type'];

                $useClientIdKey     = empty($settings[$clientIdKey]) ? $clientIdKey : $settings[$clientIdKey];
                $useClientSecretKey = empty($settings[$clientSecretKey]) ? $clientSecretKey
                    : $settings[$clientSecretKey];
                $parameters = array_merge(
                    $parameters,
                    [
                        $useClientIdKey     => $this->keys[$clientIdKey],
                        $useClientSecretKey => isset($this->keys[$clientSecretKey]) ? $this->keys[$clientSecretKey] : '',
                        'grant_type'        => $grantType,
                    ]
                );

                if (!empty($settings['refresh_token']) && !empty($this->keys[$settings['refresh_token']])) {
                    $parameters[$settings['refresh_token']] = $this->keys[$settings['refresh_token']];
                }

                if ($grantType == 'authorization_code') {
                    $parameters['code'] = $this->request->get('code');
                }
                if (empty($settings['ignore_redirecturi'])) {
                    $callback                   = $this->getAuthCallbackUrl();
                    $parameters['redirect_uri'] = $callback;
                }

                return [$parameters, $headers];
            }
        }

        return [$parameters, $headers];
    }

    /**
     * Generate the auth login URL.  Note that if oauth2, response_type=code is assumed.  If this is not the case,
     * override this function.
     *
     * @return string
     */
    public function getAuthLoginUrl()
    {
        $authType = $this->getAuthenticationType();

        if ($authType == 'oauth2') {
            $callback    = $this->getAuthCallbackUrl();
            $clientIdKey = $this->getClientIdKey();
            $state       = $this->getAuthLoginState();
            $url         = $this->getAuthenticationUrl()
                .'?client_id='.$this->keys[$clientIdKey]
                .'&response_type=code'
                .'&redirect_uri='.urlencode($callback)
                .'&state='.$state;

            if ($scope = $this->getAuthScope()) {
                $url .= '&scope='.urlencode($scope);
            }

            if ($this->session) {
                $this->session->set($this->getName().'_csrf_token', $state);
            }

            return $url;
        } else {
            return $this->router->generate(
                'mautic_integration_auth_callback',
                ['integration' => $this->getName()]
            );
        }
    }

    /**
     * State variable to append to login url (usually used in oAuth flows).
     *
     * @return string
     */
    public function getAuthLoginState()
    {
        return hash('sha1', uniqid(mt_rand()));
    }

    /**
     * Get the scope for auth flows.
     *
     * @return string
     */
    public function getAuthScope()
    {
        return '';
    }

    /**
     * Gets the URL for the built in oauth callback.
     *
     * @return string
     */
    public function getAuthCallbackUrl()
    {
        $defaultUrl = $this->router->generate(
            'mautic_integration_auth_callback',
            ['integration' => $this->getName()],
            UrlGeneratorInterface::ABSOLUTE_URL //absolute
        );

        /** @var PluginIntegrationAuthCallbackUrlEvent $event */
        $event = $this->dispatcher->dispatch(
            PluginEvents::PLUGIN_ON_INTEGRATION_GET_AUTH_CALLBACK_URL,
            new PluginIntegrationAuthCallbackUrlEvent($this, $defaultUrl)
        );

        return $event->getCallbackUrl();
    }

    /**
     * Retrieves and stores tokens returned from oAuthLogin.
     *
     * @param array $settings
     * @param array $parameters
     *
     * @return bool|string false if no error; otherwise the error string
     *
     * @throws ApiErrorException if OAuth2 state does not match
     */
    public function authCallback($settings = [], $parameters = [])
    {
        $authType = $this->getAuthenticationType();

        switch ($authType) {
            case 'oauth2':
                if ($this->session) {
                    $state      = $this->session->get($this->getName().'_csrf_token', false);
                    $givenState = ($this->request->isXmlHttpRequest()) ? $this->request->request->get('state') : $this->request->get('state');

                    if ($state && $state !== $givenState) {
                        $this->session->remove($this->getName().'_csrf_token');
                        throw new ApiErrorException($this->translator->trans('mautic.integration.auth.invalid.state'));
                    }
                }

                if (!empty($settings['use_refresh_token'])) {
                    // Try refresh token
                    $refreshTokenKeys = $this->getRefreshTokenKeys();

                    if (!empty($refreshTokenKeys)) {
                        list($refreshTokenKey, $expiryKey) = $refreshTokenKeys;

                        $settings['refresh_token'] = $refreshTokenKey;
                    }
                }
                break;

            case 'oauth1a':
                // After getting request_token and authorizing, post back to access_token
                $settings['append_callback']  = true;
                $settings['include_verifier'] = true;

                // Get request token returned from Twitter and submit it to get access_token
                $settings['request_token'] = ($this->request) ? $this->request->get('oauth_token') : '';

                break;
        }

        $settings['authorize_session'] = true;

        $method = (!isset($settings['method'])) ? 'POST' : $settings['method'];
        $data   = $this->makeRequest($this->getAccessTokenUrl(), $parameters, $method, $settings);

        return $this->extractAuthKeys($data);
    }

    /**
     * Extacts the auth keys from response and saves entity.
     *
     * @param $data
     * @param $tokenOverride
     *
     * @return bool|string false if no error; otherwise the error string
     */
    public function extractAuthKeys($data, $tokenOverride = null)
    {
        //check to see if an entity exists
        $entity = $this->getIntegrationSettings();
        if ($entity == null) {
            $entity = new Integration();
            $entity->setName($this->getName());
        }
        // Prepare the keys for extraction such as renaming, setting expiry, etc
        $data = $this->prepareResponseForExtraction($data);

        //parse the response
        $authTokenKey = ($tokenOverride) ? $tokenOverride : $this->getAuthTokenKey();
        if (is_array($data) && isset($data[$authTokenKey])) {
            $keys      = $this->mergeApiKeys($data, null, true);
            $encrypted = $this->encryptApiKeys($keys);
            $entity->setApiKeys($encrypted);

            if ($this->session) {
                $this->session->set($this->getName().'_tokenResponse', $data);
            }

            $error = false;
        } elseif (is_array($data) && isset($data['access_token'])) {
            if ($this->session) {
                $this->session->set($this->getName().'_tokenResponse', $data);
            }
            $error = false;
        } else {
            $error = $this->getErrorsFromResponse($data);
            if (empty($error)) {
                $error = $this->translator->trans(
                    'mautic.integration.error.genericerror',
                    [],
                    'flashes'
                );
            }
        }

        //save the data
        $this->em->persist($entity);
        $this->em->flush();

        $this->setIntegrationSettings($entity);

        return $error;
    }

    /**
     * Called in extractAuthKeys before key comparison begins to give opportunity to set expiry, rename keys, etc.
     *
     * @param $data
     *
     * @return mixed
     */
    public function prepareResponseForExtraction($data)
    {
        return $data;
    }

    /**
     * Checks to see if the integration is configured by checking that required keys are populated.
     *
     * @return bool
     */
    public function isConfigured()
    {
        $requiredTokens = $this->getRequiredKeyFields();
        foreach ($requiredTokens as $token => $label) {
            if (empty($this->keys[$token])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if an integration is authorized and/or authorizes the request.
     *
     * @return bool
     */
    public function isAuthorized()
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $type         = $this->getAuthenticationType();
        $authTokenKey = $this->getAuthTokenKey();

        switch ($type) {
            case 'oauth1a':
            case 'oauth2':
                $refreshTokenKeys = $this->getRefreshTokenKeys();
                if (!isset($this->keys[$authTokenKey])) {
                    $valid = false;
                } elseif (!empty($refreshTokenKeys)) {
                    list($refreshTokenKey, $expiryKey) = $refreshTokenKeys;
                    if (!empty($this->keys[$refreshTokenKey]) && !empty($expiryKey) && isset($this->keys[$expiryKey])
                        && time() > $this->keys[$expiryKey]
                    ) {
                        //token has expired so try to refresh it
                        $error = $this->authCallback(['refresh_token' => $refreshTokenKey]);
                        $valid = (empty($error));
                    } else {
                        // The refresh token doesn't have an expiry so the integration will have to check for expired sessions and request new token
                        $valid = true;
                    }
                } else {
                    $valid = true;
                }
                break;
            case 'key':
                $valid = isset($this->keys['api_key']);
                break;
            case 'rest':
                $valid = isset($this->keys[$authTokenKey]);
                break;
            case 'basic':
                $valid = (!empty($this->keys['username']) && !empty($this->keys['password']));
                break;
            default:
                $valid = true;
                break;
        }

        return $valid;
    }

    /**
     * Get the URL required to obtain an oauth2 access token.
     *
     * @return string
     */
    public function getAccessTokenUrl()
    {
        return '';
    }

    /**
     * Get the authentication/login URL for oauth2 access.
     *
     * @return string
     */
    public function getAuthenticationUrl()
    {
        return '';
    }

    /**
     * Get request token for oauth1a authorization request.
     *
     * @param array $settings
     *
     * @return mixed|string
     */
    public function getRequestToken($settings = [])
    {
        // Child classes can easily pass in custom settings this way
        $settings = array_merge(
            ['authorize_session' => true, 'append_callback' => false, 'ssl_verifypeer' => true],
            $settings
        );

        // init result to empty string
        $result = '';

        $url = $this->getRequestTokenUrl();
        if (!empty($url)) {
            $result = $this->makeRequest(
                $url,
                [],
                'POST',
                $settings
            );
        }

        return $result;
    }

    /**
     * Url to post in order to get the request token if required; leave empty if not required.
     *
     * @return string
     */
    public function getRequestTokenUrl()
    {
        return '';
    }

    /**
     * Generate a bearer token.
     *
     * @param $inAuthorization
     *
     * @return string
     */
    public function getBearerToken($inAuthorization = false)
    {
        return '';
    }
}
