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
    function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken);
    function exportSubscribers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken);
    function exportPosts($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken);
    function exportMedia($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken);
    function importMedia($entity, $id, $parameters);
    function post($entity, $id, array $parameters);
}