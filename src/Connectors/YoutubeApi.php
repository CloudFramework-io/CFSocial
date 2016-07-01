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
 * Class YoutubeApi
 * @package CloudFramework\Service\SocialNetworks\Connectors
 * @author Salvador Castro <sc@bloombees.com>
 */
class YoutubeApi extends Singleton implements SocialNetworkInterface {

    const ID = "youtube";

    // Youtube/Google client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    /**
     * Set Youtube/Google Api keys
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

        if ((null === $clientScope) || (!is_array($clientScope)) || (count($clientScope) == 0)) {
            throw new ConnectorConfigException("'clientScope' parameter is required");
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new \Google_Client();
        $this->client->setClientId($this->clientId);
        $this->client->setClientSecret($this->clientSecret);
    }

    /**
     * Service that requests authorization to Youtube/Google making up the Youtube/Google login URL
     * @param string $redirectUrl
     * @return mixed
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
     * Authentication service from youtube/google sign in request
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
    public function authorize($code, $verifier, $redirectUrl)
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
            $youtubeCredentials = $this->client->authenticate($code);
        } catch(\Exception $e) {
            if (401 === $e->getCode()) {
                throw new AuthenticationException("Error fetching OAuth2 access token, client is invalid");
            } else {
                throw new ConnectorServiceException($e->getMessage(), $e->getCode());
            }
        }

        return $youtubeCredentials;
    }

    /**
     * Method that inject the access token in youtube/google client
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->client->setAccessToken(json_encode($credentials));

    }

    /**
     * Service that checks if credentials are valid and authorized in youtube/google
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
     * Service that refreshes user's credentials and returns new ones
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
            $accessToken = $this->client->refreshToken($credentials["refresh_token"]);
        } catch(\Exception $e) {
            throw new AuthenticationException("Error refreshing token: " . $e->getMessage());
        }

        return $accessToken;
    }

    /**
     * Service that queries to Youtube/Google api to revoke access token in order
     * to ensure the permissions granted to the application are removed
     * @return array
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

        return array(
            "status" => "success",
            "note" => "Following a successful revocation response, it might take some time before the revocation has full effect"
        );
    }

    /**
     * Service that queries to Google+ Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function getProfile($id)
    {
        $this->checkExpiredToken();

        $this->checkUser($id);

        try {
            $plusService = new \Google_Service_Plus($this->client);
            $person = $plusService->people->get($id);
        } catch(\Exception $e) {
            throw new ConnectorServiceException("Error getting user profile: " . $e->getMessage(), $e->getCode());
        }

        $personArray = json_decode(json_encode($person->toSimpleObject()), true);

        $profile = [
            "user_id" => $personArray["id"],
            "name" => $personArray["displayName"],
            "first_name" => ((array_key_exists("name", $personArray)) &&
                (array_key_exists("givenName", $personArray["name"])))?$personArray["name"]["givenName"]:null,
            "last_name" => ((array_key_exists("name", $personArray)) &&
                (array_key_exists("familyName", $personArray["name"])))?$personArray["name"]["familyName"]:null,
            "email" => ((array_key_exists("emails", $personArray)) &&
                (count($personArray["emails"]) > 0))?$personArray["emails"][0]["value"]:null,
            "photo" => ((array_key_exists("image", $personArray)) &&
                (array_key_exists("url", $personArray["image"])))?$personArray["image"]["url"]:null,
            "locale" => $personArray["language"],
            "url" => $personArray["url"],
            "raw" => $personArray
        ];

        return $profile;
    }

    /**
     * Service that queries to Youtube Api for video categories
     * @return array
     * @throws ConnectorServiceException
     */
    public function exportVideoCategories() {
        $this->checkExpiredToken();

        $videoCategories = array();
        $videoCategories["videocategories"] = array();

        $youtubeService = new \Google_Service_YouTube($this->client);
        $videoCategoriesList = $youtubeService->videoCategories->listVideoCategories("snippet", array("regionCode" => "ES"));

        foreach($videoCategoriesList->getItems() as $category) {
            $videoCategories["videocategories"][] = $category->toSimpleObject();
        }

        return $videoCategories;
    }

    /**
     * Service that queries to Youtube Api for playlists
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return array
     * @throws ConnectorServiceException
     */
    public function exportPlaylists($maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkExpiredToken();
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $playlists = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["mine"] = true;
                $parameters["maxResults"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["pageToken"] = $pageToken;
                }

                $youtubeService = new \Google_Service_YouTube($this->client);
                $playlistsList = $youtubeService->playlists->listPlaylists('id, snippet', $parameters);

                $playlists[$count] = array();
                foreach($playlistsList->getItems() as $playlist) {
                    $playlists[$count][] = $playlist->toSimpleObject();
                }
                $count++;

                $pageToken = $playlistsList->getNextPageToken();

                if ($count == $numberOfPages) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting playlists: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            'playlists' => $playlists,
            "pageToken" => $pageToken,
        );
    }

    /**
     * Service that queries to Youtube Api for videos
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @param null $videoIds Filter by videos ids
     * @return array
     * @throws ConnectorServiceException
     */
    public function exportVideos($maxResultsPerPage, $numberOfPages, $pageToken, $videoIds = null) {
        $this->checkExpiredToken();
        $this->checkPagination($maxResultsPerPage, $numberOfPages);

        $videos = array();
        $count = 0;

        do {
            try {
                $youtubeService = new \Google_Service_YouTube($this->client);

                $parameters = array();
                $parameters["type"] = "video";
                $parameters["forMine"] = true;
                $parameters["maxResults"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["pageToken"] = $pageToken;
                }

                if (null === $videoIds) {
                    $searchResponse = $youtubeService->search->listSearch('id,snippet', $parameters);

                    $videoResults = array();

                    foreach ($searchResponse['items'] as $searchResult) {
                        array_push($videoResults, $searchResult['id']['videoId']);
                    }
                    $videoIds = join(',', $videoResults);
                }

                $videosList = $youtubeService->videos->listVideos('id, snippet, status', array(
                    'id' => $videoIds
                ));

                $videos[$count] = array();
                foreach($videosList->getItems() as $video) {
                    $videos[$count][] = $video->toSimpleObject();
                }
                $count++;

                $pageToken = $videosList->getNextPageToken();

                if ($count == $numberOfPages) {
                    break;
                }
            } catch (Exception $e) {
                throw new ConnectorServiceException("Error exporting videos: " . $e->getMessage(), $e->getCode());
                $pageToken = null;
            }
        } while ($pageToken);

        return array(
            'videos' => $videos,
            "pageToken" => $pageToken,
        );
    }

    /**
     * Service that gets a video by its id
     * @param $videoId
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function getVideo($videoId) {
        try {
            $videos = $this->exportVideos(1, 1, 0, $videoId);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error getting video with ID ".$videoId.": " . $e->getMessage(), $e->getCode());
        }

        return $videos["videos"][0][0];
    }

    /**
     * Service that uploads a video
     * @param $id
     * @param array $parameters
     *      "title"         =>  Title of the video (required)
     *      "description"   =>  Description of the video (required)
     *      "status"        =>  Status of the video (required)
     *      "media_type"    =>  "url"|"path" (required)
     *      "value"         =>  url or path (required)
     *      "category_id"   =>  Category of the video (optional)
     *      "tags"          =>  Comma separated list of tags (optional)
     * @return bool|false|\Google_Service_YouTube_VideoStatus|mixed
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function post($id, array $parameters)
    {
        $this->checkExpiredToken();
        $this->checkUser($id);

        if ((null === $parameters["title"]) || ("" === $parameters["title"])) {
            throw new ConnectorConfigException("The title is required");
        } elseif ((null === $parameters["description"]) || ("" === $parameters["description"])) {
            throw new ConnectorConfigException("The description is required");
        } elseif ((null === $parameters["status"]) || ("" === $parameters["status"])) {
            throw new ConnectorConfigException("The status is required. Valid statuses are \"public\",
            // \"private\" and \"unlisted\"");
        } elseif ((null === $parameters["media_type"]) || ("" === $parameters["media_type"])) {
            throw new ConnectorConfigException("Media type must be 'url' or 'path'");
        } elseif ((null === $parameters["value"]) || ("" === $parameters["value"])) {
            throw new ConnectorConfigException($parameters["media_type"]." value is required");
        } elseif ("path" === $parameters["media_type"]) {
            if (!file_exists($parameters["value"])) {
                throw new ConnectorConfigException("file doesn't exist");
            } else {
                $mimeType = SocialNetworks::mime_content_type($parameters["value"]);

                if (false === strpos($mimeType,"video/")) {
                    throw new ConnectorConfigException("file must be a video");
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

            if (false === strpos($mimeType,"video/")) {
                throw new ConnectorConfigException("file must be a video");
            } else {
                $filesize = filesize($tempMedia);
                if ($filesize > SocialNetworks::MAX_IMPORT_FILE_SIZE) {
                    throw new ConnectorConfigException("Maximum file size is ".(SocialNetworks::MAX_IMPORT_FILE_SIZE_MB)."MB");
                }
            }
            $parameters["value"] = $tempMedia;
        }

        try {
            $snippet = new \Google_Service_YouTube_VideoSnippet();
            $snippet->setTitle($parameters["title"]);
            $snippet->setDescription($parameters["description"]);
            if ((null !== $parameters["tags"]) && ("" !== $parameters["tags"])) {
                $tagsArr = explode(",", $parameters["tags"]);
                $snippet->setTags($tagsArr);
            }

            if ((null !== $parameters["category_id"]) && ("" !== $parameters["category_id"])) {
                $snippet->setCategoryId($parameters["category_id"]);
            }

            // Set the video's status to "public". Valid statuses are "public",
            // "private" and "unlisted".
            $status = new \Google_Service_YouTube_VideoStatus();
            $status->privacyStatus = $parameters["status"];

            $video = new \Google_Service_YouTube_Video();
            $video->setSnippet($snippet);
            $video->setStatus($status);

            $chunkSizeBytes = SocialNetworks::BLOCK_SIZE_BYTES;

            $this->client->setDefer(true);

            $youtubeService = new \Google_Service_YouTube($this->client);
            $insertRequest = $youtubeService->videos->insert("status,snippet", $video);

            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $insertRequest,
                'video/*',
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($parameters["value"]));


            // Read the media file and upload it chunk by chunk.
            $status = false;
            $handle = fopen($parameters["value"], "rb");
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }

            fclose($handle);

            $this->client->setDefer(false);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error uploading '".$parameters["value"]."'': " . $e->getMessage(), $e->getCode());
        }

        return $status;
    }

    /**
     * Service that creates a playlist
     * @param array $parameters
     *      "title"                     =>  Title of the playlist (required)
     *      "description"               =>  Description of the playlist (required)
     *      "status"                    =>  Status of the playlist (required)
     * @return \Google_Service_YouTube_Playlist
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function createPlaylist(array $parameters)
    {
        $this->checkExpiredToken();

        if ((null === $parameters["title"]) || ("" === $parameters["title"])) {
            throw new ConnectorConfigException("The title is required");
        } elseif ((null === $parameters["description"]) || ("" === $parameters["description"])) {
            throw new ConnectorConfigException("The description is required");
        } elseif ((null === $parameters["status"]) || ("" === $parameters["status"])) {
            throw new ConnectorConfigException("The status is required. Valid statuses are \"public\",
            // \"private\" and \"unlisted\"");
        }

        try {
            $youtubeService = new \Google_Service_YouTube($this->client);

            // Playlist snippet
            $playlistSnippet = new \Google_Service_YouTube_PlaylistSnippet();
            $playlistSnippet->setTitle($parameters["title"]);
            $playlistSnippet->setDescription($parameters["description"]);

            // Playlist status
            $playlistStatus = new \Google_Service_YouTube_PlaylistStatus();
            $playlistStatus->setPrivacyStatus($parameters["status"]);

            // Playlist
            $youTubePlaylist = new \Google_Service_YouTube_Playlist();
            $youTubePlaylist->setSnippet($playlistSnippet);
            $youTubePlaylist->setStatus($playlistStatus);

            $playlist = $youtubeService->playlists->insert('snippet,status',
                $youTubePlaylist, array());
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error creating playlist: " . $e->getMessage(), $e->getCode());
        }

        return $playlist;
    }

    /**
     * Service that add an existing video to a playlist
     * @param $playlistId
     * @param $videoId
     * @return \Google_Service_YouTube_PlaylistItem
     * @throws ConnectorServiceException
     */
    public function addVideoToPlaylist($playlistId, $videoId) {
        try {
            $video = $this->getVideo($videoId);

            $youtubeService = new \Google_Service_YouTube($this->client);
            $resourceId = new \Google_Service_YouTube_ResourceId();
            $resourceId->setVideoId($videoId);
            $resourceId->setKind('youtube#video');

            $playlistItemSnippet = new \Google_Service_YouTube_PlaylistItemSnippet();
            $playlistItemSnippet->setTitle($video->snippet["title"]);
            $playlistItemSnippet->setPlaylistId($playlistId);
            $playlistItemSnippet->setResourceId($resourceId);

            $playlistItem = new \Google_Service_YouTube_PlaylistItem();
            $playlistItem->setSnippet($playlistItemSnippet);
            $playlistItemResponse = $youtubeService->playlistItems->insert(
                'snippet,contentDetails', $playlistItem, array());
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error adding video to playlist: " . $e->getMessage(), $e->getCode());
        }

        return $playlistItemResponse;
    }

    /**
     * Service that removes a video from a playlist
     * @param $playlistId
     * @param $videoId
     * @return \Google_Service_YouTube_PlaylistItem
     * @throws ConnectorServiceException
     */
    public function removeVideoFromPlaylist($playlistId, $videoId) {
        try {
            $youtubeService = new \Google_Service_YouTube($this->client);

            $playlistItemsResponse = $youtubeService->playlistItems->listPlaylistItems("id", array(
                "playlistId" => $playlistId,
                "videoId" => $videoId
            ));

            $youtubeService->playlistItems->delete($playlistItemsResponse->getItems()[0]->id);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error removing video from playlist: " . $e->getMessage(), $e->getCode());
        }

        return $playlistItemsResponse->getItems()[0];
    }

    /**
     * Service that sets the video thumbnail
     * @param $videoId
     * @param array $parameters
     *      "media_type"    =>  "url"|"path" (required)
     *      "value"         =>  url or path of the image (required)
     * Maximum file size: 2MB
     * Accepted Media MIME types: image/jpeg, image/png, application/octet-stream
     * @return bool|false|\Google_Service_YouTube_VideoStatus|mixed
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function setVideoThumbnail($videoId, array $parameters)
    {
        $this->checkExpiredToken();

        if ((null === $parameters["media_type"]) || ("" === $parameters["media_type"])) {
            throw new ConnectorConfigException("Media type must be 'url' or 'path'");
        } elseif ((null === $parameters["value"]) || ("" === $parameters["value"])) {
            throw new ConnectorConfigException($parameters["media_type"]." value is required");
        } elseif ("path" === $parameters["media_type"]) {
            if (!file_exists($parameters["value"])) {
                throw new ConnectorConfigException("file doesn't exist");
            } else {
                $mimeType = SocialNetworks::mime_content_type($parameters["value"]);

                if ((false === strpos($mimeType,"image/jpeg")) &&
                    (false === strpos($mimeType,"image/png")) &&
                    (false === strpos($mimeType,"application/octet-stream"))){
                    throw new ConnectorConfigException("file must be an image");
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

            if ((false === strpos($mimeType,"image/jpeg")) &&
                (false === strpos($mimeType,"image/png")) &&
                (false === strpos($mimeType,"application/octet-stream"))){
                throw new ConnectorConfigException("file must be an image");
            } else {
                $filesize = filesize($tempMedia);
                if ($filesize > SocialNetworks::MAX_IMPORT_FILE_SIZE) {
                    throw new ConnectorConfigException("Maximum file size is ".(SocialNetworks::MAX_IMPORT_FILE_SIZE_MB)."MB");
                }
            }
            $parameters["value"] = $tempMedia;
        }

        try {
            $chunkSizeBytes = SocialNetworks::BLOCK_SIZE_BYTES;

            $this->client->setDefer(true);

            $youtubeService = new \Google_Service_YouTube($this->client);
            $setRequest = $youtubeService->thumbnails->set($videoId);

            $media = new \Google_Http_MediaFileUpload(
                $this->client,
                $setRequest,
                $mimeType,
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($parameters["value"]));


            $status = false;
            $handle = fopen($parameters["value"], "rb");
            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }

            fclose($handle);

            $this->client->setDefer(false);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error uploading '".$parameters["value"]."'': " . $e->getMessage(), $e->getCode());
        }

        return $status['items'][0]['default'];
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

        if (null === $numberOfPages) {
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