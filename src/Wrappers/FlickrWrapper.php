<?php

namespace CloudFramework\Service\SocialNetworks\Wrappers;

use DPZ\Flickr;

class FlickrWrapper extends Flickr
{
    /**
     * @var string Flickr API key
     */
    private $consumerKey;

    /**
     * @var string Flickr API secret
     */
    private $consumerSecret;

    /**
     * @var string HTTP Method to use for API calls
     */
    private $method = 'POST';

    /**
     * @var int HTTP Response code for last call made
     */
    private $lastHttpResponseCode;

    /**
     * @var int Timeout in seconds for HTTP calls
     */
    private $httpTimeout;

    /**
     * Create a new Flickr object
     *
     * @param string $key The Flickr API key
     * @param string $secret The Flickr API secret
     * @param string $callback The callback URL for authentication
     */
    public function __construct($key, $secret = NULL, $callback = NULL)
    {
        $this->consumerKey = $key;
        $this->consumerSecret = $secret;
        parent::__construct($key, $secret, $callback);
    }

    /**
     * Upload a photo
     * @param $parameters
     * @return mixed|null
     */
    public function upload($parameters)
    {
        $requestParams = ($parameters == NULL ? array() : $parameters);

        $requestParams = array_merge($requestParams, $this->getOauthParams());

        $requestParams['oauth_token'] = $this->getOauthData(self::OAUTH_ACCESS_TOKEN);

        // We don't want to include the photo when signing the request
        // so temporarily remove it whilst we sign
        $photo = $requestParams['photo'];
        unset($requestParams['photo']);
        $this->sign(self::UPLOAD_ENDPOINT, $requestParams);
        $requestParams['photo'] = $photo;

        $xml = $this->httpRequestUpload(self::UPLOAD_ENDPOINT, $requestParams);

        $response = $this->getResponseFromXML($xml);

        return empty($response) ? NULL : $response;
    }

    /**
     * Make an HTTP request
     *
     * @param string $url
     * @param array $parameters
     * @return mixed
     */
    private function httpRequestUpload($url, $parameters)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->httpTimeout);

        if ($this->method == 'POST')
        {
            curl_setopt($curl, CURLOPT_URL, $url);
            $files = array("photo" => $parameters["photo"]);
            unset($parameters["photo"]);
            $this->curl_custom_postfields($curl, $parameters, $files);
        }
        else
        {
            // Assume GET
            curl_setopt($curl, CURLOPT_URL, "$url?" . $this->joinParameters($parameters));
        }

        $response = curl_exec($curl);
        $headers = curl_getinfo($curl);

        curl_close($curl);

        $this->lastHttpResponseCode = $headers['http_code'];

        return $response;
    }

    /**
     * Get the standard OAuth parameters
     *
     * @return array
     */
    private function getOauthParams()
    {
        $params = array (
            'oauth_nonce' => $this->makeNonce(),
            'oauth_timestamp' => time(),
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_version' => '1.0',
        );

        return $params;
    }

    /**
     * Create a nonce
     *
     * @return string
     */
    private function makeNonce()
    {
        // Create a string that will be unique for this app and this user at this time
        $reasonablyDistinctiveString = implode(':',
            array(
                $this->consumerKey,
                $this->getOauthData(self::USER_NSID),
                microtime()
            )
        );

        return md5($reasonablyDistinctiveString);
    }

    /**
     * Join an array of parameters together into a URL-encoded string
     *
     * @param array $parameters
     * @return string
     */
    private function joinParameters($parameters)
    {
        $keys = array_keys($parameters);
        sort($keys, SORT_STRING);
        $keyValuePairs = array();
        foreach ($keys as $k)
        {
            array_push($keyValuePairs, rawurlencode($k) . "=" . rawurlencode($parameters[$k]));
        }

        return implode("&", $keyValuePairs);
    }

    /**
     * Get the base string for creating an OAuth signature
     *
     * @param string $method
     * @param string $url
     * @param array $parameters
     * @return string
     */
    private function getBaseString($method, $url, $parameters)
    {
        $components = array(
            rawurlencode($method),
            rawurlencode($url),
            rawurlencode($this->joinParameters($parameters))
        );

        $baseString = implode("&", $components);

        return $baseString;
    }

    /**
     * Sign an array of parameters with an OAuth signature
     *
     * @param string $url
     * @param array $parameters
     */
    private function sign($url, &$parameters)
    {
        $baseString = $this->getBaseString($this->method, $url, $parameters);
        $signature  = $this->getSignature($baseString);
        $parameters['oauth_signature'] = $signature;
    }

    /**
     * Calculate the signature for a string
     *
     * @param string $string
     * @return string
     */
    private function getSignature($string)
    {
        $keyPart1 = $this->consumerSecret;
        $keyPart2 = $this->getOauthData(self::OAUTH_ACCESS_TOKEN_SECRET);
        if (empty($keyPart2))
        {
            $keyPart2 = $this->getOauthData(self::OAUTH_REQUEST_TOKEN_SECRET);
        }
        if (empty($keyPart2))
        {
            $keyPart2 = '';
        }

        $key = "$keyPart1&$keyPart2";

        return base64_encode(hash_hmac('sha1', $string, $key, true));
    }

    /**
     * Get the response structure from an XML response.
     * Annoyingly, upload and replace returns XML rather than serialised PHP.
     * The responses are pretty simple, so rather than depend on an XML parser we'll fake it and
     * decode using regexps
     * @param $xml
     * @return mixed
     */
    private function getResponseFromXML($xml)
    {
        $rsp = array();
        $stat = 'fail';
        $matches = array();
        preg_match('/<rsp stat="(ok|fail)">/s', $xml, $matches);
        if (count($matches) > 0)
        {
            $stat = $matches[1];
        }
        if ($stat == 'ok')
        {
            // do this in individual steps in case the order of the attributes ever changes
            $rsp['stat'] = $stat;
            $photoid = array();
            $matches = array();
            preg_match('/<photoid.*>(\d+)<\/photoid>/s', $xml, $matches);
            if (count($matches) > 0)
            {
                $photoid['_content'] = $matches[1];
            }
            $matches = array();
            preg_match('/<photoid.* secret="(\w+)".*>/s', $xml, $matches);
            if (count($matches) > 0)
            {
                $photoid['secret'] = $matches[1];
            }
            $matches = array();
            preg_match('/<photoid.* originalsecret="(\w+)".*>/s', $xml, $matches);
            if (count($matches) > 0)
            {
                $photoid['originalsecret'] = $matches[1];
            }
            $rsp['photoid'] = $photoid;
        }
        else
        {
            $rsp['stat'] = 'fail';
            $err = array();
            $matches = array();
            preg_match('/<err.* code="([^"]*)".*>/s', $xml, $matches);
            if (count($matches) > 0)
            {
                $err['code'] = $matches[1];
            }
            $matches = array();
            preg_match('/<err.* msg="([^"]*)".*>/s', $xml, $matches);
            if (count($matches) > 0)
            {
                $err['msg'] = $matches[1];
            }
            $rsp['err'] = $err;
        }
        return $rsp;
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
}
