<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use Tumblr\API\Client;
use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;

class TumblrApi extends Singleton implements SocialNetworkInterface {

    const ID = "tumblr";
    const TUMBLR_OAUTH_BASE_URL = "https://www.tumblr.com/";
    const TUMBLR_API_BASE_URL = "https://api.tumblr.com/";
    const TUMBLR_OAUTH_URL = "https://www.tumblr.com/oauth/authorize";

    // Post types
    const TUMBLR_PHOTO_POST_TYPE = "photo";

    // Tumblr client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    /**
     * Set Tumblr Api keys
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

        if ((null === $clientScope) || (!is_array($clientScope)) /*|| (count($clientScope) == 0)*/) {
            throw new ConnectorConfigException("'clientScope' parameter is required");
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        if (isset($_SESSION["oauth_token"])) {
            $this->client = new Client($this->clientId, $this->clientSecret,
                    $_SESSION["oauth_token"], $_SESSION["oauth_token_secret"]);
            unset($_SESSION["oauth_token"]);
            unset($_SESSION["oauth_token_secret"]);
        } else {
            $this->client = new Client($this->clientId, $this->clientSecret);
        }

        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_OAUTH_BASE_URL);
    }

    /**
     * Service that requests authorization to Tumblr making up the Tumblr login URL
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

        $response = $this->client->getRequestHandler()->request('POST', "oauth/request_token", $parameters);

        // get the oauth_token
        $out = $result = $response->body;
        $data = array();
        parse_str($out, $data);

        $_SESSION["oauth_token"] = $data["oauth_token"];
        $_SESSION["oauth_token_secret"] = $data["oauth_token_secret"];

        $parameters = array(
            "oauth_token" => $data["oauth_token"],
            "oauth_callback" => $redirectUrl
        );

        $authUrl = self::TUMBLR_OAUTH_URL . "?" . http_build_query($parameters);

        // Authentication request
        return $authUrl;
    }

    /**
     * Authentication service from Tumblr sign in request
     * @param string $code
     * @param string $verifier
     * @param string $redirectUrl
     * @return array
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
            );

            $response = $this->client->getRequestHandler()->request('POST', "oauth/access_token", $parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $out = $result = $response->body;
        $data = array();
        parse_str($out, $data);

        $tumblrCredentials = array(
            "access_token" => $data["oauth_token"],
            "access_token_secret" => $data["oauth_token_secret"],
        );

        return $tumblrCredentials;
    }

    /**
     * Method that injects the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->client->setToken($credentials["access_token"], $credentials["access_token_secret"]);
    }

    /**
     * Service that checks if credentials are valid
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
     * Service that queries to Tumblr Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id = null)
    {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getUserInfo();
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $userArray = json_decode(json_encode($response), true);

        $profile = [
            "user_id" => null,
            "name" => $userArray["user"]["name"],
            "first_name" => null,
            "last_name" => null,
            "email" => null,
            "photo" => null,
            "locale" => null,
            "url" => $userArray["user"]["blogs"][0]["url"],
            "raw" => $userArray
        ];

        return $profile;
    }

    /**
     * Service that creates a post in Tumblr
     * @param $id
     * @param array $parameters
     *      "type". text, photo, quote, link, chat, audio, video
     *      "blogname". Name of the blog where post will be published.
     *      "state". published (default), draft, queue, private
     *      "content". The user-supplied caption, HTML allowed (not required)
     *      "link". The URL the photo will link to when you click through.
     *       "attachment_type". Allowed values are (required):
     *          "source": The photo source URL.
     *          "data": One or more image files (submit multiple times to create a slide show).
     *                  The format is Array (URL-encoded binary contents). Limit: 10MB.
     *          "data64": The contents of an image file encoded using base64.
     *       "attachment": url, data or base64 of the photo (required)
     * @return array
     * @throws ConnectorServiceException
     */
    public function post($id, array $parameters)
    {
        if(!array_key_exists('type', $parameters)) {
            throw new ConnectorServiceException('Parameter \'type\' is required to do a post');
        }
        if(!array_key_exists('blogname', $parameters)) {
            throw new ConnectorServiceException('Parameter \'blogname\' is required to do a post');
        }

        switch($parameters["type"]) {
            case self::TUMBLR_PHOTO_POST_TYPE:
                $post = $this->createUserPhotoPost($parameters['blogname'], $parameters['attachment_type'],
                                                   $parameters['attachment'], $parameters['content'],
                                                   $parameters['link'], $parameters['state']);
                break;
        }

        $post = json_decode(json_encode($post), true);
        return array("post_id" => $post["id"], "url" => "https://" . $parameters['blogname'] . ".tumblr.com/post/" . $post["id"]);
    }

    /**
     * Service that creates a new post in one of the user's blogs in Tumblr
     * @param $blogName
     * @param $attachmentType
     * @param $attachment
     * @param null $caption
     * @param null $link
     * @param null $state
     * @return mixed
     * @throws ConnectorServiceException
     */
    private function createUserPhotoPost($blogName, $attachmentType, $attachment,
                                         $caption = null, $link = null, $state = null) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $parameters = array(
                "type" => self::TUMBLR_PHOTO_POST_TYPE,
                $attachmentType => $attachment
            );

            if(null !== $caption) {
                $parameters["caption"] = $caption;
            }

            if (null !== $link) {
                $parameters["link"] = $link;
            }

            if (null !== $state) {
                $parameters["state"] = $state;
            }

            $post = $this->client->createPost($blogName, $parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error creating photo post: ' . $e->getMessage(), $e->getCode());
        }

        return $post;
    }

    /**
     * Service that gets the authenticated user's photo posts
     * @param $blogName
     * @param $limit Maximum number of elements returned
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function exportUserPhotoPosts($blogName, $limit) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getBlogPosts($blogName, array(
                "limit" => $limit,
                "type" => self::TUMBLR_PHOTO_POST_TYPE
            ));
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $data = json_decode(json_encode($response), true);

        return array(
            "posts" => $data["posts"],
            "total" => $data["total_posts"]
        );
    }

    /**
     * Service that gets the authenticated user's dashboard posts
     * (posts from user followed by authenticated user)
     * @param $limit Maximum number of elements returned
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function exportUserDashboardPosts($limit) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getDashboardPosts(array("limit" => $limit));
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $data = json_decode(json_encode($response), true);

        return array(
            "posts" => $data["posts"]
        );
    }

    /**
     * Service that gets the authenticated user's liked posts
     * @param $limit Maximum number of elements returned
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function exportUserLikedPosts($limit) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getLikedPosts(array("limit" => $limit));
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array(
            "posts" => $response->liked_posts,
            "total" => $response->liked_count
        );
    }

    /**
     * Service that gets the authenticated user's followed blogs
     * @param $limit Maximum number of elements returned
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function exportUserFollowedBlogs($limit) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getFollowedBlogs(array("limit" => $limit));
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array(
            "blogs" => $response->blogs,
            "total" => $response->total_blogs
        );
    }

    /**
     * Service that modifies the relationship between the authenticated user and a blog.
     * @param $blogName
     * @param $action follow / unfollow
     * @return array
     * @throws ConnectorServiceException
     */
    public function modifyBlogRelationship($blogName, $action) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->$action($blogName);
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array("blog"=>$response->blog);
    }

    /**
     * Service that modifies the relationship between the authenticated user and a post.
     * @param $postId
     * @param $reblogKey The reblog key for the post id
     * @param $action like / unlike
     * @return array
     * @throws ConnectorServiceException
     */
    public function modifyPostRelationship($postId, $reblogKey, $action) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->$action($postId, $reblogKey);
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array("status"=>"success");
    }

    /**
     * Service that gets the information about a blog
     * @param $blogName
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function getUserBlog($blogName) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getBlogInfo($blogName);
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $data = json_decode(json_encode($response), true);

        return array("blog" => $data["blog"]);
    }

    /**
     * Service that gets the avatar ofa blog
     * @param $blogName
     * @param $size. The size of the avatar (square, one value for both length and width).
     *               Must be one of the values: 16, 24, 30, 40, 48, 64, 96, 128, 512
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function getUserBlogAvatar($blogName, $size) {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getBlogAvatar($blogName, $size);
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array("avatar" => $response);
    }

    /**
     * Service that queries to Tumblr api to get the publicly exposed likes from a blog
     * @param $blogName
     * @param $limit Maximum number of elements returned
     * @return mixed
     * @throws \Exception
     */
    public function getUserBlogLikes($blogName, $limit)    {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getBlogLikes($blogName, array("limit" => $limit));
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array(
            "posts" => $response->liked_posts,
            "total" => $response->liked_count,
        );
    }

    /**
     * Service that queries to Tumblr api to get followers from a blog
     * @param string $social
     * @param $blogName
     * @param $limit Maximum number of elements returned
     * @return mixed
     * @throws \Exception
     */
    public function getUserBlogFollowers($blogName, $limit)    {
        $this->client->getRequestHandler()->setBaseUrl(self::TUMBLR_API_BASE_URL);

        try {
            $response = $this->client->getBlogFollowers($blogName, array("limit" => $limit));
        } catch (\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array(
            "users" => $response->users,
            "total" => $response->total_users
        );
    }

    /**
     * Method that checks credentials are present and valid
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