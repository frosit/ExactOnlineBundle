<?php

namespace aibianchi\ExactOnlineBundle\Manager;

use Doctrine\ORM\EntityManager;
use aibianchi\ExactOnlineBundle\DAO\Connection;
use aibianchi\ExactOnlineBundle\DAO\Exception\ApiException;
use aibianchi\ExactOnlineBundle\Model\BillOfMaterialMaterial;

/**
 * Author: Jefferson Bianchi <Jefferson@aibianchi.com>
 * Author: Nils méchin <nils@zangra.com>
 * Author: Maxime Lambot <maxime@lambot.com>.
 */
abstract class ExactManager
{
    protected $list = [];
    protected $model;
    protected $config;
    protected $em;
    protected $logger;

    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function setConfig($config)
    {
        $this->config = $config;
    }

    public function init($code)
    {
        try {
            Connection::setConfig($this->config, $this->em);

            if (Connection::isExpired()) {
                if (null == $code) {
                    Connection::getAuthorization();
                }
                Connection::setCode($code);
                Connection::getAccessToken();
            }
        } catch (ApiException $e) {
            throw new Exception("Can't initiate connection: ", $e->getCode());
        }
    }

    public function refreshToken()
    {
        Connection::setConfig($this->config, $this->em);
        Connection::refreshAccessToken();
    }

    /**
     * Assert passewd value is a GUID.
     *
     * @param string $guid a GUID string probably
     *
     * @return bool
     */
    protected function assertGuid($guid)
    {
        $UUIDv4 = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';

        return 1 === preg_match($UUIDv4, $guid);
    }
}
