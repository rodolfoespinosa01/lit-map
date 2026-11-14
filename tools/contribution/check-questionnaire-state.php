<?php
use Drupal\lit_contribution\Source\PublishedBaselineAndExports;
$owner=\Drupal\user\Entity\User::load(\Drupal::service('lit_contribution.owner')->ownerId());
if($owner->id()!=4 || $owner->getAccountName()!=='ree32' || $owner->getEmail()!=='ree32@scarletmail.rutgers.edu')throw new RuntimeException('Owner mismatch');
$db=\Drupal::database();$q=$db->select('lit_contribution_review','r');$q->addField('r','survey_id');$q->addField('r','status');$q->addExpression('COUNT(*)','count');$q->groupBy('survey_id')->groupBy('status');
echo json_encode(['owner_verified'=>TRUE,'baseline'=>PublishedBaselineAndExports::baselineSurvey(),'mode'=>\Drupal::service('lit_contribution.invitation')->mode(),'reviews'=>$q->execute()->fetchAll(),'operational_source_count'=>count(\Drupal::service('lit_contribution.export_store')->snapshot())],JSON_PRETTY_PRINT)."\n";
