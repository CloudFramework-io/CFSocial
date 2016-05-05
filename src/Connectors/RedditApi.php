<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use OAuth2\Client;
use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;

class RedditApi extends Singleton implements SocialNetworkInterface {

    const ID = "reddit";
    const REDDIT_AUTHORIZE_URL = "https://ssl.reddit.com/api/v1/authorize";
    const REDDIT_ACCESS_TOKEN_URL = "https://ssl.reddit.com/api/v1/access_token";
    const REDDIT_ENDPOINT_ME_URL = "https://oauth.reddit.com/api/v1/me.json";

    // Reddit client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    /**
     * Set Reddit Api keys
     * @param $clientId
     * @param $clientSecret
     * @param $clientScope
     * @param $redirectUrl
     * @throws ConnectorConfigException
     */
    public function setApiKeys($clientId, $clientSecret, $clientScope, $redirectUrl = null) {
        if ((null === $clientId) || ("" === $clientId)) {
            throw new ConnectorConfigException("'clientId' parameter is required");
        }

        if ((null === $clientSecret) || ("" === $clientSecret)) {
            throw new ConnectorConfigException("'clientSecret' parameter is required");
        }

        if ((null === $clientScope) || (!is_array($clientScope)) /*|| (count($clientScope) == 0)*/) {
            throw new ConnectorConfigException("'clientScope' parameter is required");
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new Client($this->clientId, $this->clientSecret, Client::AUTH_TYPE_AUTHORIZATION_BASIC);
        //@TODO Look into user agent
        $this->client->setCurlOption(CURLOPT_USERAGENT,"ChangeMeClient/0.1 by YourUsername");
    }

    /**
     * Service that requests authorization to Reddit making up the Reddit login URL
     * @param string $redirectUrl
     * @throws ConnectorConfigException
     * @throws MalformedUrlException
     * @return array
     */
    public function requestAuthorization($redirectUrl)
    {
        if ((null === $redirectUrl) || (empty($redirectUrl))) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required");
        } else {
            if (!SocialNetworks::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed");
            }
        }

        $authUrl = $this->client->getAuthenticationUrl(self::REDDIT_AUTHORIZE_URL, $redirectUrl, array("scope" => implode(" ",$this->clientScope), "state" => rand(10000, 20000)));

        if ((null === $authUrl) || (empty($authUrl))) {
            throw new ConnectorConfigException("'authUrl' parameter is required");
        } else {
            if (!SocialNetworks::wellFormedUrl($authUrl)) {
                throw new MalformedUrlException("'authUrl' is malformed");
            }
        }

        // Authentication request
        return $authUrl;
    }

    /**
     * Authentication service from Reddit sign in request
     * @param string $code
     * @param string $verifier
     * @param string $redirectUrl
     * @return array
     * @throws ConnectorConfigException
     */
    public function authorize($code, $verifier, $redirectUrl)
    {
        if ((null === $code) || ("" === $code)) {
            throw new ConnectorConfigException("'code' parameter is required");
        }

        $params = array("code" => $_GET["code"], "redirect_uri" => $redirectUrl);
        $response = $this->client->getAccessToken(self::REDDIT_ACCESS_TOKEN_URL, "authorization_code", $params);

        $accessTokenResult = $response["result"];

        $redditCredentials = array("access_token" => $accessTokenResult["access_token"]);

        return $redditCredentials;
    }

    /**
     * Method that injects the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->client->setAccessToken($credentials["access_token"]);
        $this->client->setAccessTokenType(Client::ACCESS_TOKEN_BEARER);
    }

    /**
     * Service that checks if credentials are valid
     * @param array $credentials
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function checkCredentials(array $credentials) {
        $this->checkCredentialsParameters($credentials);

        try {
            return $this->getProfile();
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set'");
        }
    }

    /**
     * Service that queries to Reddit Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id = null)
    {
        $response = $this->client->fetch(self::REDDIT_ENDPOINT_ME_URL);

        $profile = [
            "user_id" => null,
            "name" => $response["result"]["name"],
            "first_name" => null,
            "last_name" => null,
            "email" => null,
            "photo" => null,
            "locale" => null,
            "url" => null,
            "raw" => $response["result"]
        ];

        return $profile;
    }

    function post($id, array $parameters)
    {
        // TODO: Implement post() method.
    }

    /**
     * Method that check credentials are present and valid
     * @param array $credentials
     * @throws ConnectorConfigException
     */
    private function checkCredentialsParameters(array $credentials) {
        if ((null === $credentials) || (!is_array($credentials)) || (count($credentials) == 0)) {
            throw new ConnectorConfigException("Invalid credentials set");
        }

        if ((!isset($credentials["access_token"])) || (null === $credentials["access_token"]) || ("" === $credentials["access_token"])) {
            throw new ConnectorConfigException("'access_token' parameter is required");
        }
    }
}