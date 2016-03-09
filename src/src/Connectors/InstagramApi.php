<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\AuthenticationException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;

/**
 * Class InstagramApi
 * @package CloudFramework\Service\SocialNetworks\Connectors
 * @author Salvador Castro <sc@bloombees.com>
 */
class InstagramApi extends Singleton implements SocialNetworkInterface {

    const ID = 'instagram';
    const INSTAGRAM_OAUTH_URL = "https://api.instagram.com/oauth/authorize/";
    const INSTAGRAM_OAUTH_ACCESS_TOKEN_URL = "https://api.instagram.com/oauth/access_token";
    const INSTAGRAM_API_USERS_URL = "https://api.instagram.com/v1/users/";
    const INSTAGRAM_API_MEDIA_URL = "https://api.instagram.com/v1/media/";
    const INSTAGRAM_SELF_USER = "self";

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    // Auth keys
    private $accessToken;

    /**
     * Set Instagram Api keys
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

        if ((null === $clientScope) || (!is_array($clientScope)) || (count($clientScope) == 0)) {
            throw new ConnectorConfigException("'clientScope' parameter is required");
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;
    }

    /**
     * Compose Instagram Api credentials array from session data
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

        $scopes = implode("+", $this->clientScope);
        $authUrl = self::INSTAGRAM_OAUTH_URL.
            "?client_id=".$this->clientId.
            "&redirect_uri=".$redirectUrl.
            "&response_type=code".
            "&scope=".$scopes;
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
     * @param string $code
     * @param string $redirectUrl
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @throws MalformedUrlException
     */
    public function authorize($code, $redirectUrl)
    {
        if ((null === $code) || ("" === $code)) {
            throw new ConnectorConfigException("'code' parameter is required");
        }

        if ((null === $redirectUrl) || (empty($redirectUrl))) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required");
        } else {
            if (!SocialNetworks::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed");
            }
        }

        $fields = "client_id=".$this->clientId.
            "&client_secret=".$this->clientSecret.
            "&grant_type=authorization_code".
            "&code=".$code.
            "&redirect_uri=".$redirectUrl;

        $instagramCredentials = $this->curlPost(self::INSTAGRAM_OAUTH_ACCESS_TOKEN_URL, $fields);

        /**
         * Returned data format instance
         *  {
        "access_token": "fb2e77d.47a0479900504cb3ab4a1f626d174d2d",
        "user": {
        "id": "1574083",
        "username": "snoopdogg",
        "full_name": "Snoop Dogg",
        "profile_picture": "...",
        "bio": "...",
        "website": "..."
        }
        }
         **/

        if (!isset($instagramCredentials["access_token"])) {
            throw new AuthenticationException("Error fetching OAuth2 access token, client is invalid");
        } else if ((!isset($instagramCredentials["user"])) || (!isset($instagramCredentials["user"]["id"])) ||
            (!isset($instagramCredentials["user"]["full_name"])) ||
            (!isset($instagramCredentials["user"]["profile_picture"]))) {
            throw new ConnectorServiceException("Error fetching user profile info");
        }

        // Instagram doesn't return the user's e-mail
        return $instagramCredentials;
    }

    /**
     * Method that inject the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->accessToken = $credentials["access_token"];
    }

    /**
     * Service that check if credentials are valid
     * @param $credentials
     * @return null
     * @throws ConnectorConfigException
     */
    public function checkCredentials($credentials) {
        $this->checkCredentialsParameters($credentials);

        try {
            $this->getProfile(SocialNetworks::ENTITY_USER, self::INSTAGRAM_SELF_USER);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set'");
        }
    }

    /**
     * Service that query to Instagram Api for users the user is followed by
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxResultsPerPage.
     * @param integer $numberOfPages
     * @param string $nextPageUrl
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $nextPageUrl) {
        $this->checkUser($id);

        $this->checkPagination($numberOfPages);

        if (!$nextPageUrl) {
            $nextPageUrl = self::INSTAGRAM_API_USERS_URL . $id .
                "/followed-by?access_token=" . $this->accessToken;
        }

        $pagination = true;
        $followers = array();
        $count = 0;

        while ($pagination) {
            $data = $this->curlGet($nextPageUrl);

            if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
                throw new ConnectorServiceException("Error getting followers:".
                    $data["meta"]["error_message"], $data["meta"]["code"]);
            }

            $followers[$count] = array();

            foreach ($data["data"] as $key => $follower) {
                $followers[$count][] = $follower;
            }

            // If number of pages is zero, then all elements are returned
            if ((($numberOfPages > 0) && ($count == $numberOfPages)) || (!isset($data->pagination->next_url))) {
                $pagination = false;
                if (!isset($data->pagination->next_url)) {
                    $nextPageUrl = null;
                }
            } else {
                $nextPageUrl = $data->pagination->next_url;
                $count++;
            }
        }

        $followers["nextPageUrl"] = $nextPageUrl;

        return json_encode($followers);
    }

    /**
     * Service that query to Instagram Api for users the user is following
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxResultsPerPage.
     * @param integer $numberOfPages
     * @param string $nextPageUrl
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportSubscribers($entity, $id, $maxResultsPerPage, $numberOfPages, $nextPageUrl) {
        $this->checkUser($id);
        $this->checkPagination($numberOfPages);

        if (!$nextPageUrl) {
            $nextPageUrl = self::INSTAGRAM_API_USERS_URL . $id .
                "/follows?access_token=" . $this->accessToken;
        }

        $pagination = true;
        $subscribers = array();
        $count = 0;

        while ($pagination) {
            $data = $this->curlGet($nextPageUrl);

            if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
                throw new ConnectorServiceException("Error getting subscribers: " .
                    $data["meta"]["error_message"], $data["meta"]["code"]);
            }

            $subscribers[$count] = array();

            foreach ($data["data"] as $key => $subscriber) {
                $subscribers[$count][] = $subscriber;
            }

            // If number of pages is zero, then all elements are returned
            if ((($numberOfPages > 0) && ($count == $numberOfPages)) || (!isset($data->pagination->next_url))) {
                $pagination = false;
                if (!isset($data->pagination->next_url)) {
                    $nextPageUrl = null;
                }
            } else {
                $nextPageUrl = $data->pagination->next_url;
                $count++;
            }
        }

        $subscribers["nextPageUrl"] = $nextPageUrl;

        return json_encode($subscribers);
    }

    public function exportPosts($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        return;
    }

    /**
     * Service that query to Instagram Api to get user profile
     * @param string $entity "user"
     * @param string $id    user id
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function getProfile($entity, $id)
    {
        $this->checkUser($id);

        $url = self::INSTAGRAM_API_USERS_URL . $id . "/?access_token=" . $this->accessToken;

        $data = $this->curlGet($url);

        if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
            throw new ConnectorServiceException("Error getting user profile: " .
                $data["meta"]["error_message"], $data["meta"]["code"]);
        }

        // Instagram API doesn't return the user's e-mail
        return json_encode($data["data"]);
    }

    /**
     * Service that query to Instagram Api service for media files
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxTotalResults.
     * @param integer $numberOfPages
     * @param string $nextPageUrl
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportMedia($entity, $id, $maxTotalResults, $numberOfPages, $nextPageUrl)
    {
        $this->checkUser($id);
        $this->checkPagination($numberOfPages, $maxTotalResults);

        if (!$nextPageUrl) {
            $nextPageUrl = self::INSTAGRAM_API_USERS_URL . $id .
                        "/media/recent/?access_token=" . $this->accessToken .
                        "&count=".$maxTotalResults;
        }

        $pagination = true;
        $files = array();
        $count = 0;

        while ($pagination) {
            $data = $this->curlGet($nextPageUrl);

            if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
                throw new ConnectorServiceException("Error exporting media: " .
                    $data["meta"]["error_message"], $data["meta"]["code"]);
            }

            $files[$count] = array();

            foreach ($data["data"] as $key => $media) {
                if ("image" === $media["type"]) {
                    $files[$count][] = $media;
                }
            }

            // If number of pages is zero, then all elements are returned
            if ((($numberOfPages > 0) && ($count == $numberOfPages)) || (!isset($data->pagination->next_url))) {
                $pagination = false;
                if (!isset($data->pagination->next_url)) {
                    $nextPageUrl = null;
                }
            } else {
                $nextPageUrl = $data->pagination->next_url;
                $count++;
            }
        }

        $files["nextPageUrl"] = $nextPageUrl;

        return json_encode($files);
    }

    /**
     * Service that get the list of recent media liked by the owner
     * @param string $entity "user"
     * @param string $id    user id
     * @param $maxTotalResults
     * @param $numberOfPages
     * @param $nextPageUrl
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportMediaRecentlyLiked($entity, $id, $maxTotalResults, $numberOfPages, $nextPageUrl)
    {
        $this->checkUser($id);
        $this->checkPagination($numberOfPages, $maxTotalResults);

        $id = self::INSTAGRAM_SELF_USER;

        if (!$nextPageUrl) {
            $nextPageUrl = self::INSTAGRAM_API_USERS_URL . $id .
                "/media/liked/?access_token=" . $this->accessToken .
                "&count=".$maxTotalResults;
        }

        $pagination = true;
        $files = array();
        $count = 0;

        while ($pagination) {
            $data = $this->curlGet($nextPageUrl);

            if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
                throw new ConnectorServiceException("Error exporting media: " .
                    $data["meta"]["error_message"], $data["meta"]["code"]);
            }

            $files[$count] = array();

            foreach ($data["data"] as $key => $media) {
                if ("image" === $media["type"]) {
                    $files[$count][] = $media;
                }
            }

            // If number of pages is zero, then all elements are returned
            if ((($numberOfPages > 0) && ($count == $numberOfPages)) || (!isset($data->pagination->next_url))) {
                $pagination = false;
                if (!isset($data->pagination->next_url)) {
                    $nextPageUrl = null;
                }
            } else {
                $nextPageUrl = $data->pagination->next_url;
                $count++;
            }
        }

        $files["nextPageUrl"] = $nextPageUrl;

        return json_encode($files);
    }

    public function importMedia($entity, $id, $parameters) {
        return;
    }

    /**
     * Service that publish a comment in an Instagram media
     * @param array $parameters
     *      "content" => Text of the comment
     *      "media_id" => Instagram media's ID
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function post($entity, $id, array $parameters) {
        if ((null === $parameters) || (!is_array($parameters)) || (count($parameters) == 0)) {
            throw new ConnectorConfigException("Invalid post parameters'");
        }

        if ((!array_key_exists('content', $parameters)) ||
            (null === $parameters["content"]) || (empty($parameters["content"]))) {
            throw new ConnectorConfigException("'content' parameter is required");
        }

        if ((!array_key_exists('media_id', $parameters)) ||
            (null === $parameters["media_id"]) || (empty($parameters["media_id"]))) {
            throw new ConnectorConfigException("'media_id' parameter is required");
        }

        $url = self::INSTAGRAM_API_MEDIA_URL.$parameters["media_id"]."/comments";

        $fields = "access_token=".$this->accessToken.
                    "&text=".$parameters["content"];

        $data = $this->curlPost($url, $fields);

        if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
            throw new ConnectorServiceException("Error making comments on an Instagram media: " . 
                $data["meta"]["error_message"], $data["meta"]["code"]);
        }

        return json_encode($data);
    }

    /**
     * Service that query to Instagram Api to get user relationship information
     * @param string $entity "user"
     * @param string $id    user id
     * @param string $userId
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function getUserRelationship($entity, $id, $userId)
    {
        $this->checkUser($userId);

        $url = self::INSTAGRAM_API_USERS_URL . $userId . "/relationship?access_token=" . $this->accessToken;

        $data = $this->curlGet($url);

        if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
            throw new ConnectorServiceException("Error getting relationship info: " .
                $data["meta"]["error_message"], $data["meta"]["code"]);
        }

        return json_encode($data["data"]);
    }

    /**
     * Service that modify the relationship between the authenticated user and the target user.
     * @param string $entity "user"
     * @param string $id    user id
     * @param $userId
     * @param $action
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function modifyUserRelationship($entity, $id, $userId, $action) {
        $this->checkUser($id);

        $fields = "action=".$action;
        $url = self::INSTAGRAM_API_USERS_URL . $userId . "/relationship?access_token=" . $this->accessToken;

        $data = $this->curlPost($url, $fields);

        if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
            throw new ConnectorServiceException("Error modifying relationship: " .
                $data["meta"]["error_message"], $data["meta"]["code"]);
        }

        return json_encode($data["data"]);
    }

    /**
     * Service that search for users
     * @param string $entity "user"
     * @param string $id    user id
     * @param $name
     * @param $maxTotalResults
     * @param $numberOfPages
     * @param $nextPageUrl
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function searchUsers($entity, $id, $name, $maxTotalResults, $numberOfPages, $nextPageUrl)
    {
        $this->checkUser($id);
        $this->checkName($name);
        $this->checkPagination($numberOfPages, $maxTotalResults);

        if (!$nextPageUrl) {
            $nextPageUrl = self::INSTAGRAM_API_USERS_URL .
                "search?q=".$name."&access_token=" . $this->accessToken .
                "&count=".$maxTotalResults;
        }

        $pagination = true;
        $users = array();
        $count = 0;

        while ($pagination) {
            $data = $this->curlGet($nextPageUrl);

            if ((null === $data["data"]) && ($data["meta"]["code"] !== 200)) {
                throw new ConnectorServiceException("Error searching users: " .
                    $data["meta"]["error_message"], $data["meta"]["code"]);
            }

            $users[$count] = array();

            foreach ($data["data"] as $key => $user) {
                $users[$count][] = $user;
            }

            // If number of pages is zero, then all elements are returned
            if ((($numberOfPages > 0) && ($count == $numberOfPages)) || (!isset($data->pagination->next_url))) {
                $pagination = false;
                if (!isset($data->pagination->next_url)) {
                    $nextPageUrl = null;
                }
            } else {
                $nextPageUrl = $data->pagination->next_url;
                $count++;
            }
        }

        $users["nextPageUrl"] = $nextPageUrl;

        return json_encode($users);
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
     * @param integer $maxTotalResults
     * @param integer $numberOfPages
     * @throws ConnectorConfigException
     */
    private function checkPagination($numberOfPages, $maxTotalResults = 0) {
        if (null === $maxTotalResults) {
            throw new ConnectorConfigException("'maxTotalResults' parameter is required");
        } else if (!is_numeric($maxTotalResults)) {
            throw new ConnectorConfigException("'maxTotalResults' parameter is not numeric");
        }

        if (null === $numberOfPages) {
            throw new ConnectorConfigException("'numberOfPages' parameter is required");
        } else if (!is_numeric($numberOfPages)) {
            throw new ConnectorConfigException("'numberOfPages' parameter is not numeric");
        }
    }

    /**
     * Method that check search name is ok
     * @param $name
     * @throws ConnectorConfigException
     */
    private function checkName($name) {
        if ((null === $name) || ("" === $name)) {
            throw new ConnectorConfigException("'name' parameter is required");
        }
    }

    /**
     * Method that calls url with GET method
     * @param $url
     * @return array
     * @throws \Exception
     */
    private function curlGet($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);

        if (!$data) {
            throw \Exception("Error calling service: ".curl_error($ch), curl_errno($ch));
        }
        return json_decode($data, true);
    }

    /**
     * Method that calls url with POST method
     * @param $url
     * @param $fields
     * @return array
     * @throws \Exception
     */
    private function curlPost($url, $fields) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($ch);
        curl_close($ch);

        if (!$data) {
            throw \Exception("Error calling service: ".curl_error($ch), curl_errno($ch));
        }
        return json_decode($data, true);
    }
}