<?php
namespace YiiNodeSocket\Models;

class Channel extends AModel {

	const SOURCE_PHP = 1;
	const SOURCE_JAVASCRIPT = 2;
	const SOURCE_PHP_OR_JAVASCRIPT = 3;

	/**
	 * @var string unique channel name
	 */
	public $name;

	/**
	 * @var bool
	 */
	public $is_authentication_required = false;

	/**
	 * @var string separated by comma, can be array
	 */
	public $allowed_roles;

	/**
	 * @var integer
	 */
	public $subscriber_source = self::SOURCE_PHP;

	/**
	 * @var integer
	 */
	public $event_source = self::SOURCE_PHP;

	/**
	 * @var string
	 */
	public $create_date;

	/**
	 * @param string $class
	 *
	 * @return AModel
	 */
	public static function model($class = __CLASS__) {
		return parent::model($class);
	}

	/**
	 * @return array
	 */
	public function getSourceList() {
		return array(
			self::SOURCE_PHP,
			self::SOURCE_JAVASCRIPT,
			self::SOURCE_PHP_OR_JAVASCRIPT
		);
	}

	/**
	 * @return array
	 */
	public function rules() {
		return array_merge(parent::rules(), array(
			array('name, is_authentication_required, subscriber_source, event_source', 'required'),
			array('name', 'validateUniqueName'),
			array('name', 'length', 'min' => 2),
			array('subscriber_source, event_source', 'numerical', 'integerOnly' => true),
			array('subscriber_source, event_source', 'in', 'range' => $this->getSourceList()),
			array('allowed_roles', 'length', 'min' => 1, 'allowEmpty' => true),
			array('create_date', 'safe')
		));
	}

	/**
	 * @param Subscriber $subscriber
	 * @param array      $subscribeOptions
	 *
	 * @return bool
	 */
	public function subscribe(Subscriber $subscriber, array $subscribeOptions = array()) {
		if ($this->getIsNewRecord() || $subscriber->getIsNewRecord()) {
			return false;
		}
		$subscriberChannel = SubscriberChannel::model()->findByAttributes(array(
			'channel_id' => $this->id,
			'subscriber_id' => $subscriber->id
		));
		if ($subscriberChannel) {
			return true;
		}
		return SubscriberChannel::model()->createLink($this, $subscriber, $subscribeOptions);
	}

	/**
	 * @param Subscriber $subscriber
	 *
	 * @return bool
	 */
	public function unSubscribe(Subscriber $subscriber) {
		if ($this->getIsNewRecord() || $subscriber->getIsNewRecord()) {
			return true;
		}
		$subscriberChannel = SubscriberChannel::model()->findByAttributes(array(
			'channel_id' => $this->id,
			'subscriber_id' => $subscriber->id
		));
		if ($subscriberChannel) {
			if ($subscriberChannel->delete()) {
				if (!empty($this->_subscribers)) {
					foreach ($this->_subscribers as $k => $sub) {
						if ($sub->id == $subscriber->id) {
							unset($this->_subscribers[$k]);
							break;
						}
					}
				}
				return true;
			}
			return false;
		}
		return true;
	}

	/**
	 * @param bool $refresh
	 *
	 * @return Subscriber[]
	 */
	public function getSubscribers($refresh = false) {
		return SubscriberChannel::model()->getSubscribers($this, $refresh);
	}

	/**
	 * Returns the list of attribute names of the model.
	 * @return array list of attribute names.
	 */
	public function attributeNames() {
		return array_merge(parent::attributeNames(), array(
			'name',
			'is_authentication_required',
			'allowed_roles',
			'subscriber_source',
			'event_source',
			'create_date'
		));
	}

	/**
	 * @return bool
	 */
	public function validateUniqueName() {
		if (!empty($this->name)) {
			$exists = $this->findByAttributes(array(
				'name' => $this->name
			));
			if ($exists) {
				if (!$this->getIsNewRecord() && $exists->id == $this->id) {
					return true;
				}
				$this->addError('name', 'Channel name should be unique');
				return false;
			}
		}
		return true;
	}



	protected function beforeSave() {
		$this->is_authentication_required = (int) $this->is_authentication_required;
		if (is_array($this->allowed_roles)) {
			$this->allowed_roles = implode(', ', $this->allowed_roles);
		} else if (!is_string($this->allowed_roles)) {
			$this->allowed_roles = '';
		}
		return parent::beforeSave();
	}
}