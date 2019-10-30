<?php

namespace aibianchi\ExactOnlineBundle\Model\Base;

/**
 * Author: Jefferson Bianchi <Jefferson@aibianchi.com>
 * Author: Nils méchin <nils@zangra.com>
 * Author: Maxime Lambot <maxime@lambot.com>
 */
abstract class Model
{
    public function toJson($skipNullValues = null)
    {
        $json = array();
        foreach ($this as $key => $value) {
            if ('url' === $key or 'primaryKey' === $key) {
                continue;
            }

            if (null === $value) {
                continue;
            }

            $json[$key] = $value;
        }

        return json_encode($json);
    }
}
