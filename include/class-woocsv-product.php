<?php	
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// @since 3.0.5 changed $woocsv_import->header to $this->header for future scheduling

class woocsv_import_product
{
	// @since 3.0.2 skip products if flag is set to true during runtime
	public $skip = false;

	public $new = true;
	
	public $header = array();

	public $tags = array();

	public $categories = array();

	public $images = array();

	public $raw_data = array();

	public $shipping_class = '';

	public $featured_image = '';

	public $product_gallery = '';
	
	public $product_type = 'simple';
	
	/* since 3.0.6
		no more use of the global $woocsv_import
	*/
	public $log = array ();

	//body
	public $body = array(
		'ID' => '',
		'post_type'  => 'product',
		'post_status' => 'publish',
		'post_title' => '',
		'post_name'  => '',
		'post_date'  => '',
		'post_date_gmt' => '',
		'post_content' => '',
		'post_excerpt' => '',
		'post_parent' => 0,
		'post_password' => '',
		'comment_status'=> 'open',
		'ping_status'=>'open',
		'menu_order'=> 0,
		'post_author' => '',
	);

	public $meta = array(
		'_sku'   => '',
		'_downloadable'  => 'no',
		'_virtual'   => 'no',
		'_price'   => '',
		'_visibility' => 'visible',
		'_stock'   => '',
		'_stock_status' => 'instock',
		'_backorders' => 'no',
		'_manage_stock' => 'yes',
		'_sale_price' => '',
		'_regular_price' => '',
		'_weight'  => '',
		'_length'  => '',
		'_width'   => '',
		'_height'  => '',
		'_tax_status' => 'taxable',
		'_tax_class'  => '',
		'_upsell_ids' => array(),
		'_crosssell_ids' => array(),
		'_sale_price_dates_from' => '',
		'_sale_price_dates_to'  => '',
		'_min_variation_price' => '',
		'_max_variation_price' => '',
		'_min_variation_regular_price' => '',
		'_max_variation_regular_price' => '',
		'_min_variation_sale_price' => '',
		'_max_variation_sale_price' => '',
		'_featured'  => 'no',
		'_file_path'  => '',
		'_download_limit' => '',
		'_download_expiry' => '',
		'_product_url' => '',
		'_button_text' => '',
//		'total_sales'=>0,
	);

	public function parse_data(){
		global $woocsv_import;
		
		//===================
		// check body data and fill in the log
		//===================

		//add action before parsing all data
		do_action('woocsv_before_parse_data');
		
		//check content, title, name
		if (	empty($this->body['post_title']) && 
				empty($this->body['post_name']) &&
				empty($this->body['post_content']) && 
				$this->body['post_type'] == 'product'
			) {
			
			$this->log[] = __('No title, slug or content. Filled in dummy content','woocsv');
			$this->body['post_content'] = ' ';
		}
		
		//check the post_status
		$post_status = array('publish','pending','draft','auto-draft','future','private','inherit','trash');
		if ( !in_array( $this->body['post_status'], $post_status) ) {
			$this->log[] = sprintf(__('post status changed from %s to publish','woocsv'),$this->body['post_status']);
			$this->body['post_status'] = 'publish';
		}
		
		//check if there is a name or a title, else put status to draft
		//added product type check to make sure only to check for simple products 
		if (empty($this->body['post_title']) && $this->body['post_type'] == 'product'  ) {
			$this->log[] = __('title is empty status changed to draft','woocsv');
			$this->body['post_status'] = 'draft';
		}
		
		//check ping status
		if ( !in_array( $this->body['ping_status'], array('open','closed')) ) {
			$this->log[] = sprintf(__('ping status changed from %s to ping','woocsv'),$this->body['ping_status']);
			$this->body['ping_status'] = 'open';
		}	
	
		//check menu_order
		if ( !is_numeric ( $this->body['menu_order'] )) {
			$this->log[] = sprintf(__('menu order changed from %s to 0','woocsv'),$this->body['menu_order']);
			$this->body['menu_order'] = 0;
		}	

		//==========================
		// check some meta data and fill in the log
		//==========================
		
		//check stock status
		if (in_array('stock_status', $this->header) && !in_array($this->meta['_stock_status'], array('instock', 'outofstock'))) { 
			$this->log[] = sprintf(__('stock status changed from %s to instock','woocsv'),$this->meta['_stock_status']);
			$this->meta['_stock_status'] = 'instock';
		}

		//check visibility
		if (in_array('visibility', $this->header) && !in_array($this->meta['_visibility'], array('visible', 'catalog', 'search', 'hidden'))) { 
			$this->log[] = sprintf(__('visibility changed from %s to visible','woocsv'),$this->meta['_visibility']);
			$this->meta['_visibility'] = 'visible';
		}

		//check backorders
		if (in_array('backorders', $this->header) && !in_array($this->meta['_backorders'], array('yes','no','notify'))) { 
			$this->log[] = sprintf(__('backorders changed from %s to no','woocsv'),$this->meta['_backorders']);
			$this->meta['_backorders'] = 'no';
		}

		//check featured
		if (in_array('featured', $this->header) && !in_array($this->meta['_featured'], array('yes','no'))) { 
			$this->log[] = sprintf(__('featured changed from %s to no','woocsv'),$this->meta['_featured']);
			$this->meta['_featured'] = 'no';
		}

		//check manage_stock
		if (in_array('manage_stock', $this->header) && !in_array($this->meta['_manage_stock'], array('yes','no'))) { 
			$this->log[] = sprintf(__('manage_stock changed from %s to no','woocsv'),$this->meta['_manage_stock']);
			$this->meta['_manage_stock'] = 'no';
		}
		
		//handle prices		
		if ($woocsv_import->get_merge_products() == 1) {	
			$regular_price = (in_array('regular_price', $this->header) && strlen($this->meta['_regular_price'] ) >0 ) ?  $this->meta['_regular_price']:$this->meta['_regular_price'];
			$sale_price = (in_array('sale_price', $this->header) && strlen($this->meta['_sale_price'] )>0) ? $this->meta['_sale_price']:$this->meta['_sale_price'];
			$price = (in_array('price', $this->header) && strlen($this->meta['_price'] )>0) ? $this->meta['_price']:$this->meta['_price'];
		} else {
			$regular_price = (in_array('regular_price', $this->header) && strlen($this->meta['_regular_price'] )>0) ?  $this->meta['_regular_price'] : '';
			$sale_price = (in_array('sale_price', $this->header) && strlen($this->meta['_sale_price'] )>0) ? $this->meta['_sale_price'] : '' ;
			$price = (in_array('price', $this->header) && strlen($this->meta['_price'] )>0) ? $this->meta['_price'] : '' ;
		}
			
		//product on sale
		if ($sale_price >0 && $sale_price < $regular_price) {
			$this->log[] = __('Product is on sale','woocsv');
			$price = $sale_price;
		} else {
		//the product is not on sale
			$price = $regular_price;
			$sale_price = '';
		}		
		
		//set prices
		$this->meta['_regular_price'] = $regular_price;
		$this->meta['_sale_price'] = $sale_price;
		$this->meta['_price'] = $price;
		
		//add action after parsing all data		
		do_action('woocsv_after_parse_data');
	}

	public function merge_product($id)
	{
		//get post data and store it
		$post = get_post( $id, 'ARRAY_A' );
		$this->body = $post;

		//get meta data and store it
		$post_meta = get_metadata('post', $id, '', true );
		foreach ($post_meta as $key=>$value) {
			$this->meta[$key] = maybe_unserialize($value[0]);
		}
		
		//get product_tpe
		$product_types = wp_get_object_terms( $this->body['ID'], 'product_type' );
		
		if ( !is_wp_error($product_types) ){
			foreach ($product_types as $product_type) {
				$this->product_type = $product_type->name;
			}
		}
			
	}

	public function get_product_by_id($sku)
	{
		global $wpdb;
		$product_id = $wpdb->get_var($wpdb->prepare("SELECT max(post_id) FROM $wpdb->postmeta a, $wpdb->posts b
				WHERE a.post_id= b.id and meta_key='_sku' AND meta_value='%s' LIMIT 1", $sku ));

		if ($product_id) $product['ID'] = $product_id; else $product_id = false;
		
		return $product_id = apply_filters('woocsv_get_product_id',$product_id,$sku);
	}

	public function save()
	{
		global $woocsv_import;
		
		// @since 3.0.2 if skip is true, skip the product during import
		if ($this->skip) {
			return false;
		}
		
		//save the post
		$post_id = wp_insert_post($this->body, true);
			
		if (is_wp_error($post_id)) {
			$this->log[] = __('Product could not be saved and skipped','woocsv');
			return false;
		} else {
			$this->log[] = sprintf(__('Product saved with ID: %s','woocsv'),$post_id);
			$this->body['ID'] = $post_id;
		}
		
		do_action( 'woocsv_product_after_body_save');
		
		//save the product type
		wp_set_object_terms( $post_id, $this->product_type , 'product_type', false );

		do_action( 'woocsv_product_before_meta_save');

		//save the meta
		foreach ($this->meta as $key=>$value) {
			update_post_meta($post_id, $key, $value);
		}

		do_action( 'woocsv_product_before_tags_save');

		//save tags
		if ($this->tags) {
			$this->save_tags($post_id);
		}

		do_action( 'woocsv_product_before_categorie_save');

		//save categories
		if (!empty($this->categories)) {
			$this->save_categories($post_id);
		}

		do_action( 'woocsv_product_before_images_save');
		
		// added empty() else it overrrides the above function)	
		if (!empty($this->featured_image)) {
			$this->save_featured_image();
		}
			
		//save the product gallery
		if (!empty($this->product_gallery)) {
			$this->save_product_gallery();
		}
			
		do_action( 'woocsv_product_before_shipping_save');

		// save shipping class
		if ($this->shipping_class) {
			$this->save_shipping_class();
		}

		do_action( 'woocsv_after_save', $this);
		
		//clear transients
		if ( function_exists('wc_delete_product_transients') ) {
			wc_delete_product_transients ($post_id);	
		}

		do_action ( 'woocsv_product_after_save' );

		//and return the ID	
		return $post_id;
	}

	public function save_tags($post_id)
	{
		global $woocsv_import;
		//2.1.1 If merging do not delete else clear currrent tag
		if (!$woocsv_import->get_merge_products())
			wp_set_object_terms( $this->body['ID'], null, 'product_tag' );
		
		//handle tags
		foreach ($this->tags as $tags) {
			$tags = explode('|', $tags);
			wp_set_object_terms( $post_id, $tags, 'product_tag', true );
		}
	}

	public function save_shipping_class()
	{
		global $woocsv_import;
		
		//2.2.2 If merging do not delete else clear currrent tag
		if ( ! $woocsv_import->get_merge_products() ) {
			wp_set_object_terms( $this->body['ID'], null, 'product_shipping_class' );
		}
		
		$term = term_exists($this->shipping_class, 'product_shipping_class');
		
		// @since  2.2.2 beter handling for shipping class
		if ( ! is_array( $term ) ) {
			$term = wp_insert_term( $this->shipping_class, 'product_shipping_class');
		}

		if ( ! is_wp_error( $term ) ) {
			wp_set_object_terms( $this->body['ID'] , array ( (int)$term['term_id'] ) , 'product_shipping_class' );			
		}
	}

	public function save_categories()
	{
		global $woocsv_import;

		//check out http://wordpress.stackexchange.com/questions/24498/wp-insert-term-parent-child-problem
		delete_option("product_cat_children");

		//clear currrent
		//2.1.1 If merging do not delete else clear currrent category
		if (!$woocsv_import->get_merge_products())
			wp_set_object_terms( $this->body['ID'], null, 'product_cat' );

		foreach ($this->categories as $category) {
			$cats = explode( '|', $category );
			foreach ($cats as $cat) {
				$cat_taxs = explode( '->', $cat );
				
				$parent = 0;
				
				foreach ( $cat_taxs as $cat_tax) {
					
					$new_cat = term_exists( $cat_tax, 'product_cat', $parent );
					if ( ! is_array( $new_cat ) ) {
						$new_cat = wp_insert_term( $cat_tax, 'product_cat', array( 'slug' => $cat_tax, 'parent'=> $parent) );
					}
					if (!is_wp_error($new_cat)) {
						$parent = $new_cat['term_id'];
					}

					if (!is_wp_error($new_cat) && $woocsv_import->get_add_to_categories() == 1)
						wp_set_object_terms( $this->body['ID'], (int)$new_cat['term_id'], 'product_cat', true );
				}

				if (!is_wp_error($new_cat) && $woocsv_import->get_add_to_categories() == 0)
					wp_set_object_terms( $this->body['ID'], (int)$new_cat['term_id'], 'product_cat', true );
			}
		}
	}
	
	public function save_featured_image() {
		global $woocsv_import;
		
		$imageID = false;
		if ($this->is_valid_url($this->featured_image)) {
			$this->log[] = __('featured image is imported using the URL','woocsv');
			$imageID = $this->save_image_with_url($this->featured_image);
		} else {
			$this->log[] = __('featured image is imported using the filename','woocsv');			
			$imageID = $this->save_image_with_name($this->featured_image);
		}
		
		if ($imageID)
			set_post_thumbnail( $this->body['ID'], $imageID );	
	}

	public function save_product_gallery()
	{	
		$images = explode('|', $this->product_gallery);
		$gallery = false;
		foreach ($images as $image) {
			if ($this->is_valid_url($image)) {
				$imageID = $this->save_image_with_url($image);
			} else {
				$imageID = $this->save_image_with_name($image);
			}
			
			if ($imageID)
				$gallery[] = $imageID;
		}

		if ($gallery) {
			$meta_value = implode(',', $gallery);
			update_post_meta($this->body['ID'], '_product_image_gallery', $meta_value);
		}
		
	}
	
	//@since 3.0.5 use WP functions to upload and handle images with url's
	function save_image_with_url($url) {
		global $woocsv_import;
		
		$tmp = download_url( $url , 10 );
		$post_id = $this->body['ID'];
		$desc = "";
		$file_array = array();
		$id = false;
	
		// Set variables for storage
		// fix file filename for query strings
		@preg_match('/[^\?]+\.(jpg|jpe|jpeg|gif|png)/i', $url, $matches);
		if (!$matches) {
			$this->log[] = sprintf(__('Image with url: %s could not be uploaded', 'woocsv'),$url);
			return $id;			
		}
		
		$file_array['name'] = basename($matches[0]);
		$file_array['tmp_name'] = $tmp;
		$desc = $file_array['name'];
		
		// If error storing temporarily, unlink
		if ( is_wp_error( $tmp ) ) {
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
			return $id;
		}
	
		// do the validation and storage stuff
		$id = media_handle_sideload( $file_array, $post_id, $desc );
	
		// If error storing permanently, unlink
		if ( is_wp_error($id) ) {
			@unlink($file_array['tmp_name']);
			return $id;
		}
		
		$this->log[] = sprintf(__('Image with url: %s uploaded', 'woocsv'),$url);
		return $id;
	}

	public function save_image_with_name($image)
	{
		global $wpdb;

		/* use  get_posts to retreive image instead of query direct!*/
		
		//set up the args
		$args = array(
            'numberposts'	=> 1,
            'orderby'		=> 'post_date',
			'order'			=> 'DESC',
            'post_type'		=> 'attachment',
            'post_mime_type'=> 'image',
            'post_status' =>'any',
		    'meta_query' => array(
		        array(
		            'key' => '_wp_attached_file',
		            'value' => sanitize_file_name($image),
		            'compare' => 'LIKE'
		        )
		    )
		);
		//get the images
        $images = get_posts($args);

        if (!empty($images)) {
        //we found a match, return it!
	        return (int)$images[0]->ID;
        } else {
        //no image found with the same name, return false
	        return false;
        }
		
	}

	public function fill_in_data()
	{
		global $woocsv_import;		
		do_action( 'woocsv_product_before_fill_in_data');
		
		$id = false;
		
		//check if the product already exists by checking it's ID		
		if (in_array('ID', $this->header) )  
		{
			$tempID = $this->raw_data[array_search('ID', $this->header)];
			if ($tempID) {			
				
				//use get_post instead of get_posts
				$test = new WC_Product($tempID);
				
			 	if ($test->post) {
				 	$this->log[] = sprintf(__('Product found (ID), ID is: %s','woocsv'), $tempID );
					$this->new = false;
					// @ since 3.0.5 add ID else merging will not work using ID's
				 	$id = $tempID;
			 	} else {	 	
					/* set the ID to null */
				 	$this->raw_data[array_search('ID', $this->header)] = '';
				 	$this->body['ID'] = '';
				 	$this->log[] = sprinf(__('ID : %s not found!','woocsv'),$tempID);
			 	}
		 	}
		 	
		}
		//check if the product already exists by checking it's sku
		if (empty($id) && in_array('sku', $this->header) && $woocsv_import->get_match_by() == 'sku' )  
		{
			$sku = $this->raw_data[array_search('sku', $this->header)];
			
			if (!empty($sku)) {
				$id = $this->get_product_by_id($sku);
				if ( !empty( $id ) ) {
					$this->new = false;
					$this->log[] = sprintf(__('Product found (SKU), ID is: %s','woocsv'), $id);
				} else {
					$this->log[] = __('New product','woocsv');
				}
			}
		}
		
		//check if the product already exists by checking it's post title		
		if (empty($id) && in_array('post_title', $this->header) && $woocsv_import->get_match_by() == 'title' )  
		{
			$post_title = $this->raw_data[array_search('post_title', $this->header)];
			
			if ($post_title) {			
			 	$testID = get_page_by_title( $post_title,ARRAY_A , 'product' );
			 	if ($testID) {
				 	$this->log[] = sprintf(__('Product found (TITLE), ID is: %s','woocsv'),$testID['ID']);;
				 	$id = $testID['ID'];
					$this->new = false;
			 	} else {
				 	$this->log[] = sprintf(__('ID : %s not found!','woocsv'),$testID['ID']);
			 	}
		 	}
		}
				
		//check for if we need to merge the product

		if ($id && $woocsv_import->get_merge_products() == 1) {
			$this->merge_product($id);
		}
		
		//fill in the product body
		foreach ($this->body as $key=>$value) {
			if (in_array($key, $this->header)) {
				$this->body[$key] = $this->raw_data[array_search($key, $this->header)];
			}
		}
		
		// get the author
		if (isset($this->body['post_author'])) {
			
			$user = get_user_by( ($woocsv_import->get_match_author_by())?$woocsv_import->get_match_author_by():'login', $this->body['post_author'] );
			if ($user) {
				$this->body['post_author'] = $user->ID;
				$this->log[] = __('user found','woocsv');			
			} else {
				$this->body['post_author'] = '';
				$this->log[] = __('user not found','woocsv');
			} 
		}
		
		//fill in the ID if the product already exists
		if ($id) {
			$this->body['ID'] = $id;
		}
		
		//fill in the meta data
		// @ since 3.0.5 
		// trim meta values to loose spaces
		foreach ($this->meta as $key=>$value) {
			if (in_array(substr($key, 1), $this->header)) {
				$this->meta[$key] = trim ($this->raw_data[array_search(substr($key, 1), $this->header)]) ;
			}
		}

		// @ since 3.0.5
		// if the product is new add total_sales to show it in the front end
		// some themes needed total_sales for popularity sorting
		if ( !empty ( $this->body['ID'] ) ) {
			$this->meta['total_sales'] = 0;
		}
		
		//check if there are tags
		if (in_array('tags', $this->header)) {
			foreach ($this->header as $key=>$value) {
				if ($value == 'tags')
					$this->tags[] = $this->raw_data[$key];
			}
		}

		//check if there is a shipping
		if (in_array('shipping_class', $this->header)) {
			$key = array_search('shipping_class', $this->header);
			$this->shipping_class = trim($this->raw_data[$key]);
		}

		//check if there are categories
		if (in_array('category', $this->header)) {
			foreach ($this->header as $key=>$value) {
				if ($value == 'category')
					$this->categories[] = $this->raw_data[$key];
			}
		} 
		
		/* change_stock */
		if (in_array('change_stock', $this->header)) {
			$key = array_search('change_stock', $this->header);
			$change_stock = $this->raw_data[$key];
			
			//get the stock
			$stock = get_post_meta($this->body['ID'],'_stock', true);
			
			//if the stock is empty set it to 0
			if (!$stock) $stock = 0;
			
			//calculate the new stock level
			$new_stock = $stock + $change_stock;

			//set new stock in the meta
			$this->meta['_stock'] = $new_stock;

			//set log
			$this->log[] = sprintf(__('Change stock modus: stock changed from %s to %s','woocsv'),$stock, $new_stock);
		}
		
		//check if there is a featured image
		if (in_array('featured_image', $this->header)) {
			$key = array_search('featured_image', $this->header);
			$this->featured_image = $this->raw_data[$key];
		}
		
		//check if there is a product gallery
		if (in_array('product_gallery', $this->header)) {
			$key = array_search('product_gallery', $this->header);
			$this->product_gallery = $this->raw_data[$key];
		}

		do_action( 'woocsv_product_after_fill_in_data');

	}

	// helpers
	public function is_valid_url($url)
	{
		// alternative way to check for a valid url
		if  (filter_var($url, FILTER_VALIDATE_URL) === FALSE) return false; else return true;

	}

}
