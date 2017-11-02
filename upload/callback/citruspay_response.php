<?php

# Required File Includes
include("../../../init.php");
include("../../../includes/functions.php");
include("../../../includes/gatewayfunctions.php");
include("../../../includes/invoicefunctions.php");


$gatewaymodule = "payu"; # Enter your gateway module name here replacing template

$GATEWAY = getGatewayVariables($gatewaymodule);
if (!$GATEWAY["type"]) die("Module Not Activated"); # Checks gateway module is active before accepting callback

$pdata  = array();
$pdata  = $_POST;

# Get Returned Variables - Adjust for Post Variable Names from your Gateway's Documentation
$txrefno = $pdata ['TxRefNo'];
$txnid = $pdata ['TxId'];
$txstatus = $pdata ['TxStatus'];
$amount = $pdata ['amount'];

if (isset ( $pdata ['firstName'] )) {
	$firstName = $pdata ['firstName'];
}
				
if (isset ( $pdata ['lastName'] )) {
	$lastName = $pdata ['lastName'];
}
				
if (isset ( $pdata ['pgRespCode'] )) {
	$pgrespcode = $pdata ['pgRespCode'];
}
				
if (isset ( $pdata ['pgTxnNo'] )) {
	$pgtxnno = $pdata ['pgTxnNo'];
}
				
if (isset ( $pdata ['issuerRefNo'] )) {
	$issuerrefno = $pdata ['issuerRefNo'];
}
				
if (isset ( $pdata ['authIdCode'] )) {
	$authidcode = $pdata ['authIdCode'];
}
				
if (isset ( $pdata ['addressZip'] )) {
	$pincode = $pdata ['addressZip'];
}
				
if (isset ( $pdata ['TxMsg'] )) {
	$msg['message'] = $pdata ['TxMsg'];
}
				
$currency = $pdata ['currency'];
				
$respSignature = $pdata ['signature'];
		
$data = $txnid . $txstatus . $amount . $pgtxnno . $issuerrefno . $authidcode . $firstName . $lastName . $pgrespcode . $pincode;
				
$signature = hash_hmac('sha1',$data,$GATEWAY['api_key']);
				
				
if($signature != "" && strcmp($signature, $respSignature) != 0)
{
	$status=0;
}
else
{
	$status=1;
}


#$amount = ($request_params["transaction_amount"]) / 100;

$invoiceid = checkCbInvoiceID($txnid, 'payu'); # Checks invoice ID is a valid invoice number or ends processing

checkCbTransID($txrefno); # Checks transaction number isn't already in the database and ends processing if it does

#$invoiceid = checkCbInvoiceID($invoiceid,$GATEWAY["name"]); # Checks invoice ID is a valid invoice number or ends processing

#checkCbTransID($transid); # Checks transaction number isn't already in the database and ends processing if it does

if($status == 1) {
   	# Successful
   
    addInvoicePayment($invoiceid, $txrefno, $amount, $gatewaymodule); # Apply Payment to Invoice: invoiceid, transactionid, amount paid, fees, modulename
   	update_query("tblinvoices",array("notes"=>"CitrusPay"),array("id"=>$invoiceid));
	logTransaction($GATEWAY["name"],$response,"Successful"); # Save to Gateway Log: name, data array, status
	
} else {
	# Unsuccessful
    logTransaction($GATEWAY["name"],$response,"Unsuccessful"); # Save to Gateway Log: name, data array, status

}

$filename = $GATEWAY['systemurl'].'/viewinvoice.php?id=' . $invoiceid;     // path of your viewinvoice.php
HEADER("location:$filename");

?>
