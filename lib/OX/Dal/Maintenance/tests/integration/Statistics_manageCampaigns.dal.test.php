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

require_once LIB_PATH . '/Dal/Maintenance/Statistics/Factory.php';
require_once MAX_PATH . '/lib/OA/Dal/DataGenerator.php';

/**
 * A class for testing the manageCampaigns() method of the
 * DB agnostic OX_Dal_Maintenance_Statistics class.
 *
 * @package    OpenXDal
 * @subpackage TestSuite
 */
class Test_OX_Dal_Maintenance_Statistics_manageCampaigns extends UnitTestCase
{
    public $doCampaigns = null;
    public $doBanners = null;
    public $doDIA = null;
    public $doClients = null;
    public $oDbh = null;

    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
        $this->doCampaigns = OA_Dal::factoryDO('campaigns');
        $this->doClients = OA_Dal::factoryDO('clients');
        $this->doBanners = OA_Dal::factoryDO('banners');
        $this->doDIA = OA_Dal::factoryDO('data_intermediate_ad');
        $this->oDbh = OA_DB::singleton();
        // Set the maintenance operation interval to 60 minutes
        $aConf = &$GLOBALS['_MAX']['CONF'];
        $aConf['maintenance']['operationInterval'] = 60;
    }

    /**
     * A method to test the activation and deactivation of campaigns within
     * the manageCampaigns() method.
     */
    public function testManageCampaigns()
    {
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('now', new Date('2004-06-05 09:00:00'));

        $oFactory = new OX_Dal_Maintenance_Statistics_Factory();
        $oDalMaintenanceStatistics = $oFactory->factory();

        // Create the required accounts & set the various ID values
        $aValues = $this->_createAccounts();
        $adminAccountId = $aValues['adminAccount'];
        $managerAccountId = $aValues['managerAccount'];
        $advertiserClientId = $aValues['advertiserClient'];

        $oTimezone = new Date_TimeZone('Australia/Sydney');

        /******************************************************************************/
        /* Prepare Campaign and Banner Data for Tests 1 - 4                           */
        /******************************************************************************/

        // Campaign 1, Orphaned
        $aData = [
            'campaignname' => 'Test Campaign 1'
        ];
        $idCampaign1 = $this->_insertPlacement($aData);

        // Campaign 2:
        // - Owned by Advertiser 1
        // - Lifetime Target Impressions of 10
        $aData = [
            'campaignname' => 'Test Campaign 2',
            'clientid' => $advertiserClientId,
            'views' => 10
        ];
        $idCampaign2 = $this->_insertPlacement($aData);

        // Campaign 3:
        // - Owned by Advertiser 1
        // - Lifetime Target Clicks of 10
        $aData = [
            'campaignname' => 'Test Campaign 3',
            'clientid' => $advertiserClientId,
            'clicks' => 10
        ];
        $idCampaign3 = $this->_insertPlacement($aData);

        // Campaign 4:
        // - Owned by Advertiser 1
        // - Lifetime Target Conversions of 10
        $aData = [
            'campaignname' => 'Test Campaign 4',
            'clientid' => $advertiserClientId,
            'conversions' => 10
        ];
        $idCampaign4 = $this->_insertPlacement($aData);

        // Campaign 5:
        // - Owned by Advertiser 1
        // - Lifetime Target Impressions of 10
        // - Lifetime Target Clicks of 10
        // - Lifetime Target Conversions of 10
        $aData = [
            'campaignname' => 'Test Campaign 5',
            'clientid' => $advertiserClientId,
            'views' => 10,
            'clicks' => 10,
            'conversions' => 10
        ];
        $idCampaign5 = $this->_insertPlacement($aData);

        // Campaign 6:
        // - Owned by Advertiser 1
        // - No Lifetime Target Impressions
        // - Campaign Expiration of 2004-06-06
        $oDate = new Date('2004-06-06 23:59:59');
        $oDate->setTZ($oTimezone);
        $oDate->toUTC();
        $aData = [
            'campaignname' => 'Test Campaign 6',
            'clientid' => $advertiserClientId,
            'views' => -1,
            'expire_time' => $oDate->getDate(DATE_FORMAT_ISO)
        ];
        $idCampaign6 = $this->_insertPlacement($aData);

        // Campaign 7:
        // - Owned by Advertiser 1
        // - No Lifetime Target Impressions
        // - Campaign Activation of 2004-06-06
        // - Currently not active
        $oDate = new Date('2004-06-06 00:00:00');
        $oDate->setTZ($oTimezone);
        $oDate->toUTC();
        $aData = [
            'campaignname' => 'Test Campaign 7',
            'clientid' => $advertiserClientId,
            'activate_time' => $oDate->getDate(DATE_FORMAT_ISO),
            'status' => OA_ENTITY_STATUS_AWAITING
        ];
        $idCampaign7 = $this->_insertPlacement($aData);

        // Banner 1
        // - In Campaign 1
        $aData = [
            'campaignid' => $idCampaign1,
        ];
        $idBanner1 = $this->_insertAd($aData);

        // Banner 2
        // - In Campaign 2
        $aData = [
            'campaignid' => $idCampaign2,
        ];
        $idBanner2 = $this->_insertAd($aData);

        // Banner 3
        // - In Campaign 2
        $aData = [
            'campaignid' => $idCampaign2,
        ];
        $idBanner3 = $this->_insertAd($aData);

        // Banner 4
        // - In Campaign 2
        $aData = [
            'campaignid' => $idCampaign2,
        ];
        $idBanner4 = $this->_insertAd($aData);

        // Banner 5
        // - In Campaign 3
        $aData = [
            'campaignid' => $idCampaign3,
        ];
        $idBanner5 = $this->_insertAd($aData);

        // Banner 6
        // - In Campaign 4
        $aData = [
            'campaignid' => $idCampaign4,
        ];
        $idBanner6 = $this->_insertAd($aData);

        // Banner 7
        // - In Campaign 5
        $aData = [
            'campaignid' => $idCampaign5,
        ];
        $idBanner7 = $this->_insertAd($aData);

        // Banner 8
        // - In Campaign 6
        $aData = [
            'campaignid' => $idCampaign6,
        ];
        $idBanner8 = $this->_insertAd($aData);

        // Banner 9
        // - In Campaign 7
        $aData = [
            'campaignid' => $idCampaign7,
        ];
        $idBanner9 = $this->_insertAd($aData);

        // Reset now
        $oServiceLocator->remove('now');

        /******************************************************************************/

        // Test 1: Prepare a date for the manageCampaigns() method to run at in UTC;
        //         2004-06-05 13:01:00 UTC is 2004-06-05 23:01:00 Australia/Sydney
        $oDate = new Date();
        $oDate->toUTC();
        $oDate->setDate('2004-06-05 13:01:00');
        $oServiceLocator->register('now', $oDate);

        // Test 1: Run the method with no summarised data, and a UTC date/time
        //         that is before the start/end dates that are set in
        //         Campaign 6 and Campaign 7
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);

        // Test 1: Campaign 1 is orphaned, test that default values are unchanged
        $this->_testCampaignByCampaignId($idCampaign1, -1, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 1: Campaign 2 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign2, 10, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 1: Campaign 3 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign3, -1, 10, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 1: Campaign 4 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign4, -1, -1, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 1: Campaign 5 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign5, 10, 10, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 1: Campaign 6 is owned, and has a campaign end date, but given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should still be running
        $this->_testCampaignByCampaignId($idCampaign6, -1, -1, -1, null, '2004-06-06 13:59:59', OA_ENTITY_STATUS_RUNNING);

        // Test 1: Campaign 7 is owned, and has a campaign start date, but given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should still not be running
        $this->_testCampaignByCampaignId($idCampaign7, -1, -1, -1, '2004-06-05 14:00:00', null, OA_ENTITY_STATUS_AWAITING);

        /******************************************************************************/

        // Test 2: Prepare a date for the manageCampaigns() method to run at in UTC;
        //         2004-06-05 14:01:00 UTC is 2004-06-06 00:01:00 Australia/Sydney
        $oDate = new Date();
        $oDate->toUTC();
        $oDate->setDate('2004-06-05 14:01:00');
        $oServiceLocator->register('now', $oDate);

        // Test 2: Run the method with no summarised data, and a UTC date/time
        //         that is before the end date of Campaign 6 (as campaigns end at
        //         the end of the day), but after the start date of Campaign 7
        //         (as campaigns start at the start of the day)
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);

        // Test 2: Campaign 1 is orphaned, test that default values are unchanged
        $this->_testCampaignByCampaignId($idCampaign1, -1, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 2: Campaign 2 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign2, 10, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 2: Campaign 3 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign3, -1, 10, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 2: Campaign 4 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign4, -1, -1, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 2: Campaign 5 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign5, 10, 10, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 2: Campaign 6 is owned, and has a campaign end date, but given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should still be running
        $this->_testCampaignByCampaignId($idCampaign6, -1, -1, -1, null, '2004-06-06 13:59:59', OA_ENTITY_STATUS_RUNNING);

        // Test 2: Campaign 7 is owned, and has a campaign start date, and given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should now be running
        $this->_testCampaignByCampaignId($idCampaign7, -1, -1, -1, '2004-06-05 14:00:00', null, OA_ENTITY_STATUS_RUNNING);

        /******************************************************************************/

        // Test 3: Prepare a date for the manageCampaigns() method to run at in UTC;
        //         2004-06-06 13:01:00 UTC is 2004-06-06 23:01:00 Australia/Sydney
        $oDate = new Date();
        $oDate->toUTC();
        $oDate->setDate('2004-06-06 13:01:00');
        $oServiceLocator->register('now', $oDate);

        // Test 3: Run the method with no summarised data, and a UTC date/time
        //         that is (still) before the end date of Campaign 6 (as campaigns
        //         end at the end of the day), but (still) after the start date of
        //         Campaign 7 (as campaigns start at the start of the day)
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);

        // Test 3: Campaign 1 is orphaned, test that default values are unchanged
        $this->_testCampaignByCampaignId($idCampaign1, -1, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 3: Campaign 2 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign2, 10, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 3: Campaign 3 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign3, -1, 10, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 3: Campaign 4 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign4, -1, -1, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 3: Campaign 5 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign5, 10, 10, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 3: Campaign 6 is owned, and has a campaign end date, but given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should still be running
        $this->_testCampaignByCampaignId($idCampaign6, -1, -1, -1, null, '2004-06-06 13:59:59', OA_ENTITY_STATUS_RUNNING);

        // Test 3: Campaign 7 is owned, and has a campaign start date, and given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should now be running
        $this->_testCampaignByCampaignId($idCampaign7, -1, -1, -1, '2004-06-05 14:00:00', null, OA_ENTITY_STATUS_RUNNING);

        /******************************************************************************/

        // Test 4: Prepare a date for the manageCampaigns() method to run at in UTC;
        //         2004-06-06 14:01:00 UTC is 2004-06-07 00:01:00 Australia/Sydney
        $oDate = new Date();
        $oDate->toUTC();
        $oDate->setDate('2004-06-06 14:01:00');
        $oServiceLocator->register('now', $oDate);

        // Test 4: Run the method with no summarised data, and a UTC date/time
        //         that is now after the end date of Campaign 6 (as campaigns
        //         end at the end of the day), and (still) after the start date of
        //         Campaign 7 (as campaigns start at the start of the day)
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);

        // Test 4: Campaign 1 is orphaned, test that default values are unchanged
        $this->_testCampaignByCampaignId($idCampaign1, -1, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 4: Campaign 2 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign2, 10, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 4: Campaign 3 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign3, -1, 10, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 4: Campaign 4 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign4, -1, -1, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 4: Campaign 5 is owned, but with no start/end date, and no data rows
        //         yet, should be unchanged from initial values
        $this->_testCampaignByCampaignId($idCampaign5, 10, 10, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 4: Campaign 6 is owned, and has a campaign end date, but given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should no longer still be running
        $this->_testCampaignByCampaignId($idCampaign6, -1, -1, -1, null, '2004-06-06 13:59:59', OA_ENTITY_STATUS_EXPIRED);

        // Test 4: Campaign 7 is owned, and has a campaign start date, and given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should now be running
        $this->_testCampaignByCampaignId($idCampaign7, -1, -1, -1, '2004-06-05 14:00:00', null, OA_ENTITY_STATUS_RUNNING);

        /******************************************************************************/
        /* Prepare Campaign Banner Delivery Data for Test 5                           */
        /******************************************************************************/

        // Banner 1 is in Campaign 1
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner1,
            'impressions' => 1,
            'clicks' => 1,
            'conversions' => 1
        ];
        $idDIA1 = $this->_insertDataIntermediateAd($aData);

        // Banner 2 is in Campaign 2
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner2,
            'impressions' => 1,
            'clicks' => 1,
            'conversions' => 1
        ];
        $idDIA2 = $this->_insertDataIntermediateAd($aData);

        // Banner 3 is in Campaign 2
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner3,
            'impressions' => 1,
            'clicks' => 0,
            'conversions' => 0
        ];
        $idDIA3 = $this->_insertDataIntermediateAd($aData);

        // Banner 4 is in Campaign 2
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner4,
            'impressions' => 8,
            'clicks' => 0,
            'conversions' => 0
        ];
        $idDIA4 = $this->_insertDataIntermediateAd($aData);

        // Banner 5 is in Campaign 3
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner5,
            'impressions' => 1000,
            'clicks' => 5,
            'conversions' => 1000
        ];
        $idDIA5 = $this->_insertDataIntermediateAd($aData);

        // Banner 6 is in Campaign 4
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner6,
            'impressions' => 1000,
            'clicks' => 1000,
            'conversions' => 1000
        ];
        $idDIA6 = $this->_insertDataIntermediateAd($aData);

        // Banner 7 is in Campaign 5
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner7,
            'impressions' => 0,
            'clicks' => 4,
            'conversions' => 6
        ];
        $idDIA7 = $this->_insertDataIntermediateAd($aData);

        // Banner 8 is in Campaign 6
        $aData = [
            'interval_start' => '2004-06-06 17:00:00',
            'interval_end' => '2004-06-06 17:59:59',
            'ad_id' => $idBanner8,
            'impressions' => 0,
            'clicks' => 4,
            'conversions' => 6
        ];
        $idDIA8 = $this->_insertDataIntermediateAd($aData);


        /******************************************************************************/

        // Test 5: Now that impressions, clicks and conversions have been
        //         delivered, re-run with the same date as Test 4 and make
        //         sure that campaigns are deactivated as required
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);

        // Test 5: Campaign 1 is orphaned, test that default values are unchanged
        $this->_testCampaignByCampaignId($idCampaign1, -1, -1, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 5: Campaign 2 is owned, with no start/end date, but now with
        //         10 impressions across three banners, so the campaign should
        //         no longer be running
        $this->_testCampaignByCampaignId($idCampaign2, 10, -1, -1, null, null, OA_ENTITY_STATUS_EXPIRED);

        // Test 5: Campaign 3 is owned, with no start/end date, but now with
        //         only 5 clicks, the campaign should still be running (even
        //         though there are lots of impressions and conversions)
        $this->_testCampaignByCampaignId($idCampaign3, -1, 10, -1, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 5: Campaign 4 is owned, with no start/end date, but now with
        //         1000 conversions, the campaign should no longer be running
        $this->_testCampaignByCampaignId($idCampaign4, -1, -1, 10, null, null, OA_ENTITY_STATUS_EXPIRED);

        // Test 5: Campaign 5 is owned, with no start/end date, but now with
        //         no impressions, only 4 clicks and only 6 conversions, the
        //         campaign should still be running
        $this->_testCampaignByCampaignId($idCampaign5, 10, 10, 10, null, null, OA_ENTITY_STATUS_RUNNING);

        // Test 5: Campaign 6 is owned, and has a campaign end date, but given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should no longer still be running
        $this->_testCampaignByCampaignId($idCampaign6, -1, -1, -1, null, '2004-06-06 13:59:59', OA_ENTITY_STATUS_EXPIRED);

        // Test 5: Campaign 7 is owned, and has a campaign start date, and given the
        //         advertiser's manager timezone and the time that the manageCampaigns()
        //         method was run, the campaign should now be running
        $this->_testCampaignByCampaignId($idCampaign7, -1, -1, -1, '2004-06-05 14:00:00', null, OA_ENTITY_STATUS_RUNNING);

        /******************************************************************************/

        DataGenerator::cleanUp();
    }

    /**
     * A method to test the activation of campaigns does NOT occur in the
     * event that the campaigns have previously been deactivated within
     * the manageCampaigns() method.
     */
    public function testManageCampaignsNoRestart()
    {
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('now', new Date('2005-12-07 10:01:00'));

        $oFactory = new OX_Dal_Maintenance_Statistics_Factory();
        $oDalMaintenanceStatistics = $oFactory->factory();

        // Create the required accounts & set the various ID values
        $aValues = $this->_createAccounts();
        $adminAccountId = $aValues['adminAccount'];
        $managerAccountId = $aValues['managerAccount'];
        $advertiserClientId = $aValues['advertiserClient'];

        /******************************************************************************/
        /* Prepare Campaign and Banner Data for Test                                  */
        /******************************************************************************/

        // Campaign 1:
        // - Owned by Advertiser 1
        // - Lifetime target of 10 impressions
        // - Start date of 2005-12-07
        // - End date of 2005-12-09
        // - Campaign currently running, will be expired after we insert stats
        $aData = [
            'campaignname' => 'Test Campaign 1',
            'clientid' => $advertiserClientId,
            'views' => 10,
            'clicks' => -1,
            'conversions' => -1,
            'activate_time' => '2005-12-06 14:00:00', // Sydney time
            'expire_time' => '2005-12-09 13:59:59', // Sydney time
            'status' => OA_ENTITY_STATUS_RUNNING
        ];
        $idCampaign1 = $this->_insertPlacement($aData);

        // Banner 1
        // - In Campaign 1
        $aData = [
            'campaignid' => $idCampaign1
        ];
        $idBanner1 = $this->_insertAd($aData);

        // 100 Impressions for Banner 1 occuring after the
        // start date of Campaign 1, and before the end date
        // of Campaign 1, when the campaign start/end dates
        // are converted into UTC
        $aData = [
            'operation_interval_id' => 25,
            'interval_start' => '2005-12-07 10:00:00',
            'interval_end' => '2005-12-07 10:59:59',
            'hour' => 10,
            'ad_id' => $idBanner1,
            'impressions' => 100,
            'clicks' => 1,
            'conversions' => 0
        ];
        $idDIA1 = $this->_insertDataIntermediateAd($aData);

        // Make sure that campaign 1 is expired
        $doCampaigns = OA_Dal::staticGetDO('campaigns', $idCampaign1);
        $doCampaigns->status = OA_ENTITY_STATUS_RUNNING;
        $doCampaigns->update();

        /******************************************************************************/

        // Test 1: Prepare a date for the manageCampaigns() method to run at in UTC;
        //         2005-12-07 11:01:00 UTC is 2005-12-07 22:01:00 Australia/Sydney
        $oDate = new Date();
        $oDate->toUTC();
        $oDate->setDate('2005-12-07 11:01:00');
        $oServiceLocator->register('now', $oDate);

        // Test 1: Run the method, and ensure that, although the date in UTC
        //         is after the start date of Campaign 1 and before the end
        //         date of Campaign 1, the campaign is NOT enabled, due to
        //         past expiration of the campaign
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);

        $this->_testCampaignByCampaignId($idCampaign1, 10, -1, -1, '2005-12-06 14:00:00', '2005-12-09 13:59:59', OA_ENTITY_STATUS_EXPIRED);

        /******************************************************************************/

        DataGenerator::cleanUp();
    }

    /**
     * A method to test the sending of emails from the
     * manageCampaigns() method - tests the sending of
     * the "campaign activated" emails.
     */
    public function testManageCampaignsEmailsPlacementActivated()
    {
        // Set now as 1 week before
        $oDateNow = new Date();
        $oDateNow->subtractSpan(new Date_Span('7-0-0-0'));
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('now', $oDateNow);

        // Create the required accounts & set the various ID values
        $aValues = $this->_createAccounts();
        $managerAgencyId = $aValues['managerAgency'];

        // Prepare a single placement that is inactive, and has an old
        // activation date (so that it will need to be activated)
        $aData = [
            'agencyid' => $managerAgencyId,
            'contact' => 'Test Placement Activated Contact',
            'email' => 'postmaster@placement.activated',
            'reportdeactivate' => 't',
        ];
        $advertiserId = $this->_insertAdvertiser($aData);

        $oDate = new Date();
        $oDateStart = new Date();
        $oDateStart->copy($oDate);
        $oDateStart->subtractSeconds(SECONDS_PER_HOUR + 1);

        $aData = [
            'clientid' => $advertiserId,
            'status' => OA_ENTITY_STATUS_AWAITING,
            'activate_time' => $oDateStart->format('%Y-%m-%d 00:00:00')
        ];
        $campaignId = $this->_insertPlacement($aData);

        // Reset now
        $oServiceLocator->remove('now');

        $aData = [
            'campaignid' => $campaignId
        ];
        $adId = $this->_insertAd($aData);

        // Create an instance of the mocked OA_Email class, and set
        // expectations on how the class' methods should be called
        // based on the above
        Mock::generate('OA_Email');
        $oEmailMock = new MockOA_Email($this);
        $oEmailMock->expectOnce('sendCampaignActivatedDeactivatedEmail', ["$campaignId"]);

        // Register the mocked OA_Email class in the service locator
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('OA_Email', $oEmailMock);

        // Run the manageCampaigns() method and ensure that the correct
        // calls to OA_Email were made
        $oDate = new Date();
        $oFactory = new OX_Dal_Maintenance_Statistics_Factory();
        $oDalMaintenanceStatistics = $oFactory->factory();
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);
        $oEmailMock->tally();

        // Clean up
        DataGenerator::cleanUp();
    }

    /**
     * A method to test the sending of emails from the
     * manageCampaigns() method - tests the sending of
     * the "campaign deactivated" emails.
     */
    public function testManageCampaignsEmailsPlacementDeactivated()
    {
        // Prepare a single placement that is active, and has a lifetime
        // impression target that has been met (so that it will need to
        // be deactivated)

        $aData = [
            'contact' => 'Test Placement Deactivated Contact',
            'email' => 'postmaster@placement.deactivated',
            'reportdeactivate' => 't',
            'report' => 't',
        ];
        $advertiserId = $this->_insertAdvertiser($aData);

        $aAdvertiser = OA_DAL::staticGetDO('clients', $advertiserId)->toArray();

        $aData = [
            'status' => OA_ENTITY_STATUS_RUNNING,
            'views' => '100'
        ];
        $campaignId = $this->_insertPlacement($aData);

        $aData = [
            'campaignid' => $campaignId
        ];
        $adId = $this->_insertAd($aData);

        $aData = [
            'operation_interval_id' => 25,
            'interval_start' => '2005-12-08 00:00:00',
            'interval_end' => '2004-12-08 00:59:59',
            'hour' => 0,
            'ad_id' => 1,
            'impressions' => 101
        ];
        $this->_insertDataIntermediateAd($aData);

        // Create an instance of the mocked OA_Email class, and set
        // expectations on how the class' methods should be called
        // based on the above
        Mock::generate('OA_Email');
        $oEmailMock = new MockOA_Email($this);
        $oEmailMock->expectOnce('sendCampaignActivatedDeactivatedEmail', ["$campaignId", 2]);

        // This is the date that is going to be used later
        $oDate = new Date();

        $oEnd = new Date($oDate);
        $oEnd->addSpan(new Date_Span('1-0-0-0'));
        $oEmailMock->expectOnce('sendCampaignDeliveryEmail', [$aAdvertiser, new Date($aAdvertiser['reportlastdate']), $oEnd, "$campaignId"]);

        // Register the mocked OA_Email class in the service locator
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('OA_Email', $oEmailMock);

        // Run the manageCampaigns() method and ensure that the correct
        // calls to OA_Email were made
        $oFactory = new OX_Dal_Maintenance_Statistics_Factory();
        $oDalMaintenanceStatistics = $oFactory->factory();
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);
        $oEmailMock->tally();

        // Clean up
        DataGenerator::cleanUp();
    }

    /**
     * A method to test the sending of emails from the
     * manageCampaigns() method - tests the sending of
     * the "campaign about to expire" emails.
     */
    public function testManageCampaignsEmailsPlacementToExpire()
    {
        // Set the date format
        global $date_format;
        $date_format = '%Y-%m-%d';

        // Set now as 1 week before
        $oDateNow = new Date('2008-01-10');
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('now', $oDateNow);

        // Insert the required preference values for dealing with email warnings
        $warnEmailAdminPreferenceId = $this->_createPreference('warn_email_admin', OA_ACCOUNT_ADMIN);
        $warnEmailAdminPreferenceImpressionLimitId = $this->_createPreference('warn_email_admin_impression_limit', OA_ACCOUNT_ADMIN);
        $warnEmailAdminPreferenceDayLimitId = $this->_createPreference('warn_email_admin_day_limit', OA_ACCOUNT_ADMIN);

        $this->_createPreference('warn_email_manager', OA_ACCOUNT_MANAGER);
        $this->_createPreference('warn_email_manager_impression_limit', OA_ACCOUNT_MANAGER);
        $this->_createPreference('warn_email_manager_day_limit', OA_ACCOUNT_MANAGER);

        $this->_createPreference('warn_email_advertiser', OA_ACCOUNT_ADVERTISER);
        $this->_createPreference('warn_email_advertiser_impression_limit', OA_ACCOUNT_ADVERTISER);
        $this->_createPreference('warn_email_advertiser_day_limit', OA_ACCOUNT_ADVERTISER);

        // Create the required accounts & set the various ID values
        $aValues = $this->_createAccounts();
        $adminAccountId = $aValues['adminAccount'];
        $advertiserClientId = $aValues['advertiserClient'];

        // Create a currently running placement with 100 impressions
        // remaining and set to expire on 2008-01-13
        $aData = [
            'clientid' => $advertiserClientId,
            'status' => OA_ENTITY_STATUS_RUNNING,
            'views' => '100',
            'expire_time' => '2008-01-13 23:59:59'
        ];
        $campaignId = $this->_insertPlacement($aData);

        // Reset now
        $oServiceLocator->remove('now');

        // Insert a banner for the placement
        $aData = [
            'campaignid' => $campaignId
        ];
        $adId = $this->_insertAd($aData);

        // Create an instance of the mocked OA_Email class, and set
        // expectations on how the class' methods should be called
        // based on the above
        Mock::generate('OA_Email');
        $oEmailMock = new MockOA_Email($this);
        $oEmailMock->expectOnce('sendCampaignImpendingExpiryEmail');

        // Register the mocked OA_Email class in the service locator
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('OA_Email', $oEmailMock);

        // Run the manageCampaigns() method and ensure that the correct
        // calls to OA_Email were made
        $oDate = new Date('2008-01-11 23:00:01');
        $oFactory = new OX_Dal_Maintenance_Statistics_Factory();
        $oDalMaintenanceStatistics = $oFactory->factory();
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);
        $oEmailMock->tally();

        // Now set the preference that states that the admin account
        // wants to get email warnings
        $this->_insertPreference($adminAccountId, $warnEmailAdminPreferenceId, 'true');

        // Create a new instance of the mocked OA_Email class, and set
        // expectations on how the class' methods should be called
        // based on the above
        $oEmailMock = new MockOA_Email($this);
        $oEmailMock->expectOnce('sendCampaignImpendingExpiryEmail');

        // Register the mocked OA_Email class in the service locator
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('OA_Email', $oEmailMock);

        // Run the manageCampaigns() method and ensure that the correct
        // calls to OA_Email were made
        $oDate = new Date('2008-01-11 23:00:01');
        $oDalMaintenanceStatistics = $oFactory->factory();
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);
        $oEmailMock->tally();

        // Now set the preference that states that the admin account
        // wants to get email warnings if there are less than 50
        // impressions remaining
        $this->_insertPreference($adminAccountId, $warnEmailAdminPreferenceImpressionLimitId, '50');

        // Create a new instance of the mocked OA_Email class, and set
        // expectations on how the class' methods should be called
        // based on the above
        $oEmailMock = new MockOA_Email($this);
        $oEmailMock->expectOnce('sendCampaignImpendingExpiryEmail');

        // Register the mocked OA_Email class in the service locator
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('OA_Email', $oEmailMock);

        // Run the manageCampaigns() method and ensure that the correct
        // calls to OA_Email were made
        $oDate = new Date('2008-01-11 23:00:01');
        $oDalMaintenanceStatistics = $oFactory->factory();
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);
        $oEmailMock->tally();

        // Delivery 60 impressions out of the 100, so that only 40 remain
        // (i.e. less than the 50 limit set above)
        $aData = [
            'operation_interval_id' => 25,
            'interval_start' => '2008-01-11 22:00:00',
            'interval_end' => '2008-01-11 22:59:59',
            'hour' => 0,
            'ad_id' => $adId,
            'impressions' => 60
        ];
        $this->_insertDataIntermediateAd($aData);

        // Create a new instance of the mocked OA_Email class, and set
        // expectations on how the class' methods should be called
        // based on the above
        $oEmailMock = new MockOA_Email($this);
        $oEmailMock->expectOnce('sendCampaignImpendingExpiryEmail', [$oDate, "$campaignId"]);

        // Register the mocked OA_Email class in the service locator
        $oServiceLocator = &OA_ServiceLocator::instance();
        $oServiceLocator->register('OA_Email', $oEmailMock);

        // Run the manageCampaigns() method and ensure that the correct
        // calls to OA_Email were made
        $oDate = new Date('2008-01-11 23:00:01');
        $oDalMaintenanceStatistics = $oFactory->factory();
        $report = $oDalMaintenanceStatistics->manageCampaigns($oDate);
        $oEmailMock->tally();

        // Clean up
        DataGenerator::cleanUp();
    }

    /**
     * A private method to create the admin account, a user, a
     * manager account and an advertiser account, and return the
     * various account and user ID values, for testing.
     *
     * @access private
     * @return array An array with the following keys:
     *                  - "adminAccount"      The admin account ID.
     *                  - "userId"            The user ID.
     *                  - "managerAgency"     The manager agency ID.
     *                  - "managerAccount"    The manager account ID.
     *                  - "advertiserClient"  The advertiser client ID.
     *                  - "advertiserAccount" The advertiser account ID.
     */
    public function _createAccounts()
    {
        // Create the admin account
        $doAccounts = OA_Dal::factoryDO('accounts');
        $doAccounts->account_name = 'System Administrator';
        $doAccounts->account_type = OA_ACCOUNT_ADMIN;
        $adminAccountId = DataGenerator::generateOne($doAccounts);

        // Create a user
        $doUsers = OA_Dal::factoryDO('users');
        $doUsers->contact_name = 'Andrew Hill';
        $doUsers->email_address = 'andrew.hill@openads.org';
        $doUsers->username = 'admin';
        $doUsers->password = md5('password');
        $doUsers->default_account_id = $adminAccountId;
        $userId = DataGenerator::generateOne($doUsers);

        // Create a manager "agency" and account
        $doAgency = OA_Dal::factoryDO('agency');
        $doAgency->name = 'Manager Account';
        $doAgency->contact = 'Andrew Hill';
        $doAgency->email = 'andrew.hill@openads.org';
        $managerAgencyId = DataGenerator::generateOne($doAgency);

        // Get the account ID for the manager "agency"
        $doAgency = OA_Dal::factoryDO('agency');
        $doAgency->agency_id = $managerAgencyId;
        $doAgency->find();
        $doAgency->fetch();
        $aAgency = $doAgency->toArray();
        $managerAccountId = $aAgency['account_id'];

        // Create an advertiser "client" and account, owned by the manager
        $doClients = OA_Dal::factoryDO('clients');
        $doClients->name = 'Advertiser Account';
        $doClients->contact = 'Andrew Hill';
        $doClients->email = 'andrew.hill@openads.org';
        $doClients->agencyid = $managerAgencyId;
        $advertiserClientId = DataGenerator::generateOne($doClients);

        // Get the account ID for the advertiser "client"
        $doClients = OA_Dal::factoryDO('clients');
        $doClients->clientid = $advertiserClientId;
        $doClients->find();
        $doClients->fetch();
        $aAdvertiser = $doClients->toArray();
        $advertiserAccountId = $aAdvertiser['account_id'];

        // Return the created ID values
        $aReturn = [
            "adminAccount" => $adminAccountId,
            "userId" => $userId,
            "managerAgency" => $managerAgencyId,
            "managerAccount" => $managerAccountId,
            "advertiserClient" => $advertiserClientId,
            "advertiserAccount" => $advertiserAccountId
        ];
        return $aReturn;
    }

    /**
     * A private method for creating preferences for testing.
     *
     * @param string  $preferenceName The name of the preference in the "preferences" table.
     * @param integer $preferenceLevel The preference level, if required.
     * @return integer The ID of the created preference.
     */
    public function _createPreference($preferenceName, $preferenceLevel = '')
    {
        $doPreferences = OA_Dal::factoryDO('preferences');
        $doPreferences->preference_name = $preferenceName;
        $doPreferences->account_type = $preferenceLevel;
        $preferenceId = DataGenerator::generateOne($doPreferences);
        return $preferenceId;
    }

    /**
     * A private method for creating preferences values for accounts for testing.
     *
     * @param integer $accountId       The account ID.
     * @param integer $preferenceId    The preference ID.
     * @param string  $preferenceValue The value of the preference.
     */
    public function _insertPreference($accountId, $preferenceId, $preferenceValue)
    {
        $doAccount_Preference_Assoc = OA_Dal::factoryDO('account_preference_assoc');
        $doAccount_Preference_Assoc->account_id = $accountId;
        $doAccount_Preference_Assoc->preference_id = $preferenceId;
        $doAccount_Preference_Assoc->value = "$preferenceValue";
        DataGenerator::generateOne($doAccount_Preference_Assoc);
    }

    /**
     * A private method for generating campaigns for testing.
     *
     * @access private
     * @param array $aData An array containing any columns names for the
     *                     campaign to be created that should be different
     *                     to the default values as listed in the method
     *                     below.
     * @return integer The ID of the campaign created.
     */
    public function _insertPlacement($aData)
    {
        $this->doCampaigns->campaignname = 'Test Placement';
        $this->doCampaigns->clientid = 1;
        $this->doCampaigns->weight = 1;
        $this->doCampaigns->priority = -1;
        $this->doCampaigns->views = -1;
        $this->doCampaigns->clicks = -1;
        $this->doCampaigns->conversions = -1;
        $this->doCampaigns->target_impression = -1;
        $this->doCampaigns->target_click = -1;
        $this->doCampaigns->target_conversion = -1;
        $this->doCampaigns->status = OA_ENTITY_STATUS_RUNNING;
        $this->doCampaigns->updated = null;
        $this->doCampaigns->activate_time = null;
        $this->doCampaigns->expire_time = null;
        foreach ($aData as $key => $val) {
            $this->doCampaigns->$key = $val;
        }
        return DataGenerator::generateOne($this->doCampaigns);
    }

    /**
     * A private method for generating advertisers for testing.
     *
     * @access private
     * @param array $aData An array containing any columns names for the
     *                     advertiser to be created.
     * @return integer The ID of the advertiser created.
     */
    public function _insertAdvertiser($aData)
    {
        foreach ($aData as $key => $val) {
            $this->doClients->$key = $val;
        }
        return DataGenerator::generateOne($this->doClients);
    }

    /**
     * A private method for generating banners for testing.
     *
     * @access private
     * @param array $aData An array containing any columns names for the
     *                     banner to be created.
     * @return integer The ID of the banner created.
     */
    public function _insertAd($aData)
    {
        foreach ($aData as $key => $val) {
            $this->doBanners->$key = $val;
        }
        return DataGenerator::generateOne($this->doBanners);
    }

    /**
     * A private method for generating intermediate statistics data
     * for testing.
     *
     * @access private
     * @param array $aData An array containing any columns names for the
     *                     data to be created that should be different
     *                     to the default values as listed in the method
     *                     below.
     * @return integer The ID of the data row created.
     */
    public function _insertDataIntermediateAd($aData)
    {
        $this->doDIA->operation_interval = 60;
        $this->doDIA->operation_interval_id = 17;
        $this->doDIA->creative_id = 0;
        $this->doDIA->zone_id = 0;
        $this->doDIA->hour = 17;
        foreach ($aData as $key => $val) {
            $this->doDIA->$key = $val;
        }
        return DataGenerator::generateOne($this->doDIA);
    }

    /**
     * A private method for obtaining all the campaign information for
     * a given campaign ID, and then testing the values obtrained.
     *
     * @access private
     * @param integer $idCampaign  The ID of the campaign to obtain.
     * @param integer $impressions The number of lifetime target impressions that should be set.
     * @param integer $clicks      The number of lifetime target clicks that should be set.
     * @param integer $conversions The number of lifetime target conversions that should be set.
     * @param string  $startDate   The campaign start date that should be set.
     * @param string  $endDate     The campaign end date that should be set.
     * @param integer $status      The campaign status that should be set.
     * @return array An array containing the database row of the campaign.
     */
    public function _testCampaignByCampaignId($idCampaign, $impressions = null, $clicks = null, $conversions = null, $startDate = null, $endDate = null, $status = null)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        $query = "
            SELECT
                *
            FROM
                " . $this->oDbh->quoteIdentifier($aConf['table']['prefix'] . 'campaigns', true) . "
            WHERE
                campaignid = " . $idCampaign;
        $aRow = $this->oDbh->queryRow($query);

        $aBacktrace = debug_backtrace();

        if (isset($impressions)) {
            $this->assertEqual($aRow['views'], $impressions, '%s originally called at line ' . $aBacktrace[0]['line']);
        }
        if (isset($clicks)) {
            $this->assertEqual($aRow['clicks'], $clicks, '%s originally called at line ' . $aBacktrace[0]['line']);
        }
        if (isset($conversions)) {
            $this->assertEqual($aRow['conversions'], $conversions, '%s originally called at line ' . $aBacktrace[0]['line']);
        }
        if (isset($startDate)) {
            $this->assertEqual($aRow['activate_time'], $startDate, '%s originally called at line ' . $aBacktrace[0]['line']);
        }
        if (isset($endDate)) {
            $this->assertEqual($aRow['expire_time'], $endDate, '%s originally called at line ' . $aBacktrace[0]['line']);
        }
        if (isset($status)) {
            $this->assertEqual($aRow['status'], $status, '%s originally called at line ' . $aBacktrace[0]['line']);
        }
    }
}
