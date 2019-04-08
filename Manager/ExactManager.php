<?php

namespace aibianchi\ExactOnlineBundle\Manager;

use Doctrine\ORM\EntityManager;
use aibianchi\ExactOnlineBundle\DAO\Connection;
use aibianchi\ExactOnlineBundle\DAO\Exception\ApiException;

/**
 * Exact Manager
 * Author: Jefferson Bianchi
 * Mail  : Jefferson@aibianchi.com
 */
class ExactManager
{
    private $list = array();
    private $model;
    private $config;
    private $em;


    public function __construct(EntityManager $em)
    {
        $this->em = $em;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
    * @return void
    */
    public function init(string $code = null, string $country)
    {
        if (!$country) {  throw new Exception("Country can't be null, please check packages/exact_online.yaml"); }
        try {
            Connection::setConfig($country, $this->config, $this->em);

            if (Connection::isExpired()) {
                if ($code == null) {
                    Connection::getAuthorization();
                }
                Connection::setCode($code);
                Connection::getAccessToken();
            }
        } catch (ApiException $e) {
            throw new Exception("Can't initiate connection: ", $e->getCode());
        }
    }

    public function refreshToken(string $country)
    {
        Connection::setConfig($country, $this->config, $this->em);
        Connection::refreshAccessToken($country);
    }

    /**
    * @return Object
    */
    public function getModel($name)
    {
        try {
            $classname   = $cname = "aibianchi\\ExactOnlineBundle\\Model\\".$name;
            $this->model = new $classname();
            return $this;
        } catch (ApiException $e) {
            throw new ApiException("Model doesn't existe : ", $e->getStatusCode());
        }
    }

    /**
    * @return void
    */
    public function persist($entity)
    {
        $json = $entity->toJson();
        Connection::Request($entity->getUrl(), "POST", $json);
    }

    /**
    * @return void
    */
    public function remove($entity)
    {
        $json     = $entity->toJson();
        $keyField = $this->getKeyField();
        $getter   = "get".$keyField;
        $url      = $entity->getUrl()."(guid'".$entity->$getter()."')";
        Connection::Request($url, "DELETE", $json);
    }

    /**
    * @return void
    */
    public function update($entity)
    {
        $json     = $entity->toJson();
        $keyField = $this->getKeyField();
        $getter   = "get".$keyField;
        $url      = $entity->getUrl()."(guid'".$entity->$getter()."')";
        Connection::Request($url, "PUT", $json);
    }

    /**
    * @return integer
    */
    public function count()
    {
        $url  =  $this->model->getUrl()."\\"."\$count";
        $data =  Connection::Request($url, "GET");
        return $data;
    }

    /**
    * getList with pagination
    * Warning: Usually this limit, also known as page size, is 60 records but it may vary per end point.
    * https://support.exactonline.com/community/s/knowledge-base#All-All-DNO-Content-resttips
    * @return Object Collection
    */
    public function getList(int $page = 1, int $maxPerPage = 5)
    {
        if ($maxPerPage>=60) {
            throw new ApiException("60 records maximum per page", 406);
        }

        $total    = $this->count();

        if ($maxPerPage>$total) {
            throw new ApiException("Maximum records is: ".$total, 406);
        }

        $nbrPages = ceil($total/$maxPerPage);
        $skip     = ($page*$maxPerPage)-$maxPerPage;
        $url      = $this->model->getUrl()."\\?"."\$skip=".$skip."&\$top=".$maxPerPage;
        $data     = Connection::Request($url, "GET");

        return $this->isArrayCollection($this->model, $data);
    }

    /**
     * You could use raw url with ODATA format
     * ex : $rawUrl = "$filter=(baseRate ge 60 and baseRate lt 300) and city eq 'Los Angeles'"
     * @param  string $rawUrl
     * @return array
     */
    public function findByRawUrl($rawUrl){

        $url = $this->model->getUrl()."\?"."\$".$rawUrl;
        $data =  Connection::Request($url, "GET");
        return $this->isArrayCollection($this->model, $data);

    }

    /**
    *    array('field' => 'searchMe'),   // Criteria
    *    array('date' => 'desc'),        // Order by
    *    5,                              // limit
    *    @return array
    */
    public function findBy(array $criteria, array $select = null, array $orderby = null, int $limit  = 5)
    {
        $url = $this->model->getUrl()."\?"."\$filter=".key($criteria)." eq '".current($criteria)."'";

        if ($select != null) {
            $url = $url."&\$select=";
            for ($i=0; $i<count($select); $i++) {
                $url = $url.$select[$i].", ";
            }
        }

        if ($limit>0) {
            $url = $url."&\$top=".$limit;
        }

        if ($orderby != null) {
            $url = $url."&\$orderby=".key($orderby)." ".current($orderby);
        }

        if (is_integer(current($criteria))){
            $url = $this->model->getUrl()."\?"."\$filter=".key($criteria)." eq ".current($criteria)."";
            $data =  Connection::Request($url, "GET");
            return $this->isSingleObject($this->model, $data);

        }

        $data =  Connection::Request($url, "GET");

        return $this->isArrayCollection($this->model, $data);
    }

    /**
    *
    *  @return Object
    */
    public function find(string $guid)
    {
        $keyField = $this->getKeyField();

        $url  = $this->model->getUrl()."\?"."\$filter=".$keyField." eq guid"."'".$guid."'";
        $data = Connection::Request($url, "GET");

        return $this->isSingleObject($data);
    }

    /**
    *
    * @return PrimaryKey field
    */
    private function getKeyField()
    {
        if (method_exists($this->model, "getPrimaryKey")) {
            $primaryKey = $entity->getPrimaryKey();
        } else {
            $primaryKey = "ID";
        }

        return $primaryKey;
    }

    /**
    * @return Object
    */
    private function isSingleObject($data)
    {
        $object = $this->model;
        foreach ($data as $key => $item) {
            $setter = "set".$key;
            if (method_exists($object, $setter)) {
                $object->$setter($item);
            }
        }
        return $object;
    }

    /**
    * @return Object collection
    */
    private function isArrayCollection($entity, $data)
    {
        foreach ($data as $keyD => $item) {
            $object = new $entity();
            foreach ($item as $key => $value) {
                $setter = "set".$key;
                if (method_exists($object, $setter)) {
                    $object->$setter($value);
                }
            }
            array_push($this->list, $object);
        }
        return $this->list;
    }
}
