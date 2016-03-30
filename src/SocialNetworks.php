<?php
namespace CloudFramework\Service\SocialNetworks;

use CloudFramework\Patterns\Singleton;

/**
 * Class SocialNetworks
 * @author Fran López <fl@bloombees.com>
 */
class SocialNetworks extends Singleton
{
    const ENTITY_USER = 'user';
    const ENTITY_PAGE = 'page';
    const MAX_IMPORT_FILE_SIZE = 37748736; // 36MB
    const MAX_IMPORT_FILE_SIZE_MB = 36;
    const BLOCK_SIZE_BYTES = 1048576; // Blocks of 1MB

    /**
     * @return string
     */
    public static function generateRequestUrl()
    {
        $protocol = (array_key_exists("HTTPS", $_SERVER) && $_SERVER["HTTPS"] === 'on') ? 'https' : 'http';
        $domain = $_SERVER['SERVER_NAME'];
        $port = "";
        if (array_key_exists('SERVER_PORT', $_SERVER)) {
            $port = ":" . $_SERVER['SERVER_PORT'];
        }
        return "$protocol://$domain$port/";
    }

    /**
     * Method that initialize a social api instance to use
     * @param $social
     * @return \CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface
     * @throws \Exception
     */
    public function getSocialApi($social) {
        $social = ucfirst($social);
        $socialNetworkClass = "CloudFramework\\Service\\SocialNetworks\\Connectors\\{$social}Api";
        if (class_exists($socialNetworkClass)) {
            try {
                return $connector = $socialNetworkClass::getInstance();
            } catch(\Exception $e) {
                throw $e;
            }
        } else {
            throw new \Exception("Social Network Requested not exists", 501);
        }
    }

    /**
     * Service that set the api keys for social network
     * @param $social
     * @param $clientId
     * @param $clientSecret
     * @param array $clientScope
     * @return mixed
     * @throws \Exception
     */
    public function setApiKeys($social, $clientId, $clientSecret, $clientScope = array()) {
        $connector = $this->getSocialApi($social);
        return $connector->setApiKeys($clientId, $clientSecret, $clientScope);
    }

    /**
     * Service that set the access token for social network
     * @param $social
     * @param array $credentials
     * @return mixed
     * @throws \Exception
     */
    public function setAccessToken($social, array $credentials) {
        $connector = $this->getSocialApi($social);
        return $connector->setAccessToken($credentials);
    }

    /**
     * Service to request authorization to the social network
     * @param string $social
     * @param string $redirectUrl
     * @return mixed
     * @throws \Exception
     */
    public function requestAuthorization($social, $redirectUrl)
    {
        $connector = $this->getSocialApi($social);
        return $connector->requestAuthorization($redirectUrl);
    }

    /**
     * Service that authorize a user in the social network.
     * (This method receives the callback from the social network after login)
     * @param string $social
     * @param string $code
     * @param string $verifier
     * @param string $redirectUrl
     * @return mixed
     * @throws \Exception
     */
    public function confirmAuthorization($social, $code = null, $verifier = null, $redirectUrl = null)
    {
        $connector = $this->getSocialApi($social);
        return $connector->authorize($code, $verifier, $redirectUrl);
    }

    /**
     * Service that check if session user's credentials are authorized and not expired / revoked
     * @param $social
     * @param $credentials
     * @return mixed
     * @throws \Exception
     */
    public function checkCredentials($social, $credentials) {
        $connector = $this->getSocialApi($social);
        return $connector->checkCredentials($credentials);
    }

    /**
     * Service that refresh credentials and return new ones
     * @param $social
     * @param $credentials
     * @throws \Exception
     */
    public function refreshCredentials($social, $credentials) {
        $connector = $this->getSocialApi($social);
        return $connector->refreshCredentials($credentials);
    }

    /**
     * Service that query to a social network api to revoke access token in order
     * to ensure the permissions granted to the application are removed
     * @param string $social
     * @return mixed
     * @throws \Exception
     */
    public function revokeToken($social)
    {
        $connector = $this->getSocialApi($social);
        if(method_exists($connector, "revokeToken")) {
            return $connector->revokeToken();
        } else {
            return false;
        }
    }

    /**
     * Service that query to a social network api to get user profile
     * @param string $social
     * @param string $id user id
     * @return mixed
     * @throws \Exception
     */
    public function getProfile($social, $id) {
        $connector = $this->getSocialApi($social);
        return $connector->getProfile($id);
    }

    /**
     * Service that query to a social network api to get followers of an user
     * @param string $social
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportFollowers($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportFollowers($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that query to a social network api to get subscribers
     * @param string $social
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportSubscribers($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportSubscribers($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that query to a social network api to get user's posts
     * @param string $social
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportUserPosts($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPosts($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that query to a social network api to get user's page posts
     * @param string $social
     * @param string $id    page id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportPagePosts($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportPagePosts($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that connect to social network api and request for media files for authenticated user
     * @param string $social
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportUserMedia($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserMedia($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that connect to social network api and request for media files for a page
     * @param string $social
     * @param string $id    page id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportPageMedia($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportPageMedia($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that connect to social network api and upload a media file (image/video) to an user's album
     * @param string $social
     * @param string $id    user id
     * @param array $parameters
     * COMMON TO ALL SOCIAL NETWORKS
     *      "media_type"    =>      "url"|"path"
     *      "value"         =>      url or path
     * FACEBOOK
     *      "title"         =>      message for the media (mandatory)
     *      "album_id"      =>      album where media will be saved in
     *
     * @return mixed
     * @throws \Exception
     */
    public function uploadUserMedia($social, $id, $parameters)
    {
        $connector = $this->getSocialApi($social);
        return $connector->uploadUserMedia($id, $parameters);
    }

    /**
     * Service that connect to social network api and upload a media file (image/video) to an user's page
     * @param string $social
     * @param string $id    page id
     * @param array $parameters
     * COMMON TO ALL SOCIAL NETWORKS
     *      "media_type"    =>      "url"|"path"
     *      "value"         =>      url or path
     * FACEBOOK
     *      "title"         =>      message for the media (mandatory)
     *      "album_id"      =>      album where media will be saved in
     *
     * @return mixed
     * @throws \Exception
     */
    public function uploadPageMedia($social, $id, $parameters)
    {
        $connector = $this->getSocialApi($social);
        return $connector->uploadPageMedia($id, $parameters);
    }

    /**
     * Service that connect to social network api and publish a post in the authenticated user timeline / biography
     * @param string $social
     * @param string $id    user id
     * @param array $parameters
     * GOOGLE
     *      "content"   => Text of the comment
     *      "attachment":
     *          "0": "link" | "image" | "video"
     *          "1": url or path for a file
     *      "access_type" => The type of entry describing to whom access to new post/activity is granted
     *              "person"          => Need a personId parameter
     *              "circle"          => Need a circleId parameter
     *              "myCircles"       => Access to members of all the person's circles
     *              "extendedCircles" => Access to members of all the person's circles, plus all of the people in their circles
     *              "domain"          => Access to members of the person's Google Apps domain
     *              "public"          => Access to anyone on the web
     *      "person_id"  => Google + user whose domain the stream will be published in (mandatory in case of access_type = "person")
     *      "circle_id"  => Google circle where the stream will be published in (mandatory in case of access_type = "circle")
     * INSTAGRAM
     *      "content"   => Text of the comment
     *      "attachment"   => Instagram media's ID
     * FACEBOOK
     *      "content"           =>  Text of the post (mandatory)
     *      "attachment" =>  Facebook ID for an existing picture in the person's photo albums to use as the thumbnail image.
     *                      They must be the owner of the photo, and the photo cannot be part of a message attachment.
     *      "link"              =>  URL
     * TWITTER
     *      "content"                   =>  Text of the tweet (required)
     *      "attachment"                =>  String of media ids separated by comma
     *      "in_reply_to_status_id"     =>  Id of tweet this new tweet is answering
     * @return mixed
     * @throws \Exception
     */
    public function post($social, $id, $parameters)
    {
        $connector = $this->getSocialApi($social);
        return $connector->post($id, $parameters);
    }

    /**
     * Service that modify the relationship between the authenticated user and the target user in a social network.
     * @param $social
     * @param string $entity "user"
     * @param string $id    user id
     * @param $userId
     * @param $action
     * @return mixed
     * @throws \Exception
     */
    public function modifyUserRelationship($social, $id, $userId, $action) {
        $connector = $this->getSocialApi($social);
        return $connector->modifyUserRelationship($id, $userId, $action);
    }

    /******************************************************************************************************
     **                                         GOOGLE END POINTS                                        **
     ******************************************************************************************************/

    /**
     * Service that query to a social network api to get followers info
     * @param string $social
     * @param string $id    user id
     * @param string $postId
     * @return mixed
     * @throws \Exception
     */
    public function exportUserPeopleInPost($social, $id, $postId)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPeopleInPost($id, $postId);
    }

    /**
     * Service that gets a list of all of the circles for a user
     * @param $social
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportUserCircles($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserCircles($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that gets a list of people in a circle
     * @param $social
     * @param string $id    user id
     * @param $circleId
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportUserPeopleInCircle($social, $id, $circleId, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPeopleInCircle($id, $circleId, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that query to a social network api to get people in all user's circles
     * @param string $social
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportUserPeopleInAllCircles($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPeopleInAllCircles($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /******************************************************************************************************
     **                                         INSTAGRAM END POINTS                                     **
     ******************************************************************************************************/

    /**
     * Service that get the list of recent media liked by the owner
     * @param $social
     * @param string $id    user id
     * @param $maxTotalResults
     * @param $numberOfPages
     * @param $nextPageUrl
     * @return mixed
     * @throws \Exception
     */
    public function exportUserMediaRecentlyLiked($social, $id, $maxTotalResults, $numberOfPages, $nextPageUrl) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserMediaRecentlyLiked($id, $maxTotalResults, $numberOfPages, $nextPageUrl);
    }

    /**
     * Service that get information about a relationship to another user in a social network
     * @param $social
     * @param string $id    user id
     * @param $userId
     * @return mixed
     * @throws \Exception
     */
    public function getUserRelationship($social, $id, $userId) {
        $connector = $this->getSocialApi($social);
        return $connector->getUserRelationship($id, $userId);
    }

    /**
     * Service that searches for users in a social network by a name passed as a parameter
     * @param $social
     * @param string $id    user id
     * @param $name
     * @param $maxTotalResults
     * @param $numberOfPages
     * @param $nextPageUrl
     */
    public function searchUsers($social, $id, $name, $maxTotalResults, $numberOfPages, $nextPageUrl) {
        $connector = $this->getSocialApi($social);
        return $connector->searchUsers($id, $name, $maxTotalResults, $numberOfPages, $nextPageUrl);
    }

    /******************************************************************************************************
     **                                         FACEBOOK END POINTS                                      **
     ******************************************************************************************************/

    /**
     * Service that connect to social network api and request for authenticated user's photos
     * @param string $social
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportUserPhotos($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPhotos($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that connect to social network api and request for authenticated user's page photos
     * @param string $social
     * @param string $id    page id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page for pagination
     * @return mixed
     * @throws \Exception
     */
    public function exportPagePhotos($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $connector = $this->getSocialApi($social);
        return $connector->exportPagePhotos($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that connect to social network api and upload a photo to an user's album
     * @param string $social
     * @param string $id    user id
     * @param array $parameters
     *      "title"         =>      message for the media (mandatory)
     *      "album_id"      =>      album where media will be saved in
     *
     * @return mixed
     * @throws \Exception
     */
    public function uploadUserPhoto($social, $id, $parameters)
    {
        $connector = $this->getSocialApi($social);
        return $connector->uploadUserPhoto($id, $parameters);
    }

    /**
     * Service that connect to social network api and upload a photo to an user's page album
     * @param string $social
     * @param string $id    page id
     * @param array $parameters
     * FACEBOOK
     *      "title"         =>      message for the media (mandatory)
     *      "album_id"      =>      album where media will be saved in
     *
     * @return mixed
     * @throws \Exception
     */
    public function uploadPagePhoto($social, $id, $parameters)
    {
        $connector = $this->getSocialApi($social);
        return $connector->uploadPagePhoto($id, $parameters);
    }

    /**
     * Service that creates a new photo album for the user in a social network
     * @param $social
     * @param string $id    user id
     * @param $title
     * @param $caption
     * @return mixed
     * @throws \Exception
     */
    public function createUserPhotosAlbum($social, $id, $title, $caption) {
        $connector = $this->getSocialApi($social);
        return $connector->createUserPhotosAlbum($id, $title, $caption);
    }

    /**
     * Service that creates a new photo album for an user's page in a social network
     * @param $social
     * @param string $id    page id
     * @param $title
     * @param $caption
     * @return mixed
     * @throws \Exception
     */
    public function createPagePhotosAlbum($social, $id, $title, $caption) {
        $connector = $this->getSocialApi($social);
        return $connector->createPagePhotosAlbum($id, $title, $caption);
    }

    /**
     * Service that gets photos albums owned by users in a social network
     * @param $social
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportPhotosUserAlbumsList($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportPhotosUserAlbumsList($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that gets photos albums owned by users in a social network
     * @param $social
     * @param string $id    page id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportPhotosPageAlbumsList($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportPhotosPageAlbumsList($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that gets photos from an album owned by user in a social network
     * @param $social
     * @param $albumId
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportPhotosFromAlbum($social, $albumId, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportPhotosFromAlbum($albumId, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that gets photos from an user's page album in a social network
     * @param $social
     * @param string $id    page id
     * @param $albumId
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportPhotosFromPageAlbum($social, $id, $albumId, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportPhotosFromPageAlbum($id, $albumId, $maxResultsPerPage, $numberOfPages, $pageToken);
    }


    /**
     * Service that gets all pages this person administers/is an admin for
     * @param $social
     * @param string $id        user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     */
    public function exportUserPages($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPages($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that query to a social network api to get page setting
     * @param string $social
     * @param string $entity    "page"
     * @param string $id        page id
     * @return mixed
     * @throws \Exception
     */
    public function getPage($social, $id)    {
        $connector = $this->getSocialApi($social);
        return $connector->getPage($id);
    }

    /**
     * Service that connect to social network api and publish a post in an authenticated user's page
     * @param string $social
     * @param string $id    page id
     * @param array $parameters
     * FACEBOOK
     *      "message"           =>  Text of the post (mandatory)
     *      "link"              =>  URL
     *      "object_attachment" =>  Facebook ID for an existing picture in the person's photo albums to use as the thumbnail image.
     *      They must be the owner of the photo, and the photo cannot be part of a message attachment.
     * @return mixed
     * @throws \Exception
     */
    public function pagePost($social, $id, $parameters)
    {
        $connector = $this->getSocialApi($social);
        return $connector->pagePost($id, $parameters);
    }

    /******************************************************************************************************
     **                                         PINTEREST END POINTS                                      **
     ******************************************************************************************************/

    /**
     * Service that export / search the user's boards in a social network
     * @param $social
     * @param string $id    user id
     * @param $query
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportUserBoards($social, $id, $query, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserBoards($id, $query, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that query to a social network api to get settings of a board
     * @param string $social
     * @param $username
     * @param $boardname
     * @return mixed
     * @throws \Exception
     */
    public function getUserBoard($social, $entity, $username, $boardname)    {
        $connector = $this->getSocialApi($social);
        return $connector->getUserBoard($entity, $username, $boardname);
    }

    /**
     * Service that creates a new board for the user in a social network
     * @param $social
     * @param string $id    user id
     * @param $name
     * @param $description
     * @return mixed
     * @throws \Exception
     */
    public function createUserBoard($social, $id, $name, $description) {
        $connector = $this->getSocialApi($social);
        return $connector->createUserBoard($id, $name, $description);
    }

    /**
     * Service that edit an existing board in a social network
     * @param $social
     * @param $username
     * @param $boardname
     * @param $name
     * @param $description
     * @return mixed
     * @throws \Exception
     */
    public function editUserBoard($social, $username, $boardname, $name, $description) {
        $connector = $this->getSocialApi($social);
        return $connector->editUserBoard($username, $boardname, $name, $description);
    }

    /**
     * Service that delete an existing board in a social network
     * @param $social
     * @param $username
     * @param $boardname
     * @return mixed
     * @throws \Exception
     */
    public function deleteUserBoard($social, $username, $boardname) {
        $connector = $this->getSocialApi($social);
        return $connector->deleteUserBoard($username, $boardname);
    }

    /**
     * Service that export / search the user's pins in a social network
     * @param $social
     * @param string $id    user id
     * @param $query
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportUserPins($social, $id, $query, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPins($id, $query, false, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that export / search the pins user has liked in a social network
     * @param $social
     * @param string $id    user id
     * @param $query
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportUserPinsLiked($social, $id, $query, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserPins($id, $query, true, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that export pins from a board in a social network
     * @param $social
     * @param $username
     * @param $boardname
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportPinsFromUserBoard($social, $username, $boardname, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportPinsFromUserBoard($username, $boardname, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that query to a social network api to get settings of a pin
     * @param string $social
     * @param string $id    pin id
     * @return mixed
     * @throws \Exception
     */
    public function getUserPin($social, $id) {
        $connector = $this->getSocialApi($social);
        return $connector->getUserPin($id);
    }

    /**
     * Service that creates a new pin for the user in a social network
     * @param $social
     * @param string $id    user id
     * @param $note
     * @param $link
     * @param $imageType
     * @param $image
     * @param $board
     * @return mixed
     * @throws \Exception
     */
    public function createUserPin($social, $id, $username, $boardname, $note, $link, $imageType, $image) {
        $connector = $this->getSocialApi($social);
        return $connector->createUserPin($id, $username, $boardname, $note, $link, $imageType, $image);
    }

    /**
     * Service that edit an existing pin in a social network
     * @param $social
     * @param string $id    pin id
     * @param string $board
     * @param $note
     * @param $link
     * @return mixed
     * @throws \Exception
     */
    public function editUserPin($social, $id, $board, $note, $link) {
        $connector = $this->getSocialApi($social);
        return $connector->editUserPin($id, $board, $note, $link);
    }

    /**
     * Service that delete an existing pin in a social network
     * @param $social
     * @param string $id    pin id
     * @return mixed
     * @throws \Exception
     */
    public function deleteUserPin($social, $id) {
        $connector = $this->getSocialApi($social);
        return $connector->deleteUserPin($id);
    }

    /**
     * Service that export the boards that the authenticated user follows in a social network
     * @param $social
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportUserFollowingBoards($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserFollowingBoards($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that export the topics that the authenticated user follows in a social network
     * @param $social
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return mixed
     * @throws \Exception
     */
    public function exportUserFollowingInterests($social, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $connector = $this->getSocialApi($social);
        return $connector->exportUserFollowingInterests($id, $maxResultsPerPage, $numberOfPages, $pageToken);
    }

    /**
     * Service that modify the relationship between the authenticated user and the target board in a social network.
     * @param $social
     * @param string $id    user id
     * @param $boardId
     * @param $action
     * @return mixed
     * @throws \Exception
     */
    public function modifyBoardRelationship($social, $id, $boardId, $action) {
        $connector = $this->getSocialApi($social);
        return $connector->modifyBoardRelationship($id, $boardId, $action);
    }

    /******************************************************************************************************
     **                                         TWITTER END POINTS                                      **
     ******************************************************************************************************/

    /**
     * Service that gets the home timeline of the user
     * @param string $id    user id
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function getUserTimeline($social, $id) {
        $connector = $this->getSocialApi($social);
        return $connector->getUserTimeline($id);
    }

    /**
     * Service that gets a single tweet information
     * @param string $id    tweet id
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function getUserTweet($social, $id) {
        $connector = $this->getSocialApi($social);
        return $connector->getUserTweet($id);
    }

    /**
     * Service that deletes a tweet
     * @param string $id    tweet id
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function deleteUserTweet($social, $id) {
        $connector = $this->getSocialApi($social);
        return $connector->deleteUserTweet($id);
    }

    /******************************************************************************************************
     **                                         GENERAL UTILITIES                                        **
     ******************************************************************************************************/

    /**
     * General function to check url format
     * @param $redirectUrl
     * @return bool
     */
    public static function wellFormedUrl($redirectUrl) {
        if (!filter_var($redirectUrl, FILTER_VALIDATE_URL) === false) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * General function to get mime type of a file
     * @param $filename
     * @return mixed|string
     */
    public static function mime_content_type($filename) {
        $mime_types = array(

            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',
            'mp4' => 'video/mp4',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        );

        $exploded = explode('.',$filename);
        $ext = strtolower(array_pop($exploded));
        if (array_key_exists($ext, $mime_types)) {
            return $mime_types[$ext];
        }
        elseif (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME);
            $mimetype = finfo_file($finfo, $filename);
            finfo_close($finfo);
            return $mimetype;
        }
        else {
            return 'application/octet-stream';
        }
    }
}