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

namespace Wallee\Webhook;

/**
 * Webhook processor to handle refund state transitions.
 */
class TransactionRefund extends AbstractOrderRelated {

	/**
	 *
	 * @see AbstractOrderRelated::load_entity()
	 * @return \Wallee\Sdk\Model\Refund
	 */
	protected function loadEntity(Request $request){
		$refund_service = new \Wallee\Sdk\Service\RefundService(\WalleeHelper::instance($this->registry)->getApiClient());
		return $refund_service->read($request->getSpaceId(), $request->getEntityId());
	}

	protected function getOrderId($refund){
		/* @var \Wallee\Sdk\Model\Refund $refund */
		return $refund->getTransaction()->getMerchantReference();
	}
	
	protected function getTransactionId($entity){
		/* @var $entity \Wallee\Sdk\Model\Refund */
		return $entity->getTransaction()->getId();
	}

	protected function processOrderRelatedInner(array $order_info, $refund){
		/* @var \Wallee\Sdk\Model\Refund $refund */
		switch ($refund->getState()) {
			case \Wallee\Sdk\Model\RefundState::FAILED:
				$this->failed($refund, $order_info);
				break;
			case \Wallee\Sdk\Model\RefundState::SUCCESSFUL:
				$this->refunded($refund, $order_info);
			default:
				// Nothing to do.
				break;
		}
	}

	protected function failed(\Wallee\Sdk\Model\Refund $refund, array $order_info){
		$refund_job = \Wallee\Entity\RefundJob::loadByExternalId($this->registry, $refund->getLinkedSpaceId(), $refund->getExternalId());
		
		if ($refund_job->getId()) {
			if ($refund->getFailureReason() != null) {
				$refund_job->setFailureReason($refund->getFailureReason()->getDescription());
			}
			
			$refund_job->setState(\Wallee\Entity\RefundJob::STATE_FAILED_CHECK);
			\Wallee\Entity\Alert::loadFailedJobs($this->registry)->modifyCount(1);
			
			$refund_job->save();
		}
	}

	protected function refunded(\Wallee\Sdk\Model\Refund $refund, array $order_info){
		$refund_job = \Wallee\Entity\RefundJob::loadByExternalId($this->registry, $refund->getLinkedSpaceId(), $refund->getExternalId());
		if ($refund_job->getId()) {
			$refund_job->setState(\Wallee\Entity\RefundJob::STATE_SUCCESS);
			$already_refunded = \Wallee\Entity\RefundJob::sumRefundedAmount($this->registry, $order_info['order_id']);
			
			if (\WalleeHelper::instance($this->registry)->areAmountsEqual($already_refunded + $refund->getAmount(), $order_info['total'],
					$order_info['currency_code'])) {
				$status = 'wallee_refund_status_id';
			}
			else {
				$status = $order_info['order_status_id'];
			}
			
			\WalleeHelper::instance($this->registry)->addOrderHistory($order_info['order_id'], $status,
					sprintf(\WalleeHelper::instance($this->registry)->getTranslation('message_refund_successful'), $refund->getId(),
							$refund->getAmount()), true);
			
			if ($refund_job->getRestock()) {
				$this->restock($refund);
			}
			
			$refund_job->save();
		}
	}

	protected function restock(\Wallee\Sdk\Model\Refund $refund){
		$db = $this->registry->get('db');
		$table = DB_PREFIX . 'product';
		foreach ($refund->getLineItems() as $line_item) {
			if ($line_item->getType() == \Wallee\Sdk\Model\LineItemType::PRODUCT) {
				$quantity = $db->escape($line_item->getQuantity());
				$id = $db->escape($line_item->getUniqueId());
				$query = "UPDATE $table SET quantity=quantity+$quantity WHERE product_id='$id';";
				$db->query($query);
			}
		}
	}
}