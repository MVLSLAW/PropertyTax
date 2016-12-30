<?php
include 'PropertyTax.php';
//Test program for the water bill class

	$temp = new PropertyTax('834 Hollins Street');
if($temp->checkPropertyTax()==200){
	print_r($temp->property_tax_array);
	//print_r($temp->html); //Uncomment this if you want to simply display the webpage returned.
	
}else{
	echo "Could not locate the address";
}
?>