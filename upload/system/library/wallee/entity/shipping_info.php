<?php
/**
 * Wallee OpenCart
 *
 * This OpenCart module enables to process payments with Wallee (wallee164).
 *
 * @package Whitelabelshortcut\Wallee
 * @author wallee144 (wallee164)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

namespace Wallee\Entity;

/**
 * This entity holds data about a transaction on the gateway.
 *
 * @method int getId()
 * @method void setTransactionId(int $id)
 * @method int getTransactionId()
 * @method void SetSpaceId(int $id)
 * @method int getSpaceId()
 * @method void setTaxClassId(int $id)
 * @method int getTaxClassId()
 * @method void setCost(float $cost)
 * @method float getCost()
 *
 */
class ShippingInfo extends AbstractEntity {
	
	protected static function getFieldDefinition(){
		return array(
			'transaction_id' => ResourceType::INTEGER,
			'space_id' => ResourceType::INTEGER,
			'cost' => ResourceType::DECIMAL,
			'tax_class_id' => ResourceType::INTEGER 
		);
	}

	protected static function getTableName(){
		return 'wallee_shipping_info';
	}

	/**
	 * 
	 * @param \Registry $registry
	 * @param int $space_id
	 * @param int $transaction_id
	 * @return \Wallee\Entity\ShippingInfo
	 */
	public static function loadByTransaction(\Registry $registry, $space_id, $transaction_id){
		$db = $registry->get('db');
		
		$table = DB_PREFIX . self::getTableName();
		$space_id = $db->escape($space_id);
		$transaction_id = $db->escape($transaction_id);
		$query = "SELECT * FROM $table WHERE space_id='$space_id' AND transaction_id='$transaction_id';";
		
		$db_result = self::query($query, $db);
		if (isset($db_result->row) && !empty($db_result->row)) {
			return new self($registry, $db_result->row);
		}
		return new self($registry);
	}
}