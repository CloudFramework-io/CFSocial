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

    const ID = "twitter";
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

        if ((null === $clientScope) || (!is_array($clientScope))) {
            throw new ConnectorConfigException("'clientScope' parameter is required");
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new TwitterOAuth($this->clientId, $this->clientSecret);
        $this->client->setGzipEncoding(false);
        $this->client->setDecodeJsonAsArray(true);
    }

    /**
     * Service that request authorization to Twitter making up the Twitter login URL
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
     * Authentication service from Twitter sign in request
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

        try {
            $parameters = array(
                "oauth_verifier" => $verifier,
                "oauth_token" => $code,
                "include_email" => "true"
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
            return $this->getProfile(null);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set'");
        }
    }

    /**
     * Service that query to Twitter Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id = null)
    {
        $response = $this->client->get("account/verify_credentials", array("include_email" => true));

        if (200 === $this->client->getLastHttpCode()) {

            $profile = array(
                "user_id" => $response["id"],
                "name" => $response["screen_name"],
                "first_name" => $response["name"],
                "last_name" => null,
                "email" => $response["email"],
                "photo" => $response["profile_image_url"],
                "locale" => $response["lang"],
                "url" => "https://twitter.com/" . $response["screen_name"],
                "raw" => $response
            );

            return $profile;
        } else {
            $lastBody= $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }

    /**
     * Service that query to Twitter Api to get user home timeline
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getUserTimeline($id)
    {
        $response = $this->client->get("statuses/home_timeline");

        if (200 === $this->client->getLastHttpCode()) {
            return $response;
        } else {
            $lastBody= $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }

    /**
     * Service that query to Twitter Api to get single tweet information
     * @param string $id    tweet id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getUserTweet($id)
    {
        $response = $this->client->get("statuses/show/".$id);

        if (200 === $this->client->getLastHttpCode()) {
            return $response;
        } else {
            $lastBody = $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }

    /**
     * Service that query to Twitter Api to delete a tweet
     * @param string $id    tweet id
     * @return array
     * @throws ConnectorServiceException
     */
    public function deleteUserTweet($id)
    {
        $response = $this->client->post("statuses/destroy/".$id);

        if (200 === $this->client->getLastHttpCode()) {
            return $response;
        } else {
            $lastBody= $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }

    /**
     * Service that query to Twitter Api for users the user is followed by
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportUserFollowers($id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $followers = array();
        $count = 0;

        do {
            $parameters = array();
            $parameters["user_id"] = $id;
            $parameters["count"] = $maxResultsPerPage;
            $parameters["cursor"] = $pageToken;

            $followersList = $this->client->get("followers/list", $parameters);

            if (200 !== $this->client->getLastHttpCode()) {
                $pageToken = null;
                $lastBody = $this->client->getLastBody();
                throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
            }

            $followers[$count] = array();
            foreach($followersList["users"] as $follower) {
                $followers[$count][] = $follower;
            }
            $count++;

            $pageToken = $followersList["next_cursor"];

            // 0 Indicates last page
            if ($pageToken == 0) {
                $pageToken = null;
            }

            // If number of pages is zero, then all elements are returned
            if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                break;
            }
        } while ($pageToken);

        return array(
            'followers' => $followers,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that query to Twitter Api for users the user is following (friends)
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportUserSubscribers($id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $friends = array();
        $count = 0;

        do {
            $parameters = array();
            $parameters["user_id"] = $id;
            $parameters["count"] = $maxResultsPerPage;
            $parameters["cursor"] = $pageToken;

            $friendsList = $this->client->get("friends/list", $parameters);

            if (200 !== $this->client->getLastHttpCode()) {
                $pageToken = null;
                $lastBody= $this->client->getLastBody();
                throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
            }

            $friends[$count] = array();
            foreach($friendsList["users"] as $friend) {
                $friends[$count][] = $friend;
            }
            $count++;

            $pageToken = $friendsList["next_cursor"];

            // 0 Indicates last page
            if ($pageToken == 0) {
                $pageToken = null;
            }

            // If number of pages is zero, then all elements are returned
            if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                break;
            }
        } while ($pageToken);

        return array(
            'friends' => $friends,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that upload a media file (image/video) to Twitter
     * @param string $id    user id
     * @param string $parameters
     *      "media_type"    =>      "url"|"path"
     *      "value"         =>      url or path
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function uploadUserMedia($id, $parameters)
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
            return $response;
        } else {
            $lastBody = $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }

    /**
     * Service that create a tweet in Twitter
     * @param string $id    user id
     * @param array $parameters
     *      "content"                   =>  Text of the tweet (required)
     *      "attachment"                =>  String of media ids separated by comma
     *      "in_reply_to_status_id"     =>  Id of tweet this new tweet is answering
     * @return array
     * @throws ConnectorServiceException
     */
    public function post($id, array $parameters)
    {
        $params = array();
        $params["status"] = $parameters["content"];
        if (isset($parameters["attachment"])) {
            $params["media_ids"] = $parameters["attachment"];
        }
        if (isset($parameters["in_reply_to_status_id"])) {
            $params["in_reply_to_status_id"] = $parameters["in_reply_to_status_id"];
        }

        $response = $this->client->post('statuses/update', $params);

        if (200 === $this->client->getLastHttpCode()) {
            $post =  $response;
            return array("post_id" => $post['id'], "url" => "https://twitter.com/" . $post['user']['id'] ."/status/" . $post['id']);
        } else {
            $lastBody = $this->client->getLastBody();
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

    /**
     * Method that check userId is ok
     * @param $userId
     * @throws ConnectorConfigException
     */
    private function checkUser($userId) {
        if ((null === $userId) || ("" === $userId)) {
            throw new ConnectorConfigException("'userId' parameter is required");
        }
    }

    /**
     * Method that check pagination parameters are ok
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @throws ConnectorConfigException
     */
    private function checkPagination($maxResultsPerPage, $numberOfPages) {
        if (null === $maxResultsPerPage) {
            throw new ConnectorConfigException("'maxResultsPerPage' parameter is required");
        } else if (!is_numeric($maxResultsPerPage)) {
            throw new ConnectorConfigException("'maxResultsPerPage' parameter is not numeric");
        }

        if (null === $numberOfPages) {
            throw new ConnectorConfigException("'numberOfPages' parameter is required");
        } else if (!is_numeric($numberOfPages)) {
            throw new ConnectorConfigException("'numberOfPages' parameter is not numeric");
        }
    }

    /******************************* DEPRECATED METHODS ********************************************/

    /**
     * Service that query to Twitter Api to get user home timeline
     * @param string $entity "user"
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     * @deprecated
     * @see TwitterApi::getUserTimeline
     */
    public function getTimeline($entity, $id)
    {
        $response = $this->client->get("statuses/home_timeline");
        if (200 === $this->client->getLastHttpCode()) {
            return $response;
        } else {
            $lastBody = $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }
    /**
     * Service that query to Twitter Api to get single tweet information
     * @param string $entity "tweet"
     * @param string $id    tweet id
     * @return array
     * @throws ConnectorServiceException
     * @deprecated
     * @see TwitterApi::getUserTweet
     */
    public function getTweet($entity, $id)
    {
        $response = $this->client->get("statuses/show/".$id);
        if (200 === $this->client->getLastHttpCode()) {
            return $response;
        } else {
            $lastBody = $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }
    /**
     * Service that query to Twitter Api to delete a tweet
     * @param string $entity "tweet"
     * @param string $id    tweet id
     * @return array
     * @throws ConnectorServiceException
     * @deprecated
     * @see TwitterApi::deleteUserTweet
     */
    public function deleteTweet($entity, $id)
    {
        $response = $this->client->post("statuses/destroy/".$id);
        if (200 === $this->client->getLastHttpCode()) {
            return $response;
        } else {
            $lastBody = $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }
    /**
     * Service that query to Twitter Api for users the user is followed by
     * @param string $entity "user"
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see TwitterApi::exportUserFollowers
     */
    public function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $followers = array();
        $count = 0;
        do {
            $parameters = array();
            $parameters["user_id"] = $id;
            $parameters["count"] = $maxResultsPerPage;
            $parameters["cursor"] = $pageToken;
            $followersList = $this->client->get("followers/list", $parameters);
            if (200 !== $this->client->getLastHttpCode()) {
                $pageToken = null;
                $lastBody = $this->client->getLastBody();
                throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
            }
            $followers[$count] = array();
            foreach($followersList["users"] as $follower) {
                $followers[$count][] = $follower;
            }
            $count++;
            $pageToken = $followersList["next_cursor"];
            // 0 Indicates last page
            if ($pageToken == 0) {
                $pageToken = null;
            }
            // If number of pages is zero, then all elements are returned
            if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                break;
            }
        } while ($pageToken);
        $followers["pageToken"] = $pageToken;
        return $followers;
    }
    /**
     * Service that query to Twitter Api for users the user is following (friends)
     * @param string $entity "user"
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see TwitterApi::exportUserSubscribers
     */
    public function exportSubscribers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $friends = array();
        $count = 0;
        do {
            $parameters = array();
            $parameters["user_id"] = $id;
            $parameters["count"] = $maxResultsPerPage;
            $parameters["cursor"] = $pageToken;
            $friendsList = $this->client->get("friends/list", $parameters);
            if (200 !== $this->client->getLastHttpCode()) {
                $pageToken = null;
                $lastBody = $this->client->getLastBody();
                throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
            }
            $friends[$count] = array();
            foreach($friendsList["users"] as $friend) {
                $friends[$count][] = $friend;
            }
            $count++;
            $pageToken = $friendsList["next_cursor"];
            // 0 Indicates last page
            if ($pageToken == 0) {
                $pageToken = null;
            }
            // If number of pages is zero, then all elements are returned
            if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                break;
            }
        } while ($pageToken);
        $friends["pageToken"] = $pageToken;
        return $friends;
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
     * @deprecated
     * @see TwitterApi::uploadUserMedia
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
            return $response;
        } else {
            $lastBody = $this->client->getLastBody();
            throw new ConnectorServiceException($lastBody["errors"][0]["message"], $lastBody["errors"][0]["code"]);
        }
    }
}
