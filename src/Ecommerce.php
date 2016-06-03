<?php
namespace CloudFramework\Service\SocialNetworks;

use CloudFramework\Patterns\Singleton;

/**
 * Class Ecommerce
 * @author Salvador Castro <sc@bloombees.com>
 */
class Ecommerce extends Singleton
{
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
     * Method that initialize an ecommerce platform api instance to use
     * @param $ecommerce
     * @return \CloudFramework\Service\SocialNetworks\Interfaces\EcommerceInterface
     * @throws \Exception
     */
    public function getEcommerceApi($ecommerce)
    {
        $ecommerce = ucfirst($ecommerce);
        $ecommerceNetworkClass = "CloudFramework\\Service\\SocialNetworks\\Connectors\\Ecommerce\\{$ecommerce}Api";
        if (class_exists($ecommerceNetworkClass)) {
            try {
                return $api = $ecommerceNetworkClass::getInstance();
            } catch (\Exception $e) {
                throw $e;
            }
        } else {
            throw new \Exception("Ecommerce Platform Requested not exists", 501);
        }
    }

    /**
     * Service that set the api keys for ecommerce platform
     * @param $ecommerce
     * @param $clientId
     * @param $clientSecret
     * @param $accessToken
     * @param $shop
     * @return mixed
     * @throws \Exception
     */
    public function setApiKeys($ecommerce, $clientId, $clientSecret, $accessToken, $shop)
    {
        $api = $this->getEcommerceApi($ecommerce);
        return $api->setApiKeys($clientId, $clientSecret, $accessToken, $shop);
    }

    /**
     * Service that set the access token for ecommerce platform
     * @param $ecommerce
     * @param array $credentials
     * @return mixed
     * @throws \Exception
     */
    public function setAccessToken($ecommerce, array $credentials) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->setAccessToken($credentials);
    }

    /**
     * Service to request authorization to the ecommerce platform
     * @param string $ecommerce
     * @param string $redirectUrl
     * @param boolean $force
     * @return mixed
     * @throws \Exception
     */
    public function requestAuthorization($ecommerce, $redirectUrl, $force = false)
    {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->requestAuthorization($redirectUrl);
    }

    /**
     * Service that authorize a user in the ecommerce platform.
     * (This method receives the callback from the ecommerce platform after login)
     * @param string $ecommerce
     * @param string $code
     * @return mixed
     * @throws \Exception
     */
    public function confirmAuthorization($ecommerce, $code)
    {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->authorize($code);
    }

    /**
     * Service that check if session user's credentials are authorized and not expired / revoked
     * @param $ecommerce
     * @param $credentials
     * @return mixed
     * @throws \Exception
     */
    public function checkCredentials($ecommerce, $credentials) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->checkCredentials($credentials);
    }

    /**
     * Service that query to an ecommerce platform api to get user profile
     * @param string $ecommerce
     * @param string $id user id
     * @return mixed
     * @throws \Exception
     */
    public function getProfile($ecommerce, $id) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->getProfile($id);
    }

    /**
     * Service that query to an ecommerce platform api to get shop information
     * @param string $ecommerce
     * @return mixed
     * @throws \Exception
     */
    public function getShop($ecommerce) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->getShop();
    }

    /**
     * Service that query to an ecommerce platform api to get shop shipping zones information
     * @param string $ecommerce
     * @return mixed
     * @throws \Exception
     */
    public function getShopShippingZones($ecommerce) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->getShopShippingZones();
    }

    /**
     * Service that get a paginated list of products from the shop
     * @param $ecommerce
     * @param $maxResultsPerPage
     * @param $pageNumber
     * @param $collectionId
     * @return mixed
     * @throws \Exception
     */
    public function exportProducts($ecommerce, $maxResultsPerPage, $pageNumber, $collectionId) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->exportProducts($maxResultsPerPage, $pageNumber, $collectionId);
    }

    /**
     * Service that get a paginated list of collections from the shop
     * @param $ecommerce
     * @param $maxResultsPerPage
     * @param $pageNumber
     * @return mixed
     * @throws \Exception
     */
    public function exportCollections($ecommerce, $maxResultsPerPage, $pageNumber) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->exportCollections($maxResultsPerPage, $pageNumber);
    }

    /**
     * Service that get an specific collection from the shop
     * @param $ecommerce
     * @param $idCollection
     * @return mixed
     * @throws \Exception
     */
    public function getCollection($ecommerce, $idCollection) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->getCollection($idCollection);
    }

    /**
     * Service that creates a new product in the shop
     * @param $ecommerce
     * @param array $parameters
     * @return mixed
     * @throws \Exception
     */
    public function createProduct($ecommerce, array $parameters) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->createProduct($parameters);
    }

    /**
     * Service that get all products from the shop
     * @param $ecommerce
     * @return mixed
     * @throws \Exception
     */
    public function exportAllProducts($ecommerce) {
        $connector = $this->getEcommerceApi($ecommerce);
        return $connector->exportAllProducts();
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
}