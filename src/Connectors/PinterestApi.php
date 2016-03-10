<?php
namespace CloudFramework\Service\SocialNetworks\Connectors;

use CloudFramework\Patterns\Singleton;
use CloudFramework\Service\SocialNetworks\Exceptions\AuthenticationException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorConfigException;
use CloudFramework\Service\SocialNetworks\Exceptions\ConnectorServiceException;
use CloudFramework\Service\SocialNetworks\Exceptions\MalformedUrlException;
use CloudFramework\Service\SocialNetworks\Interfaces\SocialNetworkInterface;
use CloudFramework\Service\SocialNetworks\SocialNetworks;

use DirkGroenen\Pinterest\Pinterest;

/**
 * Class PinterestApi
 * @package CloudFramework\Service\SocialNetworks\Connectors
 * @author Salvador Castro <sc@bloombees.com>
 */
class PinterestApi extends Singleton implements SocialNetworkInterface {

    const ID = 'pinterest';
    const PINTEREST_SELF_USER = "me";

    // Pinterest client object
    private $client;

    // API keys
    private $clientId;
    private $clientSecret;
    private $clientScope = array();

    // Auth keys
    private $accessToken;

    /**
     * Set Pinterest Api keys
     * @param $clientId
     * @param $clientSecret
     * @param $clientScope
     * @throws ConnectorConfigException
     */
    public function setApiKeys($clientId, $clientSecret, $clientScope) {
        if ((null === $clientId) || ("" === $clientId)) {
            throw new ConnectorConfigException("'clientId' parameter is required");
        }

        if ((null === $clientSecret) || ("" === $clientSecret)) {
            throw new ConnectorConfigException("'clientSecret' parameter is required");
        }

        if ((null === $clientScope) || (!is_array($clientScope)) || (count($clientScope) == 0)) {
            throw new ConnectorConfigException("'clientScope' parameter is required");
        }

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->clientScope = $clientScope;

        $this->client = new Pinterest($this->clientId, $this->clientSecret);
    }

    /**
     * Compose Pinterest Api credentials array from session data
     * @param string $redirectUrl
     * @throws ConnectorConfigException
     * @throws MalformedUrlException
     * @return array
     */
    public function requestAuthorization($redirectUrl)
    {
        if ((null === $redirectUrl) || (empty($redirectUrl))) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required");
        } else {
            if (!SocialNetworks::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed");
            }
        }

        $authUrl = $this->client->auth->getLoginUrl($redirectUrl, $this->clientScope);

        // Authentication request
        return $authUrl;
    }

    /**
     * @param string $code
     * @param string $redirectUrl
     * @return array
     * @throws AuthenticationException
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     * @throws MalformedUrlException
     */
    public function authorize($code, $redirectUrl)
    {
        if ((null === $code) || ("" === $code)) {
            throw new ConnectorConfigException("'code' parameter is required");
        }

        if ((null === $redirectUrl) || (empty($redirectUrl))) {
            throw new ConnectorConfigException("'redirectUrl' parameter is required");
        } else {
            if (!SocialNetworks::wellFormedUrl($redirectUrl)) {
                throw new MalformedUrlException("'redirectUrl' is malformed");
            }
        }

        $token = $this->client->auth->getOAuthToken($code);

        $pinterestCredentials = array("access_token" => $token->access_token);

        return $pinterestCredentials;
    }

    /**
     * Method that inject the access token in connector
     * @param array $credentials
     */
    public function setAccessToken(array $credentials) {
        $this->accessToken = $credentials["access_token"];
    }

    /**
     * Service that check if credentials are valid
     * @param $credentials
     * @return mixed
     * @throws ConnectorConfigException
     */
    public function checkCredentials($credentials) {
        $this->checkCredentialsParameters($credentials);

        try {
            return $this->getProfile(SocialNetworks::ENTITY_USER, self::PINTEREST_SELF_USER);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set");
        }
    }

    /**
     * Service that query to Pinterest Api to get user profile
     * @param string $entity "user"
     * @param string $id    user id
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function getProfile($entity, $id)
    {
        $this->checkUser($id);

        try {
            $parameters = array();
            $parameters["fields"] = "id,username,first_name,last_name,bio,created_at,counts,image";
            $this->client->auth->setOAuthToken($this->accessToken);
            $data = $this->client->users->me($parameters);
        } catch(\Exception $e) {
            throw new ConnectorConfigException("Invalid credentials set");
        }

        // Instagram API doesn't return the user's e-mail
        return json_encode($data);
    }

    /**
     * Service that search for an user
     * @param string $entity "user"
     * @param string $id    user id
     * @param $name
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function searchUsers($entity, $id, $username_or_id, $maxTotalResults = null, $numberOfPages = null,
                                $nextPageUrl = null)
    {
        $this->checkUser($id);
        $this->checkName($username_or_id);

        try {
            $this->client->auth->setOAuthToken($this->accessToken);
            $data = $this->client->users->find($username_or_id);
        } catch (Exception $e) {
            throw new ConnectorServiceException("Error searching for an user: " . $e->getMessage(), $e->getCode());
            $pageToken = null;
        }

        return json_encode($data);
    }

    /**
     * Service that query to Pinterest Api for pins of the user
     * @param string $entity "user"
     * @param string $id    user id
     * @param string $query if not null, search this token in the description of the authenticated user's pins
     * @param string $liked if true, search pins liked by the user
     * @param integer $maxResultsPerPage.
     * @param integer $numberOfPages
     * @param string $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPins($entity, $id, $query, $liked, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $this->client->auth->setOAuthToken($this->accessToken);

        $pins = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["limit"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["cursor"] = urldecode($pageToken);
                }

                $parameters["fields"] = "id,link,url,creator,board,created_at,note,color,counts,media,attribution,image,metadata,original_link";

                if (null == $query) {
                    if ($liked) {
                        $pinsList = $this->client->users->getMeLikes($parameters);
                    } else {
                        $pinsList = $this->client->users->getMePins($parameters);
                    }
                } else {
                    $pinsList = $this->client->users->searchMePins($query, $parameters);
                }

                // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                // should be checked that pinterest api is returning an empty list
                if (count($pinsList->all()) == 0) {
                    $pageToken = null;
                    break;
                }

                $pins[$count] = array();
                foreach($pinsList->all() as $pin) {
                    $pins[$count][] = $pin;
                }
                $count++;

                $pageToken = $pinsList->pagination["cursor"];

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    // Make a last call to check if next page is empty
                    $parameters["cursor"] = urldecode($pageToken);
                    if (null == $query) {
                        if ($liked) {
                            $pinsList = $this->client->users->getMeLikes($parameters);
                        } else {
                            $pinsList = $this->client->users->getMePins($parameters);
                        }
                    } else {
                        $pinsList = $this->client->users->searchMePins($query, $parameters);
                    }

                    // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                    // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                    // should be checked that pinterest api is returning an empty list
                    if (count($pinsList->all()) == 0) {
                        $pageToken = null;
                    }
                    break;
                }
            } catch (Exception $e) {
                $pageToken = null;
                throw new ConnectorServiceException("Error exporting pins: " . $e->getMessage(), $e->getCode());
            }
        } while ($pinsList->hasNextPage());

        $pins["pageToken"] = $pageToken;

        return json_encode($pins);
    }

    /**
     * Service that query to Pinterest Api for pins in a board
     * @param string $entity "board"
     * @param string $username
     * @param string $boardname
     * @param integer $maxResultsPerPage.
     * @param integer $numberOfPages
     * @param string $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportPinsFromBoard($entity, $username, $boardname, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkBoard($username, $boardname);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $this->client->auth->setOAuthToken($this->accessToken);

        $pins = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["limit"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["cursor"] = urldecode($pageToken);
                }

                $parameters["fields"] = "id,link,url,creator,board,created_at,note,color,counts,media,attribution,image,metadata,original_link";
                $boardname = strtolower(str_replace(" ","-",urldecode($boardname)));
                $pinsList = $this->client->pins->fromBoard($username."/".$boardname, $parameters);

                // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                // should be checked that pinterest api is returning an empty list
                if (count($pinsList->all()) == 0) {
                    $pageToken = null;
                    break;
                }

                $pins[$count] = array();
                foreach($pinsList->all() as $pin) {
                    $pins[$count][] = $pin;
                }
                $count++;

                $pageToken = $pinsList->pagination["cursor"];

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    // Make a last call to check if next page is empty
                    $parameters["cursor"] = urldecode($pageToken);
                    $pinsList = $this->client->pins->fromBoard($username."/".$boardname, $parameters);

                    // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                    // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                    // should be checked that pinterest api is returning an empty list
                    if (count($pinsList->all()) == 0) {
                        $pageToken = null;
                    }
                    break;
                }
            } catch (Exception $e) {
                $pageToken = null;
                throw new ConnectorServiceException("Error exporting pins from board '".$username."/".$boardname."'': " .
                                                                            $e->getMessage(), $e->getCode());
            }
        } while ($pinsList->hasNextPage());

        $pins["pageToken"] = $pageToken;

        return json_encode($pins);
    }

    /**
     * Service that query to Pinterest Api for boards of the user
     * @param string $entity "user"
     * @param string $id    user id
     * @param string $query if not null, search this token in the description of the authenticated user's pins
     * @param integer $maxResultsPerPage.
     * @param integer $numberOfPages
     * @param string $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportBoards($entity, $id, $query = null, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $this->client->auth->setOAuthToken($this->accessToken);

        $boards = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["limit"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["cursor"] = urldecode($pageToken);
                }

                $parameters["fields"] = "id,name,url,description,creator,created_at,counts,image";

                if (null == $query) {
                    $boardsList = $this->client->users->getMeBoards($parameters);
                } else {
                    $boardsList = $this->client->users->searchMeBoards($query, $parameters);
                }

                // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                // should be checked that pinterest api is returning an empty list
                if (count($boardsList->all()) == 0) {
                    $pageToken = null;
                    break;
                }

                $boards[$count] = array();
                foreach($boardsList->all() as $board) {
                    $boards[$count][] = $board;
                }
                $count++;

                $pageToken = $boardsList->pagination["cursor"];

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    // Make a last call to check if next page is empty
                    $parameters["cursor"] = urldecode($pageToken);
                    if (null == $query) {
                        $boardsList = $this->client->users->getMeBoards($parameters);
                    } else {
                        $boardsList = $this->client->users->searchMeBoards($query, $parameters);
                    }

                    // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                    // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                    // should be checked that pinterest api is returning an empty list
                    if (count($boardsList->all()) == 0) {
                        $pageToken = null;
                    }
                    break;
                }
            } catch (Exception $e) {
                $pageToken = null;
                throw new ConnectorServiceException("Error exporting boards: " . $e->getMessage(), $e->getCode());
            }
        } while ($boardsList->hasNextPage());

        $boards["pageToken"] = $pageToken;

        return json_encode($boards);
    }

    /**
     * Service that query to Pinterest Api for users the user is followed by
     * @param string $entity "user"
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportFollowers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $this->client->auth->setOAuthToken($this->accessToken);

        $followers = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["limit"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["cursor"] = urldecode($pageToken);
                }

                $followersList = $this->client->users->getMeFollowers($parameters);

                // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                // should be checked that pinterest api is returning an empty list
                if (count($followersList->all()) == 0) {
                    $pageToken = null;
                    break;
                }

                $followers[$count] = array();
                foreach($followersList->all() as $follower) {
                    $followers[$count][] = $follower;
                }
                $count++;

                $pageToken = $followersList->pagination["cursor"];

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    // Make a last call to check if next page is empty
                    $parameters["cursor"] = urldecode($pageToken);
                    $followersList = $this->client->users->getMeFollowers($parameters);

                    // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                    // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                    // should be checked that pinterest api is returning an empty list
                    if (count($followersList->all()) == 0) {
                        $pageToken = null;
                    }
                    break;
                }
            } catch (Exception $e) {
                $pageToken = null;
                throw new ConnectorServiceException("Error exporting followers: " . $e->getMessage(), $e->getCode());
            }
        } while ($followersList->hasNextPage());

        $followers["pageToken"] = $pageToken;

        return json_encode($followers);
    }

    /**
     * Service that query to Pinterest Api for users the user follows
     * @param string $entity "user"
     * @param string $id    user id
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @param $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportSubscribers($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $this->client->auth->setOAuthToken($this->accessToken);

        $subscribers = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["limit"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["cursor"] = urldecode($pageToken);
                }

                $subscribersList = $this->client->following->users($parameters);

                // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                // should be checked that pinterest api is returning an empty list
                if (count($subscribersList->all()) == 0) {
                    $pageToken = null;
                    break;
                }

                $subscribers[$count] = array();
                foreach($subscribersList->all() as $subscriber) {
                    $subscribers[$count][] = $subscriber;
                }
                $count++;

                $pageToken = $subscribersList->pagination["cursor"];

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    // Make a last call to check if next page is empty
                    $parameters["cursor"] = urldecode($pageToken);
                    $subscribersList = $this->client->following->users($parameters);

                    // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                    // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                    // should be checked that pinterest api is returning an empty list
                    if (count($subscribersList->all()) == 0) {
                        $pageToken = null;
                    }
                    break;
                }
            } catch (Exception $e) {
                $pageToken = null;
                throw new ConnectorServiceException("Error exporting subscribers: " . $e->getMessage(), $e->getCode());
            }
        } while ($subscribersList->hasNextPage());

        $subscribers["pageToken"] = $pageToken;

        return json_encode($subscribers);
    }

    /**
     * Service that query to Pinterest Api for the boards that the authenticated user follows
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxResultsPerPage.
     * @param integer $numberOfPages
     * @param string $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportFollowingBoards($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $this->client->auth->setOAuthToken($this->accessToken);

        $boards = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["limit"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["cursor"] = urldecode($pageToken);
                }

                $parameters["fields"] = "id,name,url,description,creator,created_at,counts,image";

                $boardsList = $this->client->following->boards($parameters);

                // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                // should be checked that pinterest api is returning an empty list
                if (count($boardsList->all()) == 0) {
                    $pageToken = null;
                    break;
                }

                $boards[$count] = array();
                foreach($boardsList->all() as $board) {
                    $boards[$count][] = $board;
                }
                $count++;

                $pageToken = $boardsList->pagination["cursor"];

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    // Make a last call to check if next page is empty
                    $parameters["cursor"] = urldecode($pageToken);
                    $boardsList = $this->client->following->boards($parameters);

                    // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                    // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                    // should be checked that pinterest api is returning an empty list
                    if (count($boardsList->all()) == 0) {
                        $pageToken = null;
                    }
                    break;
                }
            } catch (Exception $e) {
                $pageToken = null;
                throw new ConnectorServiceException("Error exporting following boards: " . $e->getMessage(), $e->getCode());
            }
        } while ($boardsList->hasNextPage());

        $boards["pageToken"] = $pageToken;

        return json_encode($boards);
    }

    /**
     * Service that query to Pinterest Api for the topics that the authenticated user follows
     * @param string $entity "user"
     * @param string $id    user id
     * @param integer $maxResultsPerPage.
     * @param integer $numberOfPages
     * @param string $pageToken
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function exportFollowingInterests($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken) {
        $this->checkUser($id);
        $this->checkPagination($maxResultsPerPage, $numberOfPages);
        $this->client->auth->setOAuthToken($this->accessToken);

        $interests = array();
        $count = 0;

        do {
            try {
                $parameters = array();
                $parameters["limit"] = $maxResultsPerPage;

                if ($pageToken) {
                    $parameters["cursor"] = urldecode($pageToken);
                }

                $interestsList = $this->client->following->interests($parameters);

                // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                // should be checked that pinterest api is returning an empty list
                if (count($interestsList->all()) == 0) {
                    $pageToken = null;
                    break;
                }

                $boards[$count] = array();
                foreach($interestsList->all() as $interest) {
                    $interests[$count][] = $interest;
                }
                $count++;

                $pageToken = $interestsList->pagination["cursor"];

                // If number of pages is zero, then all elements are returned
                if (($numberOfPages > 0) && ($count == $numberOfPages)) {
                    // Make a last call to check if next page is empty
                    $parameters["cursor"] = urldecode($pageToken);
                    $interestsList = $this->client->following->interests($parameters);

                    // The strange pagination behaviour in Pinterest: although there aren't more elements / more pages,
                    // current list object returns cursor/pagetoken to go to the next page, what is obvously empty, so it
                    // should be checked that pinterest api is returning an empty list
                    if (count($interestsList->all()) == 0) {
                        $pageToken = null;
                    }
                    break;
                }
            } catch (Exception $e) {
                $pageToken = null;
                throw new ConnectorServiceException("Error exporting following interests: " . $e->getMessage(), $e->getCode());
            }
        } while ($interestsList->hasNextPage());

        $interests["pageToken"] = $pageToken;

        return json_encode($interests);
    }

    /**
     * Service that query to Pinterest Api to get settings of a board
     * @param $entity   "board"
     * @param $username
     * @param $boardname
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function getBoard($entity, $username, $boardname) {
        $this->checkBoard($username, $boardname);
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $parameters["fields"] = "id,name,url,description,creator,created_at,counts,image";
            $boardname = strtolower(str_replace(" ","-",urldecode($boardname)));
            $board = $this->client->boards->get($username."/".$boardname, $parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error getting board settings: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode($board);
    }

    /**
     * Service that creates a new board for the user in Pinterest
     * @param $entity   "user"
     * @param $id       user id
     * @param $name
     * @param $description
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function createBoard($entity, $id, $name, $description) {
        $this->checkUser($id);
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $parameters = array("name" => $name);

            if (null !== $description) {
                $parameters["description"] = $description;
            }

            $board = $this->client->boards->create($parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error creating board: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode($board);
    }

    /**
     * Service that edit an existing board in Pinterest
     * @param $entity   "board"
     * @param $username
     * @param $boardname
     * @param $name
     * @param $description
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function editBoard($entity, $username, $boardname, $name, $description) {
        $this->checkBoard($username, $boardname);
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $parameters = array();
            if (null !== $name) {
                $parameters["name"] = $name;
            }

            if (null !== $description) {
                $parameters["description"] = $description;
            }

            $boardname = strtolower(str_replace(" ","-",urldecode($boardname)));
            $board = $this->client->boards->edit($username."/".$boardname,$parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error editing board: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode($board);
    }

    /**
     * Service that delete an existing board in Pinterest
     * @param $entity   "board"
     * @param $username
     * @param $boardname
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function deleteBoard($entity, $username, $boardname) {
        $this->checkBoard($username, $boardname);
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $boardname = strtolower(str_replace(" ","-",urldecode($boardname)));
            $this->client->boards->delete($username."/".$boardname);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error deleting board: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode(array("status"=>"success"));
    }

    /**
     * Service that query to Pinterest Api to get settings of a pin
     * @param $entity   "pin"
     * @param $id       pin id
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function getPin($entity, $id) {
        $this->checkPin($id);
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $parameters["fields"] = "id,link,url,creator,board,created_at,note,color,counts,media,attribution,image,metadata,original_link";
            $pin = $this->client->pins->get($id, $parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error getting pin settings: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode($pin);
    }

    /**
     * Service that creates a new pin in one of the user's boards in Pinterest
     * @param $entity   "user"
     * @param $id       user id
     * @param $username
     * @param $boardname
     * @param $note
     * @param $link
     * @param $imageType
     * @param $image
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function createPin($entity, $id, $username, $boardname, $note, $link, $imageType, $image) {
        $this->checkUser($id);
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $boardname = strtolower(str_replace(" ","-",urldecode($boardname)));
            $parameters = array(
                "note" => $note,
                $imageType => $image,
                "board" => $username."/".$boardname
            );

            if (null !== $link) {
                $parameters["link"] = $link;
            }

            $pin = $this->client->pins->create($parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error creating pin: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode($pin);
    }

    /**
     * Service that edit an existing pin in Pinterest
     * @param $entity   "pin"
     * @param $id
     * @param $board
     * @param $note
     * @param $link
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function editPin($entity, $id, $board, $note, $link) {
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $parameters = array(
                "note" => $note
            );

            if (null !== $board) {
                $parameters["board"] = strtolower(str_replace(" ","-",$board));
            }

            if (null !== $link) {
                $parameters["link"] = $link;
            }

            $pin = $this->client->pins->edit($id, $parameters);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error editing pin: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode($pin);
    }

    /**
     * Service that delete an existing board in Pinterest
     * @param $entity   "pin"
     * @param $id
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function deletePin($entity, $id) {
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            $this->client->pins->delete($id);
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error deleting pin: ' . $e->getMessage(), $e->getCode());
        }

        return json_encode(array("status"=>"success"));
    }

    /**
     * Service that modify the relationship between the authenticated user and the target user.
     * @param string $entity "user"
     * @param string $id    user id
     * @param $userId
     * @param $action
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function modifyUserRelationship($entity, $id, $userId, $action) {
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            if ("follow" === $action) {
                $this->client->following->followUser($userId);
            } else {
                $this->client->following->unfollowUser($userId);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error modifying relationship with user "'.$userId.'": ' .
                                                                        $e->getMessage(), $e->getCode());
        }

        return json_encode(array("status"=>"success"));
    }

    /**
     * Service that modify the relationship between the authenticated user and a board.
     * @param string $entity "user"
     * @param string $id    user id
     * @param $boardId
     * @param $action
     * @return string
     * @throws ConnectorConfigException
     * @throws ConnectorServiceException
     */
    public function modifyBoardRelationship($entity, $id, $boardId, $action) {
        $this->client->auth->setOAuthToken($this->accessToken);

        try {
            if ("follow" === $action) {
                $this->client->following->followBoard($boardId);
            } else {
                $this->client->following->unfollowBoard($boardId);
            }
        } catch(\Exception $e) {
            throw new ConnectorServiceException('Error modifying relationship with board "'.$boardId.'": ' .
                $e->getMessage(), $e->getCode());
        }

        return json_encode(array("status"=>"success"));
    }

    public function exportPosts($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportPosts() method.
    }

    public function exportMedia($entity, $id, $maxResultsPerPage, $numberOfPages, $pageToken)
    {
        // TODO: Implement exportMedia() method.
    }

    public function importMedia($entity, $id, $parameters)
    {
        // TODO: Implement importMedia() method.
    }

    public function post($entity, $id, array $parameters)
    {
        // TODO: Implement post() method.
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

    /**
     * Method that check search name is ok
     * @param $name
     * @throws ConnectorConfigException
     */
    private function checkName($name) {
        if ((null === $name) || ("" === $name)) {
            throw new ConnectorConfigException("'name' parameter is required");
        }
    }

    /**
     * Method that check boardId is ok
     * @param $username
     * @param $boardname
     * @throws ConnectorConfigException
     */
    private function checkBoard($username, $boardname) {
        if ((null === $username) || ("" === $username) ||
            (null === $boardname) || ("" === $boardname)) {
            throw new ConnectorConfigException("'boardId' parameter is required");
        }
    }

    /**
     * Method that check pinId is ok
     * @param $pinId
     * @throws ConnectorConfigException
     */
    private function checkPin($pinId) {
        if ((null === $pinId) || ("" === $pinId)) {
            throw new ConnectorConfigException("'pinId' parameter is required");
        }
    }

    /**
     * Method that check pagination parameters are ok
     * @param $maxResultsPerPage
     * @param $numberOfPages
     * @throws ConnectorConfigException
     */
    private function checkPagination($maxResultsPerPage, $numberOfPages) {
        if (null === $maxResultsPerPage) {
            throw new ConnectorConfigException("'maxResultsPerPage' parameter is required");
        } else if (!is_numeric($maxResultsPerPage)) {
            throw new ConnectorConfigException("'maxResultsPerPage' parameter is not numeric");
        }

        if (null === $maxResultsPerPage) {
            throw new ConnectorConfigException("'numberOfPages' parameter is required");
        } else if (!is_numeric($numberOfPages)) {
            throw new ConnectorConfigException("'numberOfPages' parameter is not numeric");
        }
    }
}