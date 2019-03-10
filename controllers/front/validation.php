<?php

require_once _PS_MODULE_DIR_.'trexle/classes/TrexleAPI.php';

class TrexleValidationModuleFrontController extends ModuleFrontController
{

	public function __construct()
	{
		parent::__construct();

		$this->context = Context::getContext();
		$this->ssl = true;
			
	}

   public function initContent()
	{
			
    	if (Configuration::get('TREXLE_SHOW_SIDEBARS') == 0) {
        	$this->display_column_right = false;
			$this->display_column_left = false;
		}
       	parent::initContent();
		$this->assign();
		
    }
	
	public function assign()
	{
		$confirm = Tools::getValue('confirm');
        $debug = Configuration::get('TREXLE_DEBUG'); 
		$success = false; 
		$error = array(); 
		
		if ($debug)
			Trexle::log("here 1 in validation.php in assign function. Confirm Value = ".Tools::getValue('confirm'));			
	
			
		/* Validate payment */
		if ($confirm == 1)
		{
			if ($debug)
			  Trexle::log("here 2 in validation.php in confirm block");
			
			// do final validations
			$cart = $this->context->cart;
			
			if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
				Tools::redirect('index.php?controller=order&step=1');

			// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
			$authorized = false;
			foreach (Module::getPaymentModules() as $module)
				if ($module['name'] == 'trexle')
				{
					$authorized = true;
					break;
				}
			if (!$authorized)
				die($this->module->l('This payment method is not available.', 'validation'));

			$customer = new Customer($cart->id_customer);
			if (!Validate::isLoadedObject($customer))
				Tools::redirect('index.php?controller=order&step=1');

			$currency_iso_code = $this->context->currency->iso_code;
			$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
			
            //// HERE START API CODE
			//
			// PROCESS TRANSACTION
			
			$cc_n = $cc_y = $cc_m = $cc_v = $preauth_id = '';
			$timestamp = date('ymd H:i');
			$ip = $_SERVER['REMOTE_ADDR'];
			$host = gethostbyaddr($ip);

			$mode = (Configuration::get('TREXLE_ENVIRONMENT') == 1 ? 1 : 0);
			$private_key = ($mode == 1 ? Configuration::get('TREXLE_PRIVATE_KEY_LIVE') : Configuration::get('TREXLE_PRIVATE_KEY_TEST'));
			$publishable_key = ($mode == 1 ? Configuration::get('TREXLE_PUBLISHABLE_KEY_LIVE') : Configuration::get('TREXLE_PUBLISHABLE_KEY_TEST'));
			$charge = Configuration::get('TREXLE_CHARGE');
			$txn_type = 'TREXLE_TXN_CHARGE'; 
			
			if (!$charge)
			{
				$txn_type = 'TREXLE_TXN_PREAUTH';
			}
			
			$order_id = $cart->id;
			$reference = Configuration::get('PS_SHOP_NAME').' - Cart ID: '.$order_id;
			$address_invoice = new address($cart->id_address_invoice); 
			$card_name = $address_invoice->firstname . " " . $address_invoice->lastname; 
			$state = State::getNameById($address_invoice->id_state);
			$country = Country::getNameById($this->context->language->id, $address_invoice->id_country); 
			
			$debug_message = "here in validation.php - ".$timestamp.': '.$txn_type.' request from '.$ip.' ['.$host.']'."<br />\n";
			
			if ($debug)
			{
					Trexle::log($debug_message);	
					Trexle::log("GET Mode: ".Configuration::get('TREXLE_ENVIRONMENT'));	
					Trexle::log("Mode: ".$mode);	
					Trexle::log("Private_key: ".$private_key);	
					Trexle::log("publishable_key: ".$publishable_key);	
					Trexle::log("Order Total: ".$total);	
			} 
			
			$txn = new trexle_transaction($mode, $private_key, $publishable_key, $debug);
		
			$cc_n = Tools::getValue('cc_number');
			$cc_m = Tools::getValue('cc_month');
			$cc_y = Tools::getValue('cc_year');
			$cc_v = Tools::getValue('cc_cvv');
			
			//echo "cc_n:".$cc_n."cc_m".$cc_m."cc_y".$cc_y."cc_v".$cc_v;
			
			if (!$cc_n || !$cc_y || !$cc_m || !$cc_v)
			{ 	
				$error = array('msg' => 'Some payment information was missing. Please try again, and ensure that all of the fields below have been filled in.', 'err' => 1); 
				
				if ($debug)
				{
					Trexle::log("Here in validation.php missing card information: ". Tools::jsonEncode($error));	
				} 
				
				die(Tools::jsonEncode($error));	 
			} 
			else
			{
				if ($debug)
				{
					Trexle::log("Here in validation.php txn_type : ".$txn_type);	
				} 
				
				if ($txn_type == 'TREXLE_TXN_PREAUTH')
				{
					$result = $txn->processCreditPreauth($total, $reference, $card_name, $address_invoice->address1, $address_invoice->address2, $address_invoice->city, $address_invoice->postcode, $state, $country, $customer->email, $cc_n, $cc_m, $cc_y, $cc_v, $currency_iso_code);
					$success = strtoupper($result['success']) == 'YES' ? true : false;
					$order_status = Configuration::get('TREXLE_PREAUTH_STATUS');
					
				} else if ($txn_type == 'TREXLE_TXN_CHARGE')
				{
					$result = $txn->processCreditCharge($total, $reference, $card_name, $address_invoice->address1, $address_invoice->address2, $address_invoice->city, $address_invoice->postcode, $state, $country, $customer->email, $cc_n, $cc_m, $cc_y, $cc_v, $currency_iso_code);
				    //$result = array(
					//	'success' => 'yes',
					//	'transactionid' => 'DummyTransID');
					$success = strtoupper($result['success']) == 'YES' ? true : false;
					$order_status = _PS_OS_PAYMENT_;
				}		
			}
			
			if ($success)
			{
				
				$auth_only = ''; 
				if ($txn_type == 'TREXLE_TXN_PREAUTH')
					$auth_only = $this->module->l('Auth Only');
				
				$message = $auth_only.' '.$this->module->l('Trexle Receipt No: ').$result['transactionid'].$this->module->l(' - Last 4 digits of the card: ').substr(Tools::getValue('cc_number'), -4);
				
				$this->module->validateOrder($cart->id, $order_status, $total, $this->module->l('Trexle'), $message, array('transaction_id' => $result['transactionid']), (int)$this->context->currency->id, false, $customer->secure_key);

				Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
			
				//$url_redirect = $this->redirectUrl('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$this->module->currentOrder.'&key='.$customer->secure_key);
		
				//die(Tools::jsonEncode(array('msg' => $url_redirect, 'err' => 0)));	 
	
				
			}
			
			// end
		}

		if (isset($result) && !empty($result['error'])) 
			die(Tools::jsonEncode(array('msg' => $result['error'], 'err' => 1)));	 
	
		$error = array('msg' => 'Unknown error. Please contact web master.', 'err' => 1); 
				
		die(Tools::jsonEncode($error));
			
		/* 
		$years = array();
		for ($i = date("Y"); $i < date("Y") + 10; $i++)
		{
			$years[$i]  = substr($i, -2);
		}

		$months = array();
		for ($i = 1; $i < 13; $i++)
		{
			$pi = $i < 10 ? '0'.$i : $i;
			$months[$pi] = $pi;
		}

		$this->context->smarty->assign(array(
			'total' => $this->context->cart->getOrderTotal(true, Cart::BOTH),
			'this_path' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/',
			'cc_number' => Tools::getValue('cc_number'),
			'cc_cvv' => Tools::getValue('cc_cvm'),
			'cc_err' => $error_msg,
			'response_code' => 'unknown',
			'years' => $years,
			'months' => $months,
			'payment_embed' => Configuration::get('TREXLE_EMBED'),
		));

		//Tools::redirect('index.php?controller=order&step=3');
			
		$this->setTemplate('paymentform.tpl');
		 * 
		 */
	}
	
	public function redirectUrl($url, $base_uri = __PS_BASE_URI__, Link $link = null)
	{
		if (!$link)
			$link = Context::getContext()->link;

		if (strpos($url, 'http://') === false && strpos($url, 'https://') === false && $link)
		{
			if (strpos($url, $base_uri) === 0)
				$url = substr($url, strlen($base_uri));
			if (strpos($url, 'index.php?controller=') !== false && strpos($url, 'index.php/') == 0)
			{
				$url = substr($url, strlen('index.php?controller='));
				if (Configuration::get('PS_REWRITING_SETTINGS'))
					$url = Tools::strReplaceFirst('&', '?', $url);
			}

			$explode = explode('?', $url);
			// don't use ssl if url is home page
			// used when logout for example
			$use_ssl = !empty($url);
			$url = $link->getPageLink($explode[0], $use_ssl);
			if (isset($explode[1]))
				$url .= '?'.$explode[1];
		}

		return $url; 
	}
}
