# Property Tax Scraper
PHP Property Tax Scraper
Created by Matthew Stubenberg
Copyright Maryland Volunteer Lawyers Service 2016

##Description
This class will let you scrape the Baltimore City Property Tax website with a given address and return an array of property tax data.
http://cityservices.baltimorecity.gov/realproperty/

##Liability Waiver
By using this tool, you release Maryland Volunteer Lawyers Service of any and all liability. Please read the terms of use on the baltimorecity.gov website before using.
http://www.baltimorecity.gov/node/2020

##Usage
<pre>
$temp = new PropertyTax('834 Hollins Street');
if($temp->checkPropertyTax()==200){
	print_r($temp->property_tax_array);
	//print_r($temp->html); //Uncomment this if you want to simply display the webpage returned.
	
}else{
	echo "Could not locate the address";
}
</pre>
##Return Array:
Result from the variable $property_tax_array in the class. If a value does not exist for a certain field it will simply be null.
<pre>
Array
(
    [AmountDue] => 0.00 
	[rescode] => NOT A PRINCIPAL RESIDENCE 
	[PropertyAddr] => 834 HOLLINS ST 
	[StateTax] => 315.06 
	[CityTax] => 6,323.62 
	[TotalTax] => 6,638.68 
	[AmtPaid] => -6,607.06 
	[STATE HOMEOWNERS CREDIT] => 
)
</pre>
##Other
The class will automatically modify an address to work with the baltimore website so "Street" becomes "St".