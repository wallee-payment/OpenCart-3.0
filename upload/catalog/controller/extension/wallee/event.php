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
require_once modification(DIR_SYSTEM . 'library/wallee/helper.php');

/**
 * Frontend event hook handler
 * See admin/model/extension/wallee/setup::addEvents
 */
class ControllerExtensionWalleeEvent extends Wallee\Controller\AbstractEvent {

	public function includeScripts(){
		try {
			$this->includeCronScript();
			$this->includeDeviceIdentifier();
		}
		catch (Exception $e) {
		}
	}

	/**
	 * Adds the wallee device identifier script
	 *
	 * @param string $route
	 * @param array $parameters
	 * @param object $output
	 */
	public function includeCronScript(){
		\Wallee\Entity\Cron::cleanUpHangingCrons($this->registry);
		\Wallee\Entity\Cron::insertNewPendingCron($this->registry);
		
		$security_token = \Wallee\Entity\Cron::getCurrentSecurityTokenForPendingCron($this->registry);
		if ($security_token) {
			$cronUrl = $this->createUrl('extension/wallee/cron', array(
				'security_token' => $security_token
			));
			$this->document->addScript($cronUrl . '" async="async');
		}
	}

	/**
	 * Adds the wallee device identifier script
	 *
	 * @param string $route
	 * @param array $parameters
	 * @param object $output
	 */
	public function includeDeviceIdentifier(){
		$script = \WalleeHelper::instance($this->registry)->getBaseUrl();
		$script .= '/s/[spaceId]/payment/device.js?sessionIdentifier=[UniqueSessionIdentifier]';
		
		$this->setDeviceCookie();
		
		$script = str_replace(array(
			'[spaceId]',
			'[UniqueSessionIdentifier]'
		), array(
			$this->config->get('wallee_space_id'),
			$this->request->cookie['wallee_device_id']
		), $script);
		
		// async hack
		$script .= '" async="async';
		
		$this->document->addScript($script);
	}

	private function setDeviceCookie(){
		if (isset($this->request->cookie['wallee_device_id'])) {
			$value = $this->request->cookie['wallee_device_id'];
		}
		else {
			$this->request->cookie['wallee_device_id'] = $value = \WalleeHelper::generateUuid();
		}
		setcookie('wallee_device_id', $value, time() + 365 * 24 * 60 * 60, '/');
	}

	/**
	 * Prevent line item changes to authorized wallee transactions.
	 *
	 * @param string $route
	 * 	 Not used in this scope but required by the caller.
	 * @param array $parameters
	 * 
	 * @see \Action::execute(), system/engine/action.php
	 */
	public function canSaveOrder(string $route, array $parameters) {
		if (!(count($parameters) && is_numeric($parameters[0]))) {
			return;
		}
		$order_id = $parameters[0];
		
		$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $order_id);
		
		if ($transaction_info->getId() === null) {
			// not a wallee transaction
			return;
		}
		
		if (\WalleeHelper::isEditableState($transaction_info->getState())) {
			// changing line items still permitted
			return;
		}
		
		$old_order = $this->getOldOrderLineItemData($order_id);
		$new_order = $this->getNewOrderLineItemData($parameters[1]);
		
		foreach ($new_order as $key => $new_item) {
			foreach ($old_order as $old_item) {
				if ($old_item['id'] == $new_item['id'] && \WalleeHelper::instance($this->registry)->areAmountsEqual($old_item['total'],
						$new_item['total'], $transaction_info->getCurrency())) {
					unset($new_order[$key]);
					break;
				}
			}
		}
		
		if (!empty($new_order)) {
			\WalleeHelper::instance($this->registry)->log($this->language->get('error_order_edit') . " ($order_id)", \WalleeHelper::LOG_ERROR);
			
			$this->language->load('extension/payment/wallee');
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode([
				'error' => $this->language->get('error_order_edit')
			]));
			$this->response->output();
			die();
		}
	}

	public function update(){
		try {
			$this->validate();
			
			$transaction_info = \Wallee\Entity\TransactionInfo::loadByOrderId($this->registry, $this->request->get['order_id']);
			
			if ($transaction_info->getState() == \Wallee\Sdk\Model\TransactionState::AUTHORIZED) {
				\Wallee\Service\Transaction::instance($this->registry)->updateLineItemsFromOrder($this->request->get['order_id']);
				return;
			}
		}
		catch (\Exception $e) {
		}
	}

	/**
	 * Return simple list of ids and total for the given new order information
	 *
	 * @param array $new_order
	 * @return array
	 */
	private function getNewOrderLineItemData(array $new_order){
		$line_items = array();
		
		foreach ($new_order['products'] as $product) {
			$line_items[] = [
				'id' => $product['product_id'],
				'total' => $product['total']
			];
		}
		
		foreach ($new_order['vouchers'] as $voucher) {
			$line_items[] = [
				'id' => $voucher['voucher_id'],
				'total' => $voucher['price']
			];
		}
		
		foreach ($new_order['totals'] as $total) {
			$line_items[] = [
				'id' => $total['code'],
				'total' => $total['value']
			];
		}
		
		return $line_items;
	}

	/**
	 * Return a simple list of ids and total for the existing order identified by order_id
	 *
	 * @param int $order_id
	 * @return array
	 */
	private function getOldOrderLineItemData($order_id){
		$line_items = array();
		$model = \WalleeHelper::instance($this->registry)->getOrderModel();
		
		foreach ($model->getOrderProducts($order_id) as $product) {
			$line_items[] = [
				'id' => $product['product_id'],
				'total' => $product['total']
			];
		}
		
		foreach ($model->getOrderVouchers($order_id) as $voucher) {
			$line_items[] = [
				'id' => $voucher['voucher_id'],
				'total' => $voucher['price']
			];
		}
		
		foreach ($model->getOrderTotals($order_id) as $total) {
			$line_items[] = [
				'id' => $total['code'],
				'total' => $total['value']
			];
		}
		
		return $line_items;
	}

	protected function getRequiredPermission(){
		return '';
	}
}
