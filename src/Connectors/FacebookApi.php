<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;
use Facebook\Facebook;

class FacebookApi extends Singleton implements SocialNetworkInterface
{
    const ID = 'facebook';
    const FACEBOOK_SELF_USER = "me";

    // Google client object
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
     * @param null $code
     * @param $redirectUrl
     * @return array
     * @throws ConnectorServiceException
     */
    public function authorize($code = null, $redirectUrl)
    {
        try {
            $helper = $this->client->getRedirectLoginHelper();
            $accessToken = $helper->getAccessToken();

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
     * @param $credentials
     * @return null
     * @throws ConnectorConfigException
     */
    public function checkCredentials($credentials) {
        $this->checkCredentialsParameters($credentials);

        try {
            return $this->getProfile(SocialNetworks::ENTITY_USER, self::FACEBOOK_SELF_USER);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set");
        }
    }

    /**
     * Service that query to Facebook Api for user's followers
     * @param $entity
     * @param $id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     */
    public function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $response = $this->client->get("/".self::FACEBOOK_SELF_USER."/friends", $this->accessToken)->getDecodedBody();
        return $response["summary"]["total_count"];
    }

    public function exportSubscribers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportSubscribers() method.
    }

    /**
     * Service that query to Facebook Api a followers count
     * @param $entity
     * @param $id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
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

        return json_encode($posts);
    }

    /**
     * Service that query to Facebook Api to get user profile
     * @param string $entity "user"
     * @param string $id    user id
     * @return string
     * @throws ConnectorServiceException
     */
    public function getProfile($entity, $id) {
        $this->checkUser($id);

        try {
            $response = $this->client->get("/".$id."?fields=id,name,first_name,middle_name,last_name,email", $this->accessToken);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error getting user profile: ' . $e->getMessage(), $e->getCode());
        }

        $profile = array(
            "user_id" => $response->getGraphUser()->getId(),
            "name" => $response->getGraphUser()->getName(),
            "first_name" => $response->getGraphUser()->getFirstName(),
            "middle_name" => $response->getGraphUser()->getMiddleName(),
            "last_name" => $response->getGraphUser()->getLastName(),
            "email" => $response->getGraphUser()->getEmail()
        );

        return json_encode($profile);
    }

    /**
     * Service that upload a media file (photo) to Facebook
     * @param string $entity "user"|"page
     * @param string $id    user or page id
     * @param string $mediaType "url"|"path"
     * @param string $value url or path
     * @param string $title message for the media
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function importMedia($entity, $id, $parameters)
    {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
            $pageinfo = json_decode($this->getPage($entity, $id), true);
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

        return json_encode($media);
    }

    /**
     * Service that query to Facebook API for user photos
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportMedia($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
            $pageinfo = json_decode($this->getPage($entity, $id), true);
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

        return json_encode($photos);
    }

    /**
     * Service that create a post in Facebook user's feed
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param array $parameters
     *      "message"           =>  Text of the post (mandatory)
     *      "link"              =>  URL
     *      "object_attachment" =>  Facebook ID for an existing picture in the person's photo albums to use as the thumbnail image.
     *      They must be the owner of the photo, and the photo cannot be part of a message attachment.
     * @return array
     * @throws ConnectorServiceException
     */
    public function post($entity, $id, array $parameters) {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
            $pageinfo = json_decode($this->getPage($entity, $id), true);
        }

        try {
            if (SocialNetworks::ENTITY_USER === $entity) {
                $response = $this->client->post("/" . self::FACEBOOK_SELF_USER . "/feed", $parameters, $this->accessToken);
            } else {
                $response = $this->client->post("/" . $id . "/feed", $parameters, $pageinfo["access_token"]);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error creating a post: ' . $e->getMessage(), $e->getCode());
        }

        $graphNode = $response->getGraphNode();

        $post = array("post_id" => $graphNode["id"]);

        return json_encode($post);
    }

    /**
     * Service that creates a new photo album for the user in facebook
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param $title
     * @param $caption
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function createPhotosAlbum($entity, $id, $title, $caption) {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
            $pageinfo = json_decode($this->getPage($entity, $id), true);
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

        return json_encode($album);
    }

    /**
     * Service that get information of all user's photo albums in facebook
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
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

        return json_encode($albums);
    }

    /**
     * Service that gets photos from an album owned by user in facebook
     * @param string $entity "user"|"page"
     * @param string $id    user or page id
     * @param $albumId
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportPhotosFromAlbum($entity, $id, $albumId, $maxResultsPerPage, $numberOfPages, $pageToken) {
        if (SocialNetworks::ENTITY_USER === $entity) {
            $this->checkUser($id);
        } else {
            $this->checkPage($id);
        }
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


        $photos["pageToken"] = $pageToken;

        return json_encode($photos);
    }

    /**
     * Service that gets all pages this person administers/is an admin for
     * @param string $entity "user"
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
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
                    $pages[$count][] = $page->asArray();
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


        $pages["pageToken"] = $pageToken;

        return json_encode($pages);
    }

    /**
     * Service that query to Facebook Api to get page settings
     * @param $entity   "page"
     * @param $id       page id
     * @return string
     * @throws ConnectorServiceException
     */
    public function getPage($entity, $id) {
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

        return json_encode($page);
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
}