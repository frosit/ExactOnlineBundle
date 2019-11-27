<?php

namespace aibianchi\ExactOnlineBundle\Manager;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use aibianchi\ExactOnlineBundle\DAO\Connection;
use aibianchi\ExactOnlineBundle\DAO\Exception\ApiException;
use aibianchi\ExactOnlineBundle\Model\Xml\XmlParamsControl;

/**
 * Author: Jefferson Bianchi <Jefferson@aibianchi.com>
 * Author: Nils méchin <nils@zangra.com>
 * Author: Maxime Lambot <maxime@lambot.com>.
 */
class ExactXmlApi extends ExactManager
{
    const FILE_OPTION_ALL = 'file_option_all';
    const FILE_OPTION_FIRST = 'file_option_first';
    const FILE_OPTION_SAVE = 'file_option_save';
    const RETURN_SIMPLE_XML = 'return_simple_xml';
    const OPTIONS = [self::FILE_OPTION_ALL, self::FILE_OPTION_FIRST, self::FILE_OPTION_SAVE, self::RETURN_SIMPLE_XML];

    protected $em;
    protected $files;
    protected $xmlBodies;
    protected $exactXmlExportDir = 'web/media/exact/xml/export/';
    protected $option;
    protected $nbrElements;
    protected $pageSize;
    protected $bodySize;

    public function __construct(EntityManager $em, $options = [])
    {
        parent::__construct($em);
        $this->files = new ArrayCollection();

        $this->options[] = self::FILE_OPTION_FIRST;
        $this->options[] = self::RETURN_SIMPLE_XML;
        if (!empty($options)) {
            $this->setOptions($options);
        }
    }

    public function setConfig($config)
    {
        parent::setConfig($config);
    }

    /**
     * Exact Export XML entities (Topics). Optionally save to file
     * Create request based on models xml Topic and input params.
     * Check params and values validity.
     *
     * @param array $params
     *
     * @return string ResponseBody
     */
    public function export(array $params, $options = [])
    {
        if (!empty($options)) {
            $this->setOptions($options);
        }

        Connection::setContentType('xml');
        $p = '';

        $url = 'XMLDownload.aspx?Topic='.$this->model::TOPIC;

        $xmlParamsChecker = new XmlParamsControl();
        $xmlParamsChecker->check($this->model::TOPIC, $params);

        foreach ($params as $key => $param) {
            $p .= '&Params_'.$key.'='.$param;
        }

        $url = $url.$p;
        $responseBody = $this->makeRequest($url);

        return $responseBody;
    }

    /**
     * Make request.
     * Check valid xml in response.
     * Get next page if available.
     * Store written file in an arrayCollection.
     *
     * @param string $url
     * @param int    $counter
     *
     * @return string raw body
     */
    private function makeRequest($url, $counter = 1)
    {
        dump(__METHOD__, $counter, $url);
        $responseBody = Connection::Request($url, 'GET');
        $xml = simplexml_load_string($responseBody);

        if (false === $xml) {
            $msg = '';
            foreach (libxml_get_errors() as $error) {
                $msg .= $error->message.' ';
            }
            throw new \Exception('Exact Xml error. '.$msg, 1);
        }

        $this->nbrElements = (string) $xml->Topics->Topic['count'];
        $this->pageSize = (string) $xml->Topics->Topic['pagesize'];
        $this->bodySize = strlen($responseBody);

        if (in_array(self::FILE_OPTION_SAVE, $this->options)) {
            $this->files[] = $this->saveData($responseBody, $counter);
        }

        if (in_array(self::RETURN_SIMPLE_XML, $this->options)) {
            return $xml;
        }

        if (in_array(self::FILE_OPTION_ALL, $this->options) && null !== $url = $this->getNextPage($xml, $url)) {
            $this->makeRequest($url, ++$counter);
        }


        return $responseBody;
    }

    /**
     * Get next paging if any.
     * Adds or replace TSPaging timestamp (TSPaging=0x000000008DDDC820 hex timestamp)
     * and returns the new url or null.
     *
     * @param \SimpleXMLElement $xml
     * @param string            $url
     *
     * @return string|null
     */
    private function getNextPage(\SimpleXMLElement $xml, $url)
    {
        $url = preg_replace('/&TSPaging=0x[0-9A-F]{16}/', '', $url);
        if ($this->nbrElements == $this->pageSize) {
            $tsPaging = '&TSPaging='.(string) $xml->Topics->Topic['ts_d'];

            return $url.$tsPaging;
        }

        return null;
    }

    /**
     * Save response body to file.
     *
     * @param string $responseBody
     * @param int    $counter
     * @param string $type
     *
     * @return string $filename
     */
    private function saveData($responseBody, $counter, $type = 'xml')
    {
        $filename = $this->model::TOPIC.'_'.date('YmdHi').'-'.$counter;
        $file = $this->exactXmlExportDir.$filename.'.'.$type;

        if (is_dir($this->exactXmlExportDir)) {
            file_put_contents($file, $responseBody);

            return $filename;
        }

        throw new \Exception($file.' could not be written. Dir exists ? Permission ?', 1);
    }

    public function setOptions($options)
    {
        foreach ($options as $option) {
            if (!in_array($option, self::OPTIONS)) {
                throw new \Exception("Unknown constant option", 1);
            }
        }
        $this->options = array_merge($this->options, $options);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setSaveOption($saveOption)
    {
        $this->saveOption = $saveOption;
    }

    public function getNbrElements()
    {
        return $this->nbrElements;
    }

    public function getPageSize()
    {
        return $this->pageSize;
    }

    public function getBodySize()
    {
        return $this->bodySize;
    }

    public function getFiles()
    {
        return $this->files;
    }
}

/*
TOPICS

<option value="APs">A/P</option>
<option value="ARs">A/R</option>
<option value="Accounts">Accounts</option>
<option value="AllocationRules">Allocation rules</option>
<option value="AssemblyOrders">Assembly orders</option>
<option value="AssetGroups">Asset groups</option>
<option value="Balances">Balance</option>
<option value="BankLinks">Bank links</option>
<option value="BillOfMaterials" selected="selected">Bill of materials</option>
<option value="BudgetScenarios">Budget scenarios</option>
<option value="Budgets">Budgets</option>
<option value="Administrations">Companies</option>
<option value="UserAdministrations">Company access rights</option>
<option value="Costcenters">Cost centres</option>
<option value="Costunits">Cost units</option>
<option value="DepreciationMethods">Depreciation methods</option>
<option value="Documents">Documents</option>
<option value="ExchangeRates">Exchange rates</option>
<option value="ExtraFieldDefinitions">Extra field definitions</option>
<option value="FinYears">Financial years</option>
<option value="FinishedAssemblies">Finished assemblies</option>
<option value="GLAccountClassifications">G/L Account Classifications</option>
<option value="GLAccounts">G/L Accounts</option>
<option value="Deliveries">Goods deliveries</option>
<option value="Receipts">Goods receipts</option>
<option value="ItemGroups">Item groups</option>
<option value="Items">Items</option>
<option value="ItemWarehouse">Items by warehouses</option>
<option value="Journals">Journals</option>
<option value="Layouts">Layouts</option>
<option value="DDMandates">Mandates</option>
<option value="ManufacturedBillofMaterials">Manufactured bill of materials</option>
<option value="MatchSets">Matching</option>
<option value="PaymentConditions">Payment conditions</option>
<option value="PurchaseOrders">Purchase orders</option>
<option value="Invoices">Sales invoices</option>
<option value="SalesOrders">Sales orders</option>
<option value="Settings">Settings</option>
<option value="ShippingMethods">Shipping methods</option>
<option value="StockCounts">Stock counts</option>
<option value="StockPositions">Stock positions</option>
<option value="Titles">Titles</option>
<option value="GLTransactions">Transactions</option>
<option value="Users">Users</option>
<option value="VATs">VAT codes</option>
<option value="WarehouseTransfers">Warehouse transfers</option>
<option value="Warehouses">Warehouses</option>
<option value="WebShopSalesOrders">Web shop sales orders</option>

 */
