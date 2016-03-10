<?php
namespace CloudFramework\Service\SocialNetworks\tests;

use CloudFramework\Service\SocialNetworks\Connectors\InstagramApi;
use GuzzleHttp\Client;

/**
 * Class InstagramApiTest
 * @author Salvador Castro <sc@bloombees.com>
 */
class InstagramApiTest extends \PHPUnit_Framework_TestCase {
    private static $redirectUrl = "http://localhost:9081/socialnetworks?instagramOAuthCallback=1";

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 600
     */
    public function testNoApiKeys() {
        $apiKeys = array();

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getAuthUrl($apiKeys, self::$redirectUrl);
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
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getAuthUrl($apiKeys, self::$redirectUrl);
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
            "client" => "da2b4d273dfe40058dac4846560c4991"
        );

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getAuthUrl($apiKeys, self::$redirectUrl);
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

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getAuthUrl($apiKeys, self::$redirectUrl);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => ""
        );

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getAuthUrl($apiKeys, self::$redirectUrl);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getAuthUrl($apiKeys, null);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getAuthUrl($apiKeys, "malformedurl");
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

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991"
        );

        $credentials = array("api_keys" => $apiKeys);

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => ""
        );

        $credentials = array("api_keys" => $apiKeys);

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys);

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => null);

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => "");

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array());

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array("access_token" => ""));

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->getProfile($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->authorize($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0",
            "code" => ""
        );

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->authorize($credentials);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26"
        ));

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->import($credentials, null);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26"
        ));

        $parameters = array();

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 625
     */
    public function testNoMediaIdInParametersToExport() {
        $apiKeys = array(
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26"
        ));

        $parameters = array("content" => "test");

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    /**
     * @expectedException CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException
     * @expectedExceptionCode 626
     */
    public function testEmptyMediaIdInParametersToExport() {
        $apiKeys = array(
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26"
        ));

        $parameters = array("mediaId" => "");

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->export($credentials, $parameters);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26"
        ));

        $parameters = array("mediaId" => "1234567890");

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->export($credentials, $parameters);
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
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $credentials = array("api_keys" => $apiKeys, "auth_keys" => array(
            "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26"
        ));

        $parameters = array("mediaId" => "1234567890", "content" => "");

        $instagramApi = new InstagramApi();

        try {
            $loginUrl = $instagramApi->export($credentials, $parameters);
        } catch(\Exception $e) {
            throw $e;
        }
    }

    public function testLogin()
    {
        $apiKeys = array(
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0"
        );

        $instagramApi = new InstagramApi();
        $loginUrl = $instagramApi->getAuthUrl($apiKeys, self::$redirectUrl);

        $client = new Client();
        $response = $client->get($loginUrl);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testGetAuth() {
        $credentials = array(
            "client" => "da2b4d273dfe40058dac4846560c4991",
            "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0",
            "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26",
        );

        $instagramApi = new InstagramApi();

        $instagramCredentials = $instagramApi->getAuth($credentials, self::$redirectUrl);

        $this->assertEquals(2, count($instagramCredentials));
        $this->assertTrue(array_key_exists("auth_keys", $instagramCredentials));
        $this->assertTrue(array_key_exists("api_keys", $instagramCredentials));
    }

    public function testImport() {
        $credentials = array(
            "api_keys" => array(
                "client" => "da2b4d273dfe40058dac4846560c4991",
                "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0",
            ),
            "auth_keys" => array(
                "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26",
            )
        );

        $instagramApi = new InstagramApi();

        $files = $instagramApi->import($credentials, "./");

        if (count($files) > 0) {
            $file = $files[0];
            $this->assertTrue(array_key_exists("id", $file));
            $this->assertTrue(array_key_exists("name", $file));
            $this->assertTrue(array_key_exists("title", $file));
        }

        return $files;
    }

    /**
     * @depends testImport
     */
    public function testExport(array $files) {
        if (count($files) > 0) {
            $credentials = array(
                "api_keys" => array(
                    "client" => "da2b4d273dfe40058dac4846560c4991",
                    "secret" => "8e2b540a13dd4ae8a455eac93e17a8f0",
                ),
                "auth_keys" => array(
                    "access_token" => "2706621438.da2b4d2.1a6e4eeebc064bb9a22da7960f608b26",
                )
            );

            $instagramApi = new InstagramApi();

            $dto = $instagramApi->export($credentials, array(
                "mediaId" => $files[0]["id"],
                "content" => "Test Publication"
            ));

            $this->assertInstanceOf('CloudFramework\Service\SocialNetworks\Dtos\ExportDTO', $dto);
        }
    }
}