<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\AuthenticationException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;
use CloudFramework\Service\SocialNetworks\Wrappers\FlickrWrapper;

class FlickrApi extends Singleton implements SocialNetworkInterface {

    const ID = "flickr";

    const PEOPLE_GETINFO_METHOD = "flickr.people.getInfo";
    const PHOTO_COMMENT_ADD_METHOD = "flickr.photos.comments.addComment";
    const PEOPLE_GETPHOTOS_METHOD = "flickr.people.getPhotos";

    // Flickr client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    /**
     * Set Flickr Api keys
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

        if ((null === $redirectUrl) || ("" === $redirectUrl)) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required");
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new FlickrWrapper($this->clientId, $this->clientSecret, $redirectUrl);
    }

    /**
     * Service that request authorization to Flickr. PHP Library redirects automatically
     * @param null $redirectUrl
     * @return array
     * @throws ConnectorConfigException
     */
    public function requestAuthorization($redirectUrl = null)
    {
        if ($this->client->authenticate($this->clientScope[0])) {
            return $this->authorize();
        } else {
            throw new ConnectorConfigException("Error in authentication / authorization", $this->client->getLastHttpResponseCode());
        }
    }

    /**
     * Authentication service from Flickr sign in request
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
        $this->client->authenticate($this->clientScope[0]);

        $flickrCredentials = $_SESSION[FlickrWrapper::SESSION_OAUTH_DATA];

        return $flickrCredentials;
    }

    /**
     * Method that inject the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        // Flickr PHP Library manages its own session variables; otherwise, the proper way should be the next:
        //$this->setOauthData(FlickrWrapper::OAUTH_ACCESS_TOKEN, $credentials["accessToken"]);
        //$this->setOauthData(FlickrWrapper::OAUTH_ACCESS_TOKEN_SECRET, $credentials["accessToken"]);
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
            throw new ConnectorConfigException("Invalid credentials set");
        }
    }

    /**
     * Service that query to Flickr Api to get user profile
     * @param null $id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id = null)
    {
        $response = $this->client->call(self::PEOPLE_GETINFO_METHOD,
                            array("user_id" => $this->client->getOauthData(FlickrWrapper::USER_NSID)));

        $ok = $response["stat"];

        if ("fail" === $ok) {
            throw new ConnectorServiceException($response["message"], $response["code"]);
        }

        $userArray = json_decode(json_encode($response), true);

        $profile = [
            "user_id" => $userArray["person"]["id"],
            "name" => $userArray["person"]["realname"]["_content"],
            "first_name" => null,
            "last_name" => null,
            "email" => null,
            "photo" => null,
            "locale" => null,
            "url" => $userArray["person"]["profileurl"]["_content"],
            "raw" => $userArray
        ];

        return $profile;
    }

    /**
     * Service that upload a photo to Flickr
     * @param string $id    user id
     * @param $parameters
     *      "media_type"    =>      "url" or "path"
     *      "value"         =>      url or path of the image file
     *      "title"         =>      The title of the photo (optional)
     *      "description"   =>      A description of the photo. May contain some limited HTML. (optional)
     *      "tags           =>      A space-seperated list of tags to apply to the photo. (optional)
     *      "is_public"     =>      Set to 0 for no, 1 for yes. Specifies who can view the photo. (optional)
     *      "is_friend"     =>      Set to 0 for no, 1 for yes. Specifies who can view the photo. (optional)
     *      "is_family"     =>      Set to 0 for no, 1 for yes. Specifies who can view the photo. (optional)
     *      "safety_level"  =>      Set to 1 for Safe, 2 for Moderate, or 3 for Restricted. (optional)
     *      "content_type"  =>      Set to 1 for Photo, 2 for Screenshot, or 3 for Other. (optional)
     *      "hidden"        =>      Set to 1 to keep the photo in global search results, 2 to hide from public searches. (optional)
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function uploadUserPhoto($id, $parameters)
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

                if (false === strpos($mimeType, "image/")) {
                    throw new ConnectorConfigException("file must be an image");
                } else {
                    $filesize = filesize($parameters["value"]);
                    if ($filesize > SocialNetworks::MAX_IMPORT_FILE_SIZE) {
                        throw new ConnectorConfigException("Maximum file size is " . (SocialNetworks::MAX_IMPORT_FILE_SIZE_MB) . "MB");
                    }
                }
            }
        } else {
            $tempMedia = tempnam("bloombees","media");
            file_put_contents($tempMedia, file_get_contents($parameters["value"]));

            $mimeType = SocialNetworks::mime_content_type($parameters["value"]);

            if (false === strpos($mimeType, "image/")) {
                throw new ConnectorConfigException("file must be an image");
            } else {
                $filesize = filesize($tempMedia);
                if ($filesize > SocialNetworks::MAX_IMPORT_FILE_SIZE) {
                    throw new ConnectorConfigException("Maximum file size is " . (SocialNetworks::MAX_IMPORT_FILE_SIZE_MB) . "MB");
                }
            }

            $parameters["value"] = $tempMedia;
        }

        $parameters["photo"] = $parameters["value"];
        unset($parameters["value"]);
        unset($parameters["media_type"]);

        $response = $this->client->upload($parameters);

        $ok = $response["stat"];

        if ("fail" === $ok) {
            throw new ConnectorServiceException($response["message"], $response["code"]);
        }

        return array("photoid" => $response["photoid"]["_content"]);
    }

    public function post($id, array $parameters)
    {
        $response = $this->client->call(self::PHOTO_COMMENT_ADD_METHOD,
            array(
                "photo_id" => $parameters["attachment"],
                "comment_text" => $parameters["content"]
            ));

        $ok = $response["stat"];

        if ("fail" === $ok) {
            throw new ConnectorServiceException($response["message"], $response["code"]);
        }

        $post = array("post_id" => $response["comment"]["id"], "url" => $response["comment"]["permalink"]);

        return $post;
    }

    /**
     * Service that query to Flickr API for user photos
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $pageNumber Specific page number
     * @param null $pageToken
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportUserPhotos($id, $maxResultsPerPage, $pageNumber, $pageToken = null)
    {
        $this->checkPagination($maxResultsPerPage, $pageNumber);

        $response = $this->client->call(self::PEOPLE_GETPHOTOS_METHOD,
            array(
                "user_id" => $this->client->getOauthData(FlickrWrapper::USER_NSID),
                "per_page" => $maxResultsPerPage,
                "page" => $pageNumber
            ));

        $ok = $response["stat"];

        if ("fail" === $ok) {
            throw new ConnectorServiceException($response["message"], $response["code"]);
        }

        $photos = json_decode(json_encode($response), true);

        return array(
            "photos" => $photos["photos"]["photo"],
            "total" => $photos["photos"]["total"]
        );
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

        if ((!isset($credentials["oauth_access_token"])) || (null === $credentials["oauth_access_token"]) || ("" === $credentials["oauth_access_token"])) {
            throw new ConnectorConfigException("'oauth_access_token' parameter is required");
        }

        if ((!isset($credentials["oauth_access_token_secret"])) || (null === $credentials["oauth_access_token_secret"]) || ("" === $credentials["oauth_access_token_secret"])) {
            throw new ConnectorConfigException("'oauth_access_token_secret' parameter is required");
        }
    }

    /**
     * Method that check pagination parameters are ok
     * @param $maxResultsPerPage
     * @param $pageNumber
     * @throws ConnectorConfigException
     */
    private function checkPagination($maxResultsPerPage, $pageNumber) {
        if (null === $maxResultsPerPage) {
            throw new ConnectorConfigException("'maxResultsPerPage' parameter is required");
        } else if (!is_numeric($maxResultsPerPage)) {
            throw new ConnectorConfigException("'maxResultsPerPage' parameter is not numeric");
        }

        if (null === $pageNumber) {
            throw new ConnectorConfigException("'pageNumber' parameter is required");
        } else if (!is_numeric($pageNumber)) {
            throw new ConnectorConfigException("'pageNumber' parameter is not numeric");
        }
    }
}