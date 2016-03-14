<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use Abraham\TwitterOAuth\TwitterOAuth;
use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\AuthenticationException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;

class TwitterApi extends Singleton implements SocialNetworkInterface {

    const ID = 'twitter';
    const TWITTER_OAUTH_URL = "https://api.twitter.com/oauth/authorize";

    // Twitter client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    /**
     * Set Twitter Api keys
     * @param $clientId
     * @param $clientSecret
     * @param $clientScope
     * @throws ConnectorConfigException
     */
    public function setApiKeys($clientId, $clientSecret, $clientScope) {
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

        $this->client = new TwitterOAuth($this->clientId, $this->clientSecret);
    }

    /**
     * Compose Twitter Api credentials array from session data
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

        $parameters = array(
            'oauth_callback' => $redirectUrl
        );

        $response = $this->client->oauth('oauth/request_token', $parameters);

        $parameters = array(
            "oauth_token" => $response["oauth_token"],
            "oauth_callback" => $redirectUrl
        );

        $authUrl = self::TWITTER_OAUTH_URL . "?" . http_build_query($parameters);

        // Authentication request
        return $authUrl;
    }

    /**
     * @param string $code
     * @param string $verifier
     * @param string $redirectUrl
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @throws MalformedUrlException
     */
    public function authorize($code, $verifier, $redirectUrl)
    {
        if ((null === $code) || ("" === $code)) {
            throw new ConnectorConfigException("'code' parameter is required");
        }

        if ((null === $verifier) || ("" === $verifier)) {
            throw new ConnectorConfigException("'verifier' parameter is required");
        }

        if ((null === $redirectUrl) || (empty($redirectUrl))) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required");
        } else {
            if (!SocialNetworks::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed");
            }
        }

        try {
            $parameters = array(
                "oauth_verifier" => $verifier,
                "oauth_token" => $code,
            );

            $response = $this->client->oauth("oauth/access_token", $parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $twitterCredentials = array(
            "access_token" => $response["oauth_token"],
            "access_token_secret" => $response["oauth_token_secret"],
        );

        return $twitterCredentials;
    }

    /**
     * Method that inject the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->client->setOauthToken($credentials["access_token"], $credentials["access_token_secret"]);
    }

    /**
     * Service that check if credentials are valid
     * @param array $credentials
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function checkCredentials(array $credentials) {
        $this->checkCredentialsParameters($credentials);

        try {
            return $this->getProfile(SocialNetworks::ENTITY_USER, null);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set'");
        }
    }

    public function exportSubscribers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportSubscribers() method.
    }

    /**
     * Service that query to Twitter Api to get user profile
     * @param string $entity "user"
     * @param string $id    user id
     * @return string
     * @throws ConnectorServiceException
     */
    public function getProfile($entity, $id)
    {
        $response = $this->client->get("account/verify_credentials", array("include_email", "true"));

        if (200 === $this->client->getLastHttpCode()) {
            return json_encode($response);
        } else {
            throw new ConnectorServiceException("Error getting user profile");
        }
    }

    public function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportFollowers() method.
    }

    public function exportMedia($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportMedia() method.
    }

    public function exportPosts($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportPosts() method.
    }

    public function importMedia($entity, $id, $parameters)
    {
        // TODO: Implement importMedia() method.
    }

    public function post($entity, $id, array $parameters)
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

        if ((!isset($credentials["access_token_secret"])) || (null === $credentials["access_token_secret"]) || ("" === $credentials["access_token_secret"])) {
            throw new ConnectorConfigException("'access_token_secret' parameter is required");
        }
    }
}