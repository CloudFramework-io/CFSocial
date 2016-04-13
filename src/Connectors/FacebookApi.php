<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;
use Facebook\Facebook;
use Facebook\Helpers\FacebookRedirectLoginHelper;

class FacebookApi extends Singleton implements SocialNetworkInterface
{
    const ID = "facebook";
    const FACEBOOK_SELF_USER = "me";

    // Facebook client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    // Auth keys
    private $accessToken;

    /**
     * Set Facebook Api keys
     * @param $clientId
     * @param $clientSecret
     * @param $clientScope
     * @throws ConnectorConfigException
     */
    public function setApiKeys($clientId, $clientSecret, $clientScope) {
        if ((null === $clientId) || ("" === $clientId)) {
            throw new ConnectorConfigException("'clientId' parameter is required", 601);
        }

        if ((null === $clientSecret) || ("" === $clientSecret)) {
            throw new ConnectorConfigException("'clientSecret' parameter is required", 602);
        }

        if ((null === $clientScope) || (!is_array($clientScope)) || (count($clientScope) == 0)) {
            throw new ConnectorConfigException("'clientScope' parameter is required", 603);
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new Facebook(array(
            "app_id" => $this->clientId,
            "app_secret" => $this->clientSecret,
            'default_graph_version' => 'v2.4',
            'cookie' => true
        ));
    }

    /**
     * Service that request authorization to Facebook making up the Facebook login URL
     * @param string $redirectUrl
     * @return array
     * @throws ConnectorConfigException
     * @throws MalformedUrlException
     */
    public function requestAuthorization($redirectUrl)
    {
        if ((null === $redirectUrl) || (empty($redirectUrl))) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required", 628);
        } else {
            if (!SocialNetworks::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed", 601);
            }
        }

        $redirect = $this->client->getRedirectLoginHelper();

        $authUrl = $redirect->getLoginUrl($redirectUrl, $this->clientScope);

        // Authentication request
        return $authUrl;
    }

    /**
     * Authentication service from Facebook sign in request
     * @param string $code
     * @param string $verifier
     * @param $redirectUrl
     * @return array
     * @throws ConnectorServiceException
     */
    public function authorize($code, $verifier, $redirectUrl = null)
    {
        try {
            if(!array_key_exists('code', $_GET)) {
                $_GET['code'] = $code;
            }
            if(!array_key_exists('state', $_GET)) {
                $_GET['state'] = $verifier;
            }
            /** @var FacebookRedirectLoginHelper $helper */
            $helper = $this->client->getRedirectLoginHelper();
            $accessToken = $helper->getAccessToken($redirectUrl);

            if (empty($accessToken)) {
                throw new ConnectorServiceException("Error taking access token from Facebook Api", 500);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array("access_token" => $accessToken->getValue());
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
     * @param array $credentials
     * @return null
     * @throws ConnectorConfigException
     */
    public function checkCredentials(array $credentials) {
        $this->checkCredentialsParameters($credentials);

        try {
            return $this->getProfile(self::FACEBOOK_SELF_USER);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set");
        }
    }

    /**
     * Service that query to Facebook Api a followers count
     * @param $id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportUserPosts($id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $posts = array();
        $count = 0;
        do {
            try {
                $endpoint = "/".self::FACEBOOK_SELF_USER."/feed?limit=".$maxResultsPerPage;
                if ($pageToken) {
                    $endpoint .= "&until=".$pageToken;
                }

                if ($count == 0) {
                    $response = $this->client->get($endpoint, $this->accessToken);
                    $postsEdge = $response->getGraphEdge();
                } else {
                    $postsEdge = $this->client->next($postsEdge);
                }

                foreach ($postsEdge as $post) {
                    $posts[$count][] = $post->asArray();
                }
                $count++;

                // Extract until parameter to set pagetoken
                $nextPageEndPoint = $postsEdge->getNextPageRequest()->getEndPoint();
                $parameters = array();
                parse_str(parse_url($nextPageEndPoint, PHP_URL_QUERY), $parameters);

                $pageToken = null;
                if (array_key_exists("until", $parameters)) {
                    $pageToken = $parameters["until"];
                }

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting posts: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            'posts' => $posts,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that query to Facebook Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id) {
        $this->checkUser($id);

        try {
            $response = $this->client->get("/".$id."?fields=id,name,first_name,middle_name,last_name,email,cover,locale,website,link,picture", $this->accessToken);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error getting user profile: ' . $e->getMessage(), $e->getCode());
        }

        /** @var  $graphUser */
        $graphUser = $response->getGraphUser();
        $profile = array(
            "user_id" => $graphUser->getId(),
            "name" => $graphUser->getName(),
            "first_name" => $graphUser->getFirstName(),
            "last_name" => $graphUser->getLastName(),
            "email" => $graphUser->getEmail(),
            "photo" => $graphUser->getPicture()->getUrl(),
            "locale" => $graphUser->getField('locale', 'en'),
            "url" => $graphUser->getLink(),
            "raw" => json_decode($graphUser, true)
        );

        return $profile;
    }

    /**
     * Service that upload a photo to Facebook user's album
     * @param string $id    user id
     * @param $parameters
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function uploadUserPhoto($id, $parameters)
    {
        $this->checkUser($id);

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
        }

        $params = array();
        $params["message"] = $parameters["title"];

        if ("url" === $parameters["media_type"]) {
            $params["url"] = $parameters["value"];
        } else {
            $params["source"] = $this->client->fileToUpload($parameters["value"]);
        }

        try {
            if (null === $parameters["album_id"]) {
                $response = $this->client->post("/".self::FACEBOOK_SELF_USER."/photos", $params, $this->accessToken);
            } else {
                $response = $this->client->post("/".$parameters["album_id"]."/photos", $params, $this->accessToken);
            }
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error importing '".$parameters["value"]."'': " . $e->getMessage(), $e->getCode());
        }

        $graphNode = $response->getGraphNode();
        $media = array("media_id" => $graphNode["id"]);

        return $media;
    }

    /**
     * Service that upload a photo to Facebook user's page album
     * @param string $id    page id
     * @param $parameters
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function uploadPagePhoto($id, $parameters)
    {
        $this->checkPage($id);
        $pageinfo = $this->getPage($id);

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
        }

        $params = array();
        $params["message"] = $parameters["title"];

        if ("url" === $parameters["media_type"]) {
            $params["url"] = $parameters["value"];
        } else {
            $params["source"] = $this->client->fileToUpload($parameters["value"]);
        }

        try {
            if (null === $parameters["album_id"]) {
                $response = $this->client->post("/".$id."/photos", $params, $pageinfo["access_token"]);
            } else {
                $response = $this->client->post("/".$parameters["album_id"]."/photos", $params, $pageinfo["access_token"]);
            }
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error importing '".$parameters["value"]."'': " . $e->getMessage(), $e->getCode());
        }

        $graphNode = $response->getGraphNode();
        $media = array("media_id" => $graphNode["id"]);

        return $media;
    }

    /**
     * Service that query to Facebook API for user photos
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportUserPhotos($id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $photos = array();
        $count = 0;
        do {
            try {
                $endpoint = "/" . self::FACEBOOK_SELF_USER . "/photos?type=uploaded&limit=" . $maxResultsPerPage;

                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }

                $response = $this->client->get($endpoint, (!isset($pageinfo)?$this->accessToken:$pageinfo["access_token"]));

                $photosEdge = $response->getGraphEdge();

                foreach ($photosEdge as $photo) {
                    $photos[$count][] = $photo->asArray();
                }
                $count++;

                $pageToken = $photosEdge->getNextCursor();

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting photos: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            'photos' => $photos,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that query to Facebook API for user photos
     * @param string $id    page id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPagePhotos($id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkPage($id);
        $pageinfo = $this->getPage($id);

        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $photos = array();
        $count = 0;
        do {
            try {
                $endpoint = "/" . $id . "/photos?type=uploaded&limit=" . $maxResultsPerPage;

                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }

                $response = $this->client->get($endpoint, (!isset($pageinfo)?$this->accessToken:$pageinfo["access_token"]));

                $photosEdge = $response->getGraphEdge();

                foreach ($photosEdge as $photo) {
                    $photos[$count][] = $photo->asArray();
                }
                $count++;

                $pageToken = $photosEdge->getNextCursor();

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting photos: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            'photos' => $photos,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that create a post in Facebook user's feed
     * @param string $id    user id
     * @param array $parameters
     *      "content"           =>  Text of the post (mandatory)
     *      "attachment" =>  Facebook ID for an existing picture in the person's photo albums to use as the thumbnail image.
     *      "link"              =>  URL
     *      They must be the owner of the photo, and the photo cannot be part of a message attachment.
     * @return array
     * @throws ConnectorServiceException
     */
    public function post($id, array $parameters) {
        if(array_key_exists('post_type', $parameters) && $parameters['post_type'] == 'page') {
            return $this->pagePost($id, $parameters);
        }
        $this->checkUser($id);

        $params = array();
        $params["message"] = $parameters["content"];
        if (isset($parameters["attachment"])) {
            $params["object_attachment"] = $parameters["attachment"];
        }
        if (isset($parameters["link"])) {
            $params["link"] = $parameters["link"];
        }

        try {
            $response = $this->client->post("/" . self::FACEBOOK_SELF_USER . "/feed", $params, $this->accessToken);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error creating a post: ' . $e->getMessage(), $e->getCode());
        }

        $graphNode = $response->getGraphNode();

        $post = array("post_id" => $graphNode["id"], "url" => "https://facebook.com/" . $id . "/" .$graphNode["id"]);

        return $post;
    }

    /**
     * Service that create a post in Facebook user's page feed
     * @param string $id    page id
     * @param array $parameters
     *      "content"           =>  Text of the post (mandatory)
     *      "attachment" =>  Facebook ID for an existing picture in the person's photo albums to use as the thumbnail image.
     *      "link"              =>  URL
     *      They must be the owner of the photo, and the photo cannot be part of a message attachment.
     * @return array
     * @throws ConnectorServiceException
     */
    public function pagePost($id, array $parameters) {
        $this->checkPage($id);
        $pageinfo = $this->getPage($id);

        $params = array();
        $params["message"] = $parameters["content"];
        if (isset($parameters["attachment"])) {
            $params["object_attachment"] = $parameters["attachment"];
        }
        if (isset($parameters["link"])) {
            $params["link"] = $parameters["link"];
        }

        try {
            $response = $this->client->post("/" . $id . "/feed", $params, $pageinfo["access_token"]);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error creating a post: ' . $e->getMessage(), $e->getCode());
        }

        $graphNode = $response->getGraphNode();

        $post = array("post_id" => $graphNode["id"], "url" => "https://facebook.com/" . $id . "/" . $graphNode["id"]);

        return $post;
    }

    /**
     * Service that creates a new photo album for the user in facebook
     * @param string $id    user id
     * @param $title
     * @param $caption
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function createUserPhotosAlbum($id, $title, $caption) {
        $this->checkUser($id);

        $parameters = array();
        $parameters["name"] = $title;
        $parameters["message"] = $caption;

        try {
            $response = $this->client->post("/".self::FACEBOOK_SELF_USER."/albums", $parameters, $this->accessToken);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error creating album '".$title."'': " . $e->getMessage(), $e->getCode());
        }

        $graphNode = $response->getGraphNode();
        $album = array("album_id" => $graphNode["id"]);

        return $album;
    }

    /**
     * Service that creates a new photo album for an user's page in facebook
     * @param string $id    user id
     * @param $title
     * @param $caption
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function createPagePhotosAlbum($id, $title, $caption) {
        $this->checkPage($id);
        $pageinfo = $this->getPage($id);

        $parameters = array();
        $parameters["name"] = $title;
        $parameters["message"] = $caption;

        try {
            $response = $this->client->post("/".$id."/albums", $parameters, $pageinfo["access_token"]);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error creating album '".$title."'': " . $e->getMessage(), $e->getCode());
        }

        $graphNode = $response->getGraphNode();
        $album = array("album_id" => $graphNode["id"]);

        return $album;
    }

    /**
     * Service that get information of all user's photo albums in facebook
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPhotosUserAlbumsList($id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $albums = array();
        $count = 0;
        do {
            try {
                $endpoint = "/" . self::FACEBOOK_SELF_USER . "/albums?limit=" . $maxResultsPerPage;

                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }

                $response = $this->client->get($endpoint, $this->accessToken);

                $albumsEdge = $response->getGraphEdge();

                foreach ($albumsEdge as $album) {
                    $albums[$count][] = $album->asArray();
                }
                $count++;

                $pageToken = $albumsEdge->getNextCursor();

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting photo albums: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);


        return array(
            "albums" => $albums,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that get information of all user's page photo albums in facebook
     * @param string $id    page id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPhotosPageAlbumsList($id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkPage($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $albums = array();
        $count = 0;
        do {
            try {
                $endpoint = "/" . $id . "/albums?limit=" . $maxResultsPerPage;

                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }

                $response = $this->client->get($endpoint, $this->accessToken);

                $albumsEdge = $response->getGraphEdge();

                foreach ($albumsEdge as $album) {
                    $albums[$count][] = $album->asArray();
                }
                $count++;

                $pageToken = $albumsEdge->getNextCursor();

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting photo albums: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            "albums" => $albums,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that gets photos from an album owned by user or in a page in facebook
     * @param string $id    user id
     * @param $albumId
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws \Exception
     */
    public function exportPhotosFromAlbum($id, $albumId, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkAlbum($albumId);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $photos = array();
        $count = 0;
        do {
            try {
                $endpoint = "/" . $albumId . "/photos?limit=" . $maxResultsPerPage;

                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }

                $response = $this->client->get($endpoint, $this->accessToken);

                $photosEdge = $response->getGraphEdge();

                foreach ($photosEdge as $photo) {
                    $photos[$count][] = $photo->asArray();
                }
                $count++;

                $pageToken = $photosEdge->getNextCursor();

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting photos in album '".$albumId."': " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            "photos" => $photos,
            "pageToken" => $pageToken
        );
    }

    /**
     * Service that gets all pages this person administers/is an admin for
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportUserPages($id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $pages = array();
        $count = 0;
        do {
            try {
                $endpoint = "/". self::FACEBOOK_SELF_USER ."/accounts?limit=".$maxResultsPerPage;
                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }

                $response = $this->client->get($endpoint, $this->accessToken);

                $pagesEdge = $response->getGraphEdge();

                foreach ($pagesEdge as $page) {
                    $pages[] = $page->asArray();
                }
                $count++;

                $pageToken = $pagesEdge->getNextCursor();

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting pages: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            "pages" => $pages,
            "totalCount" => count($pages),
            "pageToken" => $pageToken,
        );
    }

    /**
     * Service that query to Facebook Api to get page settings
     * @param $id       page id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getPage($id) {
        $this->checkPage($id);

        try {
            $response = $this->client->get("/".$id."?fields=access_token,category,name,id", $this->accessToken);
            $node = $response->getGraphNode();
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error getting page settings: ' . $e->getMessage(), $e->getCode());
        }

        $page = array(
            "access_token" => $node["access_token"],
            "category" => $node["category"],
            "name" => $node["name"],
            "id" => $node["id"]
        );

        return $page;
    }

    /**
     * Method that check credentials are present and valid
     * @param array $credentials
     * @throws ConnectorConfigException
     */
    private function checkCredentialsParameters(array $credentials) {
        if ((null === $credentials) || (!is_array($credentials)) || (count($credentials) == 0)) {
            throw new ConnectorConfigException("Invalid credentials set'");
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
     * Method that check pageId is ok
     * @param $pageId
     * @throws ConnectorConfigException
     */
    private function checkPage($pageId) {
        if ((null === $pageId) || ("" === $pageId)) {
            throw new ConnectorConfigException("'pageId' parameter is required");
        }
    }

    /**
     * Method that check albumId is ok
     * @param $albumId
     * @throws ConnectorConfigException
     */
    private function checkAlbum($albumId) {
        if ((null === $albumId) || ("" === $albumId)) {
            throw new ConnectorConfigException("'albumId' parameter is required");
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

        if (null === $maxResultsPerPage) {
            throw new ConnectorConfigException("'numberOfPages' parameter is required");
        } else if (!is_numeric($numberOfPages)) {
            throw new ConnectorConfigException("'numberOfPages' parameter is not numeric");
        }
    }

    /******************************* DEPRECATED METHODS ********************************************/

    /**
     * Service that query to Facebook Api for user's followers
     * @param $entity
     * @param $id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @deprecated
     */
    public function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $response = $this->client->get("/".self::FACEBOOK_SELF_USER."/friends", $this->accessToken)->getDecodedBody();
        return $response["summary"]["total_count"];
    }

    /**
     * Service that query to Facebook Api a followers count
     * @param $entity
     * @param $id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see FacebookApi::exportUserPosts
     */
    public function exportPosts($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $posts = array();
        $count = 0;
        do {
            try {
                $endpoint = "/".self::FACEBOOK_SELF_USER."/feed?limit=".$maxResultsPerPage;
                if ($pageToken) {
                    $endpoint .= "&until=".$pageToken;
                }
                if ($count == 0) {
                    $response = $this->client->get($endpoint, $this->accessToken);
                    $postsEdge = $response->getGraphEdge();
                } else {
                    $postsEdge = $this->client->next($postsEdge);
                }
                foreach ($postsEdge as $post) {
                    $posts[$count][] = $post->asArray();
                }
                $count++;
                // Extract until parameter to set pagetoken
                $nextPageEndPoint = $postsEdge->getNextPageRequest()->getEndPoint();
                $parameters = array();
                parse_str(parse_url($nextPageEndPoint, PHP_URL_QUERY), $parameters);
                $pageToken = null;
                if (array_key_exists("until", $parameters)) {
                    $pageToken = $parameters["until"];
                }
                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting posts: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);
        $posts["pageToken"] = $pageToken;
        return $posts;
    }

    /**
     * Service that upload a media file (photo) to Facebook
     * @param string $entity "user"|"page
     * @param string $id    user or page id
     * @param $parameters
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see FacebookApi::uploadUserPhoto
     */
    public function importMedia($entity, $id, $parameters)
    {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
            $pageinfo = $this->getPage($id);
        }
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
        }
        $params = array();
        $params["message"] = $parameters["title"];
        if ("url" === $parameters["media_type"]) {
            $params["url"] = $parameters["value"];
        } else {
            $params["source"] = $this->client->fileToUpload($parameters["value"]);
        }
        try {
            if (null === $parameters["album_id"]) {
                if (SocialNetworks::ENTITY_PAGE == $entity) {
                    $response = $this->client->post("/".$id."/photos", $params, $pageinfo["access_token"]);
                } else {
                    $response = $this->client->post("/".self::FACEBOOK_SELF_USER."/photos", $params, $this->accessToken);
                }
            } else {
                if (SocialNetworks::ENTITY_PAGE == $entity) {
                    $response = $this->client->post("/".$parameters["album_id"]."/photos", $params, $pageinfo["access_token"]);
                } else {
                    $response = $this->client->post("/".$parameters["album_id"]."/photos", $params, $this->accessToken);
                }
            }
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error importing '".$parameters["value"]."'': " . $e->getMessage(), $e->getCode());
        }
        $graphNode = $response->getGraphNode();
        $media = array("media_id" => $graphNode["id"]);
        return $media;
    }
    /**
     * Service that query to Facebook API for user photos
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see FacebookApi::exportUserPhotos
     */
    public function exportMedia($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
            $pageinfo = $this->getPage($id);
        }
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $photos = array();
        $count = 0;
        do {
            try {
                if (SocialNetworks::ENTITY_USER === $entity) {
                    $endpoint = "/" . self::FACEBOOK_SELF_USER . "/photos?type=uploaded&limit=" . $maxResultsPerPage;
                } else {
                    $endpoint = "/" . $id . "/photos?type=uploaded&limit=" . $maxResultsPerPage;
                }
                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }
                $response = $this->client->get($endpoint, (!isset($pageinfo)?$this->accessToken:$pageinfo["access_token"]));

                $photosEdge = $response->getGraphEdge();
                foreach ($photosEdge as $photo) {
                    $photos[$count][] = $photo->asArray();
                }
                $count++;
                $pageToken = $photosEdge->getNextCursor();
                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting photos: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);
        $photos["pageToken"] = $pageToken;
        return $photos;
    }

    /**
     * Service that creates a new photo album for the user in facebook
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param $title
     * @param $caption
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see FacebookApi::createUserPhotosAlbum
     */
    public function createPhotosAlbum($entity, $id, $title, $caption) {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
            $pageinfo = $this->getPage($id);
        }
        $parameters = array();
        $parameters["name"] = $title;
        $parameters["message"] = $caption;
        try {
            if (SocialNetworks::ENTITY_USER === $entity) {
                $response = $this->client->post("/".self::FACEBOOK_SELF_USER."/albums", $parameters, $this->accessToken);
            } else {
                $response = $this->client->post("/".$id."/albums", $parameters, $pageinfo["access_token"]);
            }
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error creating album '".$title."'': " . $e->getMessage(), $e->getCode());
        }
        $graphNode = $response->getGraphNode();
        $album = array("album_id" => $graphNode["id"]);
        return $album;
    }

    /**
     * Service that get information of all user's photo albums in facebook
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see FacebookApi::exportPhotosUserAlbumsList
     */
    public function exportPhotosAlbumsList($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
        }
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $albums = array();
        $count = 0;
        do {
            try {
                if (SocialNetworks::ENTITY_USER === $entity) {
                    $endpoint = "/" . self::FACEBOOK_SELF_USER . "/albums?limit=" . $maxResultsPerPage;
                } else {
                    $endpoint = "/" . $id . "/albums?limit=" . $maxResultsPerPage;
                }
                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }
                $response = $this->client->get($endpoint, $this->accessToken);
                $albumsEdge = $response->getGraphEdge();
                foreach ($albumsEdge as $album) {
                    $albums[$count][] = $album->asArray();
                }
                $count++;
                $pageToken = $albumsEdge->getNextCursor();
                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting photo albums: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);
        $albums["pageToken"] = $pageToken;
        return $albums;
    }

    /**
     * Service that gets all pages this person administers/is an admin for
     * @param string $entity "user"
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @deprecated
     * @see FacebookApi::exportUserPages
     */
    public function exportPages($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $pages = array();
        $count = 0;
        do {
            try {
                $endpoint = "/". self::FACEBOOK_SELF_USER ."/accounts?limit=".$maxResultsPerPage;
                if ($pageToken) {
                    $endpoint .= "&after=".$pageToken;
                }
                $response = $this->client->get($endpoint, $this->accessToken);
                $pagesEdge = $response->getGraphEdge();
                foreach ($pagesEdge as $page) {
                    $pages[] = $page->asArray();
                }
                $count++;
                $pageToken = $pagesEdge->getNextCursor();
                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting pages: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);
        return array(
            "pages" => $pages,
            "totalCount" => count($pages),
            "pageToken" => $pageToken,
        );
    }
}