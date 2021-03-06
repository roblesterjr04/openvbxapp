<?php
define('PAYMENT_ACTION', 'paymentAction');
define('PAYMENT_COOKIE', 'payment-' . AppletInstance::getInstanceId());
define('STATE_GATHER_CARD', 'stateGatherCard');
define('STATE_GATHER_MONTH', 'stateGatherMonth');
define('STATE_GATHER_YEAR', 'stateGatherYear');
define('STATE_GATHER_CVC', 'stateGatherCvc');
define('STATE_GATHER_AMOUNT', 'stateGatherAmount');
define('STATE_SEND_PAYMENT', 'stateSendPayment');
define('STATE_CONF_AMOUNT', 'stateConfAmount');

$response = new TwimlResponse;

$state = array(
	PAYMENT_ACTION => STATE_GATHER_CARD,
	'card' => array(),
	'amount' => 0
);

$ci =& get_instance();
$settings = PluginData::get('settings');
$description = AppletInstance::getValue('description');
$digits = clean_digits($ci->input->get_post('Digits'));
$finishOnKey = '#';
$timeout = 15;

$card_errors = array(
	'invalid_number' => STATE_GATHER_CARD,
	'incorrect_number' => STATE_GATHER_CARD,
	'invalid_expiry_month' => STATE_GATHER_MONTH,
	'invalid_expiry_year' => STATE_GATHER_YEAR,
	'expired_card' => STATE_GATHER_CARD,
	'invalid_cvc' => STATE_GATHER_CVC,
	'incorrect_cvc' => STATE_GATHER_CVC
);

if(is_object($settings))
	$settings = get_object_vars($settings);

if(isset($_COOKIE[PAYMENT_COOKIE])) {
	$state = json_decode(str_replace(', $Version=0', '', $_COOKIE[PAYMENT_COOKIE]), true);
	if(is_object($state))
		$state = get_object_vars($state);
}

if($digits !== false) {
	switch($state[PAYMENT_ACTION]) {
		case STATE_GATHER_CARD:
			$state['card']['number'] = $digits;
			$state[PAYMENT_ACTION] = STATE_GATHER_MONTH;
			break;
		case STATE_GATHER_MONTH:
			$state['card']['exp_month'] = $digits;
			$state[PAYMENT_ACTION] = STATE_GATHER_YEAR;
			break;
		case STATE_GATHER_YEAR:
			$state['card']['exp_year'] = $digits;
			$state[PAYMENT_ACTION] = $settings['require_cvc'] ? STATE_GATHER_CVC : STATE_GATHER_AMOUNT;
			break;
		case STATE_GATHER_CVC:
			$state['card']['cvc'] = $digits;
			$state[PAYMENT_ACTION] = STATE_GATHER_AMOUNT;
			break;
		case STATE_GATHER_AMOUNT:
			$state['amount'] = $digits;
			$state[PAYMENT_ACTION] = STATE_CONF_AMOUNT;
			break;
		case STATE_CONF_AMOUNT:
			if ($digits == 1) { $state[PAYMENT_ACTION] = STATE_SEND_PAYMENT; }
			elseif ($digits == 2) { $state[PAYMENT_ACTION] = STATE_GATHER_AMOUNT; }
			else { $state[PAYMENT_ACTION] = STATE_CONF_AMOUNT; }
			break;
	}
}

switch($state[PAYMENT_ACTION]) {
	case STATE_GATHER_CARD:
		$gather = $response->gather(compact('finishOnKey', 'timeout'));
		$gather->say($settings['card_prompt'], array(
			'voice' => $ci->vbx_settings->get('voice', $ci->tenant->id),
			'voice_language' => $ci->vbx_settings->get('voice_language', $ci->tenant->id)
		));
		break;
	case STATE_GATHER_MONTH:
		$gather = $response->gather(compact('finishOnKey', 'timeout'));
		$gather->say($settings['month_prompt'], array(
			'voice' => $ci->vbx_settings->get('voice', $ci->tenant->id),
			'voice_language' => $ci->vbx_settings->get('voice_language', $ci->tenant->id)
		));
		break;
	case STATE_GATHER_YEAR:
		$gather = $response->gather(compact('finishOnKey', 'timeout'));
		$gather->say($settings['year_prompt'], array(
			'voice' => $ci->vbx_settings->get('voice', $ci->tenant->id),
			'voice_language' => $ci->vbx_settings->get('voice_language', $ci->tenant->id)
		));
		break;
	case STATE_GATHER_CVC:
		$gather = $response->gather(compact('finishOnKey', 'timeout'));
		$gather->say($settings['cvc_prompt'], array(
			'voice' => $ci->vbx_settings->get('voice', $ci->tenant->id),
			'voice_language' => $ci->vbx_settings->get('voice_language', $ci->tenant->id)
		));
		break;
	case STATE_GATHER_AMOUNT:
		$gather = $response->gather(compact('finishOnKey', 'timeout'));
		$gather->say($settings['amount_prompt'], array(
			'voice' => $ci->vbx_settings->get('voice', $ci->tenant->id),
			'voice_language' => $ci->vbx_settings->get('voice_language', $ci->tenant->id)
		));
		break;
	case STATE_CONF_AMOUNT:
		$gather = $response->gather();
		$gather->say('You have entered $' . floatval($state['amount']) / 100 . '. If this is correct, press 1. If you would like to change this amount, press 2.', array(
			'voice' => $ci->vbx_settings->get('voice', $ci->tenant->id),
			'voice_language' => $ci->vbx_settings->get('voice_language', $ci->tenant->id)
		));
		break;
	case STATE_SEND_PAYMENT:
		require_once(dirname(dirname(dirname(__FILE__))) . '/stripe-php/lib/Stripe.php');
		Stripe::setApiKey($settings['api_key']);
		try {
			$charge = Stripe_Charge::create(array(
				'card' => $state['card'],
				'amount' => $state['amount'],
				'currency' => 'usd',
				'description' => $description,
				'metadata' => array(
					'phone' => $ci->input->get_post('From')
				)
			));
			if($charge->paid && true === $charge->paid) {
				setcookie(PAYMENT_COOKIE);
				$next = AppletInstance::getDropZoneUrl('success');
				if(!empty($next)) {
					$response->sms('Thank you for your donation of $' . number_format(($state['amount']/100),2) . '. You can email info@nfullertonartsfair.com if you would like a receipt');
					$response->redirect($next);
				}
				$response->respond();
				die;
			}
		}
		catch(Exception $e) {
			$error = $e->getCode();
			$response->say($e->getMessage(), array(
				'voice' => $ci->vbx_settings->get('voice', $ci->tenant->id),
				'voice_language' => $ci->vbx_settings->get('voice_language', $ci->tenant->id)
			));
			if(array_key_exists($error, $card_errors)) {
				$state[PAYMENT_ACTION] = $card_errors[$error];
				$response->redirect();
			}
			else {
				setcookie(PAYMENT_COOKIE);
				$next = AppletInstance::getDropZoneUrl('fail');
				if(!empty($next))
					$response->redirect($next);
				$response->respond();
				die;
			}
		}
}
setcookie(PAYMENT_COOKIE, json_encode($state), time() + (5 * 60));
$response->respond();
