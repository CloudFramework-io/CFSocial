<?php
namespace CloudFramework\Service\SocialNetworks\Interfaces;

/**
 * Interface EcommerceInterface
 * @package CloudFramework\Service\SocialNetworks\Interfaces
 */
interface EcommerceInterface {

    function setApiKeys($clientId, $clientSecret, $clientScope, $shop);
    function requestAuthorization($redirectUrl);
    function authorize($code);
    function setAccessToken(array $credentials);
    function checkCredentials(array $credentials);
    function getProfile($id);
    function getShop();
    function getShopShippingZones();
    function exportProducts($maxResultsPerPage, $pageNumber, $collectionId);
    function exportCollections($maxResultsPerPage, $pageNumber);
    function createProduct(array $parameters);
}