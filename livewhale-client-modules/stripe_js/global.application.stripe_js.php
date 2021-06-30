<?php

$_LW->REGISTERED_APPS['stripe_js'] = array( // configure this application module
	'title' => 'Stripe JS',
	'handlers' => ['onLoad', 'onFormsOutput', 'onFormsSuccess'],
	'custom'=>[
		'checkout_icon' => 'https://www.templejc.edu/_ingredients/extras/logo.png'
	]
);

class LiveWhaleApplicationStripeJs {


public function onLoad() {
global $_LW;
	// Setup stripe price form config
	$_LW->CONFIG['FORM_STYLES']['stripe-price'] = array('Stripe Price (SPECIAL)', 'stripe-price');
}


public function onFormsOutput($buffer, $id) {
global $_LW;

if ($fieldsets=@$buffer->elements('fieldset.stripe-price')) { // if there is a stripe payment

	// add extra resources
	$_LW->widget['metadata']['js'][]='https://checkout.stripe.com/checkout.js';
	$_LW->widget['metadata']['js'][]=$_LW->CONFIG['LIVE_URL'].'/resource/js/stripe_js%5Cstripe_handler.js';

	if ($info=$this->getStripeInfoForForm($id)) { // fetch the Stripe info
		// $_LW->json['stripe_public_key']=$info['public_key'];
		// $_LW->json['stripe_checkout_icon']=$_LW->REGISTERED_APPS['stripe_js']['custom']['checkout_icon'];
		foreach ($fieldsets as $fieldset) { // add custom fields
			$fieldset->insert($buffer->xhtml('
					<input type="hidden" name="stripe_public_key" value="'.$info['public_key'].'"/> 
					<input type="hidden" name="stripe_checkout_icon" value="'.$_LW->REGISTERED_APPS['stripe_js']['custom']['checkout_icon'].'"/> 
					<input type="hidden" name="sc-name" value="'.$info['form_title'].'"/> 
	                <input type="hidden" name="sc-description" value="Payment"/> 
	                <input type="hidden" name="sc-currency" value="USD"/>
	                <input type="hidden" name="sc-amount" value=""/> 
	                <input type="hidden" name="stripeToken" value=""/> 
	                <input type="hidden" name="stripeEmail" value=""/>
					<input type="hidden" name="stripeFormID" value="'.(int)$id.'"/> 
			'));
		};
	};
};
return $buffer;
}


public function onFormsSuccess($buffer, $form_id) {
	global $_LW;

	if (!empty($_LW->_POST['stripeToken']) && !empty($_LW->_POST['stripeFormID'])) { // if Stripe form submitted
		// $_LW->logDebug('stripe form submitted');

		// Include Stripe plugin
		$path=$_LW->INCLUDES_DIR_PATH.'/client/modules/stripe_js/includes/Stripe.php';
		if (file_exists($path)) { // if the source exists
			
			require_once($path); 
			
			if ($info=$this->getStripeInfoForForm($_LW->_POST['stripeFormID'])) { // fetch the Stripe info
				// Setup your Stripe connection
				$stripe = array(
				  "secret_key"      => $info['private_key'],
				  "publishable_key" => $info['public_key'],
				);

				Stripe::setApiKey($stripe['secret_key']);
			
				// Setup and run the charge
				$email = $_LW->_POST['stripeEmail'];
				$description = $_LW->_POST['sc-description'];
				$amount = $_LW->_POST['sc-amount']; // in cents
				$token  = $_LW->_POST['stripeToken'];

				try {

					$customer = Stripe_Customer::create(array(
					  'email' => $email,
					  'card'  => $token
					));

					$charge = Stripe_Charge::create(array(
					  'customer' => $customer->id,
					  'description' => $description,
					  'amount'   => $amount,
					  'currency' => 'usd'
					));

				} catch(\Stripe\Exception\CardException $e) {
  				  $_LW->logError('Stripe Error: Declined charge ' . $e->getError()->type);
				} catch (\Stripe\Exception\RateLimitException $e) {
				  $_LW->logError('Stripe Error: Too many requests made to the API too quickly');
				} catch (\Stripe\Exception\InvalidRequestException $e) {
				  $_LW->logError('Stripe Error: Invalid parameters were supplied to Stripe\'s API');
				} catch (\Stripe\Exception\AuthenticationException $e) {
				  $_LW->logError('Stripe Error: Authentication with Stripe\'s API failed (maybe you changed API keys recently)');
				} catch (\Stripe\Exception\ApiConnectionException $e) {
				  $_LW->logError('Stripe Error: Network communication with Stripe failed');
				} catch (\Stripe\Exception\ApiErrorException $e) {
				  $_LW->logError('Stripe Error: Display a very generic error to the user, and maybe send yourself an email');
				} catch (Exception $e) {
				  $_LW->logError('Stripe Error: Something else happened, completely unrelated to Stripe');
				}

			};

		}

		// TO DO: Maybe track CC payment alongside form submission (custom val maybe?)
		// TO DO: Add CC text to notification/confirmation email(s)
		// TO DO: Add instruction text so folks know their card _wasn't_ charged on form error?

	}
return $buffer;
}

protected function getStripeInfoForForm($id) { // gets Stripe info associated with a form
global $_LW;
if ($res2=$_LW->dbo->query('select', '
	(SELECT t2.value FROM livewhale_payments_settings AS t2 WHERE t2.name=IF(t1.value="live", "stripe_public_key_live", "stripe_public_key_test") AND t2.gid=t1.gid) AS public_key,
	(SELECT t2.value FROM livewhale_payments_settings AS t2 WHERE t2.name=IF(t1.value="live", "stripe_private_key_live", "stripe_private_key_test") AND t2.gid=t1.gid) AS private_key,
	livewhale_forms.title AS form_title
', 'livewhale_payments_settings AS t1', 't1.gid=livewhale_forms.gid AND t1.name="stripe_status"')
->innerJoin('livewhale_forms', 'livewhale_forms.id='.(int)$id)
->firstRow()
->run()) { // fetch the token
	if (!empty($res2['public_key']) && !empty($res2['private_key']) && !empty($res2['form_title'])) { // if valid keys
		//$_LW->logError('stripe form keys');
		foreach($res2 as $key=>$val) { // sanitize
			$res2[$key]=$_LW->setFormatClean($val);
		};
		return $res2; // return them
	};
};
return [];
}

}

?>