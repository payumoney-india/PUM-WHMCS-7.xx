<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function payu_MetaData()
{
    return array(
        'DisplayName' => 'PayUmoney and Citrus ICP Combined',
        'APIVersion' => '7.2', // Use API Version 1.1
        'DisableLocalCredtCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function payu_config() {

    $configarray = array(
     "FriendlyName" => array("Type" => "System", "Value"=>"PayUmoney and Citrus ICP"),
	 "mode" => array("FriendlyName" => "Gateway Mode", "Type" => "dropdown", "Options" => "Sandbox,Production", ),
     "MerchantKey" => array("FriendlyName" => "PayUmoney Key", "Type" => "text", "Size" => "20", ),
     "SALT" => array("FriendlyName" => "PayUmoney SALT", "Type" => "text", "Size" => "20",),
	 "citrusvanityurl" => array("FriendlyName" => "Citrus Payment URL", "Description" => "Citrus Payment or Vanity URL.", "Type" => "text", "Size" => "50", ),
	 "citrusaccesskey" => array("FriendlyName" => "Citrus Access Key", "Description" => "Citrus Merchant Access Key.", "Type" => "text", "Size" => "50", ),
     "citrusapikey" => array("FriendlyName" => "Citrus Secret Key", "Description" => "Citrus Merchant API/Secret Key.", "Type" => "text", "Size" => "50", ),
	 "percentcitrus" => array("FriendlyName" => "Route to Citrus %", "Description" => "Percentage of transactions you want to route to Citrus Pay", "Type" => "text", "Size" => "2", "Default" => "50", ),
	 "percentpayu" => array("FriendlyName" => "Route to PayUmoney %", "Description" => "Percentage of transactions you want to route to PayUmoney", "Type" => "text", "Size" => "2", "Default" => "50", ),
		
    );
	return $configarray;
}


function payu_link($params) {

	
	# Gateway Specific Variables
	$key = $params['MerchantKey'];
	$SALT = $params['SALT'];
	$gatewaymode = $params['mode'];
	$payment_url = $params['citrusvanityurl'];
	$access_key = $params['citrusaccesskey'];
	$api_key = $params['citrusapikey'];
	$route_citrus = is_numeric($params['percentcitrus'])? $params['percentcitrus'] : 0;
	$route_payum = is_numeric($params['percentpayu'])? $params['percentpayu'] : 0;
	
	//Decide on Citrus or PayUM
	$gateway = '';
	if(!$key && !$SALT) {
		$gateway = 'CitrusPay';			
	}
	elseif(!$payment_url  && !$access_key && !$api_key) {
		$gateway = 'PayUmoney';
	}
	else {
		if($route_citrus == 0)
		{
			$gateway = 'PayUmoney';
		}
		elseif($route_payum == 0)
		{
			$gateway = 'CitrusPay';	
		}
		else {		
	
			$cres = full_query("SELECT COUNT(*) / T.total * 100 AS percent FROM `tblinvoices` as I, (SELECT DISTINCT COUNT(*) AS total FROM `tblinvoices` where `status` like 'Paid' and `paymentmethod` like 'payu') AS T WHERE `status` like 'Paid' and `paymentmethod` like 'payu' and `notes` like 'CitrusPay'");
				
			$cper = mysql_fetch_assoc($cres);
				
			$pres = full_query("SELECT COUNT(*) / T.total * 100 AS percent FROM `tblinvoices` as I, (SELECT DISTINCT COUNT(*) AS total FROM `tblinvoices` where `status` like 'Paid' and `paymentmethod` like 'payu') AS T WHERE `status` like 'Paid' and `paymentmethod` like 'payu' and `notes` like 'PayUmoney'");			
				
			$pper = mysql_fetch_assoc($pres);
				
			if($cper['percent'] > $route_citrus && $pper['percent'] <= $route_payum) {
				$gateway = 'PayUmoney';
			}
			elseif($cper['percent'] <= $route_citrus && $pper['percent'] > $route_payum) {
				$gateway = 'CitrusPay';
			}
			else {
				if($pper['percent'] >= $cper['percent'])
					$gateway = 'CitrusPay';
				else
					$gateway = 'PayUmoney';
			}
		}
	}
			
	//Decided


	//PayUmoney
	if($gateway == 'PayUmoney')
	{
		$surl = $params['systemurl'] . '/modules/gateways/callback/payu_response.php';
		$furl = $params['systemurl'] . '/modules/gateways/callback/payu_response.php';
		$udf5="WHMCS_v_7.2";

		# Invoice Variables
		$txnid = $params['invoiceid'];
		$productinfo = $params["description"];
    	$amount = $params['amount']; # Format: ##.##

		# Client Variables
		$firstname = $params['clientdetails']['firstname'];
		$lastname = $params['clientdetails']['lastname'];
		$email = $params['clientdetails']['email'];
		$address1 = $params['clientdetails']['address1'];
		$city = $params['clientdetails']['city'];
		$state = $params['clientdetails']['state'];
		$postcode = $params['clientdetails']['postcode'];
		$country = $params['clientdetails']['country'];
		$phone = $params['clientdetails']['phonenumber'];
	    $hashSequence = $key.'|'.$txnid.'|'.$amount.'|'.$productinfo.'|'.$firstname.'|'.$email.'|||||'.$udf5.'||||||';
    
    	$hashSequence .= $SALT;
	    $hash = strtolower(hash('sha512', $hashSequence));
		# System Variables
		$companyname = 'payu';
		$systemurl = $params['systemurl'];
	    $PostURL = "https://test.payu.in/_payment";
    	if($gatewaymode == 'Production')
	      $PostURL = "https://secure.payu.in/_payment";
      
		# Enter your code submit to the Payu gateway...

		$code = '<form method="post" action='.$PostURL.' name="frmTransaction" id="frmTransaction">
				<input type="hidden" name="key" value="'.$key.'" />
				<input type="hidden" name="productinfo" value="'.$productinfo.'" />
				<input type="hidden" name="service_provider" value="payu_paisa" />
				<input type="hidden" name="txnid" value="'.$txnid.'" />
				<input type="hidden" name="firstname" value="'.$firstname.'" />
				<input type="hidden" name="address1" value="'.$address1.'" />
				<input type="hidden" name="city" value="'.$city.'" />
				<input type="hidden" name="state" value="'.$state.'" />
				<input type="hidden" name="country" value="'.$country.'" />
				<input type="hidden" name="postal_code" value="'.$postcode.'" />
				<input type="hidden" name="email" value="'.$email.'" />
				<input type="hidden" name="phone" value="'.$phone.'" />
				<input type="hidden" name="amount" value="'.$amount.'" />
				<input type="hidden" name="hash" value="'.$hash.'" />
				<input type="hidden" name="surl" value="'.$surl.'" />   
				<input type="hidden" name="furl" value="'.$furl.'" />
				<input type="hidden" name="udf5" value="'.$udf5.'" />
				<input type="submit" value="Pay Now" />
				</form>';				

		return $code;
	}
	
	if($gateway == 'CitrusPay')
	{
		$merchantTxnId = $params['invoiceid'];		
		$orderAmount = $params['amount'];
		$currencycode = $params['currency'];    
		$firstName = $params['clientdetails']['firstname'];
		$lastName = $params['clientdetails']['lastname'];
		$addressStreet1 = $params['clientdetails']['address1'];
		$addressCity = $params['clientdetails']['city'];
		$addressState = $params['clientdetails']['state'];
		$addressZip = $params['clientdetails']['postcode'];
		$addressCountry = $params['clientdetails']['country'];     
		$phoneNumber = $params['clientdetails']['phonenumber'];
		$email = $params['clientdetails']['email'];
		$paymentMode = 'NET_BANKING';
		$reqtime =  time()*1000;
		$secSignature = '';
		$returnUrl = $params['systemurl'] . '/modules/gateways/callback/citruspay_response.php'; 
      
		//Create Signature
		$data = $payment_url.$orderAmount.$merchantTxnId.$currencycode;
		$secSignature = hash_hmac('sha1',$data,$api_key);		
	
		$icpURL = "https://sboxcontext.citruspay.com/kiwi/kiwi-popover";


		$html = "<form name=\"citrusicp-form\" id=\"citrusicp-form\" method=\"POST\" onSubmit=\"return validate()\">
			<input type=\"hidden\" name=\"citrusicp_payment_id\" id=\"citrusicp_payment_id\" />
			<input type=\"hidden\" name=\"merchant_order_id\" id=\"order_id\" value=\"".$merchantTxnId."\"/>
			<input type=\"hidden\" name=\"citrus_ret\" id=\"citrus_ret\" value=\"\"/>			
			<button id='submit_citrus_payment_form' onclick='launchICP(); return false;'>Pay Now</button>			
			</form>";
			
		$js = "
				<script src=\"http://code.jquery.com/jquery-1.12.0.js\"></script>  
				<script>				
				var hidden = false;
    			function hide_button() {
			        hidden = !hidden;
			        if(hidden) {
            			document.getElementById('submit_citrus_payment_form').style.visibility = 'hidden';						
			        } else {
            			document.getElementById('submit_citrus_payment_form').style.visibility = 'visible';
			        }
    			}
				hide_button();
				";

	
		if ($gatewaymode == "Sandbox")
		{
			$js .= 'var sicp = document.createElement("script");
				sicp.id = "context";
				sicp.type = "text/javascript";
				sicp.src = "https://sboxcontext.citruspay.com/static/kiwi/app-js/icp.js";
				jQuery("head").append(sicp);';
		}
		else 
		{
			$js .= 'var sicp = document.createElement("script");
				sicp.id = "context";
				sicp.type = "text/javascript";
				sicp.src = "https://checkout-static.citruspay.com/kiwi/app-js/icp.min.js";
				jQuery("head").append(sicp);';
				
		}
		
		$js .= "
			function launchICP() {
        	    
				var dataObj = {
					orderAmount:'". $orderAmount."',
					currency:'". $currencycode."',
					phoneNumber:'". $phoneNumber."',
					email:'". $email."',
					merchantTxnId:'". $merchantTxnId."',
					secSignature:'". $secSignature."',
					firstName:'". $firstName."',
					lastName:'". $lastName."',
					addressStreet1:'". $addressStreet1."',
					addressCity:'". $addressCity."',
					addressState:'". $addressState."',
					addressCountry:'". $addressCountry."',
					addressZip:'". $addressZip."',
					vanityUrl:'". $payment_url."',
					returnUrl:'". $returnUrl."',
					notifyUrl:'". $returnUrl."',
					mode:'". "dropAround"."'
				};
			";
			
		$js .="var icpURL ='".$icpURL."';";

		$js .="var configObj = {};";
		$js .=" configObj = {";
		if ($gatewaymode == "Sandbox")
		{
			$js .=" icpUrl: '".$icpURL."',";
		}		
		$js .=" 
			eventHandler: function (cbObj) {				
				if (cbObj.event === 'icpLaunched') {
					//enable button
					hide_button();
					console.log('Citrus ICP pop-up is launched');
				} else if (cbObj.event === 'icpClosed') {
					console.log(JSON.stringify(cbObj.message));				
					console.log('Citrus ICP pop-up is closed');					
					}
				} 
			};
		";
			
		$js .="  try {
			citrusICP.launchIcp(dataObj, configObj);
			}
			catch (error) {
				console.log(error);
			}
		}";
			
						
		$js .="console.log('start timer');";
			
		$js .= "setTimeout(launchICP, 3000);";
		
		$js .= '</script>';
			
		return $html.$js;
	}
}
?>
