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

namespace Wallee\Provider;

/**
 * Provider of label descriptor group information from the gateway.
 */
class LabelDescriptionGroup extends AbstractProvider {

	protected function __construct(\Registry $registry){
		parent::__construct($registry, 'oc_wallee_label_descriptor_group');
	}

	/**
	 * Returns the label descriptor group by the given code.
	 *
	 * @param int $id
	 * @return \Wallee\Sdk\Model\LabelDescriptorGroup
	 */
	public function find($id){
		return parent::find($id);
	}

	/**
	 * Returns a list of label descriptor groups.
	 *
	 * @return \Wallee\Sdk\Model\LabelDescriptorGroup[]
	 */
	public function getAll(){
		return parent::getAll();
	}

	protected function fetchData(){
		$label_descriptor_group_service = new \Wallee\Sdk\Service\LabelDescriptionGroupService(
				\WalleeHelper::instance($this->registry)->getApiClient());
		return $label_descriptor_group_service->all();
	}

	protected function getId($entry){
		/* @var \Wallee\Sdk\Model\LabelDescriptorGroup $entry */
		return $entry->getId();
	}
}