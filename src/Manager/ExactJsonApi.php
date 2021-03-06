<?php

namespace ExactOnlineBundle\Manager;

use Doctrine\ORM\EntityManager;
use ExactOnlineBundle\DAO\Connection;
use ExactOnlineBundle\DAO\Exception\ApiException;

/**
 * Author: Jefferson Bianchi <jefferson@zangra.com>
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

        return $this->request($entity->getUrl(), 'POST', $json);
    }

    public function remove($entity)
    {
        usleep(Connection::getRateLimitDelay());

        $json = $entity->toJson();
        $keyField = $this->getKeyField();
        $getter = 'get'.$keyField;
        $url = $entity->getUrl()."(guid'".$entity->{$getter}()."')";

        return $this->request($url, 'DELETE', $json);
    }

    public function update($entity)
    {
        usleep(Connection::getRateLimitDelay());

        $json = $entity->toJson();
        $keyField = $this->getKeyField();
        $getter = 'get'.$keyField;
        $url = $entity->getUrl()."(guid'".$entity->{$getter}()."')";

        $result = $this->request($url, 'PUT', $json);
        if ('ErrorDoPersist' == $result) {
            $result = $this->persist($entity);
        }

        return $result;
    }

    public function get($asObject = false)
    {
        $url = $this->model->getUrl();
        $data = $this->request($url, 'GET');

        if ($asObject) {
            return $this->isArrayCollection($this->model, [$data]);
        }

        return $data;
    }

    public function post($json)
    {
        $url = $this->model->getUrl();

        return $this->request($url, 'POST', $json);
    }

    public function put($json)
    {
        $data = json_decode($json);
        $keyField = $this->getKeyField();
        $url = $this->model->getUrl()."(guid'".$data->{$keyField}."')";

        return $this->request($url, 'PUT', $json);
    }

    /**
     * @return int
     */
    public function count()
    {
        $url = $this->model->getUrl().'\\'.'$count';

        return $this->request($url, 'GET');
    }

    /**
     * getList with pagination
     * Warning: Usually this limit, also known as page size, is 60 records but it may vary per end point.
     * https://support.exactonline.com/community/s/knowledge-base#All-All-DNO-Content-resttips.
     *
     * @param null|mixed $page
     * @param mixed      $maxPerPage
     *
     * @return object Collection
     */
    public function getList($page = null, $maxPerPage = 60)
    {
        if (null !== $page) {
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

        $data = $this->request($url, 'GET');

        return $this->isArrayCollection($this->model, $data);
    }

    /**
     *    array('field' => 'searchMe'),   // Criteria
     *    array('date' => 'desc'),        // Order by
     *    5,                              // limit.
     *
     * @param mixed $limit
     *
     *    @return array
     */
    public function findBy(array $criteria, array $select = null, array $orderby = null, $limit = 5)
    {
        // Check if current criteria (value) is a guid
        $guidString = $this->assertGuid(current($criteria)) ? 'guid' : '';

        $url = $this->model->getUrl().'\\?'.'$filter='.key($criteria).' eq '.$guidString."'".current($criteria)."'";

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

        $data = $this->request($url, 'GET');

        return $this->isArrayCollection($this->model, $data);
    }

    /**
     * @param mixed $guid
     * @param mixed $filter
     *
     *  @return object
     */
    public function find($guid, $filter = true)
    {
        $keyField = $this->getKeyField();

        if ($filter) {
            $url = $this->model->getUrl().'?'.'$filter='.$keyField.' eq guid'."'".$guid."'";
        } else {
            $url = $this->model->getUrl().'?'.$keyField.'=guid'."'".$guid."'";
        }

        $data = $this->request($url, 'GET');

        return $this->isSingleObject($data);
    }

    private function request($url, $method, $data = null)
    {
        Connection::setContentType('json');

        return Connection::Request($url, $method, $data);
    }

    /**
     * @return object
     */
    private function isSingleObject(array $data)
    {
        $data = (isset($data[0]) && is_array($data[0])) ? $data[0] : $data;
        $object = $this->model;
        foreach ($data as $key => $item) {
            $setter = 'set'.$key;
            if (method_exists($object, $setter)) {
                $object->{$setter}($item);
            }
        }

        return $object;
    }

    /**
     * @param mixed $entity
     * @param mixed $data
     *
     * @return object collection
     */
    private function isArrayCollection($entity, $data)
    {
        foreach ($data as $keyD => $item) {
            $object = new $entity();
            foreach ($item as $key => $value) {
                $setter = 'set'.$key;
                if (method_exists($object, $setter)) {
                    $object->{$setter}($value);
                }
            }
            array_push($this->list, $object);
        }

        return $this->list;
    }
}
