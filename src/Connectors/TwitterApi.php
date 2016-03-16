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
    const MAX_IMPORT_IMAGE_FILE_SIZE = 5242880; // 5MB
    const MAX_IMPORT_IMAGE_FILE_SIZE_MB = 5;
    const MAX_IMPORT_VIDEO_FILE_SIZE = 15728640; // 15MB
    const MAX_IMPORT_VIDEO_FILE_SIZE_MB = 15;
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
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($entity, $id)
    {
        $response = $this->client->get("account/verify_credentials", array("include_email", "true"));

        if (200 === $this->client->getLastHttpCode()) {
            return json_decode(json_encode($response),true);
        } else {
            $lastBody= json_decode(json_encode($this->client->getLastBody()),true);
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }

    /**
     * Service that query to Twitter Api to get user home timeline
     * @param string $entity "user"
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getTimeline($entity, $id)
    {
        $response = $this->client->get("statuses/home_timeline");

        if (200 === $this->client->getLastHttpCode()) {
            return json_decode(json_encode($response),true);
        } else {
            $lastBody= json_decode(json_encode($this->client->getLastBody()),true);
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
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

    /**
     * Service that upload a media file (image/video) to Google+
     * @param string $entity "user"
     * @param string $id    user id
     * @param string $parameters
     *      "media_type"    =>      "url"|"path"
     *      "value"         =>      url or path
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function importMedia($entity, $id, $parameters)
    {
        if ((null === $parameters["media_type"]) || ("" === $parameters["media_type"])) {
            throw new ConnectorConfigException("Media type must be 'url' or 'path'");
        } elseif ((null === $parameters["value"]) || ("" === $parameters["value"])) {
            throw new ConnectorConfigException($parameters["media_type"]." value is required");
        } elseif ("path" === $parameters["media_type"]) {
            if (!file_exists($parameters["value"])) {
                throw new ConnectorConfigException("file doesn't exist");
            } else {
                $mimeType = SocialNetworks::mime_content_type($parameters["value"]);

                if ((false === strpos($mimeType,"image/")) && (false === strpos($mimeType,"video/"))) {
                    throw new ConnectorConfigException("file must be an image or a video");
                } else {
                    $filesize = filesize($parameters["value"]);
                    if ((false !== strpos($mimeType,"image/")) && ($filesize > self::MAX_IMPORT_IMAGE_FILE_SIZE)) {
                        throw new ConnectorConfigException("Maximum image file size is ".(self::MAX_IMPORT_IMAGE_FILE_SIZE_MB)."MB");
                    } else if ((false !== strpos($mimeType,"video/")) && ($filesize > self::MAX_IMPORT_VIDEO_FILE_SIZE)) {
                        throw new ConnectorConfigException("Maximum video file size is ".(self::MAX_IMPORT_VIDEO_FILE_SIZE_MB)."MB");
                    }
                }
            }
        } else {
            $tempMedia = tempnam("bloombees","media");
            file_put_contents($tempMedia, file_get_contents($parameters["value"]));

            $mimeType = SocialNetworks::mime_content_type($parameters["value"]);

            if ((false === strpos($mimeType,"image/")) && (false === strpos($mimeType,"video/"))) {
                throw new ConnectorConfigException("file must be an image or a video");
            } else {
                $filesize = filesize($tempMedia);
                if ((false !== strpos($mimeType,"image/")) && ($filesize > self::MAX_IMPORT_IMAGE_FILE_SIZE)) {
                    throw new ConnectorConfigException("Maximum image file size is ".(self::MAX_IMPORT_IMAGE_FILE_SIZE_MB)."MB");
                } else if ((false !== strpos($mimeType,"video/")) && ($filesize > self::MAX_IMPORT_VIDEO_FILE_SIZE)) {
                    throw new ConnectorConfigException("Maximum video file size is ".(self::MAX_IMPORT_VIDEO_FILE_SIZE_MB)."MB");
                }
            }
            $parameters["value"] = $tempMedia;
        }

        $response = $this->client->upload('media/upload', ['media' => $parameters["value"]]);

        if (200 === $this->client->getLastHttpCode()) {
            return json_decode(json_encode($response),true);
        } else {
            $lastBody= json_decode(json_encode($this->client->getLastBody()),true);
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }

    /**
     * Service that create a tweet in Twitter
     * @param string $entity "user"
     * @param string $id    user id
     * @param array $parameters
     *      "status"                    =>  Text of the tweet (required)
     *      "in_reply_to_status_id"     =>  Id of tweet this new tweet is answering
     *      "media_ids"                 =>  String of media ids separated by comma
     * @return array
     * @throws ConnectorServiceException
     */
    public function post($entity, $id, array $parameters)
    {
        $response = $this->client->post('statuses/update', $parameters);

        if (200 === $this->client->getLastHttpCode()) {
            return json_decode(json_encode($response),true);
        } else {
            $lastBody= json_decode(json_encode($this->client->getLastBody()),true);
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
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