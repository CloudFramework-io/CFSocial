<?php
namespace CloudFramework\Service\SocialNetworks\Connectors\Marketing;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Marketing;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\MarketingInterface;

use Facebook\Facebook;
use FacebookAds\Api;
use FacebookAds\Http\Exception\AuthorizationException;
use FacebookAds\Object\Ad;
use FacebookAds\Object\AdAccount;
use FacebookAds\Object\AdCreative;
use FacebookAds\Object\AdImage;
use FacebookAds\Object\AdSet;
use FacebookAds\Object\AdUser;
use FacebookAds\Object\Campaign;
use FacebookAds\Object\Fields\AdAccountFields;
use FacebookAds\Object\Fields\AdImageFields;
use FacebookAds\Object\Fields\AdPreviewFields;
use FacebookAds\Object\Fields\ObjectStorySpecFields;
use FacebookAds\Object\ObjectStory\LinkData;
use FacebookAds\Object\Fields\ObjectStory\LinkDataFields;
use FacebookAds\Object\ObjectStorySpec;
use FacebookAds\Object\TargetingSearch;
use FacebookAds\Object\TargetingSpecs;
use FacebookAds\Object\Fields\AdCreativeFields;
use FacebookAds\Object\Fields\AdFields;
use FacebookAds\Object\Fields\AdSetFields;
use FacebookAds\Object\Fields\CampaignFields;
use FacebookAds\Object\Fields\TargetingSpecsFields;
use FacebookAds\Object\Search\TargetingSearchTypes;
use FacebookAds\Object\Values\AdObjectives;

class FacebookApi extends Singleton implements MarketingInterface {
    const ID = "facebook";
    const FACEBOOK_SELF_USER = "me";

    // Facebook client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    // Auth keys
    private $accessToken;

    /**
     * Set Facebook Api keys
     * @param $clientId
     * @param $clientSecret
     * @param $clientScope
     * @param $redirectUrl
     * @throws ConnectorConfigException
     */
    public function setApiKeys($clientId, $clientSecret, $clientScope, $redirectUrl = null) {
        if ((null === $clientId) || ("" === $clientId)) {
            throw new ConnectorConfigException("'clientId' parameter is required", 601);
        }

        if ((null === $clientSecret) || ("" === $clientSecret)) {
            throw new ConnectorConfigException("'clientSecret' parameter is required", 602);
        }

        if ((null === $clientScope) || (!is_array($clientScope)) || (count($clientScope) == 0)) {
            throw new ConnectorConfigException("'clientScope' parameter is required", 603);
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new Facebook(array(
            "app_id" => $this->clientId,
            "app_secret" => $this->clientSecret,
            'default_graph_version' => 'v2.4',
            'cookie' => true
        ));
    }

    /**
     * Service that request authorization to Facebook making up the Facebook login URL
     * @param string $redirectUrl
     * @return array
     * @throws ConnectorConfigException
     * @throws MalformedUrlException
     */
    public function requestAuthorization($redirectUrl)
    {
        if ((null === $redirectUrl) || (empty($redirectUrl))) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required", 628);
        } else {
            if (!Marketing::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed", 601);
            }
        }

        $redirect = $this->client->getRedirectLoginHelper();

        $authUrl = $redirect->getLoginUrl($redirectUrl, $this->clientScope);

        // Authentication request
        return $authUrl;
    }

    /**
     * Authentication service from Facebook sign in request
     * @param string $code
     * @param string $verifier
     * @param $redirectUrl
     * @return array
     * @throws ConnectorServiceException
     */
    public function authorize($code, $verifier, $redirectUrl = null)
    {
        try {
            if(!array_key_exists('code', $_GET)) {
                $_GET['code'] = $code;
            }
            if(!array_key_exists('state', $_GET)) {
                $_GET['state'] = $verifier;
            }
            /** @var FacebookRedirectLoginHelper $helper */
            $helper = $this->client->getRedirectLoginHelper();
            $accessToken = $helper->getAccessToken($redirectUrl);

            if (empty($accessToken)) {
                throw new ConnectorServiceException("Error taking access token from Facebook Api", 500);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return array("access_token" => $accessToken->getValue());
    }

    /**
     * Method that inject the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->accessToken = $credentials["access_token"];
    }

    /**
     * Service that checks if credentials are valid
     * @param array $credentials
     * @return null
     * @throws ConnectorConfigException
     */
    public function checkCredentials(array $credentials) {
        $this->checkCredentialsParameters($credentials);
        try {
            return $this->getProfile(self::FACEBOOK_SELF_USER);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set");
        }
    }

    /**
     * Service that queries to Facebook Api to get user profile
     * @param string $id    user id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getProfile($id) {
        $this->checkUser($id);
        try {
            $response = $this->client->get("/".$id."?fields=id,name,first_name,middle_name,last_name,email,cover,locale,website,link,picture", $this->accessToken);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error getting user profile: ' . $e->getMessage(), $e->getCode());
        }
        /** @var  $graphUser */
        $graphUser = $response->getGraphUser();
        $profile = array(
            "user_id" => $graphUser->getId(),
            "name" => $graphUser->getName(),
            "first_name" => $graphUser->getFirstName(),
            "last_name" => $graphUser->getLastName(),
            "email" => $graphUser->getEmail(),
            "photo" => $graphUser->getPicture()->getUrl(),
            "locale" => $graphUser->getField('locale', 'en'),
            "url" => $graphUser->getLink(),
            "raw" => json_decode($graphUser, true)
        );
        return $profile;
    }

    /**
     * Service that read user's basic ad account data
     * @return array
     * @throws ConnectorServiceException
     */
    public function getCurrentUserAdAccount() {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $me = new AdUser(self::FACEBOOK_SELF_USER);
            $fields = array(
                AdAccountFields::ID,
                AdAccountFields::NAME,
                AdAccountFields::ACCOUNT_STATUS,
                AdAccountFields::BALANCE
            );

            $currentAdAccount = $me->getAdAccounts($fields)->current();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $currentAdAccount->getData();
    }

    /**
     * Service that gets user's ad accounts
     * @return array
     * @throws ConnectorServiceException
     */
    public function exportUserAdAccounts() {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $me = new AdUser(self::FACEBOOK_SELF_USER);
            $fields = array(
                AdAccountFields::ID,
                AdAccountFields::NAME,
                AdAccountFields::ACCOUNT_STATUS,
                AdAccountFields::BALANCE
            );
            $adAccounts = $me->getAdAccounts($fields)->getArrayCopy();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $addAccountsArr = [];
        foreach($adAccounts as $adAccount) {
            $addAccountsArr[] = $adAccount->getData();
        }

        return $addAccountsArr;
    }

    /**
     * Service that get the user's ad account data by its id
     * @param $id - Ad Account Id
     * @return array
     * @throws ConnectorServiceException
     */
    public function getAdAccount($id) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $adAccount = new AdAccount($id);
            $adAccount->read(array(
                AdAccountFields::ID,
                AdAccountFields::NAME,
                AdAccountFields::ACCOUNT_STATUS,
                AdAccountFields::BALANCE
            ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $adAccount->getData();
    }

    /**
     * Service that gets user's ad account campaigns
     * @param $id - Ad Account Id
     * @return array
     * @throws ConnectorServiceException
     */
    public function exportUserAdAccountCampaigns($id) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $adAccount = new AdAccount($id);
            $fields = array(
                CampaignFields::ID,
                CampaignFields::ACCOUNT_ID,
                CampaignFields::NAME,
                CampaignFields::OBJECTIVE,
                CampaignFields::PROMOTED_OBJECT,
                CampaignFields::EFFECTIVE_STATUS,
                CampaignFields::CONFIGURED_STATUS
            );
            $campaigns = $adAccount->getCampaigns($fields)->getArrayCopy();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $campaignsArr = [];
        foreach($campaigns as $campaign) {
            $campaignsArr[] = $campaign->getData();
        }

        return $campaignsArr;
    }

    /**
     * Service that gets user's ad account ad images
     * @param $id - Ad Account Id
     * @return array
     * @throws ConnectorServiceException
     */
    public function exportUserAdAccountAdImages($id) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $adAccount = new AdAccount($id);
            $fields = array(
                AdImageFields::ID,
                AdImageFields::CREATED_TIME,
                AdImageFields::CREATIVES,
                AdImageFields::FILENAME,
                AdImageFields::HASH,
                AdImageFields::HEIGHT,
                AdImageFields::NAME,
                AdImageFields::ORIGINAL_HEIGHT,
                AdImageFields::ORIGINAL_WIDTH,
                AdImageFields::PERMALINK_URL,
                AdImageFields::STATUS,
                AdImageFields::UPDATED_TIME,
                AdImageFields::URL,
                AdImageFields::URL_128,
                AdImageFields::WIDTH
            );
            $adImages = $adAccount->getAdImages($fields)->getArrayCopy();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        $adImagesArr = [];
        foreach($adImages as $adImage) {
            $adImagesArr[] = $adImage->getData();
        }

        return $adImagesArr;
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
            $campaign = new Campaign(null, (false === strpos($adAccountId,'act_'))?'act_'.$adAccountId:$adAccountId);
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
                CampaignFields::ACCOUNT_ID,
                CampaignFields::NAME,
                CampaignFields::OBJECTIVE,
                CampaignFields::PROMOTED_OBJECT,
                CampaignFields::EFFECTIVE_STATUS,
                CampaignFields::CONFIGURED_STATUS
            ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $campaign->getData();
    }

    /**
     * Service that gets the adsets from an existing campaign
     * @param $campaignId
     * @return array
     * @throws ConnectorServiceException
     */
    public function getCampaignAdSets($campaignId) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $campaign = new Campaign($campaignId);
            $adsets = array();
            $cursor = $campaign->getAdSets();

            // Loop over objects
            foreach ($cursor as $adset) {
                $adsetData = $adset->getData();
                $adsets[] = $this->getAdSet($adsetData["id"]);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $adsets;
    }

    /**
     * Service that gets the ads from an existing campaign
     * @param $campaignId
     * @return array
     * @throws ConnectorServiceException
     */
    public function getCampaignAds($campaignId) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $campaign = new Campaign($campaignId);
            $ads = array();
            $cursor = $campaign->getAds(ar);

            // Loop over objects
            foreach ($cursor as $ad) {
                $adData = $ad->getData();
                $ads[] = $this->getAd($adData["id"]);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $ads;
    }

    /**
     * Service that gets the ads from an existing adset
     * @param $adsetId
     * @return array
     * @throws ConnectorServiceException
     */
    public function getAdsetAds($adsetId) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $adset = new Adset($adsetId);
            $ads = array();
            $cursor = $adset->getAds(array(
                AdFields::CONFIGURED_STATUS,
                AdFields::EFFECTIVE_STATUS
            ));

            // Loop over objects
            foreach ($cursor as $ad) {
                $adData = $ad->getData();
                $ads[] = $this->getAd($adData["id"]);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $ads;
    }

    /**
     * Service that updates an existing campaign
     * @param $campaignId
     * @param $parameters
     *      "name"          =>  Name of the campaign (optional)
     *      "objective"     =>  Objective of the campaign (optional)
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
     *          "PAUSED"
     * @return array
     * @throws ConnectorServiceException
     * @see FacebookAds\Object\Values\AdObjectives
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign-group
     */
    public function updateCampaign($campaignId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $campaign = new Campaign($campaignId);
            if (isset($parameters["name"])) {
                $campaign->{CampaignFields::NAME} = $parameters["name"];
            }
            if (isset($parameters["objective"])) {
                $campaign->{CampaignFields::OBJECTIVE} = $parameters["objective"];
            }
            if (isset($parameters["status"])) {
                $campaign->{CampaignFields::STATUS_PARAM_NAME} = $parameters["status"];
            }

            $campaign->update();
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
     * Service that creates a new adSet associated with an ad account and a campaign
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
     *          "countries":    csv of geocodes of countries what the campaign is aimed to
     *          "regions":      csv of geocodes of regions what the campaign is aimed to
     *          "cities":       csv of geocodes of cities what the campaign is aimed to
     *          "gender":       array -> 1=male, 2=female. Defaults to all.
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
     *          "daily_budget":     The daily budget of the campaign defined in the ad account currency, that is,
     *                              how much money you want to spend per day. Example: 100 = 1,00€
     *          "lifetime_budget":  Lifetime budget, defined in the ad account currency. If specified, you must also
     *                              specify an end_time. Either a daily_budget or a lifetime_budget must be specified.
     *                               Example: 100 = 1,00€
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
     *          "is_autobid":   Boolean. Did the advertiser express the intent to bid automatically. This field is not available
     *                          if bid_info or bid_amount is returned. See bid_amount
     *      "status"            Possible values: ACTIVE, PAUSED, ARCHIVED, DELETED
     *                          Only ACTIVE and PAUSED are valid for creation. The other statuses can be used for update.
     *                          If it is set to PAUSED, all its active ads will be paused and have an effective status ADSET_PAUSED.
     *                          default PAUSED
     * @return array
     * @throws ConnectorServiceException
     * @see FacebookApi::searchGeolocationCode
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign/promoted-object/v2.6
     */
    public function createAdSet($adAccountId, $campaignId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            // Name
            $adset = new AdSet(null, (false === strpos($adAccountId,'act_'))?'act_'.$adAccountId:$adAccountId);
            $adset->{AdSetFields::CAMPAIGN_ID} = $campaignId;

            if (isset($parameters["name"])) {
                $adset->{AdSetFields::NAME} = $parameters["name"];
            }

            // Promoted object
            $promoted_object = array();

            if (isset($parameters["page_id"])) {
                $promoted_object["page_id"] = $parameters["page_id"];
            }

            if (isset($parameters["application_id"])) {
                $promoted_object["application_id"] = $parameters["application_id"];
            }

            if (isset($parameters["object_store_url"])) {
                $promoted_object["application_id"] = $parameters["application_id"];
            }

            if (count($promoted_object) > 0) {
                $adset->{AdSetFields::PROMOTED_OBJECT} = $promoted_object;
            }

            // Basic Targeting
            // Geolocation
            $geo_locations = array();

            if (isset($parameters["countries"])) {
                $geo_locations["countries"] = explode(",", $parameters["countries"]);
            }

            if (isset($parameters["regions"])) {
                $geo_locations["regions"] = explode(",", $parameters["regions"]);
            }

            if (isset($parameters["cities"])) {
                $geo_locations["cities"] = explode(",", $parameters["cities"]);
            }

            if (count($geo_locations) > 0) {
                $targeting = new TargetingSpecs();
                $targeting->{TargetingSpecsFields::GEO_LOCATIONS} = $geo_locations;
            }

            // Gender
            if (isset($parameters["gender"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::GENDERS} = $parameters["gender"];
                }
            }

            // Age
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

            // Page types
            if (isset($parameters["page_types"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::PAGE_TYPES} = $parameters["page_types"];
                }
            }

            if (isset($targeting)) {
                $adset->{AdSetFields::TARGETING} = $targeting;
            }

            // Schedule
            if (isset($parameters["start_time"])) {
                $adset->{AdSetFields::START_TIME} = $parameters["start_time"];
            }

            if (isset($parameters["end_time"])) {
                $adset->{AdSetFields::END_TIME} = $parameters["end_time"];
            }

            // Budget, optimization and billing
            if (isset($parameters["daily_budget"])) {
                $adset->{AdSetFields::DAILY_BUDGET} = $parameters["daily_budget"];
            }

            if (isset($parameters["lifetime_budget"])) {
                $adset->{AdSetFields::LIFETIME_BUDGET} = $parameters["lifetime_budget"];
            }

            if (isset($parameters["optimization_goal"])) {
                $adset->{AdSetFields::OPTIMIZATION_GOAL} = $parameters["optimization_goal"];
            }

            if (isset($parameters["billing_event"])) {
                $adset->{AdSetFields::BILLING_EVENT} = $parameters["billing_event"];
            }

            if (isset($parameters["bid_amount"])) {
                $adset->{AdSetFields::BID_AMOUNT} = $parameters["bid_amount"];
            }

            if (isset($parameters["is_autobid"])) {
                $adset->{AdSetFields::IS_AUTOBID} = $parameters["is_autobid"];
            }

            // Adset creation
            if (!isset($parameter["status"])) {
                $adset->create(array(
                    AdSet::STATUS_PARAM_NAME => AdSet::STATUS_PAUSED,
                ));
            } else {
                $adset->create(array(
                    AdSet::STATUS_PARAM_NAME => $parameters["status"],
                ));
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $this->getAdSet($adset->getId());
    }

    /**
     * Service that gets an user's adSet information
     * @param $adSetId
     * @return array
     * @throws ConnectorServiceException
     */
    public function getAdSet($adSetId) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $adSet = new AdSet($adSetId);
            $adSet->read(array(
                AdSetFields::ID,
                AdSetFields::NAME,
                AdSetFields::CREATED_TIME,
                AdSetFields::CAMPAIGN_ID,
                AdSetFields::TARGETING,
                AdSetFields::START_TIME,
                AdSetFields::END_TIME,
                AdSetFields::PROMOTED_OBJECT,
                AdSetFields::BILLING_EVENT,
                AdSetFields::OPTIMIZATION_GOAL,
                AdSetFields::LIFETIME_BUDGET,
                AdSetFields::DAILY_BUDGET,
                AdSetFields::BUDGET_REMAINING,
                AdSetFields::IS_AUTOBID
            ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $adSet->getData();
    }

    /**
     * Service that updates an existing adSet
     * @param $adSetId
     * @param $parameters
     *      "name"  New AdSet name
     *      "account_id"    New Account Id to depend on
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
     *          "countries":    csv of geocodes of countries what the campaign is aimed to
     *          "regions":      csv of geocodes of regions what the campaign is aimed to
     *          "cities":       csv of geocodes of cities what the campaign is aimed to
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
     *          "daily_budget":     The daily budget of the campaign defined in the ad account currency, that is,
     *                              how much money you want to spend per day. Example: 100 = 1,00€
     *          "lifetime_budget":  Lifetime budget, defined in the ad account currency. If specified, you must also
     *                              specify an end_time. Either a daily_budget or a lifetime_budget must be specified.
     *                               Example: 100 = 1,00€
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
     *          "is_autobid":   Boolean. Did the advertiser express the intent to bid automatically. This field is not available
     *                          if bid_info or bid_amount is returned. See bid_amount
     *      "status"            Possible values: ACTIVE, PAUSED, ARCHIVED, DELETED
     *                          Only ACTIVE and PAUSED are valid for creation. The other statuses can be used for update.
     *                          If it is set to PAUSED, all its active ads will be paused and have an effective status ADSET_PAUSED.
     * @return array
     * @throws ConnectorServiceException
     * @see FacebookApi::searchGeolocationCode
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign
     * @see https://developers.facebook.com/docs/marketing-api/reference/ad-campaign/promoted-object/v2.6
     */
    public function updateAdSet($adSetId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            // Name
            $adset = new AdSet($adSetId);

            if (isset($parameters["name"])) {
                $adset->{AdSetFields::NAME} = $parameters["name"];
            }

            if (isset($parameters["account_id"])) {
                $adset->{AdSetFields::ACCOUNT_ID} = $parameters["account_id"];
            }

            // Basic Targeting
            // Geolocation
            $geo_locations = array();

            if (isset($parameters["countries"])) {
                $geo_locations["countries"] = explode(",", $parameters["countries"]);
            }

            if (isset($parameters["regions"])) {
                $geo_locations["regions"] = explode(",", $parameters["regions"]);
            }

            if (isset($parameters["regions"])) {
                $geo_locations["cities"] = explode(",", $parameters["cities"]);
            }

            if (count($geo_locations) > 0) {
                $targeting = new TargetingSpecs();
                $targeting->{TargetingSpecsFields::GEO_LOCATIONS} = $geo_locations;
            }

            // Gender
            if (isset($parameters["gender"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::GENDERS} = $parameters["gender"];
                }
            }

            // Age
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

            // Page types
            if (isset($parameters["page_types"])) {
                if (!isset($targeting)) {
                    $targeting = new TargetingSpecs();
                    $targeting->{TargetingSpecsFields::PAGE_TYPES} = $parameters["page_types"];
                }
            }

            if (isset($targeting)) {
                $adset->{AdSetFields::TARGETING} = $targeting;
            }

            // Schedule
            if (isset($parameters["start_time"])) {
                $adset->{AdSetFields::START_TIME} = $parameters["start_time"];
            }

            if (isset($parameters["end_time"])) {
                $adset->{AdSetFields::END_TIME} = $parameters["end_time"];
            }

            // Budget, optimization and billing
            if (isset($parameters["daily_budget"])) {
                $adset->{AdSetFields::DAILY_BUDGET} = $parameters["daily_budget"];
            }

            if (isset($parameters["lifetime_budget"])) {
                $adset->{AdSetFields::LIFETIME_BUDGET} = $parameters["lifetime_budget"];
            }

            if (isset($parameters["optimization_goal"])) {
                $adset->{AdSetFields::OPTIMIZATION_GOAL} = $parameters["optimization_goal"];
            }

            if (isset($parameters["billing_event"])) {
                $adset->{AdSetFields::BILLING_EVENT} = $parameters["billing_event"];
            }

            if (isset($parameters["bid_amount"])) {
                $adset->{AdSetFields::BID_AMOUNT} = $parameters["bid_amount"];
            }

            if (isset($parameters["is_autobid"])) {
                $adset->{AdSetFields::IS_AUTOBID} = $parameters["is_autobid"];
            }

            // Adset edition
            if (isset($parameter["status"])) {
                $adset->{AdSetFields::STATUS_PARAM_NAME} = $parameters["status"];
            }

             $adset->update();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $this->getAdSet($adset->getId());
    }

    /**
     * Service that creates a new adcreative for an existing page post
     * @param $adAccountId
     * @param $parameters
     *      "name"      =>  AdCreative name
     *      "post_id"   =>  Promotable post id
     * @return AdCreative
     * @throws ConnectorServiceException
     */
    public function createExistingPostAdCreative($adAccountId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $adcreative = new AdCreative(null, (false === strpos($adAccountId,'act_'))?'act_'.$adAccountId:$adAccountId);

            $adcreative->setData(array(
                    AdCreativeFields::NAME => $parameters["name"],
                    AdCreativeFields::OBJECT_STORY_ID => $parameters["post_id"]
            ));

            $adcreative->create();
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $adcreative->getData();
    }

    /**
     * Service that creates a new adcreative and a new post within it
     * @param $adAccountId
     * @param $parameters
     *      "title"             =>  Post title
     *      "body"              =>  Post body
     *      "object_url"        =>  Destination URL for a link ad (not connected to a page)
     *      "image_file"        =>  Post image
     *      "image_extension"   =>  Post image extension
     * @return AdCreative
     * @throws ConnectorServiceException
     */
    public function createNewPostAdCreative($adAccountId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            // Link Ad
            switch($parameters["type"]) {
                case 1:
                    if (isset($parameters["image_file"])) {
                        $image = new AdImage(null, (false === strpos($adAccountId, 'act_')) ? 'act_' . $adAccountId : $adAccountId);
                        rename($parameters["image_file"], $parameters["image_file"] . "." . $parameters["image_extension"]);

                        $fields = array(
                            AdImageFields::FILENAME => $parameters["image_file"] . "." . $parameters["image_extension"]
                        );

                        $image->setData($fields);

                        $image->create();

                        $hash = $image->{AdImageFields::HASH};
                    }

                    $link_data = new LinkData();
                    $link_data->setData(array(
                        LinkDataFields::MESSAGE => $parameters["message"],
                        LinkDataFields::LINK => $parameters["link"],
                        LinkDataFields::CAPTION => $parameters["caption"]
                    ));

                    if (isset($hash)) {
                        $fields[LinkDataFields::IMAGE_HASH] = $hash;
                    }

                    $object_story_spec = new ObjectStorySpec();
                    $object_story_spec->setData(array(
                        ObjectStorySpecFields::PAGE_ID => $parameters["page_id"],
                        ObjectStorySpecFields::LINK_DATA => $link_data,
                    ));

                    $adcreative = new AdCreative(null, (false === strpos($adAccountId, 'act_')) ? 'act_' . $adAccountId : $adAccountId);

                    $adcreative->setData(array(
                        AdCreativeFields::NAME => $parameters["message"],
                        AdCreativeFields::OBJECT_STORY_SPEC => $object_story_spec,
                    ));

                    break;
            }

            $adcreative->create();
        } catch(AuthorizationException $ae) {
            throw new ConnectorServiceException($ae->getErrorUserMessage(), $ae->getCode());
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $adcreative->getData();
    }

    /**
     * Service that creates the final ad
     * @param $adAccountId
     * @param $adSetId
     * @param $adCreativeId
     * @param $parameters
     * @return array
     * @throws \Exception
     */
    public function createAd($adAccountId, $adSetId, $adCreativeId, $parameters) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);

            $data = array(
                AdFields::NAME => $parameters["name"],
                AdFields::ADSET_ID => $adSetId,
                AdFields::CREATIVE => array(
                    'creative_id' => $adCreativeId
                ),
            );

            $ad = new Ad(null, (false === strpos($adAccountId,'act_'))?'act_'.$adAccountId:$adAccountId);
            $ad->setData($data);
            $ad->create(array(
                Ad::STATUS_PARAM_NAME => Ad::STATUS_PAUSED,
            ));
        } catch(\Exception $e) {
            _printe($e);
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $ad->getData();
    }

    /**
     * Service that gets an user's ad information
     * @param $adId
     * @return array
     * @throws ConnectorServiceException
     */
    public function getAd($adId) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $ad = new Ad($adId);
            $ad->read(array(
                AdFields::ID,
                AdFields::NAME,
                AdFields::ACCOUNT_ID,
                AdFields::CAMPAIGN_ID,
                AdFields::ADSET_ID,
                AdFields::CREATED_TIME,
                AdFields::CREATIVE,
                AdFields::EFFECTIVE_STATUS,
                AdFields::CONFIGURED_STATUS
            ));
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $ad->getData();
    }

    /**
     * Service that gets a specific ad previews
     * @param $adId
     * @param $adFormat
     * @return array
     * @throws ConnectorServiceException
     */
    public function getAdPreviews($adId, $adFormat) {
        try {
            Api::init($this->clientId, $this->clientSecret, $this->accessToken);
            $ad = new Ad($adId);
            $parameters = array("ad_format" => $adFormat);
            $cursor = $ad->getAdPreviews(array(
                AdPreviewFields::BODY
            ), $parameters);

            // Loop over objects
            $adpreviews = [];
            foreach ($cursor as $adpreview) {
                $adpreviews[] = $adpreview->getData();
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException($e->getMessage(), $e->getCode());
        }

        return $adpreviews;
    }

    /**
     * Service that search geolocation codes from text parameter
     * @param $type     "country", "region", "city", "zip"
     * @param $text     e.g.: "japan", "andalucia", "malaga", "29007"
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
                    'limit' => 500
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

    /**
     * Method that check credentials are present and valid
     * @param array $credentials
     * @throws ConnectorConfigException
     */
    private function checkCredentialsParameters(array $credentials) {
        if ((null === $credentials) || (!is_array($credentials)) || (count($credentials) == 0)) {
            throw new ConnectorConfigException("Invalid credentials set'");
        }
        if ((!isset($credentials["access_token"])) || (null === $credentials["access_token"]) || ("" === $credentials["access_token"])) {
            throw new ConnectorConfigException("'access_token' parameter is required");
        }
    }

    /**
     * Method that check userId is ok
     * @param $userId
     * @throws ConnectorConfigException
     */
    private function checkUser($userId) {
        if ((null === $userId) || ("" === $userId)) {
            throw new ConnectorConfigException("'userId' parameter is required");
        }
    }
}