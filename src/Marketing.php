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
    public function setApiKeys($social, $clientId, $clientSecret, $clientScope = array(), $redirectUrl = null) {
        $api = $this->getSocialApi($social);
        return $api->setApiKeys($clientId, $clientSecret, $clientScope, $redirectUrl);
    }

    /**
     * Service that set the access token for social network
     * @param $social
     * @param array $credentials
     * @return mixed
     * @throws \Exception
     */
    public function setAccessToken($social, array $credentials) {
        $api = $this->getSocialApi($social);
        return $api->setAccessToken($credentials);
    }

    /**
     * Service to request authorization to the social network
     * @param string $social
     * @param string $redirectUrl
     * @param boolean $force
     * @return mixed
     * @throws \Exception
     */
    public function requestAuthorization($social, $redirectUrl, $force = false)
    {
        $api = $this->getSocialApi($social);
        if($force && method_exists($api, 'forceAuth')) {
            $api->forceAuth(true);
        }
        return $api->requestAuthorization($redirectUrl);
    }

    /**
     * Service that authorize a user in the social network.
     * (This method receives the callback from the social network after login)
     * @param string $social
     * @param string $code
     * @param string $verifier
     * @param string $redirectUrl
     * @return mixed
     * @throws \Exception
     */
    public function confirmAuthorization($social, $code = null, $verifier = null, $redirectUrl = null)
    {
        $api = $this->getSocialApi($social);
        return $api->authorize($code, $verifier, $redirectUrl);
    }

    /**
     * Service that check if session user's credentials are authorized and not expired / revoked
     * @param $social
     * @param $credentials
     * @return mixed
     * @throws \Exception
     */
    public function checkCredentials($social, $credentials) {
        $api = $this->getSocialApi($social);
        return $api->checkCredentials($credentials);
    }

    /**
     * Service that query to a social network api to get user profile
     * @param string $social
     * @param string $id user id
     * @return mixed
     * @throws \Exception
     */
    public function getProfile($social, $id) {
        $api = $this->getSocialApi($social);
        return $api->getProfile($id);
    }

    /**
     * Service that get the user's current ad account data
     * @param $social
     * @return mixed
     * @throws \Exception
     */
    public function getCurrentUserAdAccount($social) {
        $api = $this->getSocialApi($social);
        return $api->getCurrentUserAdAccount();
    }

    /**
     * Service that gets user's ad accounts
     * @param $social
     * @return mixed
     * @throws \Exception
     */
    public function exportUserAdAccounts($social) {
        $api = $this->getSocialApi($social);
        return $api->exportUserAdAccounts();
    }

    /**
     * Service that get the user's ad account data by its id
     * @param $social
     * @param $id - Ad Account Id
     * @return mixed
     * @throws \Exception
     */
    public function getAdAccount($social, $id) {
        $api = $this->getSocialApi($social);
        return $api->getAdAccount($id);
    }

    /**
     * Service that gets user's ad account campaigns
     * @param $social
     * @param $id - Ad Account Id
     * @return mixed
     * @throws \Exception
     */
    public function exportUserAdAccountCampaigns($social, $id) {
        $api = $this->getSocialApi($social);
        return $api->exportUserAdAccountCampaigns($id);
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
    public function createCampaign($social, $adAccountId, $parameters) {
        $api = $this->getSocialApi($social);
        return $api->createCampaign($adAccountId, $parameters);
    }

    /**
     * Service that gets an user's campaign information
     * @param $social
     * @param $campaignId
     * @return mixed
     * @throws \Exception
     */
    public function getCampaign($social, $campaignId) {
        $api = $this->getSocialApi($social);
        return $api->getCampaign($campaignId);
    }

    /**
     * Service that gets the adsets from an existing campaign
     * @param $social
     * @param $campaignId
     * @return mixed
     * @throws \Exception
     */
    public function getCampaignAdSets($social, $campaignId) {
        $api = $this->getSocialApi($social);
        return $api->getCampaignAdSets($campaignId);
    }

    /**
     * Service that gets the ads from an existing campaign
     * @param $social
     * @param $campaignId
     * @return mixed
     * @throws \Exception
     */
    public function getCampaignAds($social, $campaignId) {
        $api = $this->getSocialApi($social);
        return $api->getCampaignAds($campaignId);
    }

    /**
     * Service that gets the ads from an existing adset
     * @param $social
     * @param $adsetId
     * @return mixed
     * @throws \Exception
     */
    public function getAdsetAds($social, $adsetId) {
        $api = $this->getSocialApi($social);
        return $api->getAdsetAds($adsetId);
    }

    /**
     * Service that get creates a new campaign into an user's ad account
     * @param $social
     * @param $campaignId
     * @param $parameters
     *      "name" Name of the campaign (optional)
     *      "objective" Objective (optional):
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
     *      "status" Status of the campaign (optional):
     *          'ACTIVE'
     *          'PAUSED'
     * @return mixed
     * @throws \Exception
     */
    public function updateCampaign($social, $campaignId, $parameters) {
        $api = $this->getSocialApi($social);
        return $api->updateCampaign($campaignId, $parameters);
    }

    /**
     * Service that deletes an user's campaign
     * @param $social
     * @param $campaignId
     * @return mixed
     * @throws \Exception
     */
    public function deleteCampaign($social, $campaignId) {
        $api = $this->getSocialApi($social);
        return $api->deleteCampaign($campaignId);
    }

    /**
     * Service that creates a new adset associated with a campaign
     * @param $social
     * @param $adAccountId
     * @param $campaignId
     * @param $parameters
     * @return mixed
     * @throws \Exception
     */
    public function createAdSet($social, $adAccountId, $campaignId, $parameters) {
        $api = $this->getSocialApi($social);
        return $api->createAdSet($adAccountId, $campaignId, $parameters);
    }

    /**
     * Service that gets an user's adSet information
     * @param $social
     * @param $adSetId
     * @return mixed
     * @throws \Exception
     */
    public function getAdSet($social, $adSetId) {
        $api = $this->getSocialApi($social);
        return $api->getAdSet($adSetId);
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
     */
    public function updateAdSet($social, $adSetId, $parameters) {
        $api = $this->getSocialApi($social);
        return $api->updateAdSet($adSetId, $parameters);
    }

    /**
     * Service that creates a new adcreative for an existing page post
     * @param $social
     * @param $adAccountId
     * @param $parameters
     * @return mixed
     * @throws \Exception
     */
    public function createExistingPostAdCreative($social, $adAccountId, $parameters) {
        $api = $this->getSocialApi($social);
        return $api->createExistingPostAdCreative($adAccountId, $parameters);
    }

    /**
     * Service that creates a new add associated with the campaign
     * @param $social
     * @param $adAccountId
     * @param $adSetId
     * @param $adCreativeId
     * @param $parameters
     * @return mixed
     * @throws \Exception
     */
    public function createAd($social, $adAccountId, $adSetId, $adCreativeId, $parameters) {
        $api = $this->getSocialApi($social);
        return $api->createAd($adAccountId, $adSetId, $adCreativeId, $parameters);
    }

    /**
     * Service that gets an user's ad information
     * @param $social
     * @param $adId
     * @return array
     * @throws ConnectorServiceException
     */
    public function getAd($social, $adId) {
        $api = $this->getSocialApi($social);
        return $api->getAd($adId);
    }

    /**
     * Service that gets previews from a specific ad
     * @param $social
     * @param $adId
     * @param $adFormat
     * @return mixed
     * @throws \Exception
     */
    public function getAdPreviews($social, $adId, $adFormat) {
        $api = $this->getSocialApi($social);
        return $api->getAdPreviews($adId, $adFormat);
    }
    /**
     * Service that search geolocation codes from text parameter
     * @param $type
     * @param $text
     * @return \FacebookAds\Cursor
     * @throws ConnectorServiceException
     */
    public function searchGeolocationCode($social, $type, $text) {
        $api = $this->getSocialApi($social);
        return $api->searchGeolocationCode($type, $text);
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