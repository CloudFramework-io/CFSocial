<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use \BW\Vkontakte;
use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;

class VkontakteApi extends Singleton implements SocialNetworkInterface {

    const ID = "vkontakte";

    // Vkontakte client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    /**
     * Set Vkontakte Api keys
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

        $this->client = new VKontakte([
            "client_id" => $this->clientId,
            "client_secret" => $this->clientSecret,
            "scope" => $this->clientScope
        ]);
    }

    /**
     * Service that requests authorization to Vkontakte making up the Vkontakte login URL
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

        $this->client->setRedirectUri($redirectUrl);

        $authUrl = $this->client->getLoginUrl();

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
     * Authentication service from Vkontakte sign in request
     * @param string $code
     * @param string $verifier
     * @param string $redirectUrl
     * @return array
     * @throws ConnectorConfigException
     */
    public function authorize($code, $verifier, $redirectUrl)
    {
        if ((null === $code) || ("" === $code)) {
            throw new ConnectorConfigException("'code' parameter is required");
        }

        $this->client->setRedirectUri($redirectUrl);

        $this->client->authenticate($code);

        $vkontakteCredentials = array("access_token" => $this->client->getAccessToken());

        return $vkontakteCredentials;
    }

    /**
     * Method that injects the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->client->setAccessToken($credentials);
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
            return $this->getProfile($credentials["user_id"]);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set'");
        }
    }

    /**
     * Service that queries to Vkontakte Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id = null)
    {
        $response = $this->client->api('users.get', [
            "user_ids" => $id,
            'fields' => [
                'photo_50',
                'city',
                'sex'
            ]
        ]);

        if (isset($response["error"])) {
            throw new ConnectorServiceException(
                $response["error"]["error_msg"],
                $response["error"]["error_code"]
            );
        }

        return $response[0];
    }

    /**
     * Service that queries to Vkontakte API for user photos
     * @param string $id    user id
     * @param integer $maxResultsPerPage maximum elements per page
     * @param integer $numberOfPages number of pages
     * @param string $pageToken Indicates a specific page
     * @return array
     * @throws ConnectorServiceException
     */
    public function exportUserPhotos($id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        $response = $this->client->api("photos.get", [
            "owner_id" => $id,
            "album_id" => "wall",
            "rev" => 0,
            "extended" => 1
        ]);

        if (isset($response["error"])) {
            throw new ConnectorServiceException(
                $response["error"]["error_msg"],
                $response["error"]["error_code"]
            );
        }

        $response["photos"] = $response["items"];
        unset($response["items"]);

        return($response);
    }

    /**
     * Service that uploads a photo to Vkontakte user's wall
     * Further info: https://vk.com/dev/upload_files?f=Uploading%20Photos%20on%20User%20Wall
     * @param string $id    user id
     * @param $parameters
     *      "media_type"    =>      "url" or "path"
     *      "value"         =>      url or path of the image file
     * @return array
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function uploadUserPhoto($id, $parameters) {
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
            $urlparsed = parse_url($parameters["value"]);
            $extension = pathinfo($urlparsed["path"], PATHINFO_EXTENSION);

            rename($tempMedia, $tempMedia . "." . $extension);

            $parameters["value"] = $tempMedia . "." . $extension;
        }

        $wallUploadServer = $this->client->api("photos.getWallUploadServer");

        if (isset($wallUploadServer["error"])) {
            throw new ConnectorServiceException(
                $wallUploadServer["error"]["error_msg"],
                $wallUploadServer["error"]["error_code"]
            );
        }

        $uploadedPhotoData = $this->curlPost($wallUploadServer["upload_url"], [
                "photo" => $parameters["value"]
        ]);

        $response = $this->client->api("photos.saveWallPhoto", [
            "user_id" => $id,
            "server" => $uploadedPhotoData["server"],
            "photo" => $uploadedPhotoData["photo"],
            "hash" => $uploadedPhotoData["hash"]
        ]);

        if (isset($response["error"])) {
            throw new ConnectorServiceException(
                $response["error"]["error_msg"],
                $response["error"]["error_code"]
            );
        }

        return $response;
    }

    /**
     * Adds a new post on a user wall
     * @param $id
     * @param array $parameters
     * @return mixed
     * @throws ConnectorServiceException
     */
    public function post($id, array $parameters)
    {
        $params = array();
        $params["owner_id"] = $id;
        $params["message"] = $parameters["content"];
        if (isset($parameters["attachment"])) {
            $attachments = "photo".$id."_".$parameters["attachment"];
            if (isset($parameters["link"])) {
                $attachments .= "," . $parameters["link"];
            }
        }

        if (isset($attachment)) {
            $params["attachments"] = $attachments;
        }


        $response = $this->client->api("wall.post", $params);

        if (isset($response["error"])) {
            throw new ConnectorServiceException(
                $response["error"]["error_msg"],
                $response["error"]["error_code"]
            );
        }

        return $response;
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
        $this->curl_custom_postfields($ch, array(), $fields);
        $data = curl_exec($ch);

        if (!$data) {
            throw new \Exception("Error calling service: ".curl_error($ch), curl_errno($ch));
        }

        curl_close($ch);

        return json_decode($data, true);
    }

    /**
     * For safe multipart POST request for PHP5.3 ~ PHP 5.4.
     *
     * @param resource $ch cURL resource
     * @param array $assoc "name => value"
     * @param array $files "name => path"
     * @return bool
     */
    function curl_custom_postfields($ch, array $assoc = array(), array $files = array()) {

        // invalid characters for "name" and "filename"
        static $disallow = array("\0", "\"", "\r", "\n");

        // build normal parameters
        foreach ($assoc as $k => $v) {
            $k = str_replace($disallow, "_", $k);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"",
                "",
                filter_var($v),
            ));
        }

        // build file parameters
        foreach ($files as $k => $v) {
            switch (true) {
                case false === $v = realpath(filter_var($v)):
                case !is_file($v):
                case !is_readable($v):
                    continue; // or return false, throw new InvalidArgumentException
            }
            $data = file_get_contents($v);
            $v = call_user_func("end", explode(DIRECTORY_SEPARATOR, $v));
            $k = str_replace($disallow, "_", $k);
            $v = str_replace($disallow, "_", $v);
            $body[] = implode("\r\n", array(
                "Content-Disposition: form-data; name=\"{$k}\"; filename=\"{$v}\"",
                "Content-Type: application/octet-stream",
                "",
                $data,
            ));
        }

        // generate safe boundary
        do {
            $boundary = "---------------------" . md5(mt_rand() . microtime());
        } while (preg_grep("/{$boundary}/", $body));

        // add boundary for each parameters
        array_walk($body, function (&$part) use ($boundary) {
            $part = "--{$boundary}\r\n{$part}";
        });

        // add final boundary
        $body[] = "--{$boundary}--";
        $body[] = "";

        // set options
        return @curl_setopt_array($ch, array(
            CURLOPT_POST       => true,
            CURLOPT_POSTFIELDS => implode("\r\n", $body),
            CURLOPT_HTTPHEADER => array(
                //"Expect: 100-continue",
                "Content-Type: multipart/form-data; boundary={$boundary}", // change Content-Type
            ),
            CURLOPT_RETURNTRANSFER =>true
        ));
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
}