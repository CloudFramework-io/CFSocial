<?php
namespace CloudFramework\Service\SocialNetworks\Connectors\Marketing;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;

use FacebookAds\Api;
use FacebookAds\Object\AdUser;
use FacebookAds\Object\Campaign;
use FacebookAds\Object\Fields\CampaignFields;
use FacebookAds\Object\Values\AdObjectives;

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
    public function setApiKeys($clientId, $clientSecret) {
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

            $me = new AdUser(self::FACEBOOK_SELF_USER);
            $currentAdAccount = $me->getAdAccounts()->current();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $currentAdAccount->getData();
    }

    /**
     * Service that creates a new campaign associated with an ad account
     * @param $adAccountId
     * @param $parameters
     *      "name"          =>  Name of the campaign (required)
     *      "objective"     =>  Objective of the campaign (default LINK_CLICKS, not required)
     * @return Campaign
     * @throws ConnectorServiceException
     * @see FacebookAds\Object\Values\AdObjectives
     */
    public function createUserAdAccountCampaign($adAccountId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $campaign = new Campaign(null, $adAccountId);
            $campaign->setData(array(
                CampaignFields::NAME => $parameters["name"],
                CampaignFields::OBJECTIVE => isset($parameters["objective"])?$parameters["objective"]:AdObjectives::LINK_CLICKS
            ));

            $campaign->create(array(
                Campaign::STATUS_PARAM_NAME => isset($parameters["status"])?$parameters["status"]:Campaign::STATUS_PAUSED
            ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $campaign;
    }
}