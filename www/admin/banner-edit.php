<?php

/*
+---------------------------------------------------------------------------+
| Openads v${RELEASE_MAJOR_MINOR}                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
| For contact details, see: http://www.openads.org/                         |
|                                                                           |
| Copyright (c) 2000-2003 the phpAdsNew developers                          |
| For contact details, see: http://www.phpadsnew.com/                       |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

// Require the initialisation file
require_once '../../init.php';

// Required files
require_once MAX_PATH . '/www/admin/lib-maintenance-priority.inc.php';
require_once MAX_PATH . '/lib/OA/Dal.php';
require_once MAX_PATH . '/www/admin/config.php';
require_once MAX_PATH . '/lib/max/other/common.php';
require_once MAX_PATH . '/lib/max/other/html.php';

$banner = MAX_commonGetValueUnslashed('banner');

// Required files
require_once MAX_PATH . '/www/admin/lib-statistics.inc.php';
require_once MAX_PATH . '/www/admin/lib-storage.inc.php';
require_once MAX_PATH . '/www/admin/lib-swf.inc.php';
require_once MAX_PATH . '/www/admin/lib-banner.inc.php';
require_once MAX_PATH . '/www/admin/lib-zones.inc.php';
require_once MAX_PATH . '/lib/max/Admin_DA.php';
require_once MAX_PATH . '/lib/OA/Maintenance/Priority.php';

// Load plugins
$invPlugins = &MAX_Plugin::getPlugins('inventoryProperties');
foreach($invPlugins as $pluginKey => $plugin) {
    if ($plugin->getType() != 'banner-edit') {
        unset($invPlugins[$pluginKey]);
    }
}

// Register input variables
phpAds_registerGlobalUnslashed(
     'alink'
    ,'alink_chosen'
    ,'alt'
    ,'asource'
    ,'atar'
    ,'autohtml'
    ,'adserver'
    ,'bannertext'
    ,'campaignid'
    ,'checkswf'
    ,'clientid'
    ,'comments'
    ,'description'
    ,'height'
    ,'imageurl'
    ,'keyword'
    ,'message'
    ,'replaceimage'
    ,'replacealtimage'
    ,'status'
    ,'statustext'
    ,'type'
    ,'submit'
    ,'target'
    ,'transparent'
    ,'upload'
    ,'url'
    ,'weight'
    ,'width'
);

// Register input variables for plugins
foreach ($invPlugins as $plugin) {
    call_user_func_array('phpAds_registerGlobalUnslashed', $plugin->getGlobalVars());
}

/*-------------------------------------------------------*/
/* Client interface security                             */
/*-------------------------------------------------------*/
OA_Permission::enforceAccount(OA_ACCOUNT_MANAGER, OA_ACCOUNT_ADVERTISER);
OA_Permission::enforceAccessToObject('clients',   $clientid);
OA_Permission::enforceAccessToObject('campaigns', $campaignid);

if (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    OA_Permission::enforceAllowed(OA_PERM_BANNER_EDIT);
    OA_Permission::enforceAccessToObject('banners', $bannerid);
} else {
    OA_Permission::enforceAccessToObject('banners', $bannerid, true);
}

/*-------------------------------------------------------*/
/* Process submitted form                                */
/*-------------------------------------------------------*/

if (isset($submit)) {
    $doBanners = OA_Dal::factoryDO('banners');
    // Get the existing banner details (if it is not a new banner)
    if (!empty($bannerid)) {
        if ($doBanners->get($bannerid)) {
            $aBanner = $doBanners->toArray();
        }
    }

    $aVariables = array();
    $aVariables['campaignid']      = $campaignid;
    $aVariables['target']          = isset($target) ? phpAds_htmlQuotes($target) : '';
    $aVariables['height']          = isset($height) ? $height : 0;
    $aVariables['width']           = isset($width)  ? $width : 0;
    $aVariables['weight']          = !empty($weight) ? $weight : 0;
    $aVariables['autohtml']        = isset($autohtml) ? 't' : 'f';
    $aVariables['adserver']        = !empty($adserver) ? $adserver : '';
    $aVariables['alt']             = !empty($alt) ? phpAds_htmlQuotes($alt) : '';
    $aVariables['bannertext']      = !empty($bannertext) ? phpAds_htmlQuotes($bannertext) : '';
    $aVariables['htmltemplate']    = !empty($banner) ? $banner : '';
    $aVariables['description']     = !empty($description) ? $description : '';
    $aVariables['imageurl']        = (!empty($imageurl) && $imageurl != 'http://') ? $imageurl : '';
    $aVariables['url']             = (!empty($url) && $url != 'http://') ? $url : '';
    $aVariables['status']          = ($status != '') ? $status : '';
    $aVariables['statustext']      = !empty($statustext) ? $statustext : '';
    $aVariables['storagetype']     = $type;
    $aVariables['filename']        = !empty($aBanner['filename']) ? $aBanner['filename'] : '';
    $aVariables['contenttype']     = !empty($aBanner['contenttype']) ? $aBanner['contenttype'] : '';
    $aVariables['contenttype']     = ($type == 'url') ? _getFileContentType($aVariables['imageurl']) : $aVariables['contenttype'];
    $aVariables['contenttype']     = ($type == 'txt') ? 'txt' : $aVariables['contenttype'];
    $aVariables['alt_filename']    = !empty($aBanner['alt_filename']) ? $aBanner['alt_filename'] : '';
    $aVariables['alt_contenttype'] = !empty($aBanner['alt_contenttype']) ? $aBanner['alt_contenttype'] : '';
    $aVariables['comments']        = $comments;

    if (isset($keyword) && $keyword != '') {
        $keywordArray = split('[ ,]+', trim($keyword));
        $aVariables['keyword'] = implode(' ', $keywordArray);
    } else {
        $aVariables['keyword'] = '';
    }

    $editSwf = false;

    // Deal with any files that are uploaded.
    if (!empty($_FILES['upload']) && $replaceimage == 't') {
        $aFile = _handleUploadedFile('upload', $type);
        if (!empty($aFile)) {
            $aVariables['filename']      = $aFile['filename'];
            $aVariables['contenttype']   = $aFile['contenttype'];
            $aVariables['width']         = $aFile['width'];
            $aVariables['height']        = $aFile['height'];
            $aVariables['pluginversion'] = $aFile['pluginversion'];
            $editSwf                     = $aFile['editswf'];
        }
    }
    if (!empty($_FILES['uploadalt']) && $replacealtimage == 't') {
        $aFile = _handleUploadedFile('uploadalt', $type, true);
        if (!empty($aFile)) {
            $aVariables['alt_filename']    = $aFile['filename'];
            $aVariables['alt_contenttype'] = $aFile['contenttype'];
        }
    }

    // Handle SWF transparency
    if ($aVariables['contenttype'] == 'swf') {
        $aVariables['transparent'] = isset($transparent) && $transparent ? 1 : 0;
    }

    // Update existing hard-coded links if new file has not been uploaded
    if ($aVariables['contenttype'] == 'swf' && empty($_FILES['upload']['tmp_name'])
        && isset($alink) && is_array($alink) && count($alink)) {
        // Prepare the parameters
        $parameters_complete = array();

        // Prepare targets
        if (!isset($atar) || !is_array($atar)) {
            $atar = array();
        }

        foreach ($alink as $key => $val) {
            if (substr($val, 0, 7) == 'http://' && strlen($val) > 7) {
                if (!isset($atar[$key])) {
                    $atar[$key] = '';
                }

                if (isset($alink_chosen) && $alink_chosen == $key) {
                    $final['url'] = $val;
                    $final['target'] = $atar[$key];
                }
/*
                if (isset($asource[$key]) && $asource[$key] != '') {
                    $val .= '|source:'.$asource[$key];
                }
*/
                $parameters_complete[$key] = array(
                    'link' => $val,
                    'tar'  => $atar[$key]
                );
            }
        }

        $parameters = array('swf' => $parameters_complete);
    } else {
        $parameters = null;
    }

    $aVariables['parameters'] = serialize($parameters);

    // Add variables from plugins
    foreach ($invPlugins as $plugin) {
        foreach ($plugin->prepareVariables() as $k => $v) {
            $sqlupdate[] = "{$k}='{$v}'";
        }
    }

    // Delete any old banners...
    if (!empty($aBanner['filename']) && $aBanner['filename'] != $aVariables['filename']) {
        phpAds_ImageDelete($aBanner['type'], $aBanner['filename']);
    }
    if (!empty($aBanner['alt_filename']) && $aBanner['alt_filename'] != $aVariables['alt_filename']) {
        phpAds_ImageDelete($aBanner['type'], $aBanner['alt_filename']);
    }

    // Clients are only allowed to modify certain fields, ensure that other fields are unchanged
    if (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
        $aVariables['weight']       = $aBanner['weight'];
        $aVariables['description']  = $aBanner['name'];
        $aVariables['comments']     = $aBanner['comments'];
    }

    // File the data
    $doBanners->setFrom($aVariables);
    if (!empty($bannerid)) {
        $doBanners->update();
        // check if size has changed
        if ($aVariables['width'] != $aBanner['width'] || $aVariables['height'] != $aBanner['height']) {
            MAX_adjustAdZones($bannerid);
        }
    } else {
        $bannerid = $doBanners->insert();
        // Run the Maintenance Priority Engine process
        OA_Maintenance_Priority::scheduleRun();
    }

    // Determine what the next page is
    if ($editSwf) {
        $nextPage = "banner-swf.php?clientid=$clientid&campaignid=$campaignid&bannerid=$bannerid";
    } elseif (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
        $nextPage = "stats.php?entity=campaign&breakdown=banners&clientid=$clientid&campaignid=$campaignid";
    } else {
        $nextPage = "banner-acl.php?clientid=$clientid&campaignid=$campaignid&bannerid=$bannerid";
    }

    // Go to the next page
    Header("Location: $nextPage");
    exit;
}

/*-------------------------------------------------------*/
/* HTML framework                                        */
/*-------------------------------------------------------*/

if ($bannerid != '') {
    // Fetch the data from the database
    $doBanners = OA_Dal::factoryDO('banners');
    if ($doBanners->get($bannerid)) {
        $row = $doBanners->toArray();
    }

    // Set basic values
    $type        = $row['storagetype'];
    $hardcoded_links   = array();
    $hardcoded_targets = array();
    $hardcoded_sources = array();

    // Check for hard-coded links
    if (!empty($row['parameters'])) {
        $aSwfParams = unserialize($row['parameters']);
        if (!empty($aSwfParams['swf'])) {
            foreach ($aSwfParams['swf'] as $iKey => $aSwf) {
                $hardcoded_links[$iKey]   = $aSwf['link'];
                $hardcoded_targets[$iKey] = $aSwf['tar'];
                $hardcoded_sources[$iKey] = '';
            }
        }
    }
} else {
    // Set default values for new banner
    $row['alt']          = '';
    $row['status']       = '';
    $row['bannertext']   = '';
    $row['url']          = "http://";
    $row['target']       = '';
    $row['imageurl']     = "http://";
    $row['width']        = '';
    $row['height']       = '';
    $row['htmltemplate'] = '';
    $row['description']  = '';
    $row['comments']     = '';
    $row['contenttype']  = '';
    $row['adserver']     = '';
    $row['transparent']  = 0;
    $row['keyword']      = '';

    $hardcoded_links = array();
    $hardcoded_targets = array();
}

$session['htmlerrormsg'] = '';

// Initialise some parameters
$pageName = basename($_SERVER['PHP_SELF']);
$tabindex = 1;
$aEntities = array('clientid' => $clientid, 'campaignid' => $campaignid, 'bannerid' => $bannerid);

$entityId = OA_Permission::getEntityId();
if (OA_Permission::isAccount(OA_ACCOUNT_ADVERTISER)) {
    $entityType = 'advertiser_id';
} else {
    $entityType = 'agency_id';
}

// Display navigation
$aOtherCampaigns = Admin_DA::getPlacements(array($entityType => $entityId));
$aOtherBanners = Admin_DA::getAds(array('placement_id' => $campaignid), false);
MAX_displayNavigationBanner($pageName, $aOtherCampaigns, $aOtherBanners, $aEntities);

/*-------------------------------------------------------*/
/* Main code                                             */
/*-------------------------------------------------------*/

// Determine which bannertypes to show
$show_sql   = $conf['allowedBanners']['sql'];
$show_web   = $conf['allowedBanners']['web'];
$show_url   = $conf['allowedBanners']['url'];
$show_html  = $conf['allowedBanners']['html'];
$show_txt   = $conf['allowedBanners']['text'];

if (isset($type) && $type == "sql")      $show_sql     = true;
if (isset($type) && $type == "web")      $show_web     = true;
if (isset($type) && $type == "url")      $show_url     = true;
if (isset($type) && $type == "html")     $show_html    = true;
if (isset($type) && $type == "txt")      $show_txt     = true;

// If adding a new banner or used storing type is disabled
// determine which bannertype to show as default

if (!isset($type)) {
    if ($show_txt)     $type = "txt";
    if ($show_html)    $type = "html";
    if ($show_url)     $type = "url";
    if ($show_web)     $type = "web";
    if ($show_sql)     $type = "sql";
}

$tabindex = 1;

if (!isset($bannerid) || $bannerid == '') {
    echo "<form action='banner-edit.php' method='POST' enctype='multipart/form-data'>";
    echo "<input type='hidden' name='clientid' value='".$clientid."'>";
    echo "<input type='hidden' name='campaignid' value='".$campaignid."'>";
    echo "<input type='hidden' name='bannerid' value='".$bannerid."'>";

    echo "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
    echo "<tr><td height='25' colspan='3'><b>".$strChooseBanner."</b></td></tr>";
    echo "<tr><td height='25'>";
    echo "<select name='type' onChange='this.form.action=\"banner-edit.php?clientid=".$clientid."&campaignid=".$campaignid."\";this.form.submit();' accesskey='".$keyList."' tabindex='".($tabindex++)."'>";

    if ($show_sql)     echo "<option value='sql'".($type == "sql" ? ' selected' : '').">".$strMySQLBanner."</option>";
    if ($show_web)     echo "<option value='web'".($type == "web" ? ' selected' : '').">".$strWebBanner."</option>";
    if ($show_url)     echo "<option value='url'".($type == "url" ? ' selected' : '').">".$strURLBanner."</option>";
    if ($show_html)    echo "<option value='html'".($type == "html" ? ' selected' : '').">".$strHTMLBanner."</option>";
    if ($show_txt)     echo "<option value='txt'".($type == "txt" ? ' selected' : '').">".$strTextBanner."</option>";

    echo "</select>";
    echo "</td></tr></table>";
    phpAds_ShowBreak();
    echo "</form>";

} else {
    // Only display the notices when *changing* a banner size, not for new banners
    echo "<div class='errormessage' id='warning_change_zone_type' style='display:none'> <img class='errormessage' src='images/errormessage.gif' align='absmiddle' />";
    echo "<span class='tab-r'> {$GLOBALS['strWarning']}:</span><br />";
    echo "{$GLOBALS['strWarnChangeZoneType']}";
    echo "</div>";

    echo "<div class='errormessage' id='warning_change_banner_size' style='display:none'> <img class='errormessage' src='images/warning.gif' align='absmiddle' />";
    echo "<span class='tab-s'> {$GLOBALS['strNotice']}:</span><br />";
    echo "{$GLOBALS['strWarnChangeBannerSize']}";
    echo "</div>";
}

?>

<script language='JavaScript'>
<!--

    <?php

    if (isset($bannerid) && $bannerid != '') {
        echo "document.bannerHeight =" .$row["height"]. ";\n";
        echo "document.bannerWidth =" .$row["width"]. ";\n";
    }

    ?>

    function selectFile(o)
    {
        var filename = o.value.toLowerCase();
        var swflayer = findObj ('swflayer');
        var editbanner = findObj ('editbanner');

        // Show SWF Layer
        if (swflayer) {
            if (filename.indexOf('swf') + 3 == filename.length) {
                swflayer.style.display = '';
            } else {
                swflayer.style.display = 'none';
            }
        }

        // Check upload option
        if (o.name == 'upload' && editbanner.replaceimage[1] && o.value != '') {
            editbanner.replaceimage[1].checked = true;
        }
        if (o.name == 'uploadalt' && editbanner.replacealtimage[1] && o.value != '') {
            editbanner.replacealtimage[1].checked = true;
        }
    }

    function alterHtmlCheckbox() {

        if (editbanner.autohtml.checked) {
            editbanner.adserver.disabled = false;
        } else {
            editbanner.adserver.disabled = true;
        }
    }

    function oa_sizeChangeUpdateMessage(id)
    {
        if (document.bannerWidth != document.bannerForm.width.value ||
            document.bannerHeight !=  document.bannerForm.height.value) {
                oa_show(id);

        } else if (document.bannerWidth == document.bannerForm.width.value &&
                   document.bannerHeight ==  document.bannerForm.height.value) {
            oa_hide(id);
        }
    }

    function oa_show(id)
    {
        var obj = findObj(id);
        if (obj) { obj.style.display = 'block'; }
    }

    function oa_hide(id)
    {
        var obj = findObj(id);
        if (obj) { obj.style.display = 'none'; }
    }

//-->
</script>

<?php

echo "
    <form name='bannerForm' id='bannerForm' action='banner-edit.php' method='POST' enctype='multipart/form-data'";
if($type == 'html') {
    echo " onsubmit='return max_formValidateHtml(this.banner)'";
}
echo ">
        <input type='hidden' name='clientid' value='{$clientid}' />
        <input type='hidden' name='campaignid' value='{$campaignid}' />
        <input type='hidden' name='bannerid' value='{$bannerid}' />
        <input type='hidden' name='type' value='{$type}' />
        <input type='hidden' name='status' value='{$row['status']}' />
";

if(isset($session['htmlerrormsg']) && strlen($session['htmlerrormsg']) > 0) {
    echo '<font color="red">'. $session['htmlerrormsg'] . '</font>';
    echo "&nbsp;<input type='submit' name='submit' value='Save Anyway' tabindex='10'>";
}

if ($type == 'sql') {
    echo "<br /><table border='0' width='100%' cellpadding='0' cellspacing='0' bgcolor='#F6F6F6'>";
    echo "<tr><td height='25' colspan='3' bgcolor='#FFFFFF'><img src='images/icon-banner-stored.gif' align='absmiddle'>&nbsp;<b>".$strMySQLBanner."</b></td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    if (isset($row['filename']) && $row['filename'] != '') {
        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200' valign='top'>".$strUploadOrKeep."</td>";
        echo "<td><table cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr valign='top'><td><input type='radio' name='replaceimage' value='f' checked tabindex='".($tabindex++)."'></td><td>&nbsp;";

        switch ($row['contenttype']) {
            case 'swf':  echo "<img src='images/icon-filetype-swf.gif' align='absmiddle'> ".$row['filename']; break;
            case 'dcr':  echo "<img src='images/icon-filetype-swf.gif' align='absmiddle'> ".$row['filename']; break;
            case 'jpeg': echo "<img src='images/icon-filetype-jpg.gif' align='absmiddle'> ".$row['filename']; break;
            case 'gif':  echo "<img src='images/icon-filetype-gif.gif' align='absmiddle'> ".$row['filename']; break;
            case 'png':  echo "<img src='images/icon-filetype-png.gif' align='absmiddle'> ".$row['filename']; break;
            case 'rpm':  echo "<img src='images/icon-filetype-rpm.gif' align='absmiddle'> ".$row['filename']; break;
            case 'mov':  echo "<img src='images/icon-filetype-mov.gif' align='absmiddle'> ".$row['filename']; break;
            default:     echo "<img src='images/icon-banner-stored.gif' align='absmiddle'> ".$row['filename']; break;
        }

        $size = phpAds_ImageSize($type, $row['filename']);
        if (round($size / 1024) == 0) {
            echo " <i dir='".$phpAds_TextDirection."'>(".$size." bytes)</i>";
        } else {
            echo " <i dir='".$phpAds_TextDirection."'>(".round($size / 1024)." Kb)</i>";
        }

        echo "</td></tr>";
        echo "<tr valign='top'><td><input type='radio' name='replaceimage' value='t' tabindex='".($tabindex++)."'></td>";
        echo "<td>&nbsp;<input class='flat' size='26' type='file' name='upload' style='width:250px;' onChange='selectFile(this);' tabindex='".($tabindex++)."'>";

        echo "<div id='swflayer' style='display:none;'>";
        echo "<input type='checkbox' name='checkswf' value='t' checked tabindex='".($tabindex++)."'>&nbsp;".$strCheckSWF;
        echo "</div>";

        echo "</td></tr></table><br /><br /></td></tr>";
        echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
        echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
    } else {
        echo "<input type='hidden' name='replaceimage' value='t'>";

        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200' valign='top'>".$strNewBannerFile."</td>";
        echo "<td><input class='flat' size='26' type='file' name='upload' style='width:350px;' onChange='selectFile(this);' tabindex='".($tabindex++)."'>";

        echo "<div id='swflayer' style='display:none;'>";
        echo "<input type='checkbox' name='checkswf' value='t' checked tabindex='".($tabindex++)."'>&nbsp;".$strCheckSWF;
        echo "</div>";

        echo "<br /><br /></td></tr>";
        echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
        echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
    }

    if (count($hardcoded_links) == 0) {
        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strURL."</td>";
        echo "<td><input class='flat' size='35' type='text' name='url' style='width:350px;' dir='ltr' value='".phpAds_htmlQuotes($row["url"])."' tabindex='".($tabindex++)."'></td></tr>";

        echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
        echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strTarget."</td>";
        echo "<td><input class='flat' size='16' type='text' name='target' style='width:150px;' dir='ltr' value='".$row["target"]."' tabindex='".($tabindex++)."'></td></tr>";
    } else {
        $i = 0;

        foreach ($hardcoded_links as $key => $val) {
            if ($i > 0) {
                echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
                echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break-l.gif' height='1' width='100%'></td></tr>";
                echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
            }

            echo "<tr><td width='30'>&nbsp;</td>";
            echo "<td width='200'>".$strURL."</td>";
            echo "<td><input class='flat' size='35' type='text' name='alink[".$key."]' style='width:330px;' dir='ltr' value='".phpAds_htmlQuotes($val)."' tabindex='".($tabindex++)."'>";
            echo "<input type='radio' name='alink_chosen' value='".$key."'".($val == $row['url'] ? ' checked' : '')." tabindex='".($tabindex++)."'></td></tr>";

            if (isset($hardcoded_targets[$key])) {
                echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
                echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

                echo "<tr><td width='30'>&nbsp;</td>";
                echo "<td width='200'>".$strTarget."</td>";
                echo "<td><input class='flat' size='16' type='text' name='atar[".$key."]' style='width:150px;' dir='ltr' value='".phpAds_htmlQuotes($hardcoded_targets[$key])."' tabindex='".($tabindex++)."'>";
                echo "</td></tr>";
            }

            if (count($hardcoded_links) > 1) {
                echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
                echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

                echo "<tr><td width='30'>&nbsp;</td>";
                echo "<td width='200'>".$strOverwriteSource."</td>";
                echo "<td><input class='flat' size='50' type='text' name='asource[".$key."]' style='width:150px;' dir='ltr' value='".phpAds_htmlQuotes($hardcoded_sources[$key])."' tabindex='".($tabindex++)."'>";
                echo "</td></tr>";
            }
            $i++;
        }
        echo "<input type='hidden' name='url' value='".$row['url']."'>";
    }

    echo "<tr><td height='30' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strAlt."</td>";
    echo "<td><input class='flat' size='35' type='text' name='alt' style='width:350px;' value='".$row["alt"]."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strStatusText."</td>";
    echo "<td><input class='flat' size='35' type='text' name='statustext' style='width:350px;' value='".phpAds_htmlQuotes($row["statustext"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strTextBelow."</td>";
    echo "<td><input class='flat' size='35' type='text' name='bannertext' style='width:350px;' value='".$row["bannertext"]."' tabindex='".($tabindex++)."'></td></tr>";

    if (isset($bannerid) && $bannerid != '') {
        echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
        echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strSize."</td>";
        echo "<td>".$strWidth.": <input class='flat' size='5' type='text' name='width' value='".$row["width"]."' onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");' tabindex='".($tabindex++)."'>&nbsp;&nbsp;&nbsp;";
        echo $strHeight.": <input class='flat' size='5' type='text' name='height' value='".$row["height"]."' onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");' tabindex='".($tabindex++)."'></td></tr>";
    }

    if (!isset($row['contenttype']) || $row['contenttype'] == 'swf')
    {
        echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
        echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";
        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strSwfTransparency."</td>";
        echo "<td><select name='transparent' tabindex='".($tabindex++)."'>";
            echo "<option value='1'".($row['transparent'] == 1 ? ' selected' : '').">".$strYes."</option>";
            echo "<option value='0'".($row['transparent'] != 1 ? ' selected' : '').">".$strNo."</option>";
        echo "</select></td></tr>";
    }

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "</table>";
}

if ($type == 'web') {
    echo "<br /><table border='0' width='100%' cellpadding='0' cellspacing='0' bgcolor='#F6F6F6'>";
    if (!empty($message)) {
        if ($message == 'invalidFileType') {
            $message = 'you have submitted an invalid file type, please check and resubmit';
        }
        echo "<div class='errormessage' style='width: 500px;'><img class='errormessage' src='images/errormessage.gif' align='absmiddle'>";
        echo "<span class='tab-r'>$message</span></div>";
    }
    if ($row['contenttype'] == 'swf' && empty($row['alt_contenttype'])) {
        echo "<div class='errormessage'><img class='errormessage' src='images/warning.gif' align='absmiddle'>";
        echo "<span class='tab-s'>This flash creative does not have a backup gif</span><br>";
        echo "</div>";
    }
    echo "<tr><td height='25' colspan='3' bgcolor='#FFFFFF'><img src='images/icon-banner-stored.gif' align='absmiddle'>&nbsp;<b>".$strWebBanner."</b></td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    if (isset($row['filename']) && $row['filename'] != '') {
        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200' valign='top'>".$strUploadOrKeep."</td>";
        echo "<td><table cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr valign='top'><td><input type='radio' name='replaceimage' value='f' checked tabindex='".($tabindex++)."'></td><td>&nbsp;";

        switch ($row['contenttype']) {
            case 'swf':  echo "<img src='images/icon-filetype-swf.gif' align='absmiddle'> ".$row['filename']; break;
            case 'dcr':  echo "<img src='images/icon-filetype-swf.gif' align='absmiddle'> ".$row['filename']; break;
            case 'jpeg': echo "<img src='images/icon-filetype-jpg.gif' align='absmiddle'> ".$row['filename']; break;
            case 'gif':  echo "<img src='images/icon-filetype-gif.gif' align='absmiddle'> ".$row['filename']; break;
            case 'png':  echo "<img src='images/icon-filetype-png.gif' align='absmiddle'> ".$row['filename']; break;
            case 'rpm':  echo "<img src='images/icon-filetype-rpm.gif' align='absmiddle'> ".$row['filename']; break;
            case 'mov':  echo "<img src='images/icon-filetype-mov.gif' align='absmiddle'> ".$row['filename']; break;
            default:     echo "<img src='images/icon-banner-stored.gif' align='absmiddle'> ".$row['filename']; break;
        }

        $size = phpAds_ImageSize($type, $row['filename']);
        if (round($size / 1024) == 0) {
            echo " <i>(".$size." bytes)</i>";
        } else {
            echo " <i>(".round($size / 1024)." Kb)</i>";
        }

        echo "</td></tr>";
        echo "<tr valign='top'><td><input type='radio' name='replaceimage' value='t' tabindex='".($tabindex++)."'></td>";
        echo "<td>&nbsp;<input class='flat' size='26' type='file' name='upload' style='width:250px;' onChange='selectFile(this);' tabindex='".($tabindex++)."'>";

        echo "<div id='swflayer' style='display:none;'>";
        echo "<input type='checkbox' name='checkswf' value='t' checked tabindex='".($tabindex++)."'>&nbsp;".$strCheckSWF;
        echo "</div>";

        echo "</td></tr></table><br /><br /></td></tr>";
        echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
        echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
    } else {
        echo "<input type='hidden' name='replaceimage' value='t'>";
        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200' valign='top'>".$strNewBannerFile."</td>";
        echo "<td><input class='flat' size='26' type='file' name='upload' style='width:350px;' onChange='selectFile(this);' tabindex='".($tabindex++)."'>";

        echo "<div id='swflayer' style='display:none;'>";
        echo "<input type='checkbox' name='checkswf' value='t' checked tabindex='".($tabindex++)."'>&nbsp;".$strCheckSWF;
        echo "</div>";

        echo "<br /><br /></td></tr>";
        echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
        echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
    }

    if ($row['contenttype'] == 'swf') {
        if (isset($row['alt_filename']) && $row['alt_filename'] != '') {
            echo "<tr><td width='30'>&nbsp;</td>";
            echo "<td width='200' valign='top'>$strUploadOrKeepAlt</td>";
            echo "<td><table cellpadding='0' cellspacing='0' border='0'>";
            echo "<tr valign='top'><td><input type='radio' name='replacealtimage' value='f' checked tabindex='".($tabindex++)."'></td><td>&nbsp;";

            echo "<img src='images/icon-filetype-gif.gif' align='absmiddle'> ".$row['alt_filename'];

            $size = phpAds_ImageSize($type, $row['alt_filename']);
            if (round($size / 1024) == 0) {
                echo " <i>(".$size." bytes)</i>";
            } else {
                echo " <i>(".round($size / 1024)." Kb)</i>";
            }

            echo "</td></tr>";
            echo "<tr valign='top'><td><input type='radio' name='replacealtimage' value='t' tabindex='".($tabindex++)."'></td>";
            echo "<td>&nbsp;<input class='flat' size='26' type='file' name='uploadalt' style='width:250px;' onChange='selectFile(this);' tabindex='".($tabindex++)."'>";

            echo "</td></tr></table><br /><br /></td></tr>";
            echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
            echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
        } else {
            echo "<input type='hidden' name='replacealtimage' value='t'>";
            echo "<tr><td width='30'>&nbsp;</td>";
            echo "<td width='200' valign='top'>".$strNewBannerFileAlt."</td>";
            echo "<td><input class='flat' size='26' type='file' name='uploadalt' style='width:350px;' onChange='selectFile(this);' tabindex='".($tabindex++)."'>";

            echo "<br /><br /></td></tr>";
            echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
            echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
        }
    }

    if (count($hardcoded_links) == 0) {
        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strURL."</td>";
        echo "<td><input class='flat' size='35' type='text' name='url' style='width:350px;' dir='ltr' value='".phpAds_htmlQuotes($row["url"])."' tabindex='".($tabindex++)."'></td></tr>";

        echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
        echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strTarget."</td>";
        echo "<td><input class='flat' size='16' type='text' name='target' style='width:150px;' dir='ltr' value='".$row["target"]."' tabindex='".($tabindex++)."'></td></tr>";
    } else {
        $i = 0;

        foreach ($hardcoded_links as $key => $val) {
            if ($i > 0) {
                echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
                echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break-l.gif' height='1' width='100%'></td></tr>";
                echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
            }

            echo "<tr><td width='30'>&nbsp;</td>";
            echo "<td width='200'>".$strURL."</td>";
            echo "<td><input class='flat' size='35' type='text' name='alink[".$key."]' style='width:330px;' dir='ltr' value='".phpAds_htmlQuotes($val)."' tabindex='".($tabindex++)."'>";
            echo "<input type='radio' name='alink_chosen' value='".$key."'".($val == $row['url'] ? ' checked' : '')." tabindex='".($tabindex++)."'></td></tr>";

            if (isset($hardcoded_targets[$key])) {
                echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
                echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

                echo "<tr><td width='30'>&nbsp;</td>";
                echo "<td width='200'>".$strTarget."</td>";
                echo "<td><input class='flat' size='16' type='text' name='atar[".$key."]' style='width:150px;' dir='ltr' value='".phpAds_htmlQuotes($hardcoded_targets[$key])."' tabindex='".($tabindex++)."'>";
                echo "</td></tr>";
            }

            echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
            echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

            echo "<tr><td width='30'>&nbsp;</td>";
            echo "<td width='200'>".$strOverwriteSource."</td>";
            echo "<td><input class='flat' size='50' type='text' name='asource[".$key."]' style='width:150px;' dir='ltr' value='".phpAds_htmlQuotes($hardcoded_sources[$key])."' tabindex='".($tabindex++)."'>";
            echo "</td></tr>";

            $i++;
        }
        echo "<input type='hidden' name='url' value='".$row['url']."'>";
    }

    echo "<tr><td height='30' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strAlt."</td>";
    echo "<td><input class='flat' size='35' type='text' name='alt' style='width:350px;' value='".$row["alt"]."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strStatusText."</td>";
    echo "<td><input class='flat' size='35' type='text' name='statustext' style='width:350px;' value='".phpAds_htmlQuotes($row["statustext"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strTextBelow."</td>";
    echo "<td><input class='flat' size='35' type='text' name='bannertext' style='width:350px;' value='".$row["bannertext"]."' tabindex='".($tabindex++)."'></td></tr>";

    if (isset($bannerid) && $bannerid != '') {
        echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
        echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strSize."</td>";
        echo "<td>".$strWidth.": <input class='flat' size='5' type='text' name='width' value='".$row["width"]."' onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");' tabindex='".($tabindex++)."'>&nbsp;&nbsp;&nbsp;";
        echo $strHeight.": <input class='flat' size='5' type='text' name='height' value='".$row["height"]."' onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");' tabindex='".($tabindex++)."'></td></tr>";
    }

    if (!isset($row['contenttype']) || $row['contenttype'] == 'swf')
    {
        echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
        echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";
        echo "<tr><td width='30'>&nbsp;</td>";
        echo "<td width='200'>".$strSwfTransparency."</td>";
        echo "<td><select name='transparent' tabindex='".($tabindex++)."'>";
            echo "<option value='1'".($row['transparent'] == 1 ? ' selected' : '').">".$strYes."</option>";
            echo "<option value='0'".($row['transparent'] != 1 ? ' selected' : '').">".$strNo."</option>";
        echo "</select></td></tr>";
    }

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "</table>";
}

if ($type == 'url') {
    echo "<br /><table border='0' width='100%' cellpadding='0' cellspacing='0' bgcolor='#F6F6F6'>";
    echo "<tr><td height='25' colspan='3' bgcolor='#FFFFFF'><img src='images/icon-banner-url.gif' align='absmiddle'>&nbsp;<b>".$strURLBanner."</b></td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strNewBannerURL."</td>";
    echo "<td><input class='flat' size='35' type='text' name='imageurl' style='width:350px;' dir='ltr' value='".phpAds_htmlQuotes($row["imageurl"])."' tabindex='".($tabindex++)."'></td></tr>";

    echo "<tr><td height='30' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strURL."</td>";
    echo "<td><input class='flat' size='35' type='text' name='url' style='width:350px;' dir='ltr' value='".phpAds_htmlQuotes($row["url"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strTarget."</td>";
    echo "<td><input class='flat' size='16' type='text' name='target' style='width:150px;' dir='ltr' value='".$row["target"]."' tabindex='".($tabindex++)."'></td></tr>";

    echo "<tr><td height='30' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strAlt."</td>";
    echo "<td><input class='flat' size='35' type='text' name='alt' style='width:350px;' value='".$row["alt"]."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strStatusText."</td>";
    echo "<td><input class='flat' size='35' type='text' name='statustext' style='width:350px;' value='".phpAds_htmlQuotes($row["statustext"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strTextBelow."</td>";
    echo "<td><input class='flat' size='35' type='text' name='bannertext' style='width:350px;' value='".$row["bannertext"]."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strSize."</td>";

    echo "<td>".$strWidth.": <input class='flat' size='5' type='text' name='width' value='".$row["width"]."' " . (!empty($bannerid) ? "onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");'" : '' )." tabindex='".($tabindex++)."'>&nbsp;&nbsp;&nbsp;";
    echo $strHeight.": <input class='flat' size='5' type='text' name='height' value='".$row["height"]."' " . (!empty($bannerid) ? "onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");'" : '' )." tabindex='".($tabindex++)."'></td></tr>";

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "</table>";
}

if ($type == 'html') {
    echo "<br /><table border='0' width='100%' cellpadding='0' cellspacing='0' bgcolor='#F6F6F6'>";
    echo "<tr><td height='25' colspan='3' bgcolor='#FFFFFF'><img src='images/icon-banner-html.gif' align='absmiddle'>&nbsp;<b>".$strHTMLBanner."</b></td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td colspan='2'><textarea class='code' cols='45' rows='10' name='banner' wrap='off' dir='ltr' style='width:550px;";
    echo "' tabindex='".($tabindex++)."'>".htmlspecialchars($row['htmltemplate'])."</textarea></td></tr>";

    // checkbox and dropdown list allowing user to choose whether to alter the html so it can be tracked by other adservers
    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td colspan='2'>";
    echo "<table><tr>";
    echo "<td><img src='images/spacer.gif' height='1' width='250'></td>";
    echo "<td><img src='images/spacer.gif' height='1' width='280'></td>";
    echo "</tr>";
    echo "<tr>";
    echo "<td><input type='checkbox' onClick='alterHtmlCheckbox()' name='autohtml' value='t'".(!isset($row["autohtml"]) || $row["autohtml"] == 't' ? ' checked' : '')." tabindex='".($tabindex++)."'> ".$strAutoChangeHTML."</td>";
    echo "<td align='right'><select name='adserver'>";

    include_once MAX_PATH . '/lib/max/Plugin.php';
    $adPlugins = MAX_Plugin::getPlugins('3rdPartyServers');
    $adPluginsNames = MAX_Plugin::callOnPlugins($adPlugins, 'getName');
    echo "<option value='' " . ($row["adserver"] == '' ? 'selected' : '') . " >".$GLOBALS['strAdserverTypeGeneric']."</option>";
    foreach($adPluginsNames as $adPluginKey => $adPluginName) {
        echo "<option value='{$adPluginKey}' " . ($row["adserver"] == $adPluginKey ? 'selected' : '') . " >".$adPluginName."</option>";
    }
    echo "</select></td>";
    echo "</tr></table>";
    echo "</td>";
    echo "</tr>";
    // end of modified section

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strURL."</td>";
    echo "<td><input class='flat' size='35' type='text' name='url' style='width:350px;' dir='ltr' value='".phpAds_htmlQuotes($row["url"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strTarget."</td>";
    echo "<td><input class='flat' size='35' type='text' name='target' style='width:350px;' dir='ltr' value='".$row["target"]."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strSize."</td>";
    echo "<td>".$strWidth.": <input class='flat' size='5' type='text' name='width' value='".$row["width"]."' " . (!empty($bannerid) ? "onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");'" : '' )." tabindex='".($tabindex++)."'>&nbsp;&nbsp;&nbsp;";
    echo $strHeight.": <input class='flat' size='5' type='text' name='height' value='".$row["height"]."' " . (!empty($bannerid) ? "onChange='oa_sizeChangeUpdateMessage(\"warning_change_banner_size\");'" : '' )." tabindex='".($tabindex++)."'></td></tr>";

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "</table>";
}

if ($type == 'txt') {
    echo "<br /><table border='0' width='100%' cellpadding='0' cellspacing='0' bgcolor='#F6F6F6'>";
    echo "<tr><td height='25' colspan='3' bgcolor='#FFFFFF'><img src='images/icon-banner-text.gif' align='absmiddle'>&nbsp;<b>".$strTextBanner."</b></td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td colspan='2'><textarea class='code' cols='45' rows='10' name='bannertext' wrap='off' style='width:550px; ";
    echo "' tabindex='".($tabindex++)."'>".$row['bannertext']."</textarea></td></tr>";

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strURL."</td>";
    echo "<td><input class='flat' size='35' type='text' name='url' style='width:350px;' dir='ltr' value='".phpAds_htmlQuotes($row["url"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strTarget."</td>";
    echo "<td><input class='flat' size='16' type='text' name='target' style='width:150px;' dir='ltr' value='".$row["target"]."' tabindex='".($tabindex++)."'></td></tr>";

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strStatusText."</td>";
    echo "<td><input class='flat' size='35' type='text' name='statustext' style='width:350px;' value='".phpAds_htmlQuotes($row["statustext"])."' tabindex='".($tabindex++)."'></td></tr>";

    echo "<tr><td height='20' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "</table>";
}

if (OA_Permission::isAccount(OA_ACCOUNT_ADMIN) || OA_Permission::isAccount(OA_ACCOUNT_MANAGER)) {
    echo "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strKeyword."</td>";
    echo "<td><input class='flat' size='35' type='text' name='keyword' style='width:350px;' value='".phpAds_htmlQuotes($row["keyword"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strDescription."</td>";
    echo "<td><input class='flat' size='35' type='text' name='description' style='width:350px;' value='".phpAds_htmlQuotes($row["description"])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td><img src='images/spacer.gif' height='1' width='100%'></td>";
    echo "<td colspan='2'><img src='images/break-l.gif' height='1' width='200' vspace='6'></td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strWeight."</td>";
    echo "<td><input class='flat' size='6' type='text' name='weight' value='".(isset($row["weight"]) ? $row["weight"] : $pref['default_banner_weight'])."' tabindex='".($tabindex++)."'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";
    echo "<tr><td height='1' colspan='3' bgcolor='#888888'><img src='images/break.gif' height='1' width='100%'></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "<tr><td width='30'>&nbsp;</td>";
    echo "<td width='200'>".$strComments."</td>";

    echo "<td><textarea class='code' cols='45' rows='6' name='comments' wrap='off' dir='ltr' style='width:350px;";
    echo "' tabindex='".($tabindex++)."'>".htmlspecialchars($row['comments'])."</textarea></td></tr>";
    echo "<tr><td height='10' colspan='3'>&nbsp;</td></tr>";

    echo "</table>";
}

// Display plugin properties
foreach ($invPlugins as $plugin) {
    $plugin->display($tabindex, $banner);
}

echo "<br /><br />";
echo "<table border='0' width='100%' cellpadding='0' cellspacing='0'>";
echo "<tr><td height='35' colspan='3'><input type='submit' name='submit' value='".$strSaveChanges."' tabindex='".($tabindex++)."'></td></tr>";
echo "</table>";
echo "</form>";

echo "<br /><br />";
echo "<br /><br />";

/*********************************************************/
/* HTML framework                                        */
/*********************************************************/

phpAds_PageFooter();

function _handleUploadedFile($name, $type, $imageOnly=false)
{
    // Set some default parameters
    $aFile = array();
    $aFile['filename'] = '';
    $aFile['contenttype'] = '';
    $aFile['editswf'] = false;
    $aFile['pluginversion'] = 0;
    $aFile['width'] = 0;
    $aFile['height'] = 0;

    if (!empty($_FILES[$name]['name']) && $_FILES[$name]['tmp_name'] != 'none') {
        $uploaded = $_FILES[$name];
        // Store the uploaded file
        _storeUploadedFile($uploaded);
        // Set some parameters of the file...
        $aFile['width'] = $uploaded['width'];
        $aFile['height'] = $uploaded['height'];
        $aFile['contenttype'] = _getFileContentType($uploaded['name']);
        // Set Flash-specific features
        if ($aFile['contenttype'] == 'swf') {

            // Fix any wrong-case'd clickTAG commands
            if(phpAds_SWFCompressed($uploaded['buffer'])) {
                $uploaded['buffer'] = phpAds_SWFDecompress($uploaded['buffer']);
                $uploaded['buffer'] = preg_replace("/([c|C][l|L][i|I][c|C][k|K][t|T][a|A][g|G])/", "clickTAG", $uploaded['buffer']);
                $uploaded['buffer'] = phpAds_SWFCompress($uploaded['buffer']);
            } else {
                $uploaded['buffer'] = preg_replace("/([c|C][l|L][i|I][c|C][k|K][t|T][a|A][g|G])/", "clickTAG", $uploaded['buffer']);
            }

            $aFlashFile = _handleFlashFile($uploaded);
            $aFile = array_merge($aFile, $aFlashFile);
        }
        $aFile['filename'] = phpAds_ImageStore($type, basename(stripslashes($uploaded['name'])), $uploaded['buffer']);
    }
    return $aFile;
}

function _handleFlashFile($uploaded)
{
    $aFile = array();
    // Get dimensions of Flash file
    list ($aFile['width'], $aFile['height']) = phpAds_SWFDimensions($uploaded['buffer']);
    $aFile['pluginversion'] = phpAds_SWFVersion($uploaded['buffer']);
    // Check if the Flash banner includes hard coded urls
    $aFile['editswf'] = ($aFile['pluginversion'] >= 3 && phpAds_SWFInfo($uploaded['buffer']));

    return $aFile;
}

function _getBannerContentType($aVariables, $alt=false)
{
    $contentType = '';
    $type = $aVariables['type'];

    switch ($type) {
        case 'html' :
            $contentType = $alt ? '' : 'html';
            break;
        case 'url' :
            $contentType = $alt ? '' : _getFileContentType($aVariables['imageurl']);
            break;
        case 'txt' :
            $contentType = 'txt';
            break;
        default :
            $fileName = $alt ? $aVariables['alt_filename'] : $aVariables['filename'];
            $contentType = _getFileContentType($fileName, $alt);
    }

    return $contentType;
}
function _getFileContentType($fileName, $alt=false)
{
    $contentType = '';

    $ext = substr($fileName, strrpos($fileName, '.') + 1);
    switch (strtolower($ext)) {
        case 'jpeg': $contentType = 'jpeg'; break;
        case 'jpg':  $contentType = 'jpeg'; break;
        case 'png':  $contentType = 'png';  break;
        case 'gif':  $contentType = 'gif';  break;
        case 'swf':  $contentType = $alt ? '' : 'swf';  break;
        case 'dcr':  $contentType = $alt ? '' : 'dcr';  break;
        case 'rpm':  $contentType = $alt ? '' : 'rpm';  break;
        case 'mov':  $contentType = $alt ? '' : 'mov';  break;
    }
    return $contentType;
}

function _storeUploadedFile(&$uploaded, $imageOnly=false)
{
    if (function_exists('is_uploaded_file')) {
        $upload_valid = @is_uploaded_file($uploaded['tmp_name']);
    } else {
        if (!$tmp_file = get_cfg_var('upload_tmp_dir')) {
            $tmp_file = tempnam('','');
            @unlink($tmp_file);
            $tmp_file = dirname($tmp_file);
        }

        $tmp_file .= '/' . basename($uploaded['tmp_name']);
        $tmp_file = str_replace('\\', '/', $tmp_file);
        $tmp_file  = ereg_replace('/+', '/', $tmp_file);

        $up_file = str_replace('\\', '/', $uploaded['tmp_name']);
        $up_file = ereg_replace('/+', '/', $up_file);

        $upload_valid = ($tmp_file == $up_file);
    }

    $uploadError = true;
    $uploadErrorMessage = '';
    if (!$upload_valid) {
        $uploadErrorMessage = $strErrorUploadSecurity;
    } else {
        if (@file_exists ($uploaded['tmp_name'])) {
            // Read the contents of the file in a buffer
            if ($fp = @fopen($uploaded['tmp_name'], "rb")) {
                $uploaded['buffer'] = '';
                while (!feof($fp)) {
                    $uploaded['buffer'] .= @fread($fp, 8192);
                }
                @fclose ($fp);
                $uploadError = false;
            } else {
                // Check if moving the file is possible
                if (function_exists("move_uploaded_file")) {
                    $tmp_dir = MAX_PATH.'/var/cache/'.basename($uploaded['tmp_name']);

                    // Try to move the file
                    if (@move_uploaded_file ($uploaded['tmp_name'], $tmp_dir)) {
                        $uploaded['tmp_name'] = $tmp_dir;

                        // Try again if the file is readable
                        if ($fp = @fopen($uploaded['tmp_name'], "rb")) {
                            $uploaded['buffer'] = '';
                            while (!feof($fp)) {
                                $uploaded['buffer'] .= @fread($fp, 8192);
                            }
                            @fclose($fp);
                            $uploadError = false;
                        }
                    }
                }
            }

            if ($uploadError && empty($uploadErrorMessage)) {
                $uploadErrorMessage = $strErrorUploadBasedir;
            }

            // Determine width and height
            $size = @getimagesize($uploaded['tmp_name']);
            $uploaded['width'] = $size[0];
            $uploaded['height'] = $size[1];
        } else {
            $uploadErrorMessage = $strErrorUploadUnknown;
        }
    }

    if ($uploadError) {
        phpAds_PageHeader("1");
        phpAds_Die ('Error', $uploadErrorMessage);
    }

    // Remove temporary file
    if (@file_exists($uploaded['tmp_name'])) {
        @unlink ($uploaded['tmp_name']);
    }
}

?>
