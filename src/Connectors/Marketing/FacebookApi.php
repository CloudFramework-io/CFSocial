<?php
namespace CloudFramework\Service\SocialNetworks\Connectors\Marketing;

use FacebookAds\Api;
use FacebookAds\Object\AdUser;

class FacebookApi extends Singleton {
    const ID = "facebook";
    const FACEBOOK_SELF_USER = "me";

    // Facebook ads api object
    private $api;

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

        if ((null === $accessToken) || ("" === $accessToken)) {
            throw new ConnectorConfigException("'accessToken' parameter is required", 602);
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->accessToken = $accessToken;

        $this->api = Api::init($clientId, $clientSecret, $accessToken);
    }

    /**
     * Service that read user's basic ad account data
     * @return array
     */
    public function getCurrentAdAccount() {
        $me = new AdUser(FACEBOOK_SELF_USER);
        $currentAdAccount = $me->getAdAccounts()->current();

        return $currentAdAccount->getData();
    }

}