<?php

/**
 * @file
 * Contains \Drupal\migrate\Tests\MigrateSqlIdMapEnsureTablesTest.
 */

namespace Drupal\migrate\Tests;

use Drupal\migrate\Plugin\MigrateIdMapInterface;

/**
 * Tests the \Drupal\migrate\Plugin\migrate\id_map\Sql::ensureTables() method.
 *
 * @group Drupal
 * @group migrate
 */
class MigrateSqlIdMapEnsureTablesTest extends MigrateTestCase {

  /**
   * Whether the map is joinable, initialized to FALSE.
   *
   * @var bool
   */
  protected $mapJoinable = FALSE;

  /**
   * The migration configuration, initialized to set the ID and destination IDs.
   *
   * @var array
   */
  protected $migrationConfiguration = array(
    'id' => 'sql_idmap_test',
    'sourceIds' => array(
      'source_id_property' => array(
        'type' => 'int',
      ),
    ),
    'destinationIds' => array(
      'destination_id_property' => array(
        'type' => 'varchar',
        'length' => 255,
      ),
    ),
  );

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'Sql::ensureTables()',
      'description' => 'Tests the SQL ID map plugin ensureTables() method.',
      'group' => 'Migrate',
    );
  }

  /**
   * Tests the ensureTables method when the tables do not exist.
   */
  public function testEnsureTablesNotExist() {
    $fields['source_row_status'] = array(
      'type' => 'int',
      'size' => 'tiny',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => MigrateIdMapInterface::STATUS_IMPORTED,
      'description' => 'Indicates current status of the source row',
    );
    $fields['rollback_action'] = array(
      'type' => 'int',
      'size' => 'tiny',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => MigrateIdMapInterface::ROLLBACK_DELETE,
      'description' => 'Flag indicating what to do for this item on rollback',
    );
    $fields['last_imported'] = array(
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => 0,
      'description' => 'UNIX timestamp of the last time this row was imported',
    );
    $fields['hash'] = array(
      'type' => 'varchar',
      'length' => '64',
      'not null' => FALSE,
      'description' => 'Hash of source row data, for detecting changes',
    );
    $fields['sourceid1'] = $this->migrationConfiguration['sourceIds']['source_id_property'];
    $fields['destid1'] = $this->migrationConfiguration['destinationIds']['destination_id_property'];
    $fields['destid1']['not null'] = FALSE;
    $map_table_schema = array(
      'description' => 'Mappings from source identifier value(s) to destination identifier value(s).',
      'fields' => $fields,
      'primary key' => array('sourceid1'),
    );
    $schema = $this->getMockBuilder('Drupal\Core\Database\Schema')
      ->disableOriginalConstructor()
      ->getMock();
    $schema->expects($this->at(0))
      ->method('tableExists')
      ->with('migrate_map_sql_idmap_test')
      ->will($this->returnValue(FALSE));
    $schema->expects($this->at(1))
      ->method('createTable')
      ->with('migrate_map_sql_idmap_test', $map_table_schema);
    // Now do the message table.
    $fields = array();
    $fields['msgid'] = array(
      'type' => 'serial',
      'unsigned' => TRUE,
      'not null' => TRUE,
    );
    $fields['sourceid1'] = $this->migrationConfiguration['sourceIds']['source_id_property'];
    $fields['level'] = array(
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => 1,
    );
    $fields['message'] = array(
      'type' => 'text',
      'size' => 'medium',
      'not null' => TRUE,
    );
    $table_schema = array(
      'description' => 'Messages generated during a migration process',
      'fields' => $fields,
      'primary key' => array('msgid'),
    );
    $table_schema['indexes']['sourcekey'] = array('sourceid1');

    $schema->expects($this->at(2))
      ->method('createTable')
      ->with('migrate_message_sql_idmap_test', $table_schema);
    $schema->expects($this->exactly(3))
      ->method($this->anything());
    $this->runEnsureTablesTest($schema);
  }

  /**
   * Tests the ensureTables method when the tables exist.
   */
  public function testEnsureTablesExist() {
    $schema = $this->getMockBuilder('Drupal\Core\Database\Schema')
      ->disableOriginalConstructor()
      ->getMock();
    $schema->expects($this->at(0))
      ->method('tableExists')
      ->with('migrate_map_sql_idmap_test')
      ->will($this->returnValue(TRUE));
    $schema->expects($this->at(1))
      ->method('fieldExists')
      ->with('migrate_map_sql_idmap_test', 'rollback_action')
      ->will($this->returnValue(FALSE));
    $field_schema = array(
      'type' => 'int',
      'size' => 'tiny',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'default' => 0,
      'description' => 'Flag indicating what to do for this item on rollback',
    );
    $schema->expects($this->at(2))
      ->method('addField')
      ->with('migrate_map_sql_idmap_test', 'rollback_action', $field_schema);
    $schema->expects($this->at(3))
      ->method('fieldExists')
      ->with('migrate_map_sql_idmap_test', 'hash')
      ->will($this->returnValue(FALSE));
    $field_schema = array(
      'type' => 'varchar',
      'length' => '64',
      'not null' => FALSE,
      'description' => 'Hash of source row data, for detecting changes',
    );
    $schema->expects($this->at(4))
      ->method('addField')
      ->with('migrate_map_sql_idmap_test', 'hash', $field_schema);
    $schema->expects($this->exactly(5))
      ->method($this->anything());
    $this->runEnsureTablesTest($schema);
  }

  /**
   * Actually run the test.
   *
   * @param array $schema
   *   The mock schema object with expectations set. The Sql constructor calls
   *   ensureTables() which in turn calls this object and the expectations on
   *   it are the actual test and there are no additional asserts added.
   */
  protected function runEnsureTablesTest($schema) {
    $database = $this->getMockBuilder('Drupal\Core\Database\Connection')
      ->disableOriginalConstructor()
      ->getMock();
    $database->expects($this->any())
      ->method('schema')
      ->will($this->returnValue($schema));
    new TestSqlIdMap($database, array(), 'sql', array(), $this->getMigration());
  }

}
