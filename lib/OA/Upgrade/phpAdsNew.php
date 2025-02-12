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

require_once MAX_PATH . '/lib/OA/DB.php';

/**
 * OpenX Upgrade Class
 *
 */
class OA_phpAdsNew
{
    public $oDbh;

    public $detected = false;
    public $aDsn = [];
    public $aConfig = false;
    //var $aPanConfig = false;
    public $pathCfg = '/var/';
    public $fileCfg = 'config.inc.php';
    public $prefix = '';
    public $engine = '';
    public $version = '';

    public function __construct()
    {
    }

    public function init()
    {
        $this->aConfig = $this->_migratePANConfig($this->_getPANConfig());
        if ($this->detected) {
            $GLOBALS['_MAX']['CONF']['database']['type'] = $this->aConfig['database']['type'];
            $this->aDsn['database'] = $this->aConfig['database'];
            $this->aDsn['table'] = $this->aConfig['table'];
            $this->prefix = $this->aConfig['table']['prefix'];
            $this->engine = $this->aConfig['table']['type'];
            $GLOBALS['_MAX']['CONF']['table']['prefix'] = $this->prefix;
            $GLOBALS['_MAX']['CONF']['table']['type'] = $this->engine;
            $this->oDbh = OA_DB::singleton(OA_DB::getDsn($this->aConfig));
        }
    }

    public function getPANversion()
    {
        if ($this->detected) {
            PEAR::pushErrorHandling(null);
            $query = "SELECT config_version FROM {$this->prefix}config";
            $result = $this->oDbh->queryOne($query);
            PEAR::popErrorHandling();
            if (PEAR::isError($result)) {
                return false;
            }
            return $result;
        }
        return false;
    }

    public function _getPANConfig()
    {
        if (file_exists(MAX_PATH . $this->pathCfg . $this->fileCfg)) {
            $config = file_get_contents(MAX_PATH . $this->pathCfg . $this->fileCfg);
            $config = preg_replace('/set_magic_quotes_runtime\s*\(/', '//$1', $config);

            if (defined('phpAds_installed')) {
                $config = preg_replace('/define\(\'phpAds_installed/', '//$1', $config);
            }

            $tmpFile = tempnam(MAX_PATH . '/var', 'pan_');
            file_put_contents($tmpFile, $config);
            include $tmpFile;
            unlink($tmpFile);
            if (isset($phpAds_config) && is_array($phpAds_config)) {
                $this->detected = true;
                return $phpAds_config;
            }
        }
        return false;
    }

    public function _migratePANConfig($phpAds_config)
    {
        if (is_array($phpAds_config)) {
            $aResult['ui']['enabled'] = $phpAds_config['ui_enabled'] ?? true;
            $aResult['openads']['requireSSL'] = $phpAds_config['ui_forcessl'] ?? false;
            $aResult['maintenance']['autoMaintenance'] = $phpAds_config['auto_maintenance'] ?? true;
            $aResult['logging']['reverseLookup'] = $phpAds_config['reverse_lookup'] ?? false;
            $aResult['logging']['proxyLookup'] = $phpAds_config['proxy_lookup'] ?? false;
            $aResult['logging']['adImpressions'] = $phpAds_config['log_adviews'] ?? true;
            $aResult['logging']['adClicks'] = $phpAds_config['log_adclicks'] ?? true;
            $aResult['logging']['ignoreHosts'] = join(',', $phpAds_config['ignore_hosts'] ?? []);
            $aResult['logging']['blockAdImpressions'] = $phpAds_config['block_adviews'] ?? false;
            $aResult['logging']['blockAdClicks'] = $phpAds_config['block_adclicks'] ?? false;
            $aResult['p3p']['policies'] = $phpAds_config['p3p_policies'] ?? '';
            $aResult['p3p']['compactPolicy'] = $phpAds_config['p3p_compact_policy'] ?? '';
            $aResult['p3p']['policyLocation'] = $phpAds_config['p3p_policy_location'] ?? '';
            $aResult['delivery']['acls'] = $phpAds_config['acl'] ?? true;

            if (!empty($phpAds_config['table_type'])) {
                $aResult['database']['type'] = extension_loaded('mysqli') ? 'mysqli' : 'mysql';
                $aResult['table']['type'] = $phpAds_config['table_type'];
            } else {
                $aResult['database']['type'] = 'pgsql';
                $aResult['table']['type'] = '';
            }

            $aResult['database']['username'] = $phpAds_config['dbuser'] ?? '';
            $aResult['database']['password'] = $phpAds_config['dbpassword'] ?? '';
            $aResult['database']['name'] = $phpAds_config['dbname'] ?? '';
            $aResult['database']['persistent'] = $phpAds_config['persistent_connections'] ?? false;

            $aResult['table']['prefix'] = $phpAds_config['table_prefix'] ?? '';

            // Required for ACLs upgrade recompile mechanism
            $aResult['table']['banners'] = 'banners';
            $aResult['table']['channel'] = 'channel';

            // pan has a setting dblocal to indicate a socket connection
            // max v0.1 doesn't, just have to detect if the port is a port number or socket path
            $aResult['database']['host'] = ($phpAds_config['dbhost'] ?: 'localhost');
            $aResult['database']['port'] = ($phpAds_config['dbport'] ?: ($aResult['database']['type'] == 'mysql' || $aResult['database']['type'] == 'mysqli' ? '3306' : '5432'));
            if (!empty($phpAds_config['dblocal'])) {
                // must be pan (mysql/pgsql)
                $aResult['database']['protocol'] = 'unix';
                $aResult['database']['socket'] = ($aResult['database']['host'] == 'localhost' ? '' : preg_replace('/^:/', '', $phpAds_config['dbhost']));
                $aResult['database']['host'] = 'localhost';
            } elseif (isset($phpAds_config['dbport']) && !is_numeric($phpAds_config['dbport'])) {
                // must be max v0.1 (mysql only)
                $aResult['database']['protocol'] = 'unix';
                $aResult['database']['host'] = 'localhost';
                $aResult['database']['port'] = '3306';
                $aResult['database']['socket'] = $phpAds_config['dbport'];
            } else {
                $aResult['database']['protocol'] = 'tcp';
                $aResult['database']['socket'] = '';
            }

            return $aResult;
        }

        return [];
    }

    public function renamePANConfigFile($prefix = 'backup_')
    {
        if (file_exists(MAX_PATH . $this->pathCfg . $this->fileCfg)) {
            if (copy(MAX_PATH . $this->pathCfg . $this->fileCfg, MAX_PATH . $this->pathCfg . $prefix . $this->fileCfg)) {
                unlink(MAX_PATH . $this->pathCfg . $this->fileCfg);
            }
        }
        return (!file_exists(MAX_PATH . $this->pathCfg . $this->fileCfg));
    }

    /**
     * A method to check the integrity of a PAN configuration array.
     *
     * @param OA_Upgrade Parent Upgrader class
     * @return bool True on success
     */
    public function checkPANConfigIntegrity($oUpgrader)
    {
        $phpAds_config = $this->_getPANConfig();

        // Geo-targeting checks
        $message = "Warning: ";
        $postWarningMessage = " As a result, your geotargeting settings can not be migrated, and you will need to re-configure " .
                              "your geotargeting database(s) after upgrading. Please see the " .
                              "<a href='" . PRODUCT_DOCSURL . "/faq'>FAQ</a> for more information.";
        if (!empty($phpAds_config['geotracking_type'])) {
            // Test for errors in the PAN geotargeting configuration
            if ($phpAds_config['geotracking_type'] == 'geoip') {
                if (!empty($phpAds_config['geotracking_location']) && file_exists($phpAds_config['geotracking_location'])) {
                    if (is_readable($phpAds_config['geotracking_location'])) {
                        $phpAds_config['geotracking_conf'] = $this->phpAds_geoip_getConf($phpAds_config['geotracking_location']);
                    } else {
                        $message .= "A GeoIP database is not readable." . $postWarningMessage;
                        $oUpgrader->oLogger->logWarning($message);
                    }

                    if (empty($phpAds_config['geotracking_conf'])) {
                        $message .= "A GeoIP database malformed." . $postWarningMessage;
                        $oUpgrader->oLogger->logWarning($message);
                    }
                } else {
                    $message .= "A GeoIP database was not found." . $postWarningMessage;
                    $oUpgrader->oLogger->logWarning($message);
                }
            } elseif ($phpAds_config['geotracking_type'] == 'ip2country') {
                $message .= "The Ip2Country geotargeting database is no longer supported." . $postWarningMessage;
                $oUpgrader->oLogger->logWarning($message);
            }
            // Warn about the fact that PAN region geotargeting cannot be upgraded
            $message = "Warning: Some Openads 2.0 geotargeting limitations may need to be modified on upgrade. " .
                       "Please see the <a href='" . PRODUCT_DOCSURL . "/faq'>FAQ</a> for more information.";
            $oUpgrader->oLogger->logWarning($message);
        }

        return true;
    }


    public static function phpAds_geoip_getConf($db)
    {
        $ret = '';

        if ($db && ($fp = @fopen($db, "rb"))) {
            $info = OA_phpAdsNew::phpAds_geoip_get_info($fp);

            $info['plugin_conf']['databaseTimestamp'] = filemtime($db);

            $ret = serialize($info['plugin_conf']);

            @fclose($fp);
        }

        return $ret;
    }

    private static function phpAds_geoip_get_defaults()
    {
        return [
            'COUNTRY_BEGIN' => 16776960,
            'STATE_BEGIN_REV0' => 16700000,
            'STATE_BEGIN_REV1' => 16000000,
            'GEOIP_STANDARD' => 0,
            'GEOIP_MEMORY_CACHE' => 1,
            'GEOIP_SHARED_MEMORY' => 2,
            'STRUCTURE_INFO_MAX_SIZE' => 20,
            'DATABASE_INFO_MAX_SIZE' => 100,

            'GEOIP_COUNTRY_EDITION' => 1,
            'GEOIP_PROXY_EDITION' => 8,
            'GEOIP_ASNUM_EDITION' => 9,
            'GEOIP_NETSPEED_EDITION' => 10,
            'GEOIP_REGION_EDITION_REV0' => 7,
            'GEOIP_REGION_EDITION_REV1' => 3,
            'GEOIP_CITY_EDITION_REV0' => 6,
            'GEOIP_CITY_EDITION_REV1' => 2,
            'GEOIP_ORG_EDITION' => 5,
            'GEOIP_ISP_EDITION' => 4,

            'SEGMENT_RECORD_LENGTH' => 3,
            'STANDARD_RECORD_LENGTH' => 3,
            'ORG_RECORD_LENGTH' => 4,
            'MAX_RECORD_LENGTH' => 4,
            'MAX_ORG_RECORD_LENGTH' => 300,
            'FULL_RECORD_LENGTH' => 50,

            'US_OFFSET' => 1,
            'CANADA_OFFSET' => 677,
            'WORLD_OFFSET' => 1353,
            'FIPS_RANGE' => 360,

            'GEOIP_UNKNOWN_SPEED' => 0,
            'GEOIP_DIALUP_SPEED' => 1,
            'GEOIP_CABLEDSL_SPEED' => 2,
            'GEOIP_CORPORATE_SPEED' => 3
        ];
    }

    private static function phpAds_geoip_get_info($fp)
    {
        // Default variables
        extract(self::phpAds_geoip_get_defaults());

        /* default to GeoIP Country Edition */
        $databaseType = $GEOIP_COUNTRY_EDITION;
        $record_length = $STANDARD_RECORD_LENGTH;
        fseek($fp, -3, SEEK_END);

        $buf = str_repeat('\0', $SEGMENT_RECORD_LENGTH);

        for ($i = 0; $i < $STRUCTURE_INFO_MAX_SIZE; $i++) {
            $delim = fread($fp, 3);

            if ($delim == "\xFF\xFF\xFF") {
                $databaseType = ord(fread($fp, 1));

                if ($databaseType >= 106) {
                    /* backwards compatibility with databases from April 2003 and earlier */
                    $databaseType -= 105;
                }

                if ($databaseType == $GEOIP_REGION_EDITION_REV0) {
                    /* Region Edition, pre June 2003 */
                    $databaseSegments = $STATE_BEGIN_REV0;
                } elseif ($databaseType == $GEOIP_REGION_EDITION_REV1) {
                    /* Region Edition, post June 2003 */
                    $databaseSegments = $STATE_BEGIN_REV1;
                } elseif ($databaseType == $GEOIP_CITY_EDITION_REV0 ||
                        $databaseType == $GEOIP_CITY_EDITION_REV1 ||
                        $databaseType == $GEOIP_ORG_EDITION ||
                        $databaseType == $GEOIP_ISP_EDITION ||
                        $databaseType == $GEOIP_ASNUM_EDITION) {
                    /* City/Org Editions have two segments, read offset of second segment */
                    $databaseSegments = 0;
                    $buf = fread($fp, $SEGMENT_RECORD_LENGTH);
                    for ($j = 0; $j < $SEGMENT_RECORD_LENGTH; $j++) {
                        $databaseSegments |= (ord($buf[$j]) << ($j << 3));
                    }
                    if ($databaseType == $GEOIP_ORG_EDITION ||
                        $databaseType == $GEOIP_ISP_EDITION) {
                        $record_length = $ORG_RECORD_LENGTH;
                    }
                }
                break;
            } else {
                fseek($fp, -4, SEEK_CUR);
            }
        }

        if ($databaseType == $GEOIP_COUNTRY_EDITION ||
            $databaseType == $GEOIP_PROXY_EDITION ||
            $databaseType == $GEOIP_NETSPEED_EDITION) {
            $databaseSegments = $COUNTRY_BEGIN;
        }

        if (!isset($databaseSegments)) {
            // There was an error: db not supported?
            return false;
        }

        return [
            'plugin_conf' => [
                'databaseType' => $databaseType,
                'databaseSegments' => $databaseSegments,
                'record_length' => $record_length
            ],
            'capabilities' => [
                'country' => in_array($databaseType, [$GEOIP_COUNTRY_EDITION, $GEOIP_REGION_EDITION_REV0, $GEOIP_REGION_EDITION_REV1, $GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'continent' => in_array($databaseType, [$GEOIP_COUNTRY_EDITION, $GEOIP_REGION_EDITION_REV0, $GEOIP_REGION_EDITION_REV1, $GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'region' => in_array($databaseType, [$GEOIP_REGION_EDITION_REV0, $GEOIP_REGION_EDITION_REV1, $GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'fips_code' => in_array($databaseType, [$GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'city' => in_array($databaseType, [$GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'postal_code' => in_array($databaseType, [$GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'latitude' => in_array($databaseType, [$GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'longitude' => in_array($databaseType, [$GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'dma_code' => in_array($databaseType, [$GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'area_code' => in_array($databaseType, [$GEOIP_CITY_EDITION_REV0, $GEOIP_CITY_EDITION_REV1]),
                'org_isp' => in_array($databaseType, [$GEOIP_ORG_EDITION, $GEOIP_ISP_EDITION]),
                'netspeed' => $databaseType == $GEOIP_NETSPEED_EDITION
            ]
        ];
    }

    public static function phpPgAdsIndexToOpenads($index, $table, $prefix)
    {
        return substr($index, 0, 30 - strlen($table) - strlen($prefix));
    }

    public static function phpPgAdsPrefixedIndex($index, $prefix)
    {
        return substr($prefix . $index, 0, 31);
    }
}
