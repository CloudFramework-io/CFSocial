<?php
namespace CloudFramework\Service\SocialNetworks;

use CloudFramework\Patterns\Singleton;

/**
 * Class Marketing
 * @author Salvador Castro <sc@bloombees.com>
 */
class Marketing extends Singleton
{
    /**
     * Method that initialize a social api instance to use
     * @param $social
     * @return \CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface
     * @throws \Exception
     */
    public function getSocialApi($social) {
        $social = ucfirst($social);
        $socialNetworkClass = "CloudFramework\\Service\\SocialNetworks\\Connectors\\Marketing\\{$social}Api";
        if (class_exists($socialNetworkClass)) {
            try {
                return $api = $socialNetworkClass::getInstance();
            } catch(\Exception $e) {
                throw $e;
            }
        } else {
            throw new \Exception("Social Network Requested not exists", 501);
        }
    }

    /**
     * Service that set the api keys for social network
     * @param $social
     * @param $clientId
     * @param $clientSecret
     * @param $accessToken
     * @return mixed
     * @throws \Exception
     */
    public function setApiKeys($social, $clientId, $clientSecret, $accessToken) {
        $api = $this->getSocialApi($social);
        return $api->setApiKeys($clientId, $clientSecret, $accessToken);
    }

    /**
     * Service that set the access token for social network
     * @param $social
     * @param array $credentials
     * @return mixed
     * @throws \Exception
     */
    public function setAccessToken($social, array $credentials) {
        $connector = $this->getSocialApi($social);
        return $connector->setAccessToken($credentials);
    }

    /**
     * Service that get the user's current ad account data
     * @param $social
     * @return mixed
     * @throws \Exception
     */
    public function getCurrentAdAccount($social) {
        $api = $this->getSocialApi($social);
        return $api->getCurrentAdAccount();
    }

    /**
     * Service that get creates a new campaign into an user's ad account
     * @param $social
     * @param $adAccountId
     * @param $parameters
     *      "name" Name of the campaign (required)
     *      "objective" Objective (not required):
     *          'CANVAS_APP_ENGAGEMENT
     *              Increase the interaction with your application
     *          'CANVAS_APP_INSTALLS'
     *              Increase the installation of your application
     *          'EVENT_RESPONSES'
     *              Increase the number of attendants to your event
     *          'LOCAL_AWARENESS'
     *              Go to people who are near your business
     *          'MOBILE_APP_ENGAGEMENT'
     *              Increase the interaction with your mobile app
     *          'MOBILE_APP_INSTALLS'
     *              Increase the installations of your mobile app
     *          'NONE'
     *              None
     *          'OFFER_CLAIMS'
     *              Create offers for users to redeem in your establishment
     *          'PAGE_LIKES'
     *              Promote your page and get I like to connect with more people relevant.
     *         'POST_ENGAGEMENT'
     *              Promote your posts.
     *          'PRODUCT_CATALOG_SALES'
     *              Promote a list of products you want to advertise on Facebook
     *          'LINK_CLICKS' (default)
     *              Attract people to your website
     *          'CONVERSIONS'
     *              Increase conversions on your site. You will need a pixel conversion for your site before you can create this ad
     *          'VIDEO_VIEWS'
     *              Create ads that make more people watch a video
     *      "status" Status of the campaign (not required):
     *          'ACTIVE'
     *          'PAUSED' (default)
     * @return mixed
     * @throws \Exception
     */
    public function createUserAdAccountCampaign($social, $adAccountId, $parameters) {
        $api = $this->getSocialApi($social);
        return $api->createUserAdAccountCampaign($adAccountId, $parameters);
    }
}