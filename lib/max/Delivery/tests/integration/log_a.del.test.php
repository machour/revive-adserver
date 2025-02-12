<?php

/*
+---------------------------------------------------------------------------+
| Revive Adserver                                                           |
| http://www.revive-adserver.com                                            |
|                                                                           |
| Copyright: See the COPYRIGHT.txt file.                                    |
| License: GPLv2 or later, see the LICENSE.txt file.                        |
+---------------------------------------------------------------------------+
*/

require_once MAX_PATH . '/lib/max/Delivery/log.php';
require_once LIB_PATH . '/OperationInterval.php';

/**
 * A class for performing end-to-end integration testing of the delivery logging
 * function MAX_Delivery_log_logConversion().
 *
 * @package    MaxDelivery
 * @subpackage TestSuite
 */
class Test_Max_Delivery_Log_A extends UnitTestCase
{
    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * A method to test the MAX_Delivery_log_logConversion() function.
     */
    public function test_MAX_Delivery_log_logConversion()
    {
        $aConf = &$GLOBALS['_MAX']['CONF'];
        $aConf['maintenance']['operationInterval'] = 60;

        $GLOBALS['_MAX']['NOW'] = time();
        $oNowDate = new Date($GLOBALS['_MAX']['NOW']);
        $aDates = OX_OperationInterval::convertDateToOperationIntervalStartAndEndDates($oNowDate);
        $intervalStart = $aDates['start']->format('%Y-%m-%d %H:%M:%S');

        $oConversionDate = new Date();
        $oConversionDate->copy($oNowDate);
        $oConversionDate->subtractSeconds(60);

        $_SERVER['REMOTE_ADDR'] = '127.0.0.99';

        // Test to ensure that the openXDeliveryLog plugin's data bucket
        // table does not exist
        $oTable = new OA_DB_Table();
        $tableExists = $oTable->extistsTable($aConf['table']['prefix'] . 'data_bkt_a');
        $this->assertFalse($tableExists);

        // Test calling the main logging function without any plugins installed,
        // to ensure that this does not result in any kind of error
        $aConversion = [
            'action_type' => MAX_CONNECTION_AD_CLICK,
            'tracker_type' => MAX_CONNECTION_TYPE_SALE,
            'status' => MAX_CONNECTION_STATUS_APPROVED,
            'cid' => 2,
            'zid' => 3,
            'dt' => $GLOBALS['_MAX']['NOW'] - 60,
            'window' => 60
        ];
        MAX_Delivery_log_logConversion(1, $aConversion);

        // Install the openXDeliveryLog plugin
        TestEnv::installPluginPackage('openXDeliveryLog', false);

        // Test to ensure that the openXDeliveryLog plugin's data bucket
        // table now does exist
        $tableExists = $oTable->extistsTable($aConf['table']['prefix'] . 'data_bkt_a');
        $this->assertTrue($tableExists);

        // Ensure that there are is nothing logged in the data bucket table
        $doData_bkt_a = OA_Dal::factoryDO('data_bkt_a');
        $doData_bkt_a->find();
        $rows = $doData_bkt_a->getRowCount();
        $this->assertEqual($rows, 0);

        // Call the conversion logging function
        $aConversionInfo = MAX_Delivery_log_logConversion(1, $aConversion);

        // Ensure that the data was logged correctly
        $doData_bkt_a = OA_Dal::factoryDO('data_bkt_a');
        $doData_bkt_a->find();
        $rows = $doData_bkt_a->getRowCount();
        $this->assertEqual($rows, 1);

        $doData_bkt_a = OA_Dal::factoryDO('data_bkt_a');
        $doData_bkt_a->server_conv_id = 1;
        $doData_bkt_a->find();
        $rows = $doData_bkt_a->getRowCount();
        $this->assertEqual($rows, 1);
        $doData_bkt_a->fetch();
        $this->assertEqual($doData_bkt_a->server_ip, 'singleDB');
        $this->assertEqual($doData_bkt_a->tracker_id, 1);
        $this->assertEqual($doData_bkt_a->date_time, $oNowDate->format('%Y-%m-%d %H:%M:%S'));
        $this->assertEqual($doData_bkt_a->action_date_time, $oConversionDate->format('%Y-%m-%d %H:%M:%S'));
        $this->assertEqual($doData_bkt_a->creative_id, 2);
        $this->assertEqual($doData_bkt_a->zone_id, 3);
        $this->assertEqual($doData_bkt_a->ip_address, '127.0.0.99');
        $this->assertEqual($doData_bkt_a->action, MAX_CONNECTION_AD_CLICK);
        $this->assertEqual($doData_bkt_a->window, 60);
        $this->assertEqual($doData_bkt_a->status, MAX_CONNECTION_STATUS_APPROVED);

        $this->assertTrue(is_array($aConversionInfo));
        $this->assertTrue(is_array($aConversionInfo['deliveryLog:oxLogConversion:logConversion']));
        $this->assertEqual($aConversionInfo['deliveryLog:oxLogConversion:logConversion']['server_conv_id'], 1);
        $this->assertEqual($aConversionInfo['deliveryLog:oxLogConversion:logConversion']['server_raw_ip'], 'singleDB');

        $aConversion['cid'] = 5;

        // Call the conversion logging function
        $aConversionInfo = MAX_Delivery_log_logConversion(1, $aConversion);

        // Ensure that the data was logged correctly
        $doData_bkt_a = OA_Dal::factoryDO('data_bkt_a');
        $doData_bkt_a->find();
        $rows = $doData_bkt_a->getRowCount();
        $this->assertEqual($rows, 2);

        $doData_bkt_a = OA_Dal::factoryDO('data_bkt_a');
        $doData_bkt_a->server_conv_id = 1;
        $doData_bkt_a->find();
        $rows = $doData_bkt_a->getRowCount();
        $this->assertEqual($rows, 1);
        $doData_bkt_a->fetch();
        $this->assertEqual($doData_bkt_a->server_ip, 'singleDB');
        $this->assertEqual($doData_bkt_a->tracker_id, 1);
        $this->assertEqual($doData_bkt_a->date_time, $oNowDate->format('%Y-%m-%d %H:%M:%S'));
        $this->assertEqual($doData_bkt_a->action_date_time, $oConversionDate->format('%Y-%m-%d %H:%M:%S'));
        $this->assertEqual($doData_bkt_a->creative_id, 2);
        $this->assertEqual($doData_bkt_a->zone_id, 3);
        $this->assertEqual($doData_bkt_a->ip_address, '127.0.0.99');
        $this->assertEqual($doData_bkt_a->action, MAX_CONNECTION_AD_CLICK);
        $this->assertEqual($doData_bkt_a->window, 60);
        $this->assertEqual($doData_bkt_a->status, MAX_CONNECTION_STATUS_APPROVED);

        $doData_bkt_a = OA_Dal::factoryDO('data_bkt_a');
        $doData_bkt_a->server_conv_id = 2;
        $doData_bkt_a->find();
        $rows = $doData_bkt_a->getRowCount();
        $this->assertEqual($rows, 1);
        $doData_bkt_a->fetch();
        $this->assertEqual($doData_bkt_a->server_ip, 'singleDB');
        $this->assertEqual($doData_bkt_a->tracker_id, 1);
        $this->assertEqual($doData_bkt_a->date_time, $oNowDate->format('%Y-%m-%d %H:%M:%S'));
        $this->assertEqual($doData_bkt_a->action_date_time, $oConversionDate->format('%Y-%m-%d %H:%M:%S'));
        $this->assertEqual($doData_bkt_a->creative_id, 5);
        $this->assertEqual($doData_bkt_a->zone_id, 3);
        $this->assertEqual($doData_bkt_a->ip_address, '127.0.0.99');
        $this->assertEqual($doData_bkt_a->action, MAX_CONNECTION_AD_CLICK);
        $this->assertEqual($doData_bkt_a->window, 60);
        $this->assertEqual($doData_bkt_a->status, MAX_CONNECTION_STATUS_APPROVED);

        $this->assertTrue(is_array($aConversionInfo));
        $this->assertTrue(is_array($aConversionInfo['deliveryLog:oxLogConversion:logConversion']));
        $this->assertEqual($aConversionInfo['deliveryLog:oxLogConversion:logConversion']['server_conv_id'], 2);
        $this->assertEqual($aConversionInfo['deliveryLog:oxLogConversion:logConversion']['server_raw_ip'], 'singleDB');

        // Uninstall the openXDeliveryLog plugin
        TestEnv::uninstallPluginPackage('openXDeliveryLog', false);

        // Restore the test configuration file
        TestEnv::restoreConfig();
    }
}
