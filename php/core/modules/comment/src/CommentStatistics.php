<?php
/**
 * @file
 * Contains \Drupal\comment\CommentStatistics.
 */

namespace Drupal\comment;


use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;

class CommentStatistics implements CommentStatisticsInterface {

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The current logged in user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The entity manager service.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs the CommentStatistics service.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The active database connection.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current logged in user.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(Connection $database, AccountInterface $current_user, EntityManagerInterface $entity_manager, StateInterface $state) {
    $this->database = $database;
    $this->currentUser = $current_user;
    $this->entityManager = $entity_manager;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function read($entities, $entity_type) {
    return $this->database->select('comment_entity_statistics', 'ces')
      ->fields('ces')
      ->condition('ces.entity_id', array_keys($entities))
      ->condition('ces.entity_type', $entity_type)
      ->execute()
      ->fetchAllAssoc('entity_id');
  }

  /**
   * {@inheritdoc}
   */
  public function delete(EntityInterface $entity) {
    $this->database->delete('comment_entity_statistics')
      ->condition('entity_id', $entity->id())
      ->condition('entity_type', $entity->getEntityTypeId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function create(ContentEntityInterface $entity, $fields) {
    $query = $this->database->insert('comment_entity_statistics')
      ->fields(array(
        'entity_id',
        'entity_type',
        'field_id',
        'cid',
        'last_comment_timestamp',
        'last_comment_name',
        'last_comment_uid',
        'comment_count',
      ));
    foreach ($fields as $field_name => $detail) {
      // Skip fields that entity does not have.
      if (!$entity->hasField($field_name)) {
        continue;
      }
      // Get the user ID from the entity if it's set, or default to the
      // currently logged in user.
      $last_comment_uid = 0;
      if ($entity instanceof EntityOwnerInterface) {
        $last_comment_uid = $entity->getOwnerId();
      }
      if (!isset($last_comment_uid)) {
        // Default to current user when entity does not implement
        // EntityOwnerInterface or author is not set.
        $last_comment_uid = $this->currentUser->id();
      }
      // Default to REQUEST_TIME when entity does not have a changed property.
      $last_comment_timestamp = REQUEST_TIME;
      if ($entity instanceof EntityChangedInterface) {
        $last_comment_timestamp = $entity->getChangedTime();
      }
      $query->values(array(
        'entity_id' => $entity->id(),
        'entity_type' => $entity->getEntityTypeId(),
        'field_id' => $entity->getEntityTypeId() . '__' . $field_name,
        'cid' => 0,
        'last_comment_timestamp' => $last_comment_timestamp,
        'last_comment_name' => NULL,
        'last_comment_uid' => $last_comment_uid,
        'comment_count' => 0,
      ));
    }
    $query->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumCount($entity_type) {
    return $this->database->query('SELECT MAX(comment_count) FROM {comment_entity_statistics} WHERE entity_type = :entity_type', array(':entity_type' => $entity_type))->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getRankingInfo() {
    return array(
      'comments' => array(
        'title' => t('Number of comments'),
        'join' => array(
          'type' => 'LEFT',
          'table' => 'comment_entity_statistics',
          'alias' => 'ces',
          // Default to comment field as this is the most common use case for
          // nodes.
          'on' => "ces.entity_id = i.sid AND ces.entity_type = 'node' AND ces.field_id = 'node__comment'",
        ),
        // Inverse law that maps the highest reply count on the site to 1 and 0
        // to 0. Note that the CAST here is necessary for PostgreSQL, because the
        // PostgreSQL PDO driver sometimes puts values in as strings instead of
        // numbers in complex expressions like this.
        'score' => '2.0 - 2.0 / (1.0 + ces.comment_count * (CAST (:comment_scale AS DECIMAL(10, 4))))',
        'arguments' => array(':comment_scale' => \Drupal::state()->get('comment.node_comment_statistics_scale') ?: 0),
      ),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function update(CommentInterface $comment) {
    // Allow bulk updates and inserts to temporarily disable the maintenance of
    // the {comment_entity_statistics} table.
    if (!$this->state->get('comment.maintain_entity_statistics')) {
      return;
    }

    $query = $this->database->select('comment', 'c');
    $query->addExpression('COUNT(cid)');
    $count = $query->condition('c.entity_id', $comment->getCommentedEntityId())
      ->condition('c.entity_type', $comment->getCommentedEntityTypeId())
      ->condition('c.field_id', $comment->getFieldId())
      ->condition('c.status', CommentInterface::PUBLISHED)
      ->execute()
      ->fetchField();

    if ($count > 0) {
      // Comments exist.
      $last_reply = $this->database->select('comment', 'c')
        ->fields('c', array('cid', 'name', 'changed', 'uid'))
        ->condition('c.entity_id', $comment->getCommentedEntityId())
        ->condition('c.entity_type', $comment->getCommentedEntityTypeId())
        ->condition('c.field_id', $comment->getFieldId())
        ->condition('c.status', CommentInterface::PUBLISHED)
        ->orderBy('c.created', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchObject();
      // Use merge here because entity could be created before comment field.
      $this->database->merge('comment_entity_statistics')
        ->fields(array(
          'cid' => $last_reply->cid,
          'comment_count' => $count,
          'last_comment_timestamp' => $last_reply->changed,
          'last_comment_name' => $last_reply->uid ? '' : $last_reply->name,
          'last_comment_uid' => $last_reply->uid,
        ))
        ->keys(array(
          'entity_id' => $comment->getCommentedEntityId(),
          'entity_type' => $comment->getCommentedEntityTypeId(),
          'field_id' => $comment->getFieldId(),
        ))
        ->execute();
    }
    else {
      // Comments do not exist.
      $entity = $comment->getCommentedEntity();
      // Get the user ID from the entity if it's set, or default to the
      // currently logged in user.
      if ($entity instanceof EntityOwnerInterface) {
        $last_comment_uid = $entity->getOwnerId();
      }
      if (!isset($last_comment_uid)) {
        // Default to current user when entity does not implement
        // EntityOwnerInterface or author is not set.
        $last_comment_uid = $this->currentUser->id();
      }
      $this->database->update('comment_entity_statistics')
        ->fields(array(
          'cid' => 0,
          'comment_count' => 0,
          // Use the created date of the entity if it's set, or default to
          // REQUEST_TIME.
          'last_comment_timestamp' => ($entity instanceof EntityChangedInterface) ? $entity->getChangedTime() : REQUEST_TIME,
          'last_comment_name' => '',
          'last_comment_uid' => $last_comment_uid,
        ))
        ->condition('entity_id', $comment->getCommentedEntityId())
        ->condition('entity_type', $comment->getCommentedEntityTypeId())
        ->condition('field_id', $comment->getFieldId())
        ->execute();
    }
  }

}
