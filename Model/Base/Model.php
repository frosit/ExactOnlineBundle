<?php

namespace aibianchi\ExactOnlineBundle\Model\Base;

use aibianchi\ExactOnlineBundle\DAO\Connection;

/**
 * Author: Jefferson Bianchi
 * Email : Jefferson@aibianchi.com
 */
abstract class Model {

    public function toJson(){

         $json = array();
            foreach($this as $key => $value) {

                if ( $key === "url" or $key === "primaryKey" ){
                    continue;
                }

                if (null === $value) {
                    continue;
                }

                $json[$key] = $value;
            }
            dump($json);
            return json_encode($json);
    }

}

?>