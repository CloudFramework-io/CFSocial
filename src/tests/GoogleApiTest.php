<?php
namespace CloudFramework\Service\SocialNetworks\Tests;

use CloudFramework\Service\SocialNetworks\Connectors\GoogleApi;
use GuzzleHttp\Client;

/**
 * Class GoogleApiTest
 * @author Salvador Castro <sc@bloombees.com>
 */
class GoogleApiTest extends \PHPUnit_Framework_TestCase {
    private static $redirectUrl = "http://localhost:9081/socialnetworks?googleOAuthCallback";

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 600
     */
    public function testNoApiKeys() {
        $apiKeys = array();

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getAuthUrl($apiKeys, self::$redirectUrl);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 601
     */
    public function testNoClient() {
        $apiKeys = array(
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getAuthUrl($apiKeys, self::$redirectUrl);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 603
     */
    public function testNoSecret() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com"
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getAuthUrl($apiKeys, self::$redirectUrl);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 602
     */
    public function testEmptyClient() {
        $apiKeys = array(
            "client" => ""
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getAuthUrl($apiKeys, self::$redirectUrl);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 604
     */
    public function testEmptySecret() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => ""
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getAuthUrl($apiKeys, self::$redirectUrl);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 624
     */
    public function testEmptyRedirectUrl() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getAuthUrl($apiKeys, null);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException
     * @expectedExceptionCode 600
     */
    public function testMalformedRedirectUrl() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getAuthUrl($apiKeys, "malformedurl");
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 600
     */
    public function testEmptyCredentials() {
        $credentials = array();

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 600
     */
    public function testNotIssetApiKeys() {
        $credentials = array("auth_keys" => array());

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 600
     */
    public function testNullApiKeys() {
        $credentials = array("api_keys" => null);

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 600
     */
    public function testNotArrayApiKeys() {
        $credentials = array("api_keys" => "");

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 601
     */
    public function testNoClientInCredentialsApiKeys() {
        $apiKeys = array(
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys);

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

   /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 602
     */
    public function testEmptyClientInCredentialsApiKeys() {
        $apiKeys = array(
            "client" => ""
        );

        $credentials = array("api_keys" => $apiKeys);

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 603
     */
    public function testNoSecretInCredentialsApiKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com"
        );

        $credentials = array("api_keys" => $apiKeys);

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 604
     */
    public function testEmptySecretInCredentialsApiKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => ""
        );

        $credentials = array("api_keys" => $apiKeys);

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 605
     */
    public function testNotIssetAuthKeysInCredentials() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys);

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 605
     */
    public function testNullAuthKeysInCredentials() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => null);

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 605
     */
    public function testNotArrayAuthKeysInCredentials() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => "");

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 606
     */
    public function testNoAccessTokenInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array());

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 607
     */
    public function testEmptyAccessTokenInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array("access_token" => ""));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 608
     */
    public function testNoTokenTypeInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk"
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 609
     */
    public function testEmptyTokenTypeInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => ""
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 610
     */
    public function testNoExpiresInInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer"
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 611
     */
    public function testEmptyNoExpiresInInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => ""
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 612
     */
    public function testNoCreatedInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600"
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 613
     */
    public function testEmptyCreatedInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => ""
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 614
     */
    public function testNoRefreshTokenInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805"
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 615
     */
    public function testEmptyRefreshTokenInCredentialsAuthKeys() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => ""
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->getProfile($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 616
     */
    public function testNoCodeInCredentials() {
        $credentials = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->authorize($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 617
     */
    public function testEmptyCodeInCredentials() {
        $credentials = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j",
            "code" => ""
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->authorize($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\AuthenticationException
     * @expectedExceptionCode 601
     */
    public function testWrongCodeInCredentials() {
        $credentials = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j",
            "code" => "wrongcode"
        );

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->authorize($credentials);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 618
     */
    public function testEmptyPathToImportTo() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
        ));

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->import($credentials, null);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 619
     */
    public function testNoParametersToExport() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
        ));

        $parameters = array();

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 620
     */
    public function testNoUserIdInParametersToExport() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
        ));

        $parameters = array("content" => "test");

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 621
     */
    public function testEmptyUserIdInParametersToExport() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
        ));

        $parameters = array("userId" => "");

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 622
     */
    public function testNoContentInParametersToExport() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
        ));

        $parameters = array("userId" => "me");

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 623
     */
    public function testEmptyContentInParametersToExport() {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
        ));

        $parameters = array("userId" => "me", "content" => "");

        $googleApi = new GoogleApi();

        try {
            $loginUrl = $googleApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    public function testLogin()
    {
        $apiKeys = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
        );

        $googleApi = new GoogleApi();
        $loginUrl = $googleApi->getAuthUrl($apiKeys, self::$redirectUrl);

        $client = new Client();
        $response = $client->get($loginUrl);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetAuth() {
        $credentials = array(
            "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
            "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j",
            "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
            "token_type" => "Bearer",
            "expires_in" => "3600",
            "created" => "1453740805",
            "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
        );

        $googleApi = new GoogleApi();

        $googleCredentials = $googleApi->getAuth($credentials, self::$redirectUrl);

        $this->assertEquals(2, count($googleCredentials));
        $this->assertTrue(array_key_exists("auth_keys", $googleCredentials));
        $this->assertTrue(array_key_exists("api_keys", $googleCredentials));
    }

    public function testExport() {
        $credentials = array(
            "api_keys" => array(
                "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
                "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
            ),
            "auth_keys" => array(
                "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
                "token_type" => "Bearer",
                "expires_in" => "3600",
                "created" => "1453740805",
                "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
            )
        );

        $googleApi = new GoogleApi();

        $dto = $googleApi->export($credentials, array(
            "userId" => "me",
            "content" => "Test Publication"
        ));

        $this->assertInstanceOf('CloudFramework\Service\SocialNetworks\Dtos\ExportDTO', $dto);
    }

    public function testImport() {
        $credentials = array(
            "api_keys" => array(
                "client" => "63108327498-mgodb2hd7n1kpfahvda7npqupk5uhdsp.apps.googleusercontent.com",
                "secret" => "BsWhjY0wXVXDcyQ_m7QiVl6j"
            ),
            "auth_keys" => array(
                "access_token" => "ya29.dALHX7cUlTQncll7v7kJ9UuO45Gy2TIdWHwifFAuRAYyZtO1O7NISVFOnTVKMAGLdxWk",
                "token_type" => "Bearer",
                "expires_in" => "3600",
                "created" => "1453740805",
                "refresh_token" => "1/sJiGDqPwAc_HHQpuyfP3EDrrXuXSre7yYWCv3G2ImvRIgOrJDtdun6zK6XiATCKT"
            )
        );

        $googleApi = new GoogleApi();

        $files = $googleApi->import($credentials, "./");

        if (count($files) > 0) {
            $file = $files[0];
            $this->assertTrue(array_key_exists("id", $file));
            $this->assertTrue(array_key_exists("name", $file));
            $this->assertTrue(array_key_exists("title", $file));
        }
    }
}