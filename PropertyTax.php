<?php
//Property Tax
/**
 * PropertyTax.php
 *
 * @author     Matthew Stubenberg
 * @copyright  2016 Maryland Volunteer Lawyers Service
 * @license    http://www.php.net/license/3_0.txt  PHP License 3.0
 * @version    1.0
 */
/*

/*
This class checks the Baltimore City Gov website and returns the amount due, res code, address of the property (to verify), and if they have a homeowners credit.

$temp = new PropertyTax('834 Hollins Street');
$temp->checkPropertyTax();

$temp->checkPropertyTax(); Return Values:
		100 if it couldn't find the address
		200 if everything went well
		300 if it found an address but it wasn't the one we searched.
		400 if the curl itself failed and hit an httpcode that wasn't 200.
		
$temp->property_tax_array //To get the results

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

$temp = new PropertyTax('3430 Elmora Ave');
$temp->checkPropertyTax();
print_r($temp->property_tax_array);
*/
require_once('simple_html_dom.php');
class PropertyTax{
	public $original_address;
	public $corrected_address;
	public $property_tax_array;
	public $html;
	
	public function __construct($address){
		$this->original_address = $address;
		$this->corrected_address = $this->fixAddressPropertyTax($this->original_address);
	}
	public function checkPropertyTax(){
		$this->html = $this->curlWebsite($this->corrected_address);
		if($this->html !== false){
			return $this->parseResults($this->html);
		}else{
			return 400;
		}

	}
	public function parseResults($html){
		/*
		Returns 100 if it couldn't find the address
		Returns 200 if everything went well
		Returns 300 if it found an address but it wasn't the one we searched.
		*/
		
		$return_array = array();
		$domparser = new \simple_html_dom();
		$domparser->load($html);
		
		$amount_due_tag = $domparser->find("span[id=ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_AmountDue]")[0]; //This tag should be on a successful prop tax pull.
		if($amount_due_tag == null){
			//Means there was an error on the page somewhere or we didn't find the address.
			return 100;
		}else{
			//Means we found a page with a property tax tag.
			//Now we need to check if it's the same address we searched.
			$prop_address = $domparser->find("span[id=ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_PropertyAddr]")[0]->plaintext;
			if(strcasecmp(trim($prop_address),$this->corrected_address) == 0){
				//Means the address are the same so we are good to go in getting the rest of the values.
				//Array of all the ids for the values we plan to pick up.
				$id_array=array(
					'ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_AmountDue',
					'ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_rescode',
					'ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_PropertyAddr',
					'ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_StateTax',
					'ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_CityTax',
					'ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_TotalTax',
					'ctl00_ctl00_rootMasterContent_LocalContentPlaceHolder_AmtPaid');
				
				foreach($id_array as $id){
					$underscore = strripos($id,'_'); //Should Return the last occurance of _ in a string.
					$key = substr($id,$underscore+1);
					$value = $domparser->find("span[id=" . $id . "]")[0]->plaintext;
					$return_array[$key] = trim($value);
				}
				
				//Unfortunately, the Homeowners tax credit is not located in an easily id so we need to search every span tag for the value. Return true if we find it.
				$span_tag_array = $domparser->find('span');
				$return_array['STATE HOMEOWNERS CREDIT'] = false; //Sets this as false initially
				
				foreach($span_tag_array as $span){
					$span_text = $span->plaintext;
					if(stripos($span_text,'STATE HOMEOWNERS CREDIT') !== false){
						//Found the homestead property tax credit
						$return_array['STATE HOMEOWNERS CREDIT'] = true;
						break;
					}
				}
				$this->property_tax_array = $return_array;
				return 200;
			}else{
				//Means the addresses are different. It must have had a similar one that appeared in the first slot on the result page.
				return 300;
			}
		}
	}
	public function curlWebsite($address){
		//Actually pulls the data from Legal Server.
		$url = "http://cityservices.baltimorecity.gov/realproperty/default.aspx";
		$year = date("Y");
		$month = date("n");
		if($month >= 7) $year++; //We want the next tax fiscal year ending which is sometimes not the year we are in. 7/1/2016 - 6/30/2017 would be year 2017
		
		$data = array(
			'ctl00$ctl00$rootMasterContent$LocalContentPlaceHolder$hdnAddress'=>$address,
			'ctl00$ctl00$rootMasterContent$LocalContentPlaceHolder$hdnYear'=>$year,
			'__EVENTTARGET'=>'ctl00$ctl00$rootMasterContent$LocalContentPlaceHolder$DataGrid1$ctl02$lnkBtnSelect',
			'__VIEWSTATE'=>'/wEPDwUKMTE0NDM0OTIyNQ8WAh4FWWVhcnMVBAQyMDE3BDIwMTYEMjAxNQQyMDE0FgJmD2QWAmYPZBYEZg9kFgQCAg8WAh4EVGV4dGVkAgUPFgIeB1Zpc2libGVnFgJmDxYCHwFlZAIBD2QWCgIBDw8WAh4ISW1hZ2VVcmwFVmh0dHA6Ly9jaXR5c2VydmljZXMuYmFsdGltb3JlY2l0eS5nb3YvcmVtb3RlbWFzdGVydjMvaW1hZ2VzL2ludGVybmV0L2ljb25zL2xvYWRpbmcuZ2lmZGQCBA8WAh8CZ2QCBg8WAh8CZxYCAgEPFgIfAQUNUmVhbCBQcm9wZXJ0eWQCBw9kFggCAQ9kFgICAQ9kFgRmDw8WBh8BBRJTZWFyY2ggVW5hdmFpbGFibGUeB1Rvb2xUaXAFOFNlYXJjaCBpcyBjdXJyZW50bHkgdW5hdmFpbGFibGUsIHBsZWFzZSB0cnkgYWdhaW4gbGF0ZXIuHghSZWFkT25seWcWBB4Hb25mb2N1cwUxaWYodGhpcy52YWx1ZT09J0tleXdvcmQgb3IgU2VhcmNoJyl0aGlzLnZhbHVlPScnOx4Gb25ibHVyBTFpZih0aGlzLnZhbHVlPT0nJyl0aGlzLnZhbHVlPSdLZXl3b3JkIG9yIFNlYXJjaCc7ZAIBDw8WAh4HRW5hYmxlZGgWAh4Hb25jbGljawVoaWYoZG9jdW1lbnQuZ2V0RWxlbWVudEJ5SWQoJ2N0bDAwX2N0bDAwX3R4dEdvb2dsZUN1c3RvbVNlYXJjaCcpLnZhbHVlPT0nS2V5d29yZCBvciBTZWFyY2gnKXJldHVybiBmYWxzZTtkAgIPZBYEAgEPFgIfAQUMRmluYW5jZSBNZW51ZAIDDxQrAAIUKwACDxYGHgtfIURhdGFCb3VuZGceF0VuYWJsZUFqYXhTa2luUmVuZGVyaW5naB4MRGF0YVNvdXJjZUlEBRJTaXRlTWFwRGF0YVNvdXJjZTFkDxQrABMUKwACDxYIHwEFBEhvbWUeC05hdmlnYXRlVXJsBSFodHRwOi8vZmluYW5jZS5iYWx0aW1vcmVjaXR5Lmdvdi8eBVZhbHVlBQRIb21lHwQFBEhvbWVkZBQrAAIPFggfAQUUQWNjb3VudGluZyAmIFBheXJvbGwfDQUzaHR0cDovL2ZpbmFuY2UuYmFsdGltb3JlY2l0eS5nb3YvYnVyZWF1cy9hY2NvdW50aW5nHw4FFEFjY291bnRpbmcgJiBQYXlyb2xsHwQFFEFjY291bnRpbmcgJiBQYXlyb2xsZGQUKwACDxYIHwEFHEJ1ZGdldCAmIE1hbmFnZW1lbnQgUmVzZWFyY2gfDQUeaHR0cDovL2JibXIuYmFsdGltb3JlY2l0eS5nb3YvHw4FHEJ1ZGdldCAmIE1hbmFnZW1lbnQgUmVzZWFyY2gfBAUcQnVkZ2V0ICYgTWFuYWdlbWVudCBSZXNlYXJjaGRkFCsAAg8WCB8BBQlQdXJjaGFzZXMfDQUyaHR0cDovL2ZpbmFuY2UuYmFsdGltb3JlY2l0eS5nb3YvYnVyZWF1cy9wdXJjaGFzZXMfDgUJUHVyY2hhc2VzHwQFCVB1cmNoYXNlc2RkFCsAAg8WCB8BBQ9SaXNrIE1hbmFnZW1lbnQfDQUtaHR0cDovL2ZpbmFuY2UuYmFsdGltb3JlY2l0eS5nb3YvYnVyZWF1cy9yaXNrHw4FD1Jpc2sgTWFuYWdlbWVudB8EBQ9SaXNrIE1hbmFnZW1lbnRkZBQrAAIPFggfAQUTVHJlYXN1cnkgTWFuYWdlbWVudB8NBTFodHRwOi8vZmluYW5jZS5iYWx0aW1vcmVjaXR5Lmdvdi9idXJlYXVzL3RyZWFzdXJ5Hw4FE1RyZWFzdXJ5IE1hbmFnZW1lbnQfBAUTVHJlYXN1cnkgTWFuYWdlbWVudGRkFCsAAg8WCB8BBRNSZXZlbnVlIENvbGxlY3Rpb25zHw0FNGh0dHA6Ly9maW5hbmNlLmJhbHRpbW9yZWNpdHkuZ292L2J1cmVhdXMvY29sbGVjdGlvbnMfDgUTUmV2ZW51ZSBDb2xsZWN0aW9ucx8EBRNSZXZlbnVlIENvbGxlY3Rpb25zZGQUKwACDxYIHwEFE0RvY3VtZW50cyAmIFJlcG9ydHMfDQU0aHR0cDovL2ZpbmFuY2UuYmFsdGltb3JlY2l0eS5nb3YvcHVibGljLWluZm8vcmVwb3J0cx8OBRNEb2N1bWVudHMgJiBSZXBvcnRzHwQFE0RvY3VtZW50cyAmIFJlcG9ydHNkZBQrAAIPFggfAQUPT25saW5lIFBheW1lbnRzHw0FLWh0dHA6Ly9jaXR5c2VydmljZXMuYmFsdGltb3JlY2l0eS5nb3YvcGF5c3lzLx8OBQ9PbmxpbmUgUGF5bWVudHMfBAUPT25saW5lIFBheW1lbnRzZGQUKwACDxYIHwEFEzxoMj5GQVEgLyBIZWxwPC9oMj4fDQUPL1JlYWxQcm9wZXJ0eS8jHw4FEzxoMj5GQVEgLyBIZWxwPC9oMj4fBGVkZBQrAAIPFggfAQUNVGF4IFNhbGUgRkFRcx8NBRtodHRwOi8vd3d3LmJpZGJhbHRpbW9yZS5jb20fDgUNVGF4IFNhbGUgRkFRcx8EBQ1UYXggU2FsZSBGQVFzZGQUKwACDxYIHwEFEVBhcmtpbmcgRmluZXMgRkFRHw0FQWh0dHA6Ly93d3cuYmFsdGltb3JlY2l0eS5nb3YvYW5zd2Vycy9pbmRleC5waHA/YWN0aW9uPXNob3cmY2F0PTEwHw4FEVBhcmtpbmcgRmluZXMgRkFRHwQFEVBhcmtpbmcgRmluZXMgRkFRZGQUKwACDxYIHwEFEVJlYWwgUHJvcGVydHkgRkFRHw0FQWh0dHA6Ly93d3cuYmFsdGltb3JlY2l0eS5nb3YvYW5zd2Vycy9pbmRleC5waHA/YWN0aW9uPXNob3cmY2F0PTEyHw4FEVJlYWwgUHJvcGVydHkgRkFRHwQFEVJlYWwgUHJvcGVydHkgRkFRZGQUKwACDxYIHwEFFVBhcmtpbmcgRmluZXMgTGlzdGluZx8NBWtodHRwOi8vd3d3LmJhbHRpbW9yZWNpdHkuZ292L2dvdmVybm1lbnQvdHJhbnNwb3J0YXRpb24vZG93bmxvYWRzLzEyMDcvMTIxOTA3IFBhcmtpbmcgRmluZXMgTGlzdGluZyAyMDA3LnBkZh8OBRVQYXJraW5nIEZpbmVzIExpc3RpbmcfBAUVUGFya2luZyBGaW5lcyBMaXN0aW5nZGQUKwACDxYIHwEFGEF2b2lkaW5nIFBhcmtpbmcgVGlja2V0cx8NBWhodHRwOi8vd3d3LmJhbHRpbW9yZWNpdHkuZ292L2dvdmVybm1lbnQvdHJhbnNwb3J0YXRpb24vZG93bmxvYWRzLzEyMDcvMTIxOTA3IFBhcmtpbmcgVGlja2V0IEJyb2NodXJlLnBkZh8OBRhBdm9pZGluZyBQYXJraW5nIFRpY2tldHMfBAUYQXZvaWRpbmcgUGFya2luZyBUaWNrZXRzZGQUKwACDxYIHwEFEVRyYW5zZmVyIFRheCBVbml0Hw0FQWh0dHA6Ly93d3cuYmFsdGltb3JlY2l0eS5nb3YvYW5zd2Vycy9pbmRleC5waHA/YWN0aW9uPXNob3cmY2F0PTExHw4FEVRyYW5zZmVyIFRheCBVbml0HwQFEVRyYW5zZmVyIFRheCBVbml0ZGQUKwACDxYIHwEFCkxpZW5zIFVuaXQfDQU9aHR0cDovL3d3dy5iYWx0aW1vcmVjaXR5Lmdvdi9nb3Zlcm5tZW50L2ZpbmFuY2UvZmFxdGxpZW5zLnBocB8OBQpMaWVucyBVbml0HwQFCkxpZW5zIFVuaXRkZBQrAAIPFggfAQUXTGllbiBDZXJ0aWZpY2F0ZSBQb2xpY3kfDQVfaHR0cDovL3d3dy5iYWx0aW1vcmVjaXR5Lmdvdi9nb3Zlcm5tZW50L2ZpbmFuY2UvaW1hZ2VzL0xpZW4gQ2VydGlmaWNhdGUgcG9saWN5IF8yXyBPY3QgMjAwOC5wZGYfDgUXTGllbiBDZXJ0aWZpY2F0ZSBQb2xpY3kfBAUXTGllbiBDZXJ0aWZpY2F0ZSBQb2xpY3lkZBQrAAIPFggfAQUIQ29udGFjdHMfDWUfDgUIQ29udGFjdHMfBGVkZA8UKwETZmZmZmZmZmZmZmZmZmZmZmZmZhYBBXNUZWxlcmlrLldlYi5VSS5SYWRNZW51SXRlbSwgVGVsZXJpay5XZWIuVUksIFZlcnNpb249MjAwOC4yLjgyNi4yMCwgQ3VsdHVyZT1uZXV0cmFsLCBQdWJsaWNLZXlUb2tlbj0xMjFmYWU3ODE2NWJhM2Q0ZBYmZg8PFggfAQUESG9tZR8NBSFodHRwOi8vZmluYW5jZS5iYWx0aW1vcmVjaXR5Lmdvdi8fDgUESG9tZR8EBQRIb21lZGQCAQ8PFggfAQUUQWNjb3VudGluZyAmIFBheXJvbGwfDQUzaHR0cDovL2ZpbmFuY2UuYmFsdGltb3JlY2l0eS5nb3YvYnVyZWF1cy9hY2NvdW50aW5nHw4FFEFjY291bnRpbmcgJiBQYXlyb2xsHwQFFEFjY291bnRpbmcgJiBQYXlyb2xsZGQCAg8PFggfAQUcQnVkZ2V0ICYgTWFuYWdlbWVudCBSZXNlYXJjaB8NBR5odHRwOi8vYmJtci5iYWx0aW1vcmVjaXR5Lmdvdi8fDgUcQnVkZ2V0ICYgTWFuYWdlbWVudCBSZXNlYXJjaB8EBRxCdWRnZXQgJiBNYW5hZ2VtZW50IFJlc2VhcmNoZGQCAw8PFggfAQUJUHVyY2hhc2VzHw0FMmh0dHA6Ly9maW5hbmNlLmJhbHRpbW9yZWNpdHkuZ292L2J1cmVhdXMvcHVyY2hhc2VzHw4FCVB1cmNoYXNlcx8EBQlQdXJjaGFzZXNkZAIEDw8WCB8BBQ9SaXNrIE1hbmFnZW1lbnQfDQUtaHR0cDovL2ZpbmFuY2UuYmFsdGltb3JlY2l0eS5nb3YvYnVyZWF1cy9yaXNrHw4FD1Jpc2sgTWFuYWdlbWVudB8EBQ9SaXNrIE1hbmFnZW1lbnRkZAIFDw8WCB8BBRNUcmVhc3VyeSBNYW5hZ2VtZW50Hw0FMWh0dHA6Ly9maW5hbmNlLmJhbHRpbW9yZWNpdHkuZ292L2J1cmVhdXMvdHJlYXN1cnkfDgUTVHJlYXN1cnkgTWFuYWdlbWVudB8EBRNUcmVhc3VyeSBNYW5hZ2VtZW50ZGQCBg8PFggfAQUTUmV2ZW51ZSBDb2xsZWN0aW9ucx8NBTRodHRwOi8vZmluYW5jZS5iYWx0aW1vcmVjaXR5Lmdvdi9idXJlYXVzL2NvbGxlY3Rpb25zHw4FE1JldmVudWUgQ29sbGVjdGlvbnMfBAUTUmV2ZW51ZSBDb2xsZWN0aW9uc2RkAgcPDxYIHwEFE0RvY3VtZW50cyAmIFJlcG9ydHMfDQU0aHR0cDovL2ZpbmFuY2UuYmFsdGltb3JlY2l0eS5nb3YvcHVibGljLWluZm8vcmVwb3J0cx8OBRNEb2N1bWVudHMgJiBSZXBvcnRzHwQFE0RvY3VtZW50cyAmIFJlcG9ydHNkZAIIDw8WCB8BBQ9PbmxpbmUgUGF5bWVudHMfDQUtaHR0cDovL2NpdHlzZXJ2aWNlcy5iYWx0aW1vcmVjaXR5Lmdvdi9wYXlzeXMvHw4FD09ubGluZSBQYXltZW50cx8EBQ9PbmxpbmUgUGF5bWVudHNkZAIJDw8WCB8BBRM8aDI+RkFRIC8gSGVscDwvaDI+Hw0FDy9SZWFsUHJvcGVydHkvIx8OBRM8aDI+RkFRIC8gSGVscDwvaDI+HwRlZGQCCg8PFggfAQUNVGF4IFNhbGUgRkFRcx8NBRtodHRwOi8vd3d3LmJpZGJhbHRpbW9yZS5jb20fDgUNVGF4IFNhbGUgRkFRcx8EBQ1UYXggU2FsZSBGQVFzZGQCCw8PFggfAQURUGFya2luZyBGaW5lcyBGQVEfDQVBaHR0cDovL3d3dy5iYWx0aW1vcmVjaXR5Lmdvdi9hbnN3ZXJzL2luZGV4LnBocD9hY3Rpb249c2hvdyZjYXQ9MTAfDgURUGFya2luZyBGaW5lcyBGQVEfBAURUGFya2luZyBGaW5lcyBGQVFkZAIMDw8WCB8BBRFSZWFsIFByb3BlcnR5IEZBUR8NBUFodHRwOi8vd3d3LmJhbHRpbW9yZWNpdHkuZ292L2Fuc3dlcnMvaW5kZXgucGhwP2FjdGlvbj1zaG93JmNhdD0xMh8OBRFSZWFsIFByb3BlcnR5IEZBUR8EBRFSZWFsIFByb3BlcnR5IEZBUWRkAg0PDxYIHwEFFVBhcmtpbmcgRmluZXMgTGlzdGluZx8NBWtodHRwOi8vd3d3LmJhbHRpbW9yZWNpdHkuZ292L2dvdmVybm1lbnQvdHJhbnNwb3J0YXRpb24vZG93bmxvYWRzLzEyMDcvMTIxOTA3IFBhcmtpbmcgRmluZXMgTGlzdGluZyAyMDA3LnBkZh8OBRVQYXJraW5nIEZpbmVzIExpc3RpbmcfBAUVUGFya2luZyBGaW5lcyBMaXN0aW5nZGQCDg8PFggfAQUYQXZvaWRpbmcgUGFya2luZyBUaWNrZXRzHw0FaGh0dHA6Ly93d3cuYmFsdGltb3JlY2l0eS5nb3YvZ292ZXJubWVudC90cmFuc3BvcnRhdGlvbi9kb3dubG9hZHMvMTIwNy8xMjE5MDcgUGFya2luZyBUaWNrZXQgQnJvY2h1cmUucGRmHw4FGEF2b2lkaW5nIFBhcmtpbmcgVGlja2V0cx8EBRhBdm9pZGluZyBQYXJraW5nIFRpY2tldHNkZAIPDw8WCB8BBRFUcmFuc2ZlciBUYXggVW5pdB8NBUFodHRwOi8vd3d3LmJhbHRpbW9yZWNpdHkuZ292L2Fuc3dlcnMvaW5kZXgucGhwP2FjdGlvbj1zaG93JmNhdD0xMR8OBRFUcmFuc2ZlciBUYXggVW5pdB8EBRFUcmFuc2ZlciBUYXggVW5pdGRkAhAPDxYIHwEFCkxpZW5zIFVuaXQfDQU9aHR0cDovL3d3dy5iYWx0aW1vcmVjaXR5Lmdvdi9nb3Zlcm5tZW50L2ZpbmFuY2UvZmFxdGxpZW5zLnBocB8OBQpMaWVucyBVbml0HwQFCkxpZW5zIFVuaXRkZAIRDw8WCB8BBRdMaWVuIENlcnRpZmljYXRlIFBvbGljeR8NBV9odHRwOi8vd3d3LmJhbHRpbW9yZWNpdHkuZ292L2dvdmVybm1lbnQvZmluYW5jZS9pbWFnZXMvTGllbiBDZXJ0aWZpY2F0ZSBwb2xpY3kgXzJfIE9jdCAyMDA4LnBkZh8OBRdMaWVuIENlcnRpZmljYXRlIFBvbGljeR8EBRdMaWVuIENlcnRpZmljYXRlIFBvbGljeWRkAhIPDxYIHwEFCENvbnRhY3RzHw1lHw4FCENvbnRhY3RzHwRlZGQCBQ8WAh8BBRE8aDI+Q09OVEFDVFM8L2gyPmQCBg8WAh8BBasDPGRpdiBzdHlsZT0ncGFkZGluZzoxMHB4Oyc+PGEgaHJlZj0nbWFpbHRvOkJhbHRpbW9yZUNpdHlDb2xsZWN0aW9uc0BiYWx0aW1vcmVjaXR5Lmdvdic+PHN0cm9uZz5SZXZlbnVlIENvbGxlY3Rpb25zPC9zdHJvbmc+PC9hPjxici8+MjAwIEhvbGxpZGF5IFN0LiwgUm9vbSA3PGJyLz48YnIvPjxhIGhyZWY9J2h0dHA6Ly93d3cuYmFsdGltb3JlY2l0eS5nb3YvZ292ZXJubWVudC9maW5hbmNlL3JldmVudWUucGhwI2NvbnRhY3RzJz48c3Ryb25nPkFsbCAgQ29udGFjdCBOdW1iZXJzPC9zdHJvbmc+PC9hPjxici8+PGJyIC8+IDxici8+PGgxPkFkbWluaXN0cmF0aW9uPC9oMT4gPGJyLz48c3Ryb25nPiBIZW5yeSBSYXltb25kICA8YnIgLz4gPC9zdHJvbmc+PGVtPkNoaWVmPC9lbT48YnIgLz5CdXJlYXUgb2YgUmV2ZW51ZSBDb2xsZWN0aW9uczwvZGl2PmQCCQ9kFgICAQ9kFhACAQ8WAh4EaHJlZgU2aHR0cDovL2NpdHlzZXJ2aWNlcy5iYWx0aW1vcmVjaXR5Lmdvdi9TcGVjaWFsQmVuZWZpdHMvZAICDw8WCB8BBW1UaGUgRVhFQ1VURSBwZXJtaXNzaW9uIHdhcyBkZW5pZWQgb24gdGhlIG9iamVjdCAnQWRkTG9nRW50cnknLCBkYXRhYmFzZSAnRmluYW5jZV9SZWFsUHJvcGVydHknLCBzY2hlbWEgJ2RibycuHwJoHglGb3JlQ29sb3IKjQEeBF8hU0ICBGRkAgMPDxYCHwJoZBYGZg8QZA8WBGYCAQICAgMWBBAFCTIwMTYvMjAxNwUEMjAxN2cQBQkyMDE1LzIwMTYFBDIwMTZnEAUJMjAxNC8yMDE1BQQyMDE1ZxAFCTIwMTMvMjAxNAUEMjAxNGcWAWZkAgQPDxYCHwEFDjgzNCBIb2xsaW5zIFN0ZGQCBg8PFgIfAmhkZAIEDw8WAh8CaGQWAgIBDxYCHwEFgAk8b2w+PGxpPlRoaXMgcGFnZSBpcyBmb3IgUmVhbCBQcm9wZXJ0eSB0YXhlcy4gIFVzZSB0aGlzIGxpbmsgZm9yIDxhIGhyZWY9Jy9TcGVjaWFsQmVuZWZpdHMvJz5TcGVjaWFsIEJlbmVmaXQgRGlzdHJpY3QgU3VyY2hhcmdlczwvYT4uIA0KPGxpPklmIHlvdSBrbm93IHRoZSBCbG9jayAmIExvdCwgZW50ZXIgb25seSB0aGUgYmxvY2sgJiBsb3QuIA0KPGxpPklmIHlvdSBhcmUgc2VhcmNoaW5nIGJ5IHByb3BlcnR5IGFkZHJlc3Mgb3Igb3duZXIgbmFtZSwgeW91IG1heSBlbnRlciBhbnkgcG9ydGlvbiBvZiBlaXRoZXIgb3IgYm90aCBvZiB0aG9zZSBmaWVsZHMuICBXaGVuIHlvdSBlbnRlciBkYXRhIGluIGEgc2VhcmNoIGZpZWxkLCB0aGUgZGF0YSB5b3UgZW50ZXJlZCBpcyBsb29rZWQgZm9yIGFueXdoZXJlIHdpdGhpbiB0aGF0IGZpZWxkLiBGb3IgZXhhbXBsZSwgaWYgeW91IGVudGVyIEJsdWUgaW4gdGhlIEFkZHJlc3MgZmllbGQsIHlvdSB3aWxsIGdldCByZXN1bHRzIGluY2x1ZGluZyBCbHVlYmVycnksIEJsdWVib25uZXQsIFRydWVCbHVlLCBldGMuIA0KPGxpPkRpcmVjdGlvbnMgc3VjaCBhcyBOb3J0aCwgU291dGgsIEVhc3QsIFdlc3Qgc2hvdWxkIGJlIGVudGVyZWQgYXMgTixTLEUsVyB3aXRoIG5vIHBlcmlvZC4gDQo8bGk+SWYgeW91ciBzZWFyY2ggZmFpbHMsIHJldHJ5IHdpdGggbGVzcyBpbmZvcm1hdGlvbiBzdWNoIGFzLCBGaXJzdCBTZWFyY2g6IE93bmVyPVJvc2VuYmxhdHQsIHJlc3VsdHM9MCBTZWNvbmQgU2VhcmNoOiBPd25lcj1Sb3NlbiByZXN1bHRzPTEyNCANCjxsaT5MZWF2ZSBvZmYgYWxsIHN0cmVldCBzdWZmaXhlcyBzdWNoIGFzIFN0LixXYXksIFJvYWQgZXRjLiANCjxsaT5XaGVuIHNlYXJjaGluZyBieSBuYW1lLCBlbnRlciBpbiBMYXN0TmFtZSwgRmlyc3ROYW1lIGZvcm1hdC4gDQo8bGk+SWYgYWxsIHlvdXIgc2VhcmNoZXMgYXJlIHVuc3VjY2Vzc2Z1bCwgcGxlYXNlIGNvbnRhY3QgdGhlIERlcHQuIG9mIEZpbmFuY2UgYXQgNDEwLTM5Ni0zOTg3DQo8bGk+PHN0cm9uZz5SZXR1cm5lZCBzZWFyY2ggcmVzdWx0cyBhcmUgbGltaXRlZCB0byA1MCByZWNvcmRzLiBJZiB5b3UgcmVhY2ggdGhpcyBsaW1pdCwgcGxlYXNlIHJlZmluZSB5b3VyIHNlYXJjaCBjcml0ZXJpYS48c3Ryb25nPg0KPC9vbD5kAgUPDxYCHwJnZBYKAgEPDxYEHwJnHwEFNTxiPkNyaXRlcmlhIFVzZWQ6PC9iPlllYXI9MjAxNyBBZGRyZXNzPTgzNCBIb2xsaW5zIFN0ZGQCAw8PFggfEAqQAR8BBRY8Yj5SZWNvcmRzIGZvdW5kOjwvYj4xHwJnHxECBGRkAgUPDxYEHwJnHwEFGTxiPlNvcnRlZCBCeTo8L2I+QmxvY2tMb3RkZAIHDw8WAh8CZ2RkAgkPPCsACwEADxYMHgtfIUl0ZW1Db3VudAIBHghEYXRhS2V5cxYBBQkwMjIwIDAxNyAeDERhdGFLZXlGaWVsZAUIYmxvY2tsb3QeCVBhZ2VDb3VudAIBHhVfIURhdGFTb3VyY2VJdGVtQ291bnQCAR4QQ3VycmVudFBhZ2VJbmRleGZkFgJmD2QWAgIBD2QWDGYPDxYCHwEFBTAyMjAgZGQCAQ8PFgIfAQUEMDE3IGRkAgIPDxYCHwEFITgzNCBIT0xMSU5TIFNUICAgICAgICAgICAgICAgICAgIGRkAgMPZBYIAgEPDxYCHwEFIUhPTExJTlMgODM0LCBMTEMuICAgICAgICAgICAgICAgIGRkAgMPDxYCHwEFITM3MCBNQUdPVEhZIFJPQUQgICAgICAgICAgICAgICAgIGRkAgUPDxYCHwEFIVNFVkVSTkEgUEFSSywgTUQgICAgICAgMjExNDYgICAgIGRkAgcPDxYCHwEFISAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgIGRkAgQPZBYCAgEPZBYCAgEPFQEhODM0IEhPTExJTlMgU1QgICAgICAgICAgICAgICAgICAgZAIFD2QWAgIBD2QWAgIBDxUBITgzNCBIT0xMSU5TIFNUICAgICAgICAgICAgICAgICAgIGQCBg9kFgQCDQ9kFgJmD2QWAmYPZBYCZg8PFgIfAmhkZAIVD2QWAgIBDw8WAh8BBS9QYXkgT25saW5lIHdpdGggQ3JlZGl0IENhcmQgb3IgQ2hlY2tpbmcgQWNjb3VudGRkAgcPZBYCAgcPZBYCAgEPPCsACwBkAggPDxYCHwJoZGQYAQUeX19Db250cm9sc1JlcXVpcmVQb3N0QmFja0tleV9fFgIFHmN0bDAwJGN0bDAwJGltZ0J0bkdvb2dsZVNlYXJjaAUUY3RsMDAkY3RsMDAkUmFkTWVudTGZCLjGymASBRe8DK90/FhRN2LsTQ=='
			);
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$result = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		
		if($httpcode == 200){
			//Success
			return $result;	
		}else{
			//echo "\r\n <br> Error in reaching property tax website. Return code is (" . $httpcode . ")";
			return false;
		}
	}
	public function fixAddressPropertyTax($address){
		//I need a special property tax one because for some reason "Rd" doesn't work but "Road" does work.
		
		$address = str_replace(".","",$address); //Gets Rid of any periods
		
		$addressarray = explode(" ",$address);
		for($x=0;$x< sizeof($addressarray); $x++){
			switch ($addressarray[$x]){
				case "Avenue":
					$addressarray[$x] = "Ave";
					break;
				case "Street":
					$addressarray[$x] = "St";
					break;
				case "Rd":
					$addressarray[$x] = "Road";
					break;
				case "Drive":
					$addressarray[$x] = "Dr";
					break;
				case "Circle":
					$addressarray[$x] = "Cr";
					break;
				case "Terrace":
					$addressarray[$x] = "Terr";
					break;
				case "Boulevard":
					$addressarray[$x] = "Bvld";
					break;
				case "Court":
					$addressarray[$x] = "Ct";
					break;
				case "North":
					$addressarray[$x] = "N";
					break;
				case "South":
					$addressarray[$x] = "S";
					break;
				case "West":
					$addressarray[$x] = "W";
					break;
				case "East":
					$addressarray[$x] = "E";
					break;
			}
		}
		$newaddress = implode(" ",$addressarray);
		return $newaddress;
	}
}
