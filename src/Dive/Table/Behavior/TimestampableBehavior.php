<?php
 /*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Table\Behavior;

use Dive\Record;
use Dive\Record\RecordEvent;

/**
 * Class TimestampableBehavior
 *
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 */
class TimestampableBehavior extends Behavior
{

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            Record::EVENT_PRE_SAVE => 'onSave',
            Record::EVENT_PRE_UPDATE => 'onUpdate',
            Record::EVENT_PRE_INSERT => 'onInsert'
        );
    }


    /**
     * @param RecordEvent $event
     * @param string      $eventName
     */
    private function process(RecordEvent $event, $eventName)
    {
        $record = $event->getRecord();
        $tableName = $record->getTable()->getTableName();
        $fields = $this->getTableEventFields($tableName, $eventName);
        if ($fields) {
            $this->setFieldTimestamps($record, $fields);
        }
    }


    /**
     * @param RecordEvent $event
     */
    public function onSave(RecordEvent $event)
    {
        $this->process($event, __FUNCTION__);
    }


    /**
     * @param RecordEvent $event
     */
    public function onInsert(RecordEvent $event)
    {
        $this->process($event, __FUNCTION__);
    }


    /**
     * @param RecordEvent $event
     */
    public function onUpdate(RecordEvent $event)
    {
        $this->process($event, __FUNCTION__);
    }


    /**
     * @return string
     */
    public function getTimestamp()
    {
        $datetime = new \DateTime();
        return $datetime->format('Y-m-d H:i:s');
    }


    /**
     * @param  string $tableName
     * @param  string $eventName
     * @return array
     */
    private function getTableEventFields($tableName, $eventName)
    {
        $config = $this->getTableConfig($tableName);
        if (!empty($config[$eventName])) {
            return (array)$config[$eventName];
        }
        return array();
    }


    /**
     * @param Record $record
     * @param array  $fields
     */
    private function setFieldTimestamps(Record $record, array $fields)
    {
        $timestamp = $this->getTimestamp();
        foreach ($fields as $field) {
            $record->set($field, $timestamp);
        }
    }
}
