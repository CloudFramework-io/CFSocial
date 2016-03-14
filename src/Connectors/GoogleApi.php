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
 * Class GoogleApi
 * @package CloudFramework\Service\SocialNetworks\Connectors
 * @author Salvador Castro <sc@bloombees.com>
 */
class GoogleApi extends Singleton implements SocialNetworkInterface {

    const ID = 'google';

    // Google client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    /**
     * Set Google Api keys
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

        $this->client = new \Google_Client();
        $this->client->setClassConfig("Google_Http_Request", "disable_gzip", true);
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
    }

    /**
     * Service that request authorization to Google making up the Google login URL
     * @param string $redirectUrl
     * @return array
     * @throws ConnectorConfigException
     * @throws MalformedUrlException
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

        $this->client->setRedirectUri($redirectUrl);
        $this->client->setAccessType("offline");
        foreach($this->clientScope as $scope) {
            $this->client->addScope($scope);
        }

        $authUrl = $this->client->createAuthUrl();

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
     * Authentication service from google sign in request
     * @param string $code
     * @param string $verifier
     * @param string $redirectUrl
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorException
     * @throws MalformedException
     * @throws \Exception
     *
     */
    public function authorize($code, $verifier = null, $redirectUrl)
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

        $this->client->setRedirectUri($redirectUrl);

        try {
            $this->client->authenticate($code);

            $googleCredentials = json_decode($this->client->getAccessToken(), true);
        } catch(\Exception $e) {
            if (401 === $e->getCode()) {
                throw new AuthenticationException("Error fetching OAuth2 access token, client is invalid");
            } else {
                throw new ConnectorServiceException($e->getMessage(), $e->getCode());
            }
        }

        return $googleCredentials;
    }

    /**
     * Method that inject the access token in google client
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->client->setAccessToken(json_encode($credentials));

    }

    /**
     * Service that check if credentials are valid and authorized in google
     * @param array $credentials
     * @return mixed
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function checkCredentials(array $credentials) {
        $this->checkCredentialsParameters($credentials);

        try {
            $oauthService = new \Google_Service_Oauth2($this->client);
            $optParams["access_token"] = $credentials["access_token"];
            $optParams["id_token"] = $credentials["id_token"];
            $tokenInfo = $oauthService->tokeninfo($optParams);
        } catch (\Exception $e) {
            throw new ConnectorServiceException("The token has expired, has been tampered with, or the permissions revoked.");
        }

        return $tokenInfo;
    }

    /**
     * Service that refresh user's credentials and returns new ones
     * @param $credentials
     * @return mixed
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     */
    public function refreshCredentials($credentials) {
        $this->checkCredentialsParameters($credentials);
        if ((!isset($credentials["refresh_token"])) || (null === $credentials["refresh_token"]) || ("" === $credentials["refresh_token"])) {
            throw new ConnectorConfigException("'refresh_token' parameter is required");
        }

        try {
            $this->client->setClientId($this->clientId);
            $this->client->setClientSecret($this->clientSecret);
            $this->client->refreshToken($credentials["refresh_token"]);
        } catch(\Exception $e) {
            throw new AuthenticationException("Error refreshing token: " . $e->getMessage());
        }

        return json_decode($this->client->getAccessToken(), true);
    }

    /**
     * Service that query to Google api to revoke access token in order
     * to ensure the permissions granted to the application are removed
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function revokeToken()
    {
        try {
            $this->client->revokeToken();
        } catch(\Exception $e) {
            throw new ConnectorServiceException("Error revoking token: " . $e->getMessage(), $e->getCode());
        }

        return json_encode(array(
            "status" => "success",
            "note" => "Following a successful revocation response, it might take some time before the revocation has full effect"
        ));
    }

    /**
     * Service that query to Google Api for people in user circles
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkExpiredToken();
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $people = array();
        $count = 0;

        do {
            try {
                $plusDomainsService = new \Google_Service_PlusDomains($this->client);
                $parameters = array();
                $parameters["maxResults"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["pageToken"] = $pageToken;
                }

                $peopleList = $plusDomainsService->people->listPeople($id, "circled", $parameters);

                if (!isset($people["total"])) {
                    $people["total"] = $peopleList->getTotalItems();
                }

                $people[$count] = array();
                foreach($peopleList->getItems() as $person) {
                    $people[$count][] = $person->toSimpleObject();
                }
                $count++;

                $pageToken = $peopleList->getNextPageToken();

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting people: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        $people["pageToken"] = $pageToken;

        return json_encode($people);
    }

    public function exportSubscribers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportSubscribers() method.
    }

    /**
     * Service that query to Google Api for followers info (likes and shares) of a post
     * @param string $userId
     * @param string $postId
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPeopleInPost($entity, $id, $postId)
    {
        $this->checkExpiredToken();
        $this->checkUser($id);

        if ((null === $postId) || ("" === $postId)) {
            throw new ConnectorConfigException("'postId' parameter is required");
        }

        try {
            $people = array();
            $plusDomainsService = new \Google_Service_PlusDomains($this->client);
            $plusoners = $plusDomainsService->people->listByActivity($postId, "plusoners");
            $resharers = $plusDomainsService->people->listByActivity($postId, "resharers");
            $sharedto = $plusDomainsService->people->listByActivity($postId, "sharedto");

            $people["plusoners"] = array("total" => (null === $plusoners->getTotalItems())?0:$plusoners->getTotalItems());
            $people["resharers"] = array("total" => (null === $resharers->getTotalItems())?0:$resharers->getTotalItems());
            $people["sharedto"] = array("total" => (null === $sharedto->getTotalItems())?0:$sharedto->getTotalItems());

            foreach($plusoners->getItems() as $plusoner) {
                $people["plusoners"][] = $plusoner->toSimpleObject();
            }

            foreach($resharers->getItems() as $resharer) {
                $people["resharers"][] = $resharer->toSimpleObject();
            }

            foreach($sharedto->getItems() as $shared) {
                $people["sharedto"][] = $shared->toSimpleObject();
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException("Error getting people in Google+ post: " . $e->getMessage(), $e->getCode());
        }

        return json_encode($people);
    }

    /**
     * Service that query to Google Api for posts/activities of a user
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPosts($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkExpiredToken();
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $activities = array();
        $count = 0;

        do {
            try {
                $plusDomainsService = new \Google_Service_PlusDomains($this->client);
                $parameters = array();
                $parameters["maxResults"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["pageToken"] = $pageToken;
                }

                $activitiesList = $plusDomainsService->activities->listActivities($id, "user", $parameters);

                $activities[$count] = array();
                foreach($activitiesList->getItems() as $activity) {
                    $activities[$count][] = $activity->toSimpleObject();
                }
                $count++;

                $pageToken = $activitiesList->getNextPageToken();

                if ($count == $numberOfPages) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting posts: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        $activities["pageToken"] = $pageToken;

        return json_encode($activities);
    }

    /**
     * Service that query to Google+ Api to get user profile
     * @param string $entity "user"
     * @param string $id    user id
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function getProfile($entity, $id)
    {
        $this->checkExpiredToken();

        $this->checkUser($id);

        try {
            $plusService = new \Google_Service_Plus($this->client);
            $profile = $plusService->people->get($id);
        } catch(\Exception $e) {
            throw new ConnectorServiceException("Error getting user profile: " . $e->getMessage(), $e->getCode());
        }

        return json_encode($profile->toSimpleObject());
    }

    /**
     * Service that query to Google Api Drive service for images
     * @param string $entity "user"
     * @param string $id    user id
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
        $this->checkExpiredToken();
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $files = array();
        $count = 0;
        do {
            try {
                $driveService = new \Google_Service_Drive($this->client);
                $parameters = array();
                $parameters["q"] = "((mimeType contains 'image' or mimeType contains 'video') and trashed = false)";
                $parameters["maxResults"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["pageToken"] = $pageToken;
                }

                $filesList = $driveService->files->listFiles($parameters);
                $pageToken = $filesList->getNextPageToken();

                $items = $filesList->getItems();
                if ((count($items) == 0) && (null !== $pageToken)) {
                    continue;
                }

                $files[$count] = $items;
                $count++;

                // If number of pages == 0, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting images: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        $files["pageToken"] = $pageToken;

        return json_encode($files);
    }

    /**
     * Service that upload a media file (image/video) to Google+
     * @param string $entity "user"
     * @param string $id    user id
     * @param string $parameters
     *      "media_type"    =>      "url"|"path"
     *      "value"         =>      url or path
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function importMedia($entity, $id, $parameters)
    {
        $this->checkExpiredToken();
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

                if ((false === strpos($mimeType,"image/")) && (false === strpos($mimeType,"video/"))) {
                    throw new ConnectorConfigException("file must be an image or a video");
                } else {
                    $filesize = filesize($parameters["value"]);
                    if ($filesize > SocialNetworks::MAX_IMPORT_FILE_SIZE) {
                        throw new ConnectorConfigException("Maximum file size is ".(SocialNetworks::MAX_IMPORT_FILE_SIZE_MB)."MB");
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
                if ($filesize > SocialNetworks::MAX_IMPORT_FILE_SIZE) {
                    throw new ConnectorConfigException("Maximum file size is ".(SocialNetworks::MAX_IMPORT_FILE_SIZE_MB)."MB");
                }
            }
            $parameters["value"] = $tempMedia;
        }

        try {
            $plusDomainsService = new \Google_Service_PlusDomains($this->client);
            $plusDomainsMedia = new \Google_Service_PlusDomains_Media();
            $plusDomainsMedia->setDisplayName("Uploaded media file");

            // Size of each chunk of data in bytes. Setting it higher leads faster upload (less chunks,
            // for reliable connections). Setting it lower leads better recovery (fine-grained chunks)
            $chunkSizeBytes = SocialNetworks::BLOCK_SIZE_BYTES;

            // Setting the defer flag to true tells the client to return a request which can be called
            // with ->execute(); instead of making the API call immediately.
            $this->client->setDefer(true);

            $insertRequest = $plusDomainsService->media->insert($id, "cloud", $plusDomainsMedia);

            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $insertRequest,
                $mimeType,
                null,
                true,
                $chunkSizeBytes
            );

            $media->setFileSize($filesize);

            // Upload the various chunks. $status will be false until the process is complete.
            $status = false;
            $handle = fopen($parameters["value"], "rb");

            while (!$status && !feof($handle)) {
                $chunk = stream_get_contents($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }

            // The final value of $status will be the data from the API for the object that has been uploaded.
            $result = false;
            if($status != false) {
                $result = $status;
            }

            fclose($handle);

            // Reset to the client to execute requests immediately in the future.
            $this->client->setDefer(false);

            return json_encode($result);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error importing '".$parameters["value"]."'': " . $e->getMessage(), $e->getCode());
        }
    }

    /**
     * Service that publish in Google +
     * @param string $entity "user"
     * @param string $id    user id
     * @param array $parameters
     *      "user_id"    => User whose google domain the stream will be published in
     *      "content"   => Text of the comment
     *      "access_type" => The type of entry describing to whom access to new post/activity is granted
     *              "person"          => Need a personId parameter
     *              "circle"          => Need a circleId parameter
     *              "myCircles"       => Access to members of all the person's circles
     *              "extendedCircles" => Access to members of all the person's circles, plus all of the people in their circles
     *              "domain"          => Access to members of the person's Google Apps domain
     *              "public"          => Access to anyone on the web
     *      "attachment":
     *          "0": "link" | "image" | "video"
     *          "1": url or path for a file
     *      "person_id"  => Google + user whose domain the stream will be published in (mandatory in case of access_type = "person")
     *      "circle_id"  => Google circle where the stream will be published in (mandatory in case of access_type = "circle")
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function post($entity, $id, array $parameters) {
        $this->checkExpiredToken();

        if ((null === $parameters) || (!is_array($parameters)) || (count($parameters) == 0)) {
            throw new ConnectorConfigException("Invalid post parameters'");
        }

        if ((!isset($parameters["user_id"])) || (null === $parameters["user_id"]) || ("" === $parameters["user_id"])) {
            throw new ConnectorConfigException("'user_id' post parameter is required");
        }

        if ((!isset($parameters["content"])) || (null === $parameters["content"]) || ("" === $parameters["content"])) {
            throw new ConnectorConfigException("'content' post parameter is required");
        }

        if ((!isset($parameters["access_type"])) || (null === $parameters["access_type"]) || ("" === $parameters["access_type"])) {
            throw new ConnectorConfigException("'access_type' post parameter is required");
        } else {
            if (("circle" == $parameters["access_type"]) &&
                ((!isset($parameters["circle_id"])) || (null === $parameters["circle_id"]) || ("" === $parameters["circle_id"]))) {
                throw new ConnectorConfigException("'circle_id' post parameter is required since access_type is 'circle'");
            }

            if (("person" == $parameters["access_type"]) &&
                ((!isset($parameters["person_id"])) || (null === $parameters["person_id"]) || ("" === $parameters["person_id"]))) {
                throw new ConnectorConfigException("'person_id' post parameter is required since access_type is 'person'");
            }
        }

        if ((isset($parameters["attachment"])) && (!is_array($parameters["attachment"]))) {
            throw new ConnectorConfigException("'attachment' post parameter must be an array");
        } else {
            if (count($parameters["attachment"]) == 0) {
                throw new ConnectorConfigException("'attachment' post parameter array is empty'");
            }
            if ((isset($parameters["attachment"][0])) &&
                (("link" !== $parameters["attachment"][0]) &&
                ("photo" !== $parameters["attachment"][0]) &&
                ("video" !== $parameters["attachment"][0]))) {
                throw new ConnectorConfigException("'attachment' type must be 'link', 'photo' or 'video'");
            }

            if ((isset($parameters["attachment"][1])) &&
                ((null === $parameters["attachment"][1]) ||
                ("" === $parameters["attachment"][1]))) {
                throw new ConnectorConfigException("'attachment' value must be an url ('link') or a file path ('photo' or 'video')");
            } else {
                if (("link" === $parameters["attachment"][0]) && (!SocialNetworks::wellFormedUrl($parameters["attachment"][1]))) {
                    throw new ConnectorConfigException("'attachment' value url is malformed");
                }
            }
        }

        // Activity
        $postBody = new \Google_Service_PlusDomains_Activity();

        // Activity object
        $object = new \Google_Service_PlusDomains_ActivityObject();
        $object->setObjectType("activity");
        $object->setOriginalContent($parameters["content"]);

        // Activity attachments
        $attachments = array();

        if (isset($parameters["attachment"])) {
            switch($parameters["attachment"][0]) {
                case "link":
                    $linkAttachment = new \Google_Service_PlusDomains_ActivityObjectAttachments();
                    $linkAttachment->setObjectType("article");
                    $linkAttachment->setUrl($parameters["attachment"][1]);
                    $postBody->setUrl($parameters["attachment"][1]);
                    $attachments[] = $linkAttachment;
                    break;
                default:
                    $mediaAttachment = new \Google_Service_PlusDomains_ActivityObjectAttachments();
                    $mediaAttachment->setObjectType($parameters["attachment"][0]);
                    $mediaAttachment->setId($parameters["attachment"][1]);
                    $attachments[] = $mediaAttachment;
                    break;
            }
        }

        if (count($attachments) > 0) {
            $object->setAttachments($attachments);
        }

        $postBody->setObject($object);

        // Activity access
        $access = new \Google_Service_PlusDomains_Acl();
        $access->setDomainRestricted(true);

        $resource = new \Google_Service_PlusDomains_PlusDomainsAclentryResource();

        $resource->setType($parameters["access_type"]);
        if ("circle" === $resource->getType()) {
            $resource->setId($parameters["circle_id"]);
        } else if ("person" === $resource->getType()) {
            $resource->setId($parameters["person_id"]);
        }

        $resources = array();
        $resources[] = $resource;

        $access->setItems($resources);
        $postBody->setAccess($access);

        try {
            $plusDomainsService = new \Google_Service_PlusDomains($this->client);
            $activity = $plusDomainsService->activities->insert($parameters["user_id"], $postBody);
        } catch(\Exception $e) {
            throw new ConnectorServiceException("Error creating post: " . $e->getMessage(), $e->getCode());
        }

        return json_encode($activity);
    }

    /**
     * Service that query to Google Api for a list of circles for an user
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportCircles($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkExpiredToken();
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $circles = array();
        $count = 0;

        do {
            try {
                $plusDomainsService = new \Google_Service_PlusDomains($this->client);
                $parameters = array();
                $parameters["maxResults"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["pageToken"] = $pageToken;
                }

                $circlesList = $plusDomainsService->circles->listCircles($id, $parameters);

                if (!isset($circles["total"])) {
                    $circles["total"] = $circlesList->getTotalItems();
                }

                $circles[$count] = array();
                foreach($circlesList->getItems() as $circle) {
                    $circles[$count][] = $circle->toSimpleObject();
                }
                $count++;

                $pageToken = $circlesList->getNextPageToken();

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting circles: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        $circles["pageToken"] = $pageToken;

        return json_encode($circles);
    }

    /**
     * Service that query to Google Api for people in an specific circle
     * @param string $entity "user"
     * @param string $id    user id
     * @param string $circleId
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return string
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPeopleInCircle($entity, $id, $circleId, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $this->checkExpiredToken();
        $this->checkUser($id);

        if ((null === $circleId) || ("" === $circleId)) {
            throw new ConnectorConfigException("'circleId' parameter is required");
        }

        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $people = array();
        $count = 0;

        do {
            try {
                $plusDomainsService = new \Google_Service_PlusDomains($this->client);
                $parameters = array();
                $parameters["maxResults"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["pageToken"] = $pageToken;
                }

                $peopleList = $plusDomainsService->people->listByCircle($circleId, $parameters);

                if (!isset($people["total"])) {
                    $people["total"] = $peopleList->getTotalItems();
                }

                $people[$count] = array();
                foreach($peopleList->getItems() as $person) {
                    $people[$count][] = $person->toSimpleObject();
                }
                $count++;

                $pageToken = $peopleList->getNextPageToken();

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting people in circle with id '".$circleId."' :" . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        $people["pageToken"] = $pageToken;

        return json_encode($people);
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



        if ((!isset($credentials["id_token"])) || (null === $credentials["id_token"]) || ("" === $credentials["id_token"])) {
            throw new ConnectorConfigException("'id_token' parameter is required");
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

        if (null === $maxResultsPerPage) {
            throw new ConnectorConfigException("'numberOfPages' parameter is required");
        } else if (!is_numeric($numberOfPages)) {
            throw new ConnectorConfigException("'numberOfPages' parameter is not numeric");
        }
    }

    /**
     * Method that check whether current access token is expired
     * @throws ConnectorServiceException
     */
    private function checkExpiredToken() {
        if ($this->client->isAccessTokenExpired()) {
            throw new ConnectorServiceException("The token has expired, has been tampered with, or the permissions revoked.");
        }
    }
}