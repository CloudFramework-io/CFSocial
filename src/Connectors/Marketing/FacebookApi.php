<?php
namespace CloudFramework\Service\SocialNetworks\Connectors\Marketing;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;

use FacebookAds\Api;
use FacebookAds\Object\AdSet;
use FacebookAds\Object\AdUser;
use FacebookAds\Object\Campaign;
use FacebookAds\Object\TargetingSearch;
use FacebookAds\Object\TargetingSpecs;
use FacebookAds\Object\Fields\AdSetFields;
use FacebookAds\Object\Fields\CampaignFields;
use FacebookAds\Object\Fields\TargetingSpecsFields;
use FacebookAds\Object\Search\TargetingSearchTypes;
use FacebookAds\Object\Values\AdObjectives;
use FacebookAds\Object\Values\BillingEvents;
use FacebookAds\Object\Values\OptimizationGoals;
use FacebookAds\Object\Values\PageTypes;

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
    public function getCurrentUserAdAccount() {
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
     *      "objective"     =>  Objective of the campaign (default LINK_CLICKS, optional)
     *          "CANVAS_APP_ENGAGEMENT      Increase the interaction with your application
     *          "CANVAS_APP_INSTALLS"       Increase the installation of your application
     *          "EVENT_RESPONSES"           Increase the number of attendants to your event
     *          "LOCAL_AWARENESS"           Go to people who are near your business
     *          "MOBILE_APP_ENGAGEMENT"     Increase the interaction with your mobile app
     *          "MOBILE_APP_INSTALLS"       Increase the installations of your mobile app
     *          "NONE"                      None
     *          "OFFER_CLAIMS"              Create offers for users to redeem in your establishment
     *          "PAGE_LIKES"                Promote your page and get I like to connect with more people relevant.
     *          "POST_ENGAGEMENT" (default) Promote your posts.
     *          "PRODUCT_CATALOG_SALES"     Promote a list of products you want to advertise on Facebook
     *          "LINK_CLICKS"               Attract people to your website
     *          "CONVERSIONS"               Increase conversions on your site. You will need a pixel conversion for your
     *                                      site before you can create this ad
     *          "VIDEO_VIEWS"               Create ads that make more people watch a video
     *      "status"        =>  Status of the campaign (optional)
     *          "ACTIVE"
     *          "PAUSED"    (Default)
     * @return array
     * @throws ConnectorServiceException
     * @see FacebookAds\Object\Values\AdObjectives
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign-group
     */
    public function createCampaign($adAccountId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $campaign = new Campaign(null, $adAccountId);
            $campaign->setData(array(
                CampaignFields::NAME => $parameters["name"],
                CampaignFields::OBJECTIVE => isset($parameters["objective"])?$parameters["objective"]:AdObjectives::POST_ENGAGEMENT
            ));

            // Pause the campaign so that you don't get billed while testing
            $campaign->create(array(
                Campaign::STATUS_PARAM_NAME => isset($parameters["status"])?$parameters["status"]:Campaign::STATUS_PAUSED
            ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $campaign->getData();
    }

    /**
     * Service that gets an user's campaign information
     * @param $campaignId
     * @return array
     * @throws ConnectorServiceException
     */
    public function getCampaign($campaignId) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $campaign = new Campaign($campaignId);
            $campaign->read(array(
                CampaignFields::ID,
                CampaignFields::NAME,
                CampaignFields::OBJECTIVE,
            ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $campaign->getData();
    }

    /**
     * Service that deletes a campaign
     * @param $campaignId
     * @return array
     * @throws ConnectorServiceException
     */
    public function deleteCampaign($campaignId) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $campaign = new Campaign($campaignId);
            $campaign->delete();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $campaign->getData();
    }

    /**
     * Service that creates a new campaign associated with an ad account
     * @param $adAccountId
     * @param $campaignId
     * @param $parameters
     *      "name"  New AdSet name
     *      PROMOTED OBJECT
     *          "page_id"           Promoted page id. It must be set if objective of the campaign is either PAGE_LIKES
     *                              or POST_ENGAGEMENT or LOCAL_AWARENESS
     *          "application_id"    Promoted application. It must be set if objective of the campaign is either
     *                              CANVAS_APP_ENGAGEMENT or CANVAS_APP_INSTALLS or MOBILE_APP_ENGAGEMENT or
     *                              MOBILE_APP_INSTALLS
     *          "object_store_url" The uri of the mobile / digital store where an application can be bought / downloaded.
     *                              This is platform specific. When combined with the "application_id" this uniquely
     *                              specifies an object which can be the subject of a Facebook advertising campaign.
     *                              It must be set if objective of the campaign is either MOBILE_APP_ENGAGEMENT or
     *                              MOBILE_APP_INSTALLS
     *      TARGETING (BASIC, @TODO Basic with radius, Interest and Behavioural)
     *          "countries":    array of geocodes of countries what the campaign is aimed to
     *          "regions":      array of geocodes of regions what the campaign is aimed to
     *          "cities":       array of geocodes of cities what the campaign is aimed to
     *          "gender":       1=male, 2=female. Defaults to all.
     *          "age_min":      Minimum age. If used, must be 13 or higher. If omitted, will default to 18
     *          "age_max":      Maximum age. If used, must be 65 or lower.
     *          "page_types":   array of placements where user wants ads to be delivered:
     *              desktopfeed:        News Feed on Facebook Desktop
     *              rightcolumn:        Right column on Facebook Desktop
     *              mobilefeed:         News Feed on Facebook Mobile. Note: Video ads within Mobile News Feed are also
     *                                  eligible to appear in suggested video feeds.
     *              instagramstream:    The stream of media on Instagram mobile app. More details on Instagram Ads doc.
     *              mobileexternal:     Audience Network. Note that this page type must be selected with mobilefeed.
     *              home:               Exclusively on right column on Facebook Desktop (only available for Premium)
     *      SCHEDULE
     *          "start_time":   How long your ad will run: start_time. (UTC UNIX timestamp)
     *          "end_time":     How long your ad will run: end_time (UTC UNIX timestamp)
     *      BUDGET, OPTIMIZATION AND BILLING
     *          "daily_budget":     The daily budget of the campaign, defined in your account currency; that is,
     *                              how much money you want to spend per day
     *          "lifetime_budget":  Lifetime budget, defined in your account currency. If specified, you must also
     *                              specify an end_time. Either a daily_budget or a lifetime_budget must be specified.
     *          "optimization_goal": Which optimization goal (What result you want to achieve with your ad) this ad set
     *                              is using:
     *              NONE:               Only available in read mode for campaigns created pre v2.4
     *              APP_INSTALLS:       Optimize for people more likely to install your app.
     *              BRAND_AWARENESS:    Optimize to reach the most number of users who are likely to spend at least a
     *                                  minimum amount of time on the image or video.
     *              CLICKS:             Optimize for people more likely to click anywhere in the ad.
     *              ENGAGED_USER:       Optimize for people more likely to take a particular action in your app
     *              EXTERNAL:           FBX only
     *              EVENT_RESPONSES:    Optimize for people more likely to attend your event
     *              IMPRESSIONS:        Show the ads as many times as possible
     *              LINK_CLICKS:        Optimize for people more likely to click in the link of the ad.
     *              OFFER_CLAIMS:       Optimize for people more likely to claim the offer.
     *              OFFSITE_CONVERSION: Optimize for people more likely to make a conversion in the site
     *              PAGE_LIKES:         Optimize for people more likely to like your page.
     *              PAGE_ENGAGEMENT:    Optimize for people more likely to engage with your page.
     *              POST_ENGAGEMENT:    Optimize for people more likely to engage with your post.
     *              REACH:              Optimize to reach the most unique users of each day or interval specified in
     *                                  frequency_control_specs.
     *              SOCIAL_IMPRESSIONS: Increase the number of impressions with social context. I.e. with the names of
     *                                  one or more of the user's friends attached to the ad who have already liked the
     *                                  page or installed the app.
     *              VIDEO_VIEWS:        Optimize for people more likely to watch videos.LEAD_GENERATION: Optimize for people
     *                                  more likely to fill out a lead generation form.
     *          "billing_event": The billing event (How you want to pay) that this adset is using:
     *              APP_INSTALLS:       Pay when people install your app.
     *              CLICKS:             Pay when people click anywhere in the ad.
     *              IMPRESSIONS:        Pay when the ads are shown to people.
     *              LINK_CLICKS:        Pay when people click on the link of the ad.
     *              OFFER_CLAIMS:       Pay when people claim the offer.
     *              PAGE_LIKES:         Pay when people like your page.
     *              POST_ENGAGEMENT:    Pay when people engage with your post.
     *              VIDEO_VIEWS:        Pay when people watch videos.
     *          "bid_amount":   What value you place on your optimization event occuring. Bid amount for this ad set,
     *                          defined as your true value bid based on optimization_goal. If an ad level bid_amount is
     *                          specified, updating this value will overwrite the previous ad level bid.
     *                          Either bid_amount or is_autobid is required except in Reach and Frequency ad sets.
     *                          The bid amount's unit is cent for currencies like USD, EUR, and the basic unit for currencies
     *                          like JPY, KRW. The bid amount for ads with IMPRESSION, REACH as billing_event is per
     *                          1,000 occurrences, and has to be at least 2 US cents or more; that for ads with other
     *                          billing_event is for each occurrence, and has a minimum value 1 US cents. The minimum
     *                          bid amounts of other currencies are of similar value to the US Dollar values provided.
     * @return array
     * @throws ConnectorServiceException
     * @see FacebookApi::searchGeolocationCode
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign/promoted-object/v2.6
     */
    public function createAdSet($adAccountId, $campaignId, $parameters) {
        try {
            // Basic Targeting
            // Geolocation
            $geo_locations = array();

            if (isset($parameters["countries"])) {
                $geo_locations["countries"] = $parameters["countries"];
            }

            if (isset($parameters["regions"])) {
                $geo_locations["regions"] = $parameters["regions"];
            }

            if (isset($parameters["regions"])) {
                $geo_locations["cities"] = $parameters["cities"];
            }

            if (count($geo_locations) > 0) {
                $targeting = new TargetingSpecs();
                $targeting->{TargetingSpecsFields::GEO_LOCATIONS} = $geo_locations;
            }

            if (isset($parameters["gender"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::GENDERS} = $parameters["gender"];
                }
            }

            if (isset($parameters["age_min"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::AGE_MIN} = $parameters["age_min"];
                }
            }

            if (isset($parameters["age_max"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::AGE_MAX} = $parameters["age_max"];
                }
            }

            if (isset($parameters["page_types"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::PAGE_TYPES} = $parameters["page_types"];
                }
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        //return $campaign->getData();
    }

    /**
     * Service that search geolocation codes from text parameter
     * @param $type     "country", "region", "city", "zip"
     * @param $text     e.g.: "japan", "andalucia", "malaga"
     * @return \FacebookAds\Cursor
     * @throws ConnectorServiceException
     */
    public function searchGeolocationCode($type, $text) {
        $this->checkGeolocationParameters($type, $text);

        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $result = TargetingSearch::search(
                TargetingSearchTypes::GEOLOCATION,
                null,
                $text,
                array(
                    'location_types' => array($type),
                ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $body = json_decode($result->getLastResponse()->getBody(), true);

        return $body["data"];
    }

    /**
     * Method that check geo location parameters are ok
     * @param $type
     * @param $text
     * @throws ConnectorConfigException
     */
    private function checkGeolocationParameters($type, $text) {
        if ((null === $type) || ("" === $type)) {
            throw new ConnectorConfigException("'type' parameter is required");
        }

        if ((null === $text) || ("" === $text)) {
            throw new ConnectorConfigException("'text' parameter is required");
        }
    }
}