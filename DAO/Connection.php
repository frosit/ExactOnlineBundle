<?php

namespace aibianchi\ExactOnlineBundle\DAO;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7;
use Doctrine\ORM\EntityManager;
use aibianchi\ExactOnlineBundle\DAO\Exception\ApiException;
use aibianchi\ExactOnlineBundle\Model\Base\Me;
use aibianchi\ExactOnlineBundle\Entity\Exact;

/**
 * Class Connection
 * Author: Jefferson Bianchi
 * Email : Jefferson@aibianchi.com.
 */
class Connection
{
    const CONTENT_TYPE_JSON = 'application/json';
    const CONTENT_TYPE_XML = 'application/xml';

    private static $baseUrl;
    private static $apiUrl;
    private static $authUrl;
    private static $tokenUrl;
    private static $redirectUrl;

    private static $exactClientId;
    private static $exactClientSecret;
    private static $code;

    private static $em;
    private static $instance;
    private $contentType = self::CONTENT_TYPE_JSON;
    private $accept = self::CONTENT_TYPE_JSON.';odata=verbose,text/plain';

    public static function setConfig(array $config, EntityManager $em)
    {
        self::$em = $em;
        self::$baseUrl = $config['baseUrl'];
        self::$apiUrl = $config['apiUrl'];
        self::$authUrl = $config['authUrl'];
        self::$tokenUrl = $config['tokenUrl'];
        self::$redirectUrl = $config['redirectUrl'];
        self::$exactClientId = $config['clientId'];
        self::$exactClientSecret = $config['clientSecret'];
    }

    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /*
    *  Exact api will post on redirect URL
    */
    public static function getAuthorization()
    {
        $url = self::$baseUrl.self::$authUrl;
        $param = array(
                    'client_id' => self::$exactClientId,
                    'redirect_uri' => self::$redirectUrl,
                    'response_type' => 'code',
                    'force_login' => '1',
            );
        $query = http_build_query($param);

        header('Location: '.$url.'?'.$query, true, 302);
        die('Redirect');
    }

    public static function getAccessToken()
    {
        $url = self::$baseUrl.self::$tokenUrl;
        $client = new Client();
        $response = $client->post($url, [
                'form_params' => [
                    'code' => self::$code,
                    'client_id' => self::$exactClientId,
                    'grant_type' => 'authorization_code',
                    'client_secret' => self::$exactClientSecret,
                    'redirect_uri' => self::$redirectUrl,
                ],
            ]
        );

        $body = $response->getBody();
        $obj = json_decode((string) $body);
        self::persistExact($obj);
    }

    private static function persistExact($obj)
    {
        $Exact = self::$em->getRepository('ExactOnlineBundle:Exact')->findLast();
        if (null != $Exact) {
            $code = $Exact->getCode();
        } else {
            $code = self::$code;
        }

        $exact = new Exact();
        $exact->setAccessToken($obj->access_token);
        $exact->setCode($code);
        $exact->setTokenExpires($obj->expires_in);
        $exact->setRefreshToken($obj->refresh_token);

        self::$em->Persist($exact);
        self::$em->flush();
    }

    public static function refreshAccessToken()
    {
        if (self::isExpired()) {
            $Exact = self::$em->getRepository('ExactOnlineBundle:Exact')->findLast();
            $url = self::$baseUrl.self::$tokenUrl;
            $client = new Client();

            $response = $client->post($url, array(
                'form_params' => array(
                    'refresh_token' => $Exact->getRefreshToken(),
                    'grant_type' => 'refresh_token',
                    'client_id' => self::$exactClientId,
                    'client_secret' => self::$exactClientSecret,
                ),
            ));
            $body = $response->getBody();
            $obj = json_decode((string) $body);
            self::persistExact($obj);
        }
    }

    public static function isExpired()
    {
        $Exact = self::$em->getRepository('ExactOnlineBundle:Exact')->findLast();
        if (null == $Exact) {
            return true;
        }
        $createAt = $Exact->getCreatedAt();
        $now = new \DateTime('now');
        $expiresIn = $Exact->getTokenExpires();
        $seconds = ($now->getTimeStamp()) - ($createAt->getTimeStamp());

        if ($expiresIn - 60 < $seconds) {
            return true;
        }

        return false;
    }

    private static function createRequest($method = 'GET', $endpoint, $body = null, array $params = [], array $headers = [])
    {
        $headers = array_merge($headers, [
            'Accept' => $this->accept,
            'Content-Type' => $this->contentType,
            'Prefer' => 'return=representation',
            'X-aibianchi' => 'Exact Online Bundle <https://github.com/zangra-dev/ExactOnlineBundle/>',
        ]);
        $Exact = self::$em->getRepository('ExactOnlineBundle:Exact')->findLast();

        if (null == $Exact->getAccessToken()) {
            throw new ApiException('Access token was not initialized', 498);
        }

        if (!empty($params)) {
            $endpoint .= '?'.http_build_query($params);
        }

        $headers['Authorization'] = 'Bearer '.$Exact->getAccessToken();

        return  $request = new Request($method, $endpoint, $headers, $body);
    }

    public static function Request($url, $method, $json = null)
    {
        self::refreshAccessToken();

        try {
            if ('current/Me' == $url) {
                $url = self::$baseUrl.self::$apiUrl.'/'.$url;
            } else {
                $url = self::$baseUrl.self::$apiUrl.'/'.self::getDivision().'/'.$url;
            }
            $client = new Client();
            $Exact = self::$em->getRepository('ExactOnlineBundle:Exact')->findLast();

            $request = self::createRequest($method, $url, $json);
            $response = $client->send($request);
            $array = self::parseResponse($response);

            if (null == $array) {
                throw new ApiException('no data is present', 204);
            }

            return $array;
        } catch (ApiException $e) {
            throw new ApiException($e->getMessage(), $e->getStatusCode());
        }
    }

    private static function parseResponse(Response $response, $returnSingleIfPossible = true)
    {
        try {
            if (204 === $response->getStatusCode()) {
                throw new ApiException($response->getMessage(), $response->getStatusCode());
            }

            Psr7\rewind_body($response);
            $json = json_decode($response->getBody()->getContents(), true);

            if (is_array($json)) {
                if (array_key_exists('d', $json)) {
                    if (array_key_exists('__next', $json['d'])) {
                        $nextUrl = $json['d']['__next'];
                    } else {
                        $nextUrl = null;
                    }
                    if (array_key_exists('results', $json['d'])) {
                        if ($returnSingleIfPossible && 1 == count($json['d']['results'])) {
                            return $json['d']['results'][0];
                        }

                        return $json['d']['results'];
                    }

                    return $json['d'];
                }
            }

            return $json;
        } catch (\ApiException $e) {
            throw new ApiException($e->getMessage(), $e->getStatusCode());
        }
    }

    private function setContentType($type = 'json')
    {
        if ('xml' === $type) {
            $this->contentType = self::CONTENT_TYPE_XML;
            $this->accept = self::CONTENT_TYPE_XML;
        }

        if ('json' === $type) {
            $this->contentType = self::CONTENT_TYPE_JSON;
        }
    }

    /**
     * @return mixed
     */
    public static function getCode()
    {
        return self::$code;
    }

    /**
     * @param mixed $code
     *
     * @return self
     */
    public static function setCode($code)
    {
        self::$code = $code;

        return self::$code;
    }

    /**
     * @return mixed
     */
    public static function getDivision()
    {
        $me = new Me();

        return $me->getCurrentDivision();
    }
}
