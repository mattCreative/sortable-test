<?php

if(!empty($argv[1]) && !empty($argv[2]) && !empty($argv[3])){
	$results = new listingFilter($argv[1], $argv[2], $argv[3]);
}else{
	$results = new listingFilter();
}

class listingFilter{

	private $_products 			= array();				//array for all of the product information
	private $_terms 			= array();				//array for all of the terms parsed from the products
	private $_matches			= array();				//matches (results)
	private $_found 			= 0;					//number of matched listings
	public  $_listings			= 0;					//count of how many listings were matched
	private $_time				= 0;					//seconds to parse listings
	
	public $family_OTHER		= '_OTHER';				//default family name to signify products without 'families'
	
	/*
	 *	constructor
	 */
	public function __construct($productsFile = 'products.txt', $listingsFile = 'listings.txt', $resultsFile = 'results.txt'){


		$this->_products = $this->parseProducts($productsFile);			//automatically fills terms related to these products
		$this->_time = $this->_parseListings($listingsFile);
		$this->writeResults($resultsFile);
		$this->displayResults();
	}
	
	
	/* 
	 *	parseProducts - parse the product file, builds product array and terms tree
	 * 
	 *	@param string $file
	 * 
	 *	@return array
	 */
	public function parseProducts($file){
		
		$products 				= array();
		$product_id 				= 0;					//product index in products array (for look-up)
	
		$fh = fopen($file, 'r') or die('Products file DNE.');
		
		while (!feof($fh)) {
			$line = fgets($fh);
			if(strlen($line) > 0){
			
				$products[] = json_decode($line, true);
				//$_remove = array();			//terms to remove from product_name which have already been mapped
				
				$product_name_LC						 	= strtolower($products[$product_id]['product_name']);
				$manufacturer_LC 							= strtolower($products[$product_id]['manufacturer']);
				$model_LC									= strtolower($products[$product_id]['model']);
				
				if(!empty($products[$product_id]['family'])){
					$family_LC								= rtrim(strtolower($products[$product_id]['family']));
					$family_LC = str_replace('-', '', $family_LC);
					//$_remove[] = $family_LC;
				}else{
					$family_LC = $this->family_OTHER;
				}

				//strip hyphens
				$model_LC = str_replace('-', '', $model_LC);

				$this->_terms[$manufacturer_LC][$family_LC][$model_LC] = $product_id;
				
				//create variations of the model name 
				if(strstr($model_LC, ' ') !== false){
					$alt_space = str_replace(' ', '', $model_LC);
					$this->_terms[$manufacturer_LC][$family_LC][$alt_space] = $product_id;
				}
				
				$product_id++;
			}
		}
		fclose($fh);
		
		return $products;
	
	}
	
	
	/* 
	 *	parseProducts - parse listings file and compare to products via term tree
	 * 
	 *	@param string $file
	 * 
	 *	@return array
	 */
	private function _parseListings($file){
	
		$listing_id			= 0;					//listing index in listings array (for look-up);
		
		$fh = fopen($file, 'r') or die('Listings file DNE.');
		//$fh2 = fopen('missed.txt', 'w');
		
		$start_time = date('U');
		$eos = 0;
		$ixus = 0;
		while (!feof($fh)) {
			$line = fgets($fh);
			$listing = json_decode($line, true);
			
			$listing_manufacturer 			= null;			//matched listing manufacturer
			$listing_family					= null;			//matched listing family
			$match 							= false;		//has product match been found?
			
			//format manufacturuer/title for handling
			$listing_manufacturer_LC 		= strtolower($listing['manufacturer']);
			$listing_title_LC 				= strtolower($listing['title']);
			
			//remove un-necessary punctuation
			$listing_title_LC 				= str_replace(',', '', $listing_title_LC);
			$listing_title_LC 				= str_replace('-', '', $listing_title_LC);
			
			//parse listings manufacturer for terms
			$manufacturer_terms 			= explode(' ', $listing_manufacturer_LC);
			
			//check to see if manufacturer matches possible prodcuts manufacturers
			foreach($manufacturer_terms as $term){
				if(!empty($this->_terms[$term])){
					$listing_manufacturer = $term;
					break;
				}
			}
			
			//if manufacturer matched, filter title terms for family
			if(!empty($listing_manufacturer)){
			
				//search listing title for terms
				$family_matches = 0;
				$family_matches_arr = array();
				foreach($this->_terms[$listing_manufacturer] as $family => $models){
					if(substr_count($listing_title_LC, $family) >= 1){
						$listing_family = $family;
						$family_matches_arr[] = $family;
						if($family_matches == 0){
							$family_matches++;
						}else{
							$family_matches++;
							break;
						}
					}
				}
				
				if($family_matches > 1){
					if($family_matches_arr[0] == 'eos'){
						$eos++;
					}
					if($family_matches_arr[0] == 'digital ixus'){
						$ixus++;
					}
					
				}
				
				//make sure no multiple family matches
				if($family_matches <= 1){
				
					//if no product family found, test against non-family products
					if(empty($listing_family)){
						$listing_family = $this->family_OTHER;
					}
					
					if(!empty($this->_terms[$listing_manufacturer][$listing_family])){
						
						$model_match 		= null;
						$model_matches 		= 0;
						foreach($this->_terms[$listing_manufacturer][$listing_family] as $model => $product_ref){
							if(substr_count($listing_title_LC, $model) >= 1){
								$model_match = $product_ref;
								if($model_matches == 0){
									$model_matches++;
								}else{
									$model_matches++;
									break;
								}
							}
						}

						
						if($model_matches == 1){
							$this->_addMatch($model_match, $listing);
							$match = true;
					
						}else if($model_matches == 0){
						
							//no match found in 'other'? maybe it its 'family' was no labeled.. check all of them!
							if(!$match && $listing_family = $this->family_OTHER){
							
								$model_match 		= null;
								$model_matches 		= 0;
								foreach($this->_terms[$listing_manufacturer] as $family => $models){
								
									if($family != $this->family_OTHER){
										foreach($models as $model => $product_ref){
										
											if(substr_count($listing_title_LC, $model) >= 1){
												$model_match = $product_ref;
												if($model_matches == 0){
													$model_matches++;
												}else{
													$model_matches++;
													break;
												}
											}
										
										}
										
									}
									
								}
								
								if($model_matches == 1){
									$this->_addMatch($model_match, $listing);
									$match = true;
								}
								
							}
						}
						
					}
				
				}
				
			}

			if(!$match){
				//fwrite($fh2, $listing_id . ' : ' . $listing_title_LC . "\r\n");
			}
			
			$listing_id++;
			$this->_listings++;
		}
		
		$end_time = date('U');
		
		fclose($fh);
		//fclose($fh2);

		return ($end_time - $start_time);
		
	}
	
	
	private function _addMatch($product, $listing){
	
		if(!empty($this->_matches[$product])){
			$this->_matches[$product]['listings'][] = $listing;
		}else{
			$this->_matches[$product] = array(
				'product_name' 	=> $this->_products[$product]['product_name'],
				'listings'		=> array()
			);
			
			$this->_matches[$product]['listings'][] = $listing;
		}

		$this->_found++;
		
		return true;
	}
	
	
	public function writeResults($results){
	
		//print matches to file
		$fh = fopen($results, 'w');
		
		foreach($this->_matches as $line){
			fwrite($fh, json_encode($line) . "\r\n");
		}
		fclose($fh);
	
		return true;
	}
	
	
	public function displayResults(){
		echo 'Parsed ' . $this->_listings . ' listings.. Matched ' . $this->_found . ' in ' . $this->_time . ' seconds. ';
		
		//echo '<br />' . print_r($this->_terms, true);
	}


	
}

?>
