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
 * Password recovery DAL for Openads
 *
 * @package OpenXDal
 */

require_once MAX_PATH . '/lib/OA/Dal.php';
require_once MAX_PATH . '/lib/max/Plugin.php';

class OA_Dal_PasswordRecovery extends OA_Dal
{
    /**
     * Search users with a matching email address
     *
     * @param string e-mail
     * @return array matching users
     */
    public function searchMatchingUsers($email)
    {
        $doUsers = OA_Dal::factoryDO('users');
        $doUsers->email_address = $email;
        $doUsers->find();

        $users = [];
        while ($doUsers->fetch()) {
            $users[] = $doUsers->toArray();
        }

        return $users;
    }

    /**
     * Generate and save a recovery ID for a user
     *
     * @param int user ID
     * @return string generated recovery ID
     */
    public function generateRecoveryId($userId)
    {
        $doPwdRecovery = OA_Dal::factoryDO('password_recovery');
        
        // Make sure that recoveryId is unique in password_recovery table
        do {
            $recoveryId = strtoupper(bin2hex(random_bytes(12)));
            $recoveryId = substr(chunk_split($recoveryId, 8, "-"), 0, -1);
            $doPwdRecovery->recovery_id = $recoveryId;
        } while ($doPwdRecovery->find() > 0);

        $doPwdRecovery = OA_Dal::factoryDO('password_recovery');
        $doPwdRecovery->whereAdd('user_id = ' . DBC::makeLiteral($userId));
        $doPwdRecovery->delete(true);

        $doPwdRecovery = OA_Dal::factoryDO('password_recovery');
        $doPwdRecovery->user_type = 'user';
        $doPwdRecovery->user_id = $userId;
        $doPwdRecovery->recovery_id = $recoveryId;
        $doPwdRecovery->updated = OA::getNowUTC();

        $doPwdRecovery->insert();

        return $recoveryId;
    }

    /**
     * Check if recovery ID is valid
     *
     * @param string recovery ID
     * @return bool true if recovery ID is valid
     */
    public function checkRecoveryId($recoveryId)
    {
        $doPwdRecovery = OA_Dal::factoryDO('password_recovery');
        $doPwdRecovery->recovery_id = $recoveryId;

        $now = $doPwdRecovery->quote(OA::getNowUTC());
        $oneHourInterval = OA_Dal::quoteInterval(1, 'hour');

        $doPwdRecovery->whereAdd("updated >= DATE_SUB({$now}, {$oneHourInterval})");

        return (bool)$doPwdRecovery->count();
    }

    /**
     * Save the new password in the user properties
     *
     * @param string recovery ID
     * @param string new password
     * @return bool Ttrue the new password was correctly saved
     */
    public function saveNewPasswordAndLogin($recoveryId, $password)
    {
        $doPwdRecovery = OA_Dal::factoryDO('password_recovery');
        $doPwdRecovery->recovery_id = $recoveryId;
        $doPwdRecoveryClone = clone($doPwdRecovery);
        $doPwdRecovery->find();

        if ($doPwdRecovery->fetch()) {
            $userId = $doPwdRecovery->user_id;
            
            $doPlugin = &OA_Auth::staticGetAuthPlugin();
            $doPlugin->setNewPassword($userId, $password);

            $doPwdRecoveryClone->delete();

            phpAds_SessionStart();
            $doUser = OA_Dal::staticGetDO('users', $userId);
            phpAds_SessionDataRegister(OA_Auth::getSessionData($doUser));
            phpAds_SessionDataStore();

            return true;
        }

        return false;
    }
}
