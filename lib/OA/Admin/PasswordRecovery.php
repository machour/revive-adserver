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

/**
 * Password recovery for Revive Adserver
 *
 */

require_once RV_PATH . '/lib/RV.php';

require_once MAX_PATH . '/lib/OA.php';
require_once MAX_PATH . '/lib/OA/Dal/PasswordRecovery.php';
require_once MAX_PATH . '/lib/OA/Auth.php';
require_once MAX_PATH . '/lib/OA/Email.php';
require_once MAX_PATH . '/lib/OA/ServiceLocator.php';

require_once LIB_PATH . '/Admin/Redirect.php';


class OA_Admin_PasswordRecovery
{
    /**
     *  @var OA_Dal_PasswordRecovery
     */
    public $_dal;

    /**
     * PHP4-style constructor
     */
    public function __construct()
    {
        $this->_useDefaultDal();
    }

    public function _useDefaultDal()
    {
        $oServiceLocator = OA_ServiceLocator::instance();
        $dal = &$oServiceLocator->get('OA_Dal_PasswordRecovery');
        if (!$dal) {
            $dal = new OA_Dal_PasswordRecovery();
        }
        $this->_dal = &$dal;
    }

    /**
     * Display page header
     *
     */
    public function pageHeader()
    {
        phpAds_PageHeader(phpAds_PasswordRecovery);

        echo "<br><br>";
    }

    /**
     * Display page footer and make sure that the session gets destroyed
     *
     */
    public function pageFooter()
    {
        // Remove session
        unset($GLOBALS['session']);

        phpAds_PageFooter();
    }

    /**
     * Display an entire page with the password recovery form.
     *
     * This method, combined with handlePost allows semantic, REST-style
     * actions.
     */
    public function handleGet($vars)
    {
        $this->pageHeader();
        if (empty($vars['id'])) {
            $this->displayRecoveryRequestForm();
        } elseif ($this->_dal->checkRecoveryId($vars['id'])) {
            $this->displayRecoveryResetForm($vars['id']);
        } else {
            OX_Admin_Redirect::redirect();
        }
        $this->pageFooter();
    }

    /**
     * Display an entire page with the password recovery form.
     *
     * This method, combined with handleGet allows semantic, REST-style
     * actions.
     */
    public function handlePost($vars)
    {
        OA_Permission::checkSessionToken();

        $this->pageHeader();
        if (empty($vars['id'])) {
            if (empty($vars['email'])) {
                $this->displayRecoveryRequestForm($GLOBALS['strEmailRequired']);
            } else {
                $sent = $this->sendRecoveryEmail($vars['email']);

                // Always pretend an email was sent, even if not to avoid information disclosure
                $this->displayMessage($GLOBALS['strNotifyPageMessage']);
            }
        } elseif (empty($vars['newpassword']) || empty($vars['newpassword2']) || $vars['newpassword'] != $vars['newpassword2']) {
            $this->displayRecoveryResetForm($vars['id'], $GLOBALS['strNotSamePasswords']);
        } elseif ($this->_dal->checkRecoveryId($vars['id'])) {
            $this->_dal->saveNewPasswordAndLogin($vars['id'], $vars['newpassword']);

            phpAds_SessionRegenerateId(true);
            OX_Admin_Redirect::redirect();
        } else {
            $this->displayRecoveryRequestForm($GLOBALS['strPwdRecWrongId']);
        }
        $this->pageFooter();
    }

    /**
     * Display a message
     *
     * @param string message to be displayed
     */
    public function displayMessage($message)
    {
        phpAds_showBreak();

        echo "<br /><span class='install'>{$message}</span><br /><br />";

        phpAds_showBreak();
    }

    /**
     * Display recovery request form
     *
     * @param string error message text
     */
    public function displayRecoveryRequestForm($errormessage = '')
    {
        if (!empty($errormessage)) {
            echo "<div class='errormessage' style='width: 400px;'><img class='errormessage' src='" . OX::assetPath() . "/images/errormessage.gif' align='absmiddle'>";
            echo "<span class='tab-r'>{$errormessage}</span></div>";
        }

        echo "<form method='post' action='password-recovery.php'>\n";

        echo "<input type='hidden' name='token' value='" . phpAds_SessionGetToken() . "'/>\n";

        echo "<div class='install'>" . $GLOBALS['strPwdRecEnterEmail'] . "</div>";
        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr><td colspan='2'><img src='" . OX::assetPath() . "/images/break-el.gif' width='400' height='1' vspace='8'></td></tr>";
        echo "<tr height='24'><td>" . $GLOBALS['strEMail'] . ":&nbsp;</td><td><input type='text' name='email' /></td></tr>";
        echo "<tr height='24'><td>&nbsp;</td><td><input type='submit' value='" . $GLOBALS['strProceed'] . "' /></td></tr>";
        echo "<tr><td colspan='2'><img src='" . OX::assetPath() . "/images/break-el.gif' width='400' height='1' vspace='8'></td></tr>";
        echo "</table>";

        echo "</form>\n";
    }

    /**
     * Display new password form
     *
     * @param string error message text
     */
    public function displayRecoveryResetForm($id, $errormessage = '')
    {
        if (!empty($errormessage)) {
            echo "<div class='errormessage' style='width: 400px;'><img class='errormessage' src='" . OX::assetPath() . "/images/errormessage.gif' align='absmiddle'>";
            echo "<span class='tab-r'>{$errormessage}</span></div>";
        }

        echo "<form method='post' action='password-recovery.php'>\n";
        echo "<input type='hidden' name='id' value=\"" . htmlspecialchars($id) . "\" />";
        echo "<input type='hidden' name='token' value='" . phpAds_SessionGetToken() . "'/>\n";

        echo "<div class='install'>" . $GLOBALS['strPwdRecEnterPassword'] . "</div>";
        echo "<table cellpadding='0' cellspacing='0' border='0'>";
        echo "<tr><td colspan='2'><img src='" . OX::assetPath() . "/images/break-el.gif' width='400' height='1' vspace='8'></td></tr>";
        echo "<tr height='24'><td>" . $GLOBALS['strPassword'] . ":&nbsp;</td><td><input type='password' name='newpassword' /></td></tr>";
        echo "<tr height='24'><td>" . $GLOBALS['strRepeatPassword'] . ":&nbsp;</td><td><input type='password' name='newpassword2' /></td></tr>";
        echo "<tr height='24'><td>&nbsp;</td><td><input type='submit' value='" . $GLOBALS['strProceed'] . "' /></td></tr>";
        echo "<tr><td colspan='2'><img src='" . OX::assetPath() . "/images/break-el.gif' width='400' height='1' vspace='8'></td></tr>";
        echo "</table>";

        echo "</form>\n";
    }

    /**
     * Check if the user is allowed to see the password recovery tools
     *
     */
    public function checkAccess()
    {
        return !OA_Auth::isLoggedIn() && !OA_Auth::suppliedCredentials();
    }

    /**
     * Send the password recovery email
     *
     * @todo Set email language according to the account preferences
     *
     * @param string email address
     * @return int Number of emails sent
     */
    public function sendRecoveryEmail($email)
    {
        $aConf = &$GLOBALS['_MAX']['CONF'];
        $sent = 0;

        // Find all users matching the specified email address -
        // the email address may be associated with multiple users
        $aUsers = $this->_dal->searchMatchingUsers($email);

        $applicationName = $aConf['ui']['applicationName'] ?: PRODUCT_NAME;

        // Send a separate password reset link in an email for each
        // of the users found that match the email address
        foreach ($aUsers as $u) {
            // Generate the password reset email subject
            $emailSubject = sprintf($GLOBALS['strPwdRecEmailPwdRecovery'], $applicationName);

            // Generate the password reset URL for this user
            $recoveryId = $this->_dal->generateRecoveryId($u['user_id']);
            $recoveryUrl = Max::constructURL(MAX_URL_ADMIN, "password-recovery.php?id={$recoveryId}");

            // Load the appropriate language details for the email recipient
            Language_Loader::load('default', $u['language']);

            // Generate the body of the password reset email for this user
            $emailBody = $GLOBALS['strPwdRecEmailBody'];
            $emailBody = str_replace('{name}', $u['contact_name'], $emailBody);
            $emailBody = str_replace('{username}', $u['username'], $emailBody);
            $emailBody = str_replace('{reset_link}', $recoveryUrl, $emailBody);
            if (!empty($aConf['email']['fromName']) && !empty($aConf['email']['fromAddress'])) {
                $adminSignature = "{$GLOBALS['strPwdRecEmailSincerely']}\n\n{$aConf['email']['fromName']}\n{$aConf['email']['fromAddress']}";
            } elseif (!empty($aConf['email']['fromName'])) {
                $adminSignature = "{$GLOBALS['strPwdRecEmailSincerely']}\n\n{$aConf['email']['fromName']}";
            } elseif (!empty($aConf['email']['fromAddress'])) {
                $adminSignature = "{$GLOBALS['strPwdRecEmailSincerely']}\n\n{$aConf['email']['fromAddress']}";
            } else {
                $adminSignature = "";
            }
            $emailBody = str_replace('{admin_signature}', $adminSignature, $emailBody);
            $emailBody = str_replace('{application_name}', $applicationName, $emailBody);

            // Send the password reset email
            $oEmail = new OA_Email();
            $oEmail->sendMail($emailSubject, $emailBody, $email, $u['username']);

            // Iterate the number of emails sent
            $sent++;
        }

        // Return the number of emails sent
        return $sent;
    }
}
