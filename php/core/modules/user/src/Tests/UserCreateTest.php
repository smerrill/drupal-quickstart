<?php

/**
 * @file
 * Definition of Drupal\user\Tests\UserCreateTest.
 */

namespace Drupal\user\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Test the create user administration page.
 */
class UserCreateTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('image', 'contact');

  public static function getInfo() {
    return array(
      'name' => 'User create',
      'description' => 'Test the create user administration page.',
      'group' => 'User',
    );
  }

  /**
   * Create a user through the administration interface and ensure that it
   * displays in the user list.
   */
  protected function testUserAdd() {
    $user = $this->drupalCreateUser(array('administer users'));
    $this->drupalLogin($user);

    // Create a field and an instance.
    $field_name = 'test_field';
    $field = array(
      'name' => $field_name,
      'entity_type' => 'user',
      'module' => 'image',
      'type' => 'image',
      'cardinality' => 1,
      'locked' => FALSE,
      'indexes' => array('target_id' => array('target_id')),
      'settings' => array(
        'uri_scheme' => 'public',
      ),
    );
    entity_create('field_config', $field)->save();

    $instance = array(
      'field_name' => $field_name,
      'entity_type' => 'user',
      'label' => 'Picture',
      'bundle' => 'user',
      'description' => t('Your virtual face or picture.'),
      'required' => FALSE,
      'settings' => array(
        'file_extensions' => 'png gif jpg jpeg',
        'file_directory' => 'pictures',
        'max_filesize' => '30 KB',
        'alt_field' => 0,
        'title_field' => 0,
        'max_resolution' => '85x85',
        'min_resolution' => '',
      ),
    );
    entity_create('field_instance_config', $instance)->save();

    // Test user creation page for valid fields.
    $this->drupalGet('admin/people/create');
    $this->assertFieldbyId('edit-status-0', 0, 'The user status option Blocked exists.', 'User login');
    $this->assertFieldbyId('edit-status-1', 1, 'The user status option Active exists.', 'User login');
    $this->assertFieldByXPath('//input[@type="radio" and @id="edit-status-1" and @checked="checked"]', NULL, 'Default setting for user status is active.');

    // Test that the password strength indicator displays.
    $config = \Drupal::config('user.settings');

    $config->set('password_strength', TRUE)->save();
    $this->drupalGet('admin/people/create');
    $this->assertRaw(t('Password strength:'), 'The password strength indicator is displayed.');

    $config->set('password_strength', FALSE)->save();
    $this->drupalGet('admin/people/create');
    $this->assertNoRaw(t('Password strength:'), 'The password strength indicator is not displayed.');

    // We create two users, notifying one and not notifying the other, to
    // ensure that the tests work in both cases.
    foreach (array(FALSE, TRUE) as $notify) {
      $name = $this->randomName();
      $edit = array(
        'name' => $name,
        'mail' => $this->randomName() . '@example.com',
        'pass[pass1]' => $pass = $this->randomString(),
        'pass[pass2]' => $pass,
        'notify' => $notify,
      );
      $this->drupalPostForm('admin/people/create', $edit, t('Create new account'));

      if ($notify) {
        $this->assertText(t('A welcome message with further instructions has been e-mailed to the new user @name.', array('@name' => $edit['name'])), 'User created');
        $this->assertEqual(count($this->drupalGetMails()), 1, 'Notification e-mail sent');
      }
      else {
        $this->assertText(t('Created a new user account for @name. No e-mail has been sent.', array('@name' => $edit['name'])), 'User created');
        $this->assertEqual(count($this->drupalGetMails()), 0, 'Notification e-mail not sent');
      }

      $this->drupalGet('admin/people');
      $this->assertText($edit['name'], 'User found in list of users');
      $user = user_load_by_name($name);
      $this->assertEqual($user->isActive(), 'User is not blocked');
    }
  }
}
