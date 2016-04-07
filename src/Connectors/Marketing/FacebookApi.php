<?php
namespace CloudFramework\Service\SocialNetworks\Connectors\Marketing;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;

use FacebookAds\Api;
use FacebookAds\Object\AdUser;

class FacebookApi extends Singleton {
    const ID = "facebook";
    const FACEBOOK_SELF_USER = "me";

    // API keys
    private $clientId;
    private $clientSecret;

    // Auth keys
    private $accessToken;

    /**
     * Set Facebook Ads Api keys
     * @param $clientId
     * @param $clientSecret
     * @throws ConnectorConfigException
     */
    public function setApiKeys($clientId, $clientSecret, $accessToken) {
        if ((null === $clientId) || ("" === $clientId)) {
            throw new ConnectorConfigException("'clientId' parameter is required", 601);
        }

        if ((null === $clientSecret) || ("" === $clientSecret)) {
            throw new ConnectorConfigException("'clientSecret' parameter is required", 602);
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
    }

    /**
     * Method that inject the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->accessToken = $credentials["access_token"];

    }

    /**
     * Service that read user's basic ad account data
     * @return array
     */
    public function getCurrentAdAccount() {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $me = new AdUser(FACEBOOK_SELF_USER);
            $currentAdAccount = $me->getAdAccounts()->current();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $currentAdAccount->getData();
    }

}