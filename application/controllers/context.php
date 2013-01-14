<?php
require APPPATH.'/libraries/REST_Controller.php';

class Context extends REST_Controller {

	protected $cache 	= array();
	protected $ttl 		= '86400'; 

	public function index_get()	{
		
		if (empty($_GET)) {
			$this->load->helper('url');			
			redirect('welcome');	
		}
		
		
		$data['latitude'] 			  = '';
		$data['longitude'] 			  = '';
		$data['city_geocoded'] 		  = '';
		$data['state_geocoded'] 	  = '';          				

		$data['council_district'] 	  = '';
		$data['community_district']   = '';

		$data['fid'] 				  = '';
		$data['state_id'] 	   		  = '';
		$data['place_id'] 	   		  = '';

		$data['gnis_fid']		      = '';
		$data['place_name'] 	      = '';
		$data['political_desc']       = '';
		$data['title']		          = '';
		$data['address1']  	          = '';
		$data['address2']  	          = '';
		$data['city']		 	      = '';
		$data['zip']		 	      = '';
		$data['zip4']		 	      = '';
		$data['state']	 	          = '';
		$data['place_url'] 	          = '';
		$data['population'] 	      = '';
		$data['county']		          = '';

		$data['community_district']	  = '';
		$data['community_district_fid'] = '';
		$data['community_board']      = '';
		$data['cd_address']		      = '';
		$data['borough']			  = ''; 
		
		$latlong					  = ''; 
									      	        		
		
			$data['input'] 						= $this->input->get('location', TRUE);
			
			if(!$data['input'] ) {
				
				$this->response('No location provided', 400);

			}
			
			$fullstack 					= $this->input->get('fullstack', TRUE);			
			$key 						= $data['input'] . '_context_' . $fullstack;


			// Check in cache
			if ( $cache = $this->cache_get( $key ) ) {
				$this->response($cache, 200);
			}			
			
			// Geocode our address
			$location 					= $this->geocode(urlencode($data['input']));

			if(!empty($location->query->results->Result->latitude)) {
			
				$location = $location->query->results->Result;
				
				$data['latitude'] 			= $location->latitude;
				$data['longitude'] 			= $location->longitude;
				$data['city_geocoded'] 		= $location->city;
				$data['state_geocoded'] 	= $location->state;

				$latlong 					= $data['latitude'] . " " . $data['longitude'];									
			} 
			else {
				$data_errors[] = 'The location could not be geocoded';
			}
			
			// If we're not using a geocoder, but we have a lat,long directly...
			// there should be validation here to see if provided latlong is actually valid and well-formed
			if (!$latlong) $latlong = $data['input'];


			if($latlong && $fullstack == 'true') {
								
				$state_legislators = $this->state_legislators($data['latitude'], $data['longitude']);

				if(!empty($state_legislators)) {
					$state_chambers = $this->process_state_legislators($state_legislators);				
					$data['state_chambers'] = $state_chambers;
				}
				else {
					$data_errors[] = 'State Legislators API (OpenStates) did not respond (perhaps a timeout)';
				}
				
				$national_legislators = $this->national_legislators($data['latitude'], $data['longitude']);	
				
				if (!empty($national_legislators)) {	
					$national_chambers = $this->process_nat_legislators($national_legislators); 		
		  
					ksort($national_chambers);
		  
					$data['national_chambers'] = $national_chambers;				
				}
				else {
					$data_errors[] = 'National Legislators API (Sunlight) did not respond (perhaps a timeout)';
				}				

			}



			if ($latlong) {
				
							
				$data['census_city']			= $this->get_city($data['latitude'], $data['longitude']);
				
				// currently this is only getting local nyc data
				// 
				// if ($fullstack == 'true') {
				// 	
				// 	$council 					= $this->layer_data('census:city_council', 'coundist,gid', $latlong);
				// 	$community_d 				= $this->layer_data('census:community_district', 'borocd,gid', $latlong);
                // 
				// 	$data['council_district'] 	= (!empty($council['features'])) ? $council['features'][0]['properties']['coundist'] : '';
				// 	$data['council_district_fid'] 	= (!empty($council['features'])) ? $council['features'][0]['properties']['gid'] : '';			
		        // 
			    // 
				// 	$data['community_district'] = (!empty($community_d['features'])) ? $community_d['features'][0]['properties']['borocd'] : '';
				// 	$data['community_district_fid'] = (!empty($community_d['features'])) ? "community_district." . $community_d['features'][0]['properties']['gid'] : '';
                // 
				// }
				

				if (!empty($data['census_city'])) {
					
					
						$data['state_id'] 	   		= 	$data['census_city']['STATE'];				
 						$data['place_id'] 	   		= 	$data['census_city']['PLACE'];									
						



					$sql = "SELECT municipalities.GOVERNMENT_NAME, 
					       	 	 	 municipalities.POLITICAL_DESCRIPTION, 
									 municipalities.TITLE, 
									 municipalities.ADDRESS1,
									 municipalities.ADDRESS2, 
									 municipalities.CITY, 
									 municipalities.STATE_ABBR, 
									 municipalities.ZIP, 
									 municipalities.ZIP4, 
									 municipalities.WEB_ADDRESS,
									 municipalities.POPULATION_2005, 
									 municipalities.COUNTY_AREA_NAME, 
		 							 municipalities.MAYOR_NAME, 
									 municipalities.MAYOR_TWITTER, 
									 municipalities.SERVICE_DISCOVERY, 
									 gnis.FEATURE_ID, 
									 gnis.PRIMARY_LATITUDE, 
									 gnis.PRIMARY_LONGITUDE
					   		  	FROM gnis, municipalities
										      WHERE (municipalities.FIPS_PLACE = gnis.CENSUS_CODE
										      	    )
											     AND (municipalities.FIPS_STATE = gnis.STATE_NUMERIC
											     	 )
												 AND (municipalities.FIPS_PLACE = '{$data['place_id']}'
												      )
												 AND (municipalities.FIPS_STATE = '{$data['state_id']}')
												";


					$query = $this->db->query($sql);


					if ($query->num_rows() > 0) {
					   foreach ($query->result() as $rows)  {
					      $data['gnis_fid']			=  ucwords(strtolower($rows->FEATURE_ID));
					      $data['place_name'] 		=  ucwords(strtolower($rows->GOVERNMENT_NAME));
					      $data['political_desc'] 	=  ucwords(strtolower($rows->POLITICAL_DESCRIPTION));
					      $data['title']			=  ucwords(strtolower($rows->TITLE));
					      $data['address1']  		=  ucwords(strtolower($rows->ADDRESS1));
					      $data['address2']  		=  ucwords(strtolower($rows->ADDRESS2));
					      $data['city']		 		=  ucwords(strtolower($rows->CITY));
						  $data['mayor_name']		=  $rows->MAYOR_NAME;
						  $data['mayor_twitter']	=  $rows->MAYOR_TWITTER;
						  $data['service_discovery'] =  $rows->SERVICE_DISCOVERY;	
					      $data['zip']		 		=  $rows->ZIP;
					      $data['zip4']		 		=  $rows->ZIP4;
					      $data['state']	 		=  $rows->STATE_ABBR;
					      $data['place_url'] 	   =  $rows->WEB_ADDRESS;
					      $data['population'] 	   =  $rows->POPULATION_2005;
					      $data['county'] 		   =  ucwords(strtolower($rows->COUNTY_AREA_NAME));
					   }
					}
				}	
				
				
				// Get City/County data from SBA
				if (!empty($data['city']) && !empty($data['state'])) {
					$city_data = $this->get_city_links($data['city'], $data['state']);			
				}
			
				// County lookup 
				if ($latlong) {
				
				
					$data['county_data']			= $this->get_county($data['latitude'], $data['longitude']);
				
				
					$sql = "SELECT * FROM counties
							WHERE fips_county = '{$data['county_data']['COUNTY']}' and fips_state = '{$data['county_data']['STATE']}'";
							
				
					$query = $this->db->query($sql);				
						
					if ($query->num_rows() > 0) {
					   foreach ($query->result() as $rows)  {	
							$data['counties']['county_id']					=  $rows->county_id; 	
							$data['counties']['name']							=  ucwords(strtolower($rows->name));			
							$data['counties']['political_description']		=  $rows->political_description;
							$data['counties']['title']						=  ucwords(strtolower($rows->title)); 			
							$data['counties']['address1']						=  ucwords(strtolower($rows->address1)); 	    
							$data['counties']['address2']						=  ucwords(strtolower($rows->address2)); 	
							$data['counties']['city']							=  ucwords(strtolower($rows->city));   
							$data['counties']['state']						=  $rows->state;  
							$data['counties']['zip']							=  $rows->zip;    
							$data['counties']['zip4']							=  $rows->zip4;   
							$data['counties']['website_url']					=  $rows->website_url; 
							$data['counties']['population_2006']				=  $rows->population_2006;
							$data['counties']['fips_state']					=  $rows->fips_state; 
							$data['counties']['fips_county']					=  $rows->fips_county; 	        			      			      	                               
					   }
					}				


				
				}				
				

				// County Representatives
				if (!empty($data['counties']['name']) && !empty($data['counties']['state'])) {
					
					$data['county_reps'] = $this->get_county_reps($data['counties']['state'], $data['counties']['name']);
					
				}
				


				
				
				// Currently unused hyperlocal data for NYC			
				if (is_numeric($data['community_district']) && $fullstack == 'true') {
				
				
					$sql = "SELECT * FROM community_boards
							WHERE city_id = {$data['community_district']}";


					$query = $this->db->query($sql);				
						
					if ($query->num_rows() > 0) {
					   foreach ($query->result() as $rows)  {	
							$data['community_board']		=  $rows->community_board;			
							$data['cd_address']				=  $rows->address;	
							$data['borough']				=  $rows->borough;
							$data['board_meeting']			=  $rows->board_meeting;
							$data['cabinet_meeting']		=  $rows->cabinet_meeting;
							$data['chair']					=  $rows->chair;
							$data['district_manager']		=  $rows->district_manager;
							$data['website']				=  $rows->website;	
							$data['email']					=  $rows->email;	
							$data['phone']					=  $rows->phone;	
							$data['fax']					=  $rows->fax;		
							$data['neighborhoods']			=  $rows->neighborhoods;		      	
									      	
					   }
					}				
				
				}


				// Currently unused hyperlocal data for NYC
				if (is_numeric($data['council_district']) && $fullstack == 'true') {
				
				
					$sql = "SELECT * FROM council_districts
							WHERE district = {$data['council_district']}";


					$query = $this->db->query($sql);				
						
					if ($query->num_rows() > 0) {
					   foreach ($query->result() as $rows)  {	
							$data['c_address']					=  $rows->address;	
							$data['c_committees']				=  $rows->committees;	
							$data['c_term_expiration']			=  $rows->term_expiration;
							$data['c_district_fax']			=  $rows->district_fax;
							$data['c_district_phone']			=  $rows->district_phone;	
							$data['c_email']					=  $rows->email;
							$data['c_council_member_since']	=  $rows->council_member_since;
							$data['c_headshot_photo']			=  $rows->headshot_photo;	
							$data['c_legislative_fax']			=  $rows->legislative_fax;	
							$data['c_legislative_address']		=  $rows->legislative_address;	
							$data['c_legislative_phone']		=  $rows->legislative_phone;
							$data['c_name']					=  $rows->name;		
							$data['c_twitter_user']					=  $rows->twitter_user;								      	
									      	
					   }
					}				
				
				}

			
				
								
			}
			
			// get GeoJSON from GeoServer
			if ($this->input->get('geojson', TRUE) == 'true') {				
				$data['geojson'] = $this->get_geojson($data['fid']);
			}
			
			// Service Discovery
			if (!empty($data['service_discovery'])) {
				$data['service_discovery'] = $this->get_servicediscovery($data['service_discovery']);
			}
			
		
			
			
			// Mayor data
			if (!empty($data['city']) && !empty($data['state'])) {
				$mayor = $this->get_mayors($data['city'], $data['state']);
				
				if(!empty($mayor)) {
					$data['mayor_data'] = $mayor;	
					
					// See if we can get social media channels for this mayors
					$mayor_sm = $this->get_mayor_sm($data['city']);					
					if(!empty($mayor_sm)) $data['mayor_sm'] = $mayor_sm;
					
								
				}
			}		
			
			
			// Better links for municipal data from the SBA (I'm only pulling out the url, but other data might be usefull too)

			if(!empty($city_data)) {
				$data['place_url_updated'] = $city_data[0]['url'];

				if ($fullstack == 'true') $data['city_data'] = $city_data[0];
				
			} else {
				
				if(!empty($data['mayor_data']['url'])) {
					$data['place_url_updated'] = $data['mayor_data']['url'];	
				} else {
					$data['place_url_updated'] = $data['place_url'];	
				}
			}
		
			
			
			// DC Hyperlocal data - this should be totally decoupled, but including it here as a proof of concept
			if (($data['state_id'] == '11') && ($data['place_id'] == '50000')) {
			
				$data['city_ward'] = $this->get_dc_ward($data['latitude'], $data['longitude']); 
			
				if(!empty($data['city_ward']['LABEL'])) {
					$data['council_reps'] = $this->get_dc_councilmembers($data['city_ward']['LABEL']);
				}
			
			}
			
			// City Data
			// if include file exists, load it
			// 		jurisdiction_data = get data;

			// 		$city_reps = $this->get_city_reps($data['city'], $data['state']);
			// 		
			//	
			//		

			// State data
			if (!empty($data['state_geocoded'])) {
				$state = $this->get_state($data['state_geocoded']);
				
				if(!empty($state)) {
					$data['state_data'] = $state;				
				}
				
				$governor = $this->get_governor($data['state_geocoded']);
				
				if(!empty($governor)) {
					$data['governor_data'] = $governor;				
				}	
				
				$governor_socialmedia = $this->get_governor_sm($data['state_geocoded']);			
				
				if(!empty($governor_socialmedia)) {
					$data['governor_sm'] = $governor_socialmedia;				
				}				
				
			}			
			

			// See if we have google analytics tracking code
			if($this->config->item('ganalytics_id')) {
				//$data['ganalytics_id'] = $this->config->item('ganalytics_id');
			}
			
			
			
			if ($fullstack == 'true') {
				
				$new_data = $this->re_schema($data);
				
				// basic error reporting
				if(!empty($data_errors)) $new_data['errors'] = $data_errors;
				
				// Save to cache
				$this->cache_set( $key, $new_data);
				
				$this->response($new_data, 200);
			} else
			{
						
			$endpoint['url'] = (!empty($data['place_url_updated'])) ? $data['place_url_updated'] : $data['place_url'];
			
			// In this case we're just publishing service discovery and geojson
			if (!empty($data['service_discovery'])) {
				$endpoint['service_discovery'] 	= $data['service_discovery'];
			}
			
			// only return geojson if requested
			if (isset($data['geojson'])) $endpoint['geojson'] = $data['geojson'];
			
			// Save to cache
			$this->cache_set( $key, $endpoint);			
			
			$this->response($endpoint, 200);
			}
		
	}
	

	
	function data()
	{
		$this->db->where('id', $this->uri->segment(3));
		$data['query'] = $this->db->get('dataset');
		$data['agencies'] = $this->db->get('agency');

		$this->load->view('map_view', $data);
	}	
	
	function dataset_add()
	{
		$data['query'] = $this->db->get('agency');
		
		$this->load->view('dataset_add', $data);
	}	


	function dataset_insert()
	{
			$this->db->insert('dataset', $_POST);
				
			redirect('dataset/data/'.$this->db->insert_id());
	}
	
	
	
	
	function layer_data($layer, $properties, $latlong) {


		$url = $this->config->item('geoserver_root') . 
			"/wfs?request=GetFeature&service=WFS&typename=" . 
			rawurlencode($layer) . 
			"&propertyname=" . 
			rawurlencode($properties) .
			"&CQL_FILTER=" . 
			rawurlencode("INTERSECT(the_geom, POINT (" . $latlong . "))") . 
			"&outputformat=JSON";

		
		$feature_data = $this->curl_to_json($url);

		return $feature_data;

	}
	
	
	
	function get_city($lat, $long) {	
		
		$url = "http://tigerweb.geo.census.gov/ArcGIS/rest/services/Census2010/tigerWMS/MapServer/58/query?text=&geometry=$long%2C$lat&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&relationParam=&objectIds=&where=&time=&returnCountOnly=false&returnIdsOnly=false&returnGeometry=false&maxAllowableOffset=&outSR=&outFields=*&f=json";

			$feature_data = $this->curl_to_json($url);

			if(!empty($feature_data['features'])) return $feature_data['features'][0]['attributes'];			

	}	
	
	
	
	
	function get_geojson($feature_id) {	
		
		$url = $this->config->item('geoserver_root') . '/wfs?request=getFeature&outputFormat=json&layers=census:municipal&featureid=' . $feature_id; 
		
		
			$feature_data = $this->curl_to_json($url);

			return $feature_data;

	}	
	
	
	function get_city_links($city, $state) {
		
		$key = md5( serialize( "$city, $state" )) . '_city_links';
		
		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
			
			
		$city = urlencode(strtolower($city));
		$state = urlencode(strtolower($state));
	
		$url = "http://api.sba.gov/geodata/all_links_for_city_of/$city/$state.json";

		$data = $this->curl_to_json($url);	
		
		// Save to cache
		$this->cache_set( $key, $data);

		return $data;

	}	
	
	
	
	function get_servicediscovery($url) {	
		
			$data = $this->curl_to_json($url);

			return $data;

	}	
	
	
	
	
	
	function get_mayors($city, $state) {
		
		$key = md5( serialize( "$city, $state" )) . '_city_mayor';
		
		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
		
		$city = ucwords($city);
		$state = strtoupper($state);		
		
		$query = "select * from `swdata` where city = '$city' and state = '$state' limit 1";		
		$query = urlencode($query);
		
		$url = "https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=us_mayors&query=$query";		

		$mayors = $this->curl_to_json($url);

		if(!empty($mayors)) {
			
			// Save to cache
			$this->cache_set( $key, $mayors[0]);
			
			return $mayors[0];			
		}

	}
	
	
	
	
	function get_mayor_sm($city) {
		
		$key = md5( serialize( $city )) . '_city_mayor_sm';
		
		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
		
		$city = ucwords($city);		
		
		$query = "select * from `swdata` where city = '$city' limit 1";		
		$query = urlencode($query);
		
				
		$url = "https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=us_mayors_-_social_media_accounts&query=$query";		

		$mayor = $this->curl_to_json($url);
				
		if(!empty($mayor)) {
			
			// Save to cache
			$this->cache_set( $key, $mayor[0]);
			
			return $mayor[0];			
		}		
				
		
	}	
	
	
	
	function get_county_reps($state, $county) {
		
		$key = md5( serialize( "$state$county" )) . '_county_rep';
		
		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
		
		$county = ucwords($county);	
		$state = strtoupper($state);	
				
		$query = "select rep, rep_email, rep_position from `swdata` where county = '$county' and state = '$state'";		
		$query = urlencode($query);
							
		$url = "https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=us_county_representatives&query=$query";		

		$county_reps = $this->curl_to_json($url);
				
		if(!empty($county_reps)) {
			
			// Save to cache
			$this->cache_set( $key, $county_reps);
			
			return $county_reps;			
		}		
				
		
	}	
		
	
function get_city_reps($city, $state) {
	
	// include state file
	
	return $city_reps;
	
}


	
// DC Specific 	

function get_dc_ward($lat, $long)	{
	

	$url ="http://maps.dcgis.dc.gov/DCGIS/rest/services/DCGIS_DATA/Administrative_Other_Boundaries_WebMercator/MapServer/26/query?text=&geometry=$long%2C+$lat&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&where=&returnGeometry=false&outSR=4326&outFields=NAME%2C+WARD_ID%2C+LABEL&f=json";		

	$data = $this->curl_to_json($url);

	$data = $data['features'][0]['attributes'];

	return $data;
	
}


// DC Specific 	

function get_dc_councilmembers($ward)	{
	
	$key = md5( serialize( $ward )) . '_dc_ward_members';
	
	// Check in cache
	if ( $cache = $this->cache_get( $key ) ) {
		return $cache;
	}	
	
	$ward = urlencode($ward);
	
	$url ="https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=washington_dc_wards_and_councilmembers&query=select%20*%20from%20%60swdata%60%20where%20ward_name%20%3D%20%22$ward%22%3B";		

	$response['my_rep'] = $this->curl_to_json($url);
	
	
	$url ="https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=washington_dc_wards_and_councilmembers&query=select%20*%20from%20%60swdata%60%20where%20member_type%20!%3D%20%22Ward%20Members%22%3B";		


	$response['at_large'] = $this->curl_to_json($url);

	// Save to cache
	$this->cache_set( $key, $response);

	return $response;	
	
}



function get_county($lat, $long) {	
	
	
	$url = "http://tigerweb.geo.census.gov/ArcGIS/rest/services/Census2010/tigerWMS/MapServer/115/query?text=&geometry=$long%2C$lat&geometryType=esriGeometryPoint&inSR=4326&spatialRel=esriSpatialRelIntersects&relationParam=&objectIds=&where=&time=&returnCountOnly=false&returnIdsOnly=false&returnGeometry=false&maxAllowableOffset=&outSR=&outFields=COUNTY,BASENAME,NAME,STATE&f=json";	

		$feature_data = $this->curl_to_json($url);

		if(!empty($feature_data['features'])) return $feature_data['features'][0]['attributes'];			

}
	
	
	function get_state($state) {
		
		$key = $state . '_state_data';

		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
		
		$state = ucwords($state);		
		
		$query = "select * from `swdata` where state = '$state' limit 1";		
		$query = urlencode($query);
		
		$url = "https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=50_states_data&query=$query";		

		$state = $this->curl_to_json($url);
		
		if(!empty($state[0])) {
		
			// Save to cache
			$this->cache_set( $key, $state[0]);
			
			return $state[0];		
		}

	}
	
	
	function get_governor($state) {
				
		$state = ucwords($state);		
		
		$key = $state . '_state_governor';		
		
		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
		
		$query = "select * from `swdata` where state = '$state' limit 1";		
		$query = urlencode($query);
		
				
		$url = "https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=us_governors&query=$query";		

		$state = $this->curl_to_json($url);

		if(!empty($state[0])) {

			// Save to cache
			$this->cache_set( $key, $state[0]);		
		
			return $state[0];
		}


	}	
	
	
	function get_governor_sm($state) {
		
		$state = ucwords($state);		
		
		$key = $state . '_state_governor_sm';				
		
		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
		
		$query = "select * from `swdata` where state = '$state' limit 1";		
		$query = urlencode($query);
		
				
		$url = "https://api.scraperwiki.com/api/1.0/datastore/sqlite?format=jsondict&name=us_governors_-_social_media_accounts&query=$query";		

		$state = $this->curl_to_json($url);
		
		if(!empty($state[0])) {
			
			// Save to cache
			$this->cache_set( $key, $state[0]);
			
			return $state[0];		
		}	

	}




	function geocode($location) {
		
		$this->load->helper('oauth.php');
		
		$url = "http://query.yahooapis.com/v1/yql/";
		$args = array();
		$args["q"] = 'select * from geo.placefinder where text="' . $location . '"';
		$args["format"] = "json";

		$consumer = new OAuthConsumer($this->config->item('yahoo_oauth_key'), $this->config->item('yahoo_oauth_secret'));
		$request = OAuthRequest::from_consumer_and_token($consumer, NULL,"GET", $url, $args);
		$request->sign_request(new OAuthSignatureMethod_HMAC_SHA1(), $consumer, NULL);
		$url = sprintf("%s?%s", $url, OAuthUtil::build_http_query($args));
		$ch = curl_init();
		$headers = array($request->to_header());
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$rsp = curl_exec($ch);
		$location = json_decode($rsp);

		return $location;

	}	
		
	
	
	function state_legislators($lat, $long) {
		
		$url = "http://openstates.org/api/v1/legislators/geo/?long=" . $long . "&lat=" . $lat . "&fields=state,chamber,district,full_name,url,photo_url&apikey=" . $this->config->item('sunlight_api_key');

		$state_legislators = $this->curl_to_json($url);
				
		if(!empty($state_legislators)) return $state_legislators;				
		else return false;
		
	}
	

	
	function state_boundaries($state, $chamber) {
		
		$key = $state . '_' . $chamber . '_state_boundaries';
		
		// Check in cache
		if ( $cache = $this->cache_get( $key ) ) {
			return $cache;
		}		
		
		$url = "http://openstates.org/api/v1/districts/" . $state . "/" . $chamber . "/?fields=name,boundary_id&apikey=" . $this->config->item('sunlight_api_key');

		$state_boundaries = $this->curl_to_json($url);
		$state_boundaries = $this->process_boundaries($state_boundaries);

		$this->cache_set( $key, $state_boundaries);

		return $state_boundaries;

	}

	
	
	
	function state_boundary_shape($boundary_id) {
		
		$url = "http://openstates.org/api/v1/districts/boundary/" . $boundary_id . "/?apikey=" . $this->config->item('sunlight_api_key');


		$geojson = $this->curl_to_json($url);	

		$boundary_shape['coordinates'] = $geojson['shape'];
		$boundary_shape = json_encode($boundary_shape);

		$shape['shape'] = $boundary_shape;
		$shape['shape_center_lat'] = $geojson['region']['center_lat'];
		$shape['shape_center_long'] = $geojson['region']['center_lon'];		

		return $shape;

	}	
	
	
	
	function process_boundaries($boundary_array) {

		// Clean up data model
		foreach($boundary_array as $boundarydata){	

				$district = $boundarydata['name'];
				$boundary_id = $boundarydata['boundary_id'];

				$boundaries[$district]['boundary_id'] = $boundary_id;

		}
		
		if(isset($boundaries)) {
			return $boundaries;
		}
		else {
			return false;
		}

	}	
	
	
	
function process_state_legislators($representatives) {
	
		// Get our current state
		$current_state = $representatives[0]['state'];
	
		// Clean up data model
		foreach($representatives as $repdata){
			
				$rep = array(
							'full_name' => $repdata['full_name']
							);
				
				// there are some missing fields on some entries, check for that
				if(isset($repdata['photo_url'])){
					$rep['photo_url'] = $repdata['photo_url'];
				}
				
				if(isset($repdata['url'])){
					$rep['url'] = $repdata['url'];
				}				
				
							
		
				$chamber = $repdata['chamber'];
				$district = $repdata['district'];
		
					
				$chambers[$chamber][$district]['reps'][] = $rep;	
			
		}	
		
		// Only do this if we want geospatial data in the response
		if ($this->input->get('geojson', TRUE) == 'true') {
			
			// Get the boundary_ids for this state
			$boundary_ids['upper'] = $this->state_boundaries($current_state, 'upper'); 
			$boundary_ids['lower'] = $this->state_boundaries($current_state, 'lower'); 	
		
			
			// Get shapes for each of the boundary ids we care about		
			while($districts = current($chambers)) {

				$this_chamber = key($chambers);
				if (!isset($current_chamber)) $current_chamber = '';
			
				// reset current district in case district ids are reused across chambers
				$current_district = '';			
				if ($current_chamber !== $this_chamber){
				
					while($district = current($districts)) {

						$this_district = key($districts);
						if (!isset($current_district)) $current_district = '';
					
						if ($current_district !== $this_district) {

							// get shape for this boundary id
							$boundary_id = $boundary_ids["$this_chamber"][$this_district]['boundary_id'];
											
							$shape = $this->state_boundary_shape($boundary_id);
						
							$chambers[$this_chamber][$this_district]['shape'] = $shape['shape'];
							$chambers[$this_chamber][$this_district]['centerpoint_latlong'] = $shape['shape_center_lat'] . ',' . $shape['shape_center_long'];					

						}	

					    $current_district = $this_district;
						next($districts);
					}

				}

			    $current_chamber = $this_chamber;
				next($chambers);
			}
					
		}
		
	
		return $chambers;
	
}	
	
	
	
function national_legislators($lat, $long) {

	$url = "http://services.sunlightlabs.com/api/legislators.allForLatLong.json?latitude=" . $lat . "&longitude=" . $long . "&apikey=" . $this->config->item('sunlight_api_key');


	$legislators = $this->curl_to_json($url);
	$legislators = $legislators['response']['legislators'];

	return $legislators;	

}
	
	
	
function process_nat_legislators($representatives) {
	

	
		// Clean up data model
		foreach($representatives as $repdata){
			
			$repdata = $repdata['legislator'];
			
			$full_name = $repdata['firstname'] . ' ' . $repdata['lastname'];
			
		
				$rep = array(
							'district' 		=> $repdata['district'], 
							'full_name' 	=> $full_name, 
							'name_given' 	=> $repdata['firstname'], 
							'name_family' 	=> 	$repdata['lastname'], 
							'bioguide_id' 	=> $repdata['bioguide_id'], 
							'website' 		=> $repdata['website'], 
							'url_contact' 		=> $repdata['webform'], 							
							'title' 	=> $repdata['title'],								
							'phone' 	=> $repdata['phone'], 
							'twitter_id' 	=> $repdata['twitter_id'],
							'youtube_url' 	=> $repdata['youtube_url'],																					
							'congress_office' 		=> $repdata['congress_office'], 
							'facebook_id' 	=> $repdata['facebook_id'], 
							'email' 	=> $repdata['email']														
							);
	
				$chamber = $repdata['chamber'];
				$district = $repdata['district'];
				$state = $repdata['state'];
				
				$chambers["$chamber"]['reps'][] = $rep;

				
				if(is_numeric($district)) {
					$boundary_district = $district;
					
					$chambers["$chamber"]['shape'] = 'http://www.govtrack.us/perl/wms/export.cgi?dataset=http://www.rdfabout.com/rdf/usgov/congress/' . $chamber . '/110&region=http://www.rdfabout.com/rdf/usgov/geo/us/' . $state . '/cd/110/' . $district . '&format=kml&maxpoints=1000';
				}	

			
		}	
		
	
		return $chambers;
	
}	


function curl_to_json($url) {
	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	$data=curl_exec($ch);
	curl_close($ch);


	return json_decode($data, true);	
	
}


/**
 * Retrieve data from Alternative PHP Cache (APC).
 */
protected function cache_get( $key ) {
	
	if ( !extension_loaded('apc') || (ini_get('apc.enabled') != 1) ) {
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}
	}
	else {
		return apc_fetch( $key );
	}

	return false;

}

/**
 * Store data in Alternative PHP Cache (APC).
 */
protected function cache_set( $key, $value, $ttl = null ) {

	if ( $ttl == null ) {
		$ttl = ($this->config->item('cache_ttl')) ? $this->config->item('cache_ttl') : $this->ttl;
	}

	$key = 'db_api_' . $key;

	if ( extension_loaded('apc') && (ini_get('apc.enabled') == 1) ) {
		return apc_store( $key, $value, $ttl );
	}

	$this->cache[$key] = $value;


}


function re_schema($data) {
	
	
	
	$new_data['latitude']			= $data['latitude'];
	$new_data['longitude']			= $data['longitude'];
	$new_data['input_location']		= $data['input'];	

			


 // ############################################################################################################
 // City Council  
 
 // Elected
 
 if (!empty($data['council_reps']['my_rep'])) {	
 
	$myrep = $data['council_reps']['my_rep'][0];
 
 	$elected = array(
 			$this->elected_official_model('legislative', 'Councilmember', null, null, null, $myrep['name'], $myrep['website'], $myrep['url_photo'], null, null, $myrep['email'], $myrep['phone'], null, $myrep['address'], null, null, null, null, $myrep['term_end'], null, null)		
 	);

 
 // Jurisdiction

 	$new_data['jurisdictions'][] = $this->jurisdiction_model('legislative', 'City Council', 'municipal', 'City', $myrep['ward_name'], $data['city_ward']['WARD_ID'], $myrep['ward_url'], null, null, null, null, null, null, null, null, null, null, null, null, $elected, null);
 }
 
 

// ############################################################################################################
// Municipal 

// Elected
	
$elected = null;

if (!empty($data['mayor_data'])) {	

	if (!empty($data['mayor_sm'])) {

		$social_media = null;

		if(!empty($data['mayor_sm']['twitter'])) {
		
			$twitter_username = substr($data['mayor_sm']['twitter'], strrpos($data['mayor_sm']['twitter'], '/')+1);
			$social_media[] = array("type" => "twitter","description" => "Twitter","username" => $twitter_username,"url" => $data['mayor_sm']['twitter'],"last_updated" => null);
		}

		if(!empty($data['mayor_sm']['facebook'])) {
			$social_media[] = array("type" => "facebook","description" => "Facebook","username" => null,"url" => $data['mayor_sm']['facebook'],"last_updated" => null);
		}

		if(!empty($data['mayor_sm']['youtube'])) {
			$social_media[] = array("type" => "youtube","description" => "Youtube","username" => null,"url" => $data['mayor_sm']['youtube'],"last_updated" => null);
		}	

		if(!empty($data['mayor_sm']['flickr'])) {
			$social_media[] = array("type" => "flickr","description" => "Flickr","username" => null,"url" => $data['mayor_sm']['flickr'],"last_updated" => null);
		}	
	}
	else {
		$social_media = null;
	}

	$mayor_name_full 			= isset($data['mayor_data']['name']) ? $data['mayor_data']['name'] : null;	
	$mayor_url 					= isset($data['mayor_data']['bio_url']) ? $data['mayor_data']['bio_url'] : $data['place_url_updated']; // the bio url isn't exactly what we want here, but it's close enough. Usually the bio is one page of the mayor's section of the website. Really we just want the main mayor section
	//$mayor_url 					= $data['place_url_updated']; 	// We're assuming that the place_url_updated will always match the mayor url, but be more updated. Hopefully this is a safe assumption. 	
	$mayor_url_photo 			= isset($data['mayor_data']['url_photo']) ? $data['mayor_data']['url_photo'] : null;
	$mayor_email 				= isset($data['mayor_data']['email']) ? $data['mayor_data']['email'] : null;
	$mayor_phone 				= isset($data['mayor_data']['phone']) ? $data['mayor_data']['phone'] : null;	
	$mayor_current_term_enddate = isset($data['mayor_data']['current_term_enddate']) ? date('c', strtotime($data['mayor_data']['next_election'])) : null;	
	



	$elected = array(
			$this->elected_official_model('executive', 'Mayor', null, null, null, $mayor_name_full, $mayor_url, $mayor_url_photo, null, null, $mayor_email, $mayor_phone, null, null, null, null, null, null, $mayor_current_term_enddate, null, $social_media)		
	);

} else {
	$elected = null;	
}




 if (!empty($data['council_reps']['at_large'])) {
	
	foreach ($data['council_reps']['at_large'] as $at_large) {

	$elected[] = $this->elected_official_model('legislative', 'Councilmember', $at_large['member_type'], null, null, $at_large['name'], $at_large['website'], $at_large['url_photo'], null, null, $at_large['email'], $at_large['phone'], null, $at_large['address'], null, null, null, null, $at_large['term_end'], null, null);
	
	}
	
}





// Jurisdiction
if(!empty($data['zip'])) {
	
	$municipal_zip 		= ($data['zip4']) ? $data['zip'] . '-' . $data['zip4'] : $data['zip'];	

	$municipal_metadata = array(array("key" => "place_id", "value" => $data['place_id']), 
									array("key" => "gnis_fid", "value" => $data['gnis_fid']));											
	

	$new_data['jurisdictions'][] = $this->jurisdiction_model('government', 'City', 'municipal', 'City', $data['city'], null, $data['place_url_updated'], null, null, null, $data['title'], $data['address1'], $data['address2'], $data['city'], $data['state'], $municipal_zip, null, $municipal_metadata, null, $elected, $data['service_discovery']);
}

$elected = null;	
	
// ##########################################################################################################
// Counties Jurisdictions

if (!empty($data['counties'])) {
	
	if (!empty($data['county_reps'])) {
		
		foreach ($data['county_reps'] as $co_rep) {

			$elected[] = $this->elected_official_model('administrative', $co_rep['rep_position'], null, null, null, $co_rep['rep'], null, null, null, null, $co_rep['rep_email'], null, null, null, null, null, null, null, null, null, null);

		}		
		
	} else {
		$elected = null;
	}
		
		
	

	$county_zip 		=  ($data['counties']['zip4']) ? $data['counties']['zip'] . '-' . $data['counties']['zip4'] : $data['counties']['zip'];		

	$county_metadata = array(array("key" => "fips_id", "value" => $data['counties']['fips_county']), 
							array("key" => 'county_id', "value" => $data['counties']['county_id']), 
							array("key" => 'population', "value" => $data['counties']['population_2006']));										
	

	$new_data['jurisdictions'][] = 	$this->jurisdiction_model('government', 'County', 'sub_regional', 'County', $data['counties']['name'], $data['counties']['county_id'], $data['counties']['website_url'], null, null, null, $data['counties']['title'], $data['counties']['address1'], $data['counties']['address2'], $data['counties']['city'], $data['counties']['state'], $county_zip, null, $county_metadata, null, $elected, null);

	$elected = null;
}


// ##########################################################################################################
// State Chambers Lower

if (!empty($data['state_chambers']['lower'])) {

	$rep_id = key($data['state_chambers']['lower']);


	// elected office

	$slc_reps = $data['state_chambers']['lower'][$rep_id]['reps'];	
	foreach($slc_reps as $slc_rep) {		
		$slc_rep['photo_url'] = (!empty($slc_rep['photo_url'])) ? $slc_rep['photo_url'] : null;
		$reps[] = $this->elected_official_model('legislative', 'Representative', null, null, null, $slc_rep['full_name'], $slc_rep['url'], $slc_rep['photo_url'], null, null, null, null, null, null, null, null, null, null, null, null, null);		
	}


	// jurisdiction 

	$district = 'District ' . $rep_id;		
	$new_data['jurisdictions'][] = $this->jurisdiction_model('legislative', 'House of Representatives', 'regional', 'State', $district, $rep_id, null, null, null, null, null, null, null, null, null, null, null, null, null, $reps, null);

}
	

// ##########################################################################################################
// State Chambers Upper							
		
// filtering for DC here
if (!empty($data['state_chambers']['upper']) && (!empty($data['national_chambers']['house']['reps'][0]['district'])) && ($data['national_chambers']['house']['reps'][0]['district'] !== "0")) {

	$rep_id = key($data['state_chambers']['upper']);


	// Elected Office						

	$slc_reps = null;
	$slc_reps = $data['state_chambers']['upper'][$rep_id]['reps'];
	$reps = null;

	foreach($slc_reps as $slc_rep) {
		$slc_rep['photo_url'] = (!empty($slc_rep['photo_url'])) ? $slc_rep['photo_url'] : null;	
		$reps[] = $this->elected_official_model('legislative', 'Senator', null, null, null, $slc_rep['full_name'], $slc_rep['url'], $slc_rep['photo_url'], null, null, null, null, null, null, null, null, null, null, null, null, null);

	}			
			
	// Jurisdiction						


	$district = 'District ' . $rep_id;

		$new_data['jurisdictions'][] = $this->jurisdiction_model('legislative', 'Senate', 'regional', 'State', $district, $rep_id, null, null, null, null, null, null, null, null, null, null, null, null, null, $reps, null);

}	



// ##########################################################################################################
// State

		
if (!empty($data['state_data'])) {

	$state_metadata = array(array("key" => "state_id", "value" => $data['state_id']));										
	
// Governor
	$elected = array($this->elected_official_model('executive', 'Governor', null, null, null, $data['state_data']['governor'], $data['state_data']['governor_url'], null, null, null, null, null, null, null, null, null, null, null, null, null, null));

if (!empty($data['governor_data'])) {
	$elected[0]['url_photo'] = $data['governor_data']['url_photo'];
	$elected[0]['phone'] = $data['governor_data']['phone'];
	$elected[0]['address_1'] = $data['governor_data']['address_1'];	
	$elected[0]['address_2'] = $data['governor_data']['address_2'];	
	$elected[0]['address_city'] = $data['governor_data']['address_city'];	
	$elected[0]['address_state'] = $data['governor_data']['address_state'];	
	$elected[0]['address_zip'] = $data['governor_data']['address_zip'];							
	$elected[0]['url'] = (empty($elected[0]['url'])) ? $data['governor_data']['url_governor'] : $elected[0]['url'];
}

if (!empty($data['governor_sm'])) {

	$social_media = null;

	if(!empty($data['governor_sm']['twitter'])) {
		$twitter_username = substr($data['governor_sm']['twitter'], strrpos($data['governor_sm']['twitter'], '/')+1);
		$social_media[] = array("type" => "twitter","description" => "Twitter","username" => $twitter_username,"url" => $data['governor_sm']['twitter'],"last_updated" => null);
	}
	
	if(!empty($data['governor_sm']['facebook'])) {
		$social_media[] = array("type" => "facebook","description" => "Facebook","username" => null,"url" => $data['governor_sm']['facebook'],"last_updated" => null);
	}
	
	if(!empty($data['governor_sm']['youtube'])) {
		$social_media[] = array("type" => "youtube","description" => "Youtube","username" => null,"url" => $data['governor_sm']['youtube'],"last_updated" => null);
	}	
	
	if(!empty($data['governor_sm']['flickr'])) {
		$social_media[] = array("type" => "flickr","description" => "Flickr","username" => null,"url" => $data['governor_sm']['flickr'],"last_updated" => null);
	}	


	$elected[0]['social_media'] = $social_media;
}


	$new_data['jurisdictions'][] = $this->jurisdiction_model('government', 'State', 'regional', 'State', $data['state_geocoded'], $data['state'], $data['state_data']['official_name_url'], $data['state_data']['information_url'], $data['state_data']['email'], $data['state_data']['phone_primary'], null, null, null, null, null, null, null, $state_metadata, null, $elected, null);
	

}



// ##########################################################################################################	
// US House of Reps
	
	
if (!empty($data['national_chambers']['house']['reps'])) {


$nhr = $data['national_chambers']['house']['reps'][0];


	// elected office
	
		$social_media = null;

		if(!empty($nhr['twitter_id']) || !empty($nhr['facebook_id'])) {
			$social_media = array();			
		} else {
			$social_media = null;
		}

		if(!empty($nhr['twitter_id'])) {
			$social_media[] = array("type" => "twitter","description" => "Twitter","username" => $nhr['twitter_id'],"url" => "http://twitter.com/{$nhr['twitter_id']}","last_updated" => null);
		}
		
		if(!empty($nhr['facebook_id'])) {
			$social_media[] = array("type" => "facebook","description" => "Facebook","username" => $nhr['facebook_id'],"url" => "http://facebook.com/{$nhr['facebook_id']}","last_updated" => null);
		}
		
		if(!empty($nhr['youtube_url'])) {
			$social_media[] = array("type" => "youtube","description" => "Youtube","username" => null,"url" => $nhr['youtube_url'],"last_updated" => null);
		}		
		
		
		$img_url = $this->config->item('democracymap_root') . '/img/headshot/us-congress/' . $nhr['bioguide_id'] . '.jpg';


	$elected = 	array($this->elected_official_model('legislative', $nhr['title'], null, null, null, $nhr['full_name'], $nhr['website'], $img_url, null, null, null, $nhr['phone'], null, null, null, null, null, null, null, null, $social_media));
	
	$district = "District " . $nhr['district'];

	$new_data['jurisdictions'][] = $this->jurisdiction_model('legislative', 'House of Representatives', 'national', 'United States', $district, $nhr['district'], null, null, null, null, null, null, null, null, null, null, null, null, null, $elected, null);

}





// ############################################################################################################

// US Senators

// filtering out DC here (removed this for a moment, but it's  if lower district != 0)
if (!empty($data['state_chambers']['upper']) && (!empty($data['national_chambers']['house']['reps'][0]['district'])) && ($data['national_chambers']['house']['reps'][0]['district'] !== "0")) {
	// Make sure these are empty
	$elected = null;
	$social_media = null;


	// Elected Office

	foreach($data['national_chambers']['senate']['reps'] as $slc_rep) {
		
		
		$img_url = $this->config->item('democracymap_root') . '/img/headshot/us-congress/' . $slc_rep['bioguide_id'] . '.jpg';


		if(!empty($slc_rep['twitter_id']) || !empty($slc_rep['facebook_id'])) {
			$social_media = array();			
		} else {
			//$social_media = null;
		}

		if(!empty($slc_rep['twitter_id'])) {
			$social_media[] = array("type" => "twitter",
							  							"description" => "Twitter",
							  							"username" => $slc_rep['twitter_id'],
						 	  							"url" => "http://twitter.com/{$slc_rep['twitter_id']}",
							  							 "last_updated" => null);
		}
		

		if(!empty($slc_rep['facebook_id'])) {
			$social_media[] = array("type" => "facebook",
			 	  									"description" => "Facebook",
			 	  									"username" => $slc_rep['facebook_id'],
			 	  									"url" => "http://facebook.com/{$slc_rep['facebook_id']}",
			 	  									 "last_updated" => null);
		}		
		
		if(!empty($slc_rep['youtube_url'])) {
			$social_media[] = array("type" => "youtube","description" => "Youtube","username" => null,"url" => $slc_rep['youtube_url'],"last_updated" => null);
		}		
		
		
		$elected[] = $this->elected_official_model('legislative', $slc_rep['title'], $slc_rep['district'], $slc_rep['name_given'], $slc_rep['name_family'], $slc_rep['full_name'], $slc_rep['website'], $img_url, null, $slc_rep['url_contact'], $slc_rep['email'], $slc_rep['phone'], null, $slc_rep['congress_office'], null, null, null, null, null, null, $social_media);					

}
	
	
// Jurisdiction 
	
$new_data['jurisdictions'][] = $this->jurisdiction_model('legislative', 'Senate', 'national', 'United States', $data['state_geocoded'], $data['state'], null, null, null, null, null, null, null, null, null, null, null, null, null, $elected, null);	
	

}


// Hard coding national data for now

if (!empty($new_data['jurisdictions'])) {
	
	$elected = null;
	$social_media = null;
	
	$social_media[] = array("type" => "twitter",
	 	  									"description" => "Twitter",
	 	  									"username" => "whitehouse",
	 	  									"url" => "http://twitter.com/whitehouse",
	 	  									 "last_updated" => null);	
	
	$elected[] = $this->elected_official_model('executive', 'President', null, 'Barack', 'Obama', 'Barack Obama', 'http://www.whitehouse.gov/administration/president-obama', 'http://www.whitehouse.gov/sites/default/files/imagecache/admin_official_lowres/administration-official/ao_image/President_Official_Portrait_HiRes.jpg', 'http://www.whitehouse.gov/schedule', 'http://www.whitehouse.gov/contact/submit-questions-and-comments', null, '202-456-1111', 'The White House', '1600 Pennsylvania Avenue NW', null, 'Washington', 'DC', '20500', null, null, $social_media);

	$social_media = null;
	$social_media[] = array("type" => "twitter",
	 	  									"description" => "Twitter",
	 	  									"username" => "VP",
	 	  									"url" => "https://twitter.com/VP",
	 	  									 "last_updated" => null);


	$elected[] = $this->elected_official_model('executive', 'Vice-President', null, 'Joseph', 'Biden', 'Joe Biden', 'http://www.whitehouse.gov/administration/vice-president-biden', 'http://www.whitehouse.gov/sites/default/files/imagecache/admin_official_lowres/administration-official/ao_image/vp_portrait_hi-res.jpg', null, 'http://www.whitehouse.gov/contact-vp', null, null, 'The White House', '1600 Pennsylvania Avenue NW', null, 'Washington', 'DC', '20500', null, null, $social_media);



	$social_media = null;
	$social_media[] = array("type" => "twitter",
	 	  									"description" => "Twitter",
	 	  									"username" => "USAgov",
	 	  									"url" => "http://twitter.com/USAgov",
	 	  									 "last_updated" => null);
	
	$new_data['jurisdictions'][] = $this->jurisdiction_model('government', 'Country', 'national', 'Country', 'United States of America', 'US', 'http://usa.gov', 'http://answers.usa.gov/system/selfservice.controller?CONFIGURATION=1000&PARTITION_ID=1&CMD=STARTPAGE&SUBCMD=EMAIL&USERTYPE=1&LANGUAGE=en&COUNTRY=us', null, '800-333-4636', 'USA.gov, U.S. General Services Administration', '1275 First Street, NE', null, 'Washington', 'DC', '20417',	null, null, $social_media, $elected, null);
								//$this->jurisdiction_model($type, $type_name, 		$level, 		$level_name, $name, $id, $url, $url_contact, $email, $phone, $address_name, $address_1, $address_2, $address_city, $address_state, $address_zip, $last_updated, $metadata, $social_media, $elected_office, $service_discovery);	
	# $this->elected_official_model($type, $title, $description, $name_given, $name_family, $name_full, $url, $url_photo, $url_schedule, $url_contact, $email, $phone, $address_name, $address_1, $address_2, $address_city, $address_state, $address_zip, $current_term_enddate, $last_updated, $social_media);	
	# $this->jurisdiction_model($type, $type_name, $level, $level_name, $name, $id, $url, $url_contact, $email, $phone, $address_name, $address_1, $address_2, $address_city, $address_state, $address_zip, $last_updated, $metadata, $social_media, $elected_office, $service_discovery);
	
	
}



	//$new_data['raw_data'] = $data;					
	
	return $new_data;
}
	
	

	
// TODO: Consider doing a dynamic data model instantiation by just naming the object/array key names based on the name of the variable through a foreach loop. 		
	
function jurisdiction_model($type, $type_name, $level, $level_name, $name, $id, $url, $url_contact, $email, $phone, $address_name, $address_1, $address_2, $address_city, $address_state, $address_zip, $last_updated, $metadata, $social_media, $elected_office, $service_discovery) {
	
	
$data['type'] 		  			= $type;		  	
$data['type_name'] 	  			= $type_name; 	    		  
$data['level'] 		  			= $level; 		    		  	
$data['level_name'] 			= $level_name;     		  	  	
$data['name'] 		  			= $name; 		    		  	
$data['id'] 					= $id; 		    		  	  	
$data['url'] 		  			= $url; 		    		  	
$data['url_contact']   			= $url_contact;    		  	
$data['email'] 		  			= $email; 		    		  	
$data['phone'] 		  			= $phone; 		    		  	
$data['address_name']   		= $address_name;   		  	
$data['address_1'] 	  			= $address_1; 	    		  	
$data['address_2'] 	  			= $address_2; 	    		  	
$data['address_city']  			= $address_city;  		  	
$data['address_state'] 			= $address_state;  		  	
$data['address_zip']    		= $address_zip;    		  	
$data['last_updated']   		= $last_updated;   		  	
$data['metadata']				= $metadata;		
$data['social_media']           = $social_media;		
$data['elected_office']         = $elected_office;		
$data['service_discovery']      = $service_discovery;

return $data;

}


function elected_official_model($type, $title, $description, $name_given, $name_family, $name_full, $url, $url_photo, $url_schedule, $url_contact, $email, $phone, $address_name, $address_1, $address_2, $address_city, $address_state, $address_zip, $current_term_enddate, $last_updated, $social_media) {
	
$data['type'] 					=  $type; 				  
$data['title'] 					=  $title; 			   		
$data['description'] 			=  $description; 		   		
$data['name_given'] 			=  $name_given;		   		
$data['name_family'] 			=  $name_family; 		   		
$data['name_full'] 				=  $name_full; 		   		
$data['url'] 					=  $url; 				   		
$data['url_photo'] 				=  $url_photo; 		   		
$data['url_schedule'] 			=  $url_schedule; 		   		
$data['url_contact'] 			=  $url_contact; 		   		
$data['email'] 					=  $email; 			   		
$data['phone'] 					=  $phone; 			   		
$data['address_name']			=  $address_name;		   		
$data['address_1'] 				=  $address_1; 		   		
$data['address_2'] 				=  $address_2; 		   		
$data['address_city'] 			=  $address_city; 		   		
$data['address_state'] 			=  $address_state; 	   		
$data['address_zip'] 			=  $address_zip; 		   		
$data['current_term_enddate']	=  $current_term_enddate;		
$data['last_updated'] 			=  $last_updated; 		   		
$data['social_media'] 			=  $social_media; 		   		

return $data;

}

}

# $this->elected_official_model($type, $title, $description, $name_given, $name_family, $name_full, $url, $url_photo, $url_schedule, $url_contact, $email, $phone, $address_name, $address_1, $address_2, $address_city, $address_state, $address_zip, $current_term_enddate, $last_updated, $social_media);
# $this->jurisdiction_model($type, $type_name, $level, $level_name, $name, $id, $url, $url_contact, $email, $phone, $address_name, $address_1, $address_2, $address_city, $address_state, $address_zip, $last_updated, $metadata, $social_media, $elected_office, $service_discovery);


?>