<?php
namespace CloudFramework\Service\SocialNetworks\Connectors\Ecommerce;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Connectors\Ecommerce\Libraries\ShopifyClient;
use CloudFramework\Service\SocialNetworks\Dtos\Ecommerce\ProductDTO;
use CloudFramework\Service\SocialNetworks\Dtos\Ecommerce\ProductImageDTO;
use CloudFramework\Service\SocialNetworks\Dtos\Ecommerce\ProductVariantDTO;
use CloudFramework\Service\SocialNetworks\Ecommerce;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\EcommerceInterface;

class ShopifyApi extends Singleton implements EcommerceInterface {

    const ID = "shopify";
    const SHOPIFY_ENDPOINT_CURRENT_USER_URL = "/admin/users/current.json";
    const SHOPIFY_ENDPOINT_CURRENT_SHOP_URL = "/admin/shop.json";
    const SHOPIFY_ENDPOINT_CURRENT_SHOP_SHIPPING_URL = "/admin/shipping_zones.json";
    const SHOPIFY_ENDPOINT_COLLECTIONS_URL = "/admin/custom_collections.json";
    const SHOPIFY_ENDPOINT_COLLECTIONS_COUNT_URL = "/admin/custom_collections/count.json";
    const SHOPIFY_ENDPOINT_COLLECTIONS_GET_URL = "/admin/custom_collections/";
    const SHOPIFY_ENDPOINT_PRODUCTS_URL = "/admin/products.json";
    const SHOPIFY_ENDPOINT_PRODUCTS_COUNT_URL = "/admin/products/count.json";

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
     * @param $shopDomain
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
     */
    public function getProfile($id = null)
    {
        $response = $this->client->call('GET', self::SHOPIFY_ENDPOINT_CURRENT_USER_URL);

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
     * Service that queries to Shopify Api to get shop information
     * @return array
     */
    public function getShop()
    {
        return $this->client->call('GET', self::SHOPIFY_ENDPOINT_CURRENT_SHOP_URL);
    }

    /**
     * Service that queries to Shopify Api to get shipping zones information in the shop
     * @return array
     */
    public function getShopShippingZones()
    {
        return $this->client->call('GET', self::SHOPIFY_ENDPOINT_CURRENT_SHOP_SHIPPING_URL);
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

        $products = $this->client->call('GET', self::SHOPIFY_ENDPOINT_PRODUCTS_URL, $params);
        $total = $this->client->call('GET', self::SHOPIFY_ENDPOINT_PRODUCTS_COUNT_URL);
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

        $collections = $this->client->call('GET', self::SHOPIFY_ENDPOINT_COLLECTIONS_URL, $params);
        $total = $this->client->call('GET', self::SHOPIFY_ENDPOINT_COLLECTIONS_COUNT_URL);
        return array(
            'collections' => $collections,
            "totalCount" => $total
        );
    }

    /**
     * Service that queries to Shopify Api to get a custom collection information
     * @param $idCollection
     * @return mixed
     */
    public function getCollection($idCollection)
    {
        return $this->client->call('GET', self::SHOPIFY_ENDPOINT_COLLECTIONS_GET_URL . $idCollection . ".json");
    }

    /**
     * Service that creates a new product in the shop
     * @param array $parameters
     * @return mixed
     */
    public function createProduct(array $parameters) {
        $product = array("product" => $parameters);
        $response = $this->client->call('POST', self::SHOPIFY_ENDPOINT_PRODUCTS_URL, $product);

        return $response;
    }

    /**
     * Service to import products to Shopify
     * @param array of ProductDTO $products
     * @return array
     * @see ProductDTO
     */
    public function importProducts(array $products) {
        $response = [];
        foreach($products as $product) {
            $productResponse = $this->createProduct($product->toArray());
            $response[] = $productResponse;
        }
        return $response;
    }

    /**
     * Service to export all products from Shopify
     * @param array $products
     * @return array
     * @see ProductDTO
     */
    public function exportAllProducts() {
        $maxResultsPerPage = 250; // Maximum allowed by Shopify
        $pageNumber = 1;
        $products = $this->exportProducts($maxResultsPerPage, $pageNumber);
        $productsArr = [];
        while (count($products["products"]) > 0) {
            foreach($products["products"] as $product) {
                $productObj = new ProductDTO();
                $productObj->setRaw($product);
                $productObj->setId($product["id"]);
                $productObj->setTitle($product["title"]);
                $productObj->setBodyHtml($product["body_html"]);
                $productObj->setVendor($product["vendor"]);
                $productObj->setProductType($product["product_type"]);
                $productObj->setTags(stripslashes($product["tags"]));

                if ((isset($product["images"])) && (count($product["images"]) > 0)) {
                    $images = [];
                    foreach($product["images"] as $image) {
                        $imageObj = new ProductImageDTO();
                        $imageObj->setType("src");
                        $imageObj->setImage($image["src"]);
                        $images[] = $imageObj;
                    }
                    $productObj->setImages($images);
                }

                if ((isset($product["variants"])) && (count($product["variants"]) > 0)) {
                    $variants = [];
                    foreach($product["variants"] as $variant) {
                        $options = [];
                        $optionId = 1;
                        $variantObj = new ProductVariantDTO();
                        $variantObj->setPrice($variant["price"]);
                        $variantObj->setSku($variant["sku"]);
                        $variantObj->setBarcode($variant["barcode"]);
                        $variantObj->setWeight($variant["weight"]);
                        $variantObj->setWeightUnit($variant["weight_unit"]);
                        $variantObj->setVariantPosition($variant["position"]);
                        $variantProperties = array_keys($variant);
                        foreach($variantProperties as $variantProperty) {
                            if ((false !== strpos($variantProperty, "option")) && (null !== $variant["option".$optionId])) {
                                $options[] = $variant["option".$optionId];
                                $optionId++;
                            }
                        }

                        if (count($options) > 0) {
                            $variantObj->setOptions($options);
                        }

                        $variants[] = $variantObj;
                    }
                    $productObj->setVariants($variants);
                }

                if ((isset($product["options"])) && (count($product["options"]) > 0)) {
                    $options = [];
                    foreach($product["options"] as $option) {
                        $options[] = $option["name"];
                    }
                    $productObj->setOptions($options);
                }

                $productObj->setPublished((null === $product["published_at"])?false:true);

                $productsArr[] = $productObj;
            }

            $pageNumber++;
            $products = $this->exportProducts($maxResultsPerPage, $pageNumber);
        }

        return $productsArr;
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