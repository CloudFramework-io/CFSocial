<?php
namespace CloudFramework\Service\SocialNetworks\Interfaces;

/**
 * Interface SocialNetworksInterface
 * @package CloudFramework\Service\SocialNetworks\Interfaces
 */
interface SocialNetworkInterface {

    function setApiKeys($clientId, $clientSecret, $clientScope);
    function requestAuthorization($redirectUrl);
    function authorize($code, $verifier, $redirectUrl);
    function setAccessToken(array $credentials);
    function checkCredentials(array $credentials);
    function getProfile($id);
    function post($id, array $parameters);
}