<?php

namespace aibianchi\ExactOnlineBundle\Manager;

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
    protected $em;

    public function __construct(EntityManager $em)
    {
        parent::__construct($em);
    }

    public function setConfig($config)
    {
        parent::setConfig($config);
    }

    public function init($code)
    {
        try {
            Connection::setConfig($this->config, $this->em, $this->logger);

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
        Connection::setConfig($this->config, $this->em, $this->logger);
        Connection::refreshAccessToken();
    }

    public function export(array $params)
    {
        Connection::setContentType('xml');
        $p = '';

        $url = 'XMLDownload.aspx?Topic='.$this->model::TOPIC;

        $xmlParamsChecker = new XmlParamsControl();
        $xmlParamsChecker->check($this->model::TOPIC, $params);

        foreach ($params as $key => $param) {
            $p .= '&Params_'.$key.'='.$param;
        }

        $url = $url.$p;
        $data = Connection::Request($url, 'GET');

        dump($data);
        exit;
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
