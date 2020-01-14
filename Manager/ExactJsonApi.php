<?php

namespace aibianchi\ExactOnlineBundle\Manager;

use Doctrine\ORM\EntityManager;
use aibianchi\ExactOnlineBundle\DAO\Connection;
use aibianchi\ExactOnlineBundle\DAO\Exception\ApiException;
use aibianchi\ExactOnlineBundle\Model\BillOfMaterialMaterial;
use aibianchi\ExactOnlineBundle\Model\WebhookSubscription;

/**
 * Author: Jefferson Bianchi <Jefferson@aibianchi.com>
 * Author: Nils méchin <nils@zangra.com>
 * Author: Maxime Lambot <maxime@lambot.com>.
 */
class ExactJsonApi extends ExactManager
{

    public function __construct(EntityManager $em)
    {
        parent::__construct($em);
    }

    public function setConfig($config)
    {
        parent::setConfig($config);
    }

    public function persist($entity)
    {
        usleep(Connection::getRateLimitDelay());
        $json = $entity->toJson();
        $result = Connection::Request($entity->getUrl(), 'POST', $json);
        return $result;
    }

    public function remove($entity)
    {
        usleep(Connection::getRateLimitDelay());

        $json = $entity->toJson();
        $keyField = $this->getKeyField();
        $getter = 'get'.$keyField;
        $url = $entity->getUrl()."(guid'".$entity->$getter()."')";

        return Connection::Request($url, 'DELETE', $json);
    }

    public function update($entity)
    {
        usleep(Connection::getRateLimitDelay());
        $json = $entity->toJson();
        $keyField = $this->getKeyField();
        $getter = 'get'.$keyField;
        $url = $entity->getUrl()."(guid'".$entity->$getter()."')";

        $result = Connection::Request($url, 'PUT', $json);
        if ( $result == "ErrorDoPersist") {
            $result = $this->persist($entity);
        }
        return $result;
    }

    public function get($asObject = false)
    {
        $url = $this->model->getUrl();
        $data = Connection::Request($url, 'GET');

        if ($asObject) {
            return $this->isArrayCollection($this->model, [$data]);
        }

        return $data;
    }

    /**
     * @return int
     */
    public function count()
    {
        $url = $this->model->getUrl().'\\'.'$count';
        $data = Connection::Request($url, 'GET');

        return $data;
    }

    /**
     * getList with pagination
     * Warning: Usually this limit, also known as page size, is 60 records but it may vary per end point.
     * https://support.exactonline.com/community/s/knowledge-base#All-All-DNO-Content-resttips.
     *
     * @return object Collection
     */
    public function getList($page = null, $maxPerPage = 60)
    {
        if (null !== $page) {
            # code...
            if ($maxPerPage >= 60) {
                throw new ApiException('60 records maximum per page', 406);
            }

            $total = $this->count();

            if ($maxPerPage > $total) {
                throw new ApiException('Maximum records is: '.$total, 406);
            }

            $nbrPages = ceil($total / $maxPerPage);
            $skip = ($page * $maxPerPage) - $maxPerPage;

            $url = $this->model->getUrl().'\\?'.'$skip='.$skip.'&$top='.$maxPerPage;
        } else {
            $url = $this->model->getUrl().'\\?'.'&$top='.$maxPerPage;
        }

        $data = Connection::Request($url, 'GET');

        return $this->isArrayCollection($this->model, $data);
    }

    /**
     *    array('field' => 'searchMe'),   // Criteria
     *    array('date' => 'desc'),        // Order by
     *    5,                              // limit.
     *
     *    @return array
     */
    public function findBy(array $criteria, array $select = null, array $orderby = null, $limit = 5)
    {
        // Check if current criteria (value) is a guid
        $guidString = $this->assertGuid(current($criteria)) ? 'guid' : '';

        $url = $this->model->getUrl()."\?".'$filter='.key($criteria).' eq '.$guidString."'".current($criteria)."'";

        if (null != $select) {
            $url = $url.'&$select=';
            for ($i = 0; $i < count($select); ++$i) {
                $url = $url.$select[$i].', ';
            }
        }

        if ($limit > 0) {
            $url = $url.'&$top='.$limit;
        }

        if (null != $orderby) {
            $url = $url.'&$orderby='.key($orderby).' '.current($orderby);
        }

        $data = Connection::Request($url, 'GET');

        return $this->isArrayCollection($this->model, $data);
    }

    /**
     *  @return object
     */
    public function find($guid)
    {
        $keyField = $this->getKeyField();

        $url = $this->model->getUrl()."\?".'$filter='.$keyField.' eq guid'."'".$guid."'";
        $data = Connection::Request($url, 'GET');

        return $this->isSingleObject($data);
    }

    /**
     * @return object
     */
    private function isSingleObject($data)
    {
        $object = $this->model;
        foreach ($data as $key => $item) {
            $setter = 'set'.$key;
            if (method_exists($object, $setter)) {
                $object->$setter($item);
            }
        }

        return $object;
    }

    /**
     * @return object collection
     */
    private function isArrayCollection($entity, $data)
    {
        foreach ($data as $keyD => $item) {
            $object = new $entity();
            foreach ($item as $key => $value) {
                $setter = 'set'.$key;
                if (method_exists($object, $setter)) {
                    $object->$setter($value);
                }
            }
            array_push($this->list, $object);
        }

        return $this->list;
    }
}
