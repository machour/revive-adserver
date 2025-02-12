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

require_once MAX_PATH . '/lib/OA/Admin/Statistics/Delivery/CommonCrossHistory.php';

/**
 * The class to display the delivery statistcs for the page:
 *
 * Statistics -> Advertisers & Campaigns -> Campaigns -> Banners -> Publisher Distribution -> Distribution Statistics
 *
 * @package    OpenXAdmin
 * @subpackage StatisticsDelivery
 */
class OA_Admin_Statistics_Delivery_Controller_BannerZoneHistory extends OA_Admin_Statistics_Delivery_CommonCrossHistory
{
    /**
     * The final "child" implementation of the PHP5-style constructor.
     *
     * @param array $aParams An array of parameters. The array should
     *                       be indexed by the name of object variables,
     *                       with the values that those variables should
     *                       be set to. For example, the parameter:
     *                       $aParams = array('foo' => 'bar')
     *                       would result in $this->foo = bar.
     */
    public function __construct($aParams)
    {
        // Set this page's entity/breakdown values
        $this->entity = 'banner';
        $this->breakdown = 'zone-history';

        // This page uses the day span selector element
        $this->showDaySpanSelector = true;

        parent::__construct($aParams);
    }

    /**
     * The final "child" implementation of the parental abstract method.
     *
     * @see OA_Admin_Statistics_Common::start()
     */
    public function start()
    {
        // Get parameters
        $advertiserId = $this->_getId('advertiser');
        $placementId = $this->_getId('placement');
        $adId = $this->_getId('ad');
        $zoneId = $this->_getId('zone');

        // Security check
        OA_Permission::enforceAccount(OA_ACCOUNT_ADMIN, OA_ACCOUNT_MANAGER, OA_ACCOUNT_ADVERTISER);
        $this->_checkAccess(['advertiser' => $advertiserId, 'placement' => $placementId, 'ad' => $adId]);

        // Cross-entity security check
        if (!empty($zoneId)) {
            $aZones = $this->getBannerZones($adId, $placementId);
            if (!isset($aZones[$zoneId])) {
                $this->noStatsAvailable = true;
            }
        }

        // Cross-entity security check
        if (!isset($aZones[$zoneId])) {
            $this->noStatsAvailable = true;
        }

        // Add standard page parameters
        $this->aPageParams = [
            'clientid' => $advertiserId,
            'campaignid' => $placementId,
            'bannerid' => $adId,
            'affiliateid' => $publisherId,
            'zoneid' => $zoneId
        ];

        // Load the period preset and stats breakdown parameters
        $this->_loadPeriodPresetParam();
        $this->_loadStatsBreakdownParam();

        // Load $_GET parameters
        $this->_loadParams();

        // HTML Framework
        if (OA_Permission::isAccount(OA_ACCOUNT_ADMIN) || OA_Permission::isAccount(OA_ACCOUNT_MANAGER)) {
            $this->pageId = '2.1.2.2.2.2';
            $this->aPageSections = [$this->pageId];
        } elseif (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
            $this->pageId = '1.2.2.4.2';
            $this->aPageSections = [$this->pageId];
        }

        // Add breadcrumbs
        $this->_addBreadcrumbs('banner', $adId);
        $this->addCrossBreadcrumbs('zone', $zoneId);

        // Add context
        $params = $this->aPageParams;
        foreach ($aZones as $k => $v) {
            $params['affiliateid'] = $aZones[$k]['publisher_id'];
            $params['zoneid'] = $k;
            phpAds_PageContext(
                MAX_buildName($k, MAX_getZoneName($v['name'], null, $v['anonymous'], $k)),
                $this->_addPageParamsToURI($this->pageName, $params, true),
                $zoneId == $k
            );
        }

        // Add shortcuts
        if (!OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
            $this->_addShortcut(
                $GLOBALS['strClientProperties'],
                'advertiser-edit.php?clientid=' . $advertiserId,
                'iconAdvertiser'
            );
        }
        $this->_addShortcut(
            $GLOBALS['strCampaignProperties'],
            'campaign-edit.php?clientid=' . $advertiserId . '&campaignid=' . $placementId,
            'iconCampaign'
        );
        $this->_addShortcut(
            $GLOBALS['strBannerProperties'],
            'banner-edit.php?clientid=' . $advertiserId . '&campaignid=' . $placementId . '&bannerid=' . $adId,
            'iconBanner'
        );
        $this->_addShortcut(
            $GLOBALS['strModifyBannerAcl'],
            'banner-acl.php?clientid=' . $advertiserId . '&campaignid=' . $placementId . '&bannerid=' . $adId,
            'iconTargetingChannelAcl'
        );

        // Prepare the data for display by output() method
        $aParams = [
            'ad_id' => $adId,
            'zone_id' => $zoneId
        ];
        $this->prepare($aParams, 'stats.php');
    }
}
