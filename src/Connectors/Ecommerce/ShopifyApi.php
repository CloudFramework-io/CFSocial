<?php
namespace CloudFramework\Service\SocialNetworks\Connectors\Ecommerce;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Connectors\Ecommerce\Libraries\ShopifyClient;
use CloudFramework\Service\SocialNetworks\Ecommerce;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\EcommerceInterface;

class ShopifyApi extends Singleton implements EcommerceInterface {

    const ID = "shopify";
    const SHOPIFY_ENDPOINT_CURRENT_USER_URL = "/admin/users/current.json";
    const SHOPIFY_ENDPOINT_COLLECTIONS = "/admin/custom_collections.json";
    const SHOPIFY_ENDPOINT_COLLECTIONS_COUNT = "/admin/custom_collections/count.json";
    const SHOPIFY_ENDPOINT_PRODUCTS = "/admin/products.json";
    const SHOPIFY_ENDPOINT_PRODUCTS_COUNT = "/admin/products/count.json";

    // Shopify client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    // Shopify shop domain
    private $shopDomain;

    /**
     * Set Shopify Api keys
     * @param $clientId
     * @param $clientSecret
     * @param $clientScope
     * @param $shop
     * @throws ConnectorConfigException
     */
    public function setApiKeys($clientId, $clientSecret, $clientScope, $shopDomain ) {
        if ((null === $clientId) || ("" === $clientId)) {
            throw new ConnectorConfigException("'clientId' parameter is required");
        }

        if ((null === $clientSecret) || ("" === $clientSecret)) {
            throw new ConnectorConfigException("'clientSecret' parameter is required");
        }

        if ((null === $clientScope) || (!is_array($clientScope)) || (count($clientScope) == 0)) {
            throw new ConnectorConfigException("'clientScope' parameter is required");
        }

        if ((null === $shopDomain) || ("" === $shopDomain)) {
            throw new ConnectorConfigException("'shopDomain' parameter is required");
        }

        $this->shopDomain = $shopDomain;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new ShopifyClient($shopDomain, "", $this->clientId, $this->clientSecret);
    }

    /**
     * Service that requests authorization to Shopify making up the Shopify login URL
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
            if (!Ecommerce::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed");
            }
        }

        $authUrl = $this->client->getAuthorizeUrl(implode(",",$this->clientScope), $redirectUrl);

        if ((null === $authUrl) || (empty($authUrl))) {
            throw new ConnectorConfigException("'authUrl' parameter is required");
        } else {
            if (!Ecommerce::wellFormedUrl($authUrl)) {
                throw new MalformedUrlException("'authUrl' is malformed");
            }
        }

        // Authentication request
        return $authUrl;
    }

    /**
     * Authentication service from Shopify sign in request
     * @param string $code
     * @return array
     * @throws ConnectorConfigException
     */
    public function authorize($code)
    {
        if ((null === $code) || ("" === $code)) {
            throw new ConnectorConfigException("'code' parameter is required");
        }

        $accessToken = $this->client->getAccessToken($code);

        $shopifyCredentials = array("access_token" => $accessToken);

        return $shopifyCredentials;
    }

    /**
     * Method that injects the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->client = new ShopifyClient($this->shopDomain, $credentials["access_token"], $this->clientId, $this->clientSecret);
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
            return $this->getProfile();
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set'");
        }
    }

    /**
     * Service that queries to Shopify Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id = null)
    {
        $response = $this->client->call(self::SHOPIFY_ENDPOINT_CURRENT_USER_URL);

        $profile = [
            "user_id" => $response["user"]["id"],
            "name" => $response["user"]["screen_name"],
            "first_name" => $response["user"]["first_name"],
            "last_name" => $response["user"]["last_name"],
            "email" => $response["user"]["email"],
            "photo" => null,
            "locale" => null,
            "url" => $response["user"]["url"],
            "raw" => $response["user"]
        ];

        return $profile;
    }

    /**
     * Service that get a paginated list of products from the Shopify's shop
     * @param $maxResultsPerPage
     * @param $pageNumber
     * @param $collectionId. Null if all products are desired.
     * @return array
     * @throws ConnectorConfigException
     */
    public function exportProducts($maxResultsPerPage, $pageNumber, $collectionId = null) {
        $this->checkPagination($maxResultsPerPage, $pageNumber);
        $params = array(
            "limit" => $maxResultsPerPage,
            "page" => $pageNumber
        );
        if (null !== $collectionId) {
            $params["collection_id"] = $collectionId;
        }

        $products = $this->client->call('GET', self::SHOPIFY_ENDPOINT_PRODUCTS, $params);
        $total = $this->client->call('GET', self::SHOPIFY_ENDPOINT_PRODUCTS_COUNT);
        return array(
            'products' => $products,
            "totalCount" => $total
        );
    }

    /**
     * Service that get a paginated list of collections from the Shopify's shop
     * @param $maxResultsPerPage
     * @param $pageNumber
     * @return array
     * @throws ConnectorConfigException
     */
    public function exportCollections($maxResultsPerPage, $pageNumber) {
        $this->checkPagination($maxResultsPerPage, $pageNumber);
        $params = array(
            "limit" => $maxResultsPerPage,
            "page" => $pageNumber
        );

        $collections = $this->client->call('GET', self::SHOPIFY_ENDPOINT_COLLECTIONS, $params);
        $total = $this->client->call('GET', self::SHOPIFY_ENDPOINT_COLLECTIONS_COUNT);
        return array(
            'collections' => $collections,
            "totalCount" => $total
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

        if ((!isset($credentials["access_token"])) || (null === $credentials["access_token"]) || ("" === $credentials["access_token"])) {
            throw new ConnectorConfigException("'access_token' parameter is required");
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