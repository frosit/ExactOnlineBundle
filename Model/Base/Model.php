<?php

namespace aibianchi\ExactOnlineBundle\Model\Base;

/**
 * Author: Jefferson Bianchi <Jefferson@aibianchi.com>
 * Author: Nils méchin <nils@zangra.com>
 * Author: Maxime Lambot <maxime@lambot.com>.
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

            if ('SalesOrderLines' == $key) {
                $value = $this->encodeSalesOrderLines($value);
            }

            $json[$key] = $value;
        }

        return json_encode($json);
    }

    private function encodeSalesOrderLines($value)
    {
        $salesOrderLines = array();
        foreach ($value as $line) {
            $salesOrderLine = array();
            foreach ($line as $entryKey => $entry) {
                if ('url' === $entryKey or 'primaryKey' === $entryKey) {
                    continue;
                }
                if (null === $entry) {
                    continue;
                }
                $salesOrderLine[$entryKey] = $entry;
            }
            array_push($salesOrderLines, $salesOrderLine);
        }

        return $salesOrderLines;
    }
}
