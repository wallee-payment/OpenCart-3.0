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
 * Entry point for webhooks
 */
class ControllerExtensionWalleeWebhook extends Controller {

	public function index(){
		$webhook_service = \Wallee\Service\Webhook::instance($this->registry);
		
		$requestBody = trim(file_get_contents("php://input"));
		set_error_handler(array(
			__CLASS__,
			'handleWebhookErrors' 
		));
		try {
			$request = new \Wallee\Webhook\Request(json_decode($requestBody));
			$webhook_model = $webhook_service->getWebhookEntityForId($request->getListenerEntityId());
			if ($webhook_model === null) {
				\WalleeHelper::instance($this->registry)->log(sprintf('Could not retrieve webhook model for listener entity id: %s', $request->getListenerEntityId()), \WalleeHelper::LOG_ERROR);
				header("HTTP/1.1 500 Internal Server Error");
				echo sprintf('Could not retrieve webhook model for listener entity id: %s', $request->getListenerEntityId());
				exit();
			}
			$webhook_handler_class_name = $webhook_model->getHandlerClassName();
			$webhook_handler = $webhook_handler_class_name::instance($this->registry);
			\WalleeHelper::instance($this->registry)->log("Processing webhook ({$request->getEventId()}) with $webhook_handler_class_name", \WalleeHelper::LOG_DEBUG);
			$webhook_handler->process($request);
		}
		catch (Exception $e) {
			header("HTTP/1.1 500 Internal Server Error");
			echo ($e->getMessage());
			exit();
		}
		
		exit();
	}

	public static function handleWebhookErrors($errno, $errstr, $errfile, $errline){
		$fatal = E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
		if ($errno & $fatal) {
			throw new ErrorException($errstr, $errno, E_ERROR, $errfile, $errline);
		}
		return false;
	}
}	