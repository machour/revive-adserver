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
require_once MAX_PATH . '/lib/OA/DB/Table/Core.php';
require_once MAX_PATH . '/lib/pear/Date.php';

/**
 * A class for testing the OA_DB_Table_Core class.
 *
 * @package    OpenXDB
 * @subpackage TestSuite
 */
class Test_OA_DB_Table_Core extends UnitTestCase
{
    /**
     * The constructor method.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Method to test the singleton method.
     *
     * Requirements:
     * Test 1: Test that only one instance of the class is created.
     */
    public function testSingleton()
    {
        // Mock the OA_DB class used in the constructor method
        Mock::generate('OA_DB');
        $oDbh = new MockOA_DB($this);

        // Partially mock the OA_DB_Table_Core class, overriding the
        // inherited _getDbConnection() method
        Mock::generatePartial(
            'OA_DB_Table_Core',
            'PartialMockOA_DB_Table_Core',
            ['_getDbConnection']
        );
        $oTable = new PartialMockOA_DB_Table_Core($this);
        $oTable->setReturnReference('_getDbConnection', $oDbh);

        // Test 1
        $oTable1 = &$oTable->singleton();
        $oTable2 = &$oTable->singleton();
        $this->assertIdentical($oTable1, $oTable2);

        // Ensure the singleton is destroyed
        $oTable1->destroy();
    }

    /**
     * Compare the list of tables in dist.conf.php
     * with the tables in tables_core.xml
     *
     * there are places where the list of tables in conf is used
     * such as table core test
     * if tables are not synchronised between schema and conf tests can fail, errors could occurr
     *
     * Test 1: check that schema table exists in conf
     * Test 2: check that conf table exists in schema
     * Test 3 & 4: check differences between dist.conf and working conf (could be reason for some tests failing)
     *
     * if there are differences between working conf and dist.conf
     * it means an upgrade is required
     * conf files are merged during upgrades
     * an upgrade package (pre/postscript is required to do this)
     *
     */
    public function testConfvsSchemaTables()
    {
        $oDbh = OA_DB::singleton();
        $oTable = &OA_DB_Table_Core::singleton();

        $aConfWork = $GLOBALS['_MAX']['CONF'];
        $aConfDist = @parse_ini_file(MAX_PATH . '/etc/dist.conf.php', true);

        $aTablesWork = $aConfWork['table'];
        unset($aTablesWork['prefix']);
        unset($aTablesWork['type']);
        $aTablesDist = $aConfDist['table'];
        unset($aTablesDist['prefix']);
        unset($aTablesDist['type']);
        $aTablesSchema = $oTable->aDefinition['tables'];

        // Test 1
        foreach ($aTablesSchema as $tableName => $aTableDef) {
            $this->assertTrue(in_array($tableName, $aTablesDist), $tableName . ' found in schema but not in dist.conf.php');
        }

        // Test 2
        foreach ($aTablesDist as $tableName => $alias) {
            $this->assertTrue(array_key_exists($tableName, $aTablesSchema), $tableName . ' found in dist.conf but not in tables_core.xml');
        }

        // Test 3
        foreach ($aTablesDist as $tableName => $alias) {
            $this->assertTrue(in_array($tableName, $aTablesWork), $tableName . ' found in dist.conf but not in working config');
        }

        // Test 4
        foreach ($aTablesWork as $tableName => $alias) {
            $this->assertTrue(in_array($tableName, $aTablesDist), $tableName . ' found in working config but not in dist.conf');
        }

        $oTable->destroy();
    }

    /**
     * Tests creating/dropping all of the core tables.
     *
     * Requirements:
     * Test 1: Test that all core tables can be created and dropped.
     */
    public function testAllCoreTables()
    {
        // Test 1
        $conf = &$GLOBALS['_MAX']['CONF'];
        $conf['table']['prefix'] = '';
        $oDbh = OA_DB::singleton();
        $oTable = &OA_DB_Table_Core::singleton();
        $oTable->dropAllTables();
        $aExistingTables = OA_DB_Table::listOATablesCaseSensitive();
        if (PEAR::isError($aExistingTables)) {
            // Can't talk to database, test fails!
            $this->assertTrue(false);
        }
        $this->assertEqual(count($aExistingTables), 0);
        $oTable = &OA_DB_Table_Core::singleton();
        $oTable->createAllTables();
        $aExistingTables = OA_DB_Table::listOATablesCaseSensitive();
        foreach ($conf['table'] as $key => $tableName) {
            if ($key == 'prefix' || $key == 'type') {
                continue;
            }
            // Test that the tables exists
            $this->assertTrue(in_array($tableName, $aExistingTables), 'does not exist: ' . $tableName . ' (found in conf file)');
        }
        $oTable->dropAllTables();
        $aExistingTables = OA_DB_Table::listOATablesCaseSensitive();
        if (PEAR::isError($aExistingTables)) {
            // Can't talk to database, test fails!
            $this->assertTrue(false);
        }
        $this->assertEqual(count($aExistingTables), 0);

        // Ensure the singleton is destroyed
        $oTable->destroy();
    }
}
