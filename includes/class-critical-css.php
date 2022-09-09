<?php
/**
 * Critical CSS functionality 
 * @since 1.0
 * 
 **/

class class_critical_css_for_wp{


	public function cachepath(){
		if(defined(CRITICAL_CSS_FOR_WP_CSS_DIR)){
			return CRITICAL_CSS_FOR_WP_CSS_DIR;
		}else{
			return WP_CONTENT_DIR . "/cache/critical-css-for-wp/css/";
		}
	}

	public function critical_hooks(){
		if ( function_exists('is_checkout') && is_checkout()  || (function_exists('is_feed')&& is_feed())) {
        	return;
	    }
	    if ( function_exists('elementor_load_plugin_textdomain') && \Elementor\Plugin::$instance->preview->is_preview_mode() ) {
	    	return;
		}
		add_action( 'create_term', function($term_id, $tt_id, $taxonomy){
            $this->on_term_create($term_id, $tt_id, $taxonomy);
        }, 10, 3 );

        add_action( 'save_post', function($post_ID, $post, $update){
            $this->on_post_change($post_ID, $post);
        }, 10, 3 );
        add_action( 'wp_insert_post', function($post_ID, $post, $update){
            $this->on_post_change($post_ID, $post);
        }, 10, 3 );
		add_action('wp_head', array($this, 'print_style_cc'),2);

		add_action("wp_ajax_ccfwp_showdetails_data", array($this, 'ccfwp_showdetails_data'));

		add_action("wp_ajax_ccfwp_showdetails_data_completed", array($this, 'ccfwp_showdetails_data_completed'));
		add_action("wp_ajax_ccfwp_showdetails_data_failed", array($this, 'ccfwp_showdetails_data_failed'));
		add_action("wp_ajax_ccfwp_showdetails_data_queue", array($this, 'ccfwp_showdetails_data_queue'));

		add_action("wp_ajax_ccfwp_resend_urls_for_cache", array($this, 'ccfwp_resend_urls_for_cache'));
		add_action("wp_ajax_ccfwp_resend_single_url_for_cache", array($this, 'ccfwp_resend_single_url_for_cache'));

		add_action("wp_ajax_ccfwp_reset_urls_cache", array($this, 'ccfwp_reset_urls_cache'));
		add_action("wp_ajax_ccfwp_recheck_urls_cache", array($this, 'ccfwp_recheck_urls_cache'));

		add_action("wp_ajax_ccfwp_cc_all_cron", array($this, 'every_one_minutes_event_func_crtlcss'));
		
		add_filter( 'cron_schedules', array($this, 'isa_add_every_one_hour_crtlcss') );
		 if ( ! wp_next_scheduled( 'isa_add_every_one_hour_crtlcss' ) ) {
		     wp_schedule_event( time(), 'every_one_hour',  'isa_add_every_one_hour_crtlcss' );
		 }
		add_action( 'isa_add_every_one_hour_crtlcss', array($this, 'every_one_minutes_event_func_crtlcss' ) );					
		
	}


	public function on_term_create($term_id, $tt_id, $taxonomy){

		$settings = critical_css_defaults();
		$post_types = array();
		if(!empty($settings['ccfwp_on_tax_type'])){
			foreach ($settings['ccfwp_on_tax_type'] as $key => $value) {
				if($value){
					$post_types[] = $key;					
				}
			}
		}

		if(in_array($taxonomy, $post_types)){
			$term = get_term( $term_id);	
			if($term){
				$this->insert_update_terms_url($term);					
			}
		}
			
		update_option('save_ccfwp_terms_offset', 0);	
	}

	public function on_post_change($post_id, $post){

		$settings = critical_css_defaults();
		$post_types = array('post');
		if(!empty($settings['ccfwp_on_cp_type'])){
			foreach ($settings['ccfwp_on_cp_type'] as $key => $value) {
				if($value){
					$post_types[] = $key;					
				}
			}
		}

		if(in_array($post->post_type, $post_types)){
			$permalink = get_permalink($post_id);
			$permalink = $this->append_slash_permalink($permalink);
			if($post->post_status == 'publish'){
				$this->insert_update_posts_url($post_id);
			}
		}

		update_option('save_ccfwp_posts_offset', 0);				

	}

	public function insert_update_posts_url($post_id){

		global $wpdb, $table_prefix;
			   $table_name = $table_prefix . 'critical_css_for_wp_urls';			   

		$permalink = get_permalink($post_id);
		if(!empty($permalink)){
		
		$permalink = $this->append_slash_permalink($permalink);
		
		$pid = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `url` FROM $table_name WHERE `url`=%s limit 1", 
				$permalink
			)		
		);

		if(is_null($pid)){
			$wpdb->insert( 
				$table_name, 
				array(
					'url_id'          => $post_id,  
					'type'        	  => get_post_type($post_id),  
					'type_name'       => get_post_type($post_id), 
					'url'  			  => $permalink, 					
					'status'   		  => 'queue', 					
					'created_at'      => date('Y-m-d h:i:sa'), 					
				), 
				array('%d','%s', '%s', '%s', '%s', '%s') 
			);

		} 
		
		}				  

	}

	function isa_add_every_one_hour_crtlcss( $schedules ) {
	    $schedules['every_one_hour'] = array(
	            'interval'  => 30 * 1,
	            'display'   => __( 'Every 30 Seconds', 'criticalcssforwp' )
	    );
	    return $schedules;
	}

	public function insert_update_terms_url($term){
        if(!is_object($term)){
			return; 
		}
		global  $wpdb, $table_prefix;
			    $table_name = $table_prefix . 'critical_css_for_wp_urls';			   			   
				$permalink = get_term_link($term);
			
			if(!empty($permalink)){
				
			$permalink = $this->append_slash_permalink($permalink);

			$pid = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT `url` FROM $table_name WHERE `url`=%s limit 1", 
					$permalink
				)		
			);

			if(is_null($pid)){
				$wpdb->insert( 
					$table_name, 
					array(
						'url_id'          => $term->term_id,  
						'type'        	  => $term->taxonomy,  
						'type_name'       => $term->taxonomy, 
						'url'  			  => $permalink, 					
						'status'   		  => 'queue', 					
						'created_at'      => date('Y-m-d h:i:sa')					
					), 
					array('%d','%s', '%s', '%s', '%s', '%s') 
				);

			} 			

			}			   

	}

	public function save_posts_url(){

			global $wpdb, $table_prefix;
			$table_name = $table_prefix . 'critical_css_for_wp_urls';

			$settings = critical_css_defaults();

			$post_types = array('post');
			
			if(!empty($settings['ccfwp_on_cp_type'])){
				foreach ($settings['ccfwp_on_cp_type'] as $key => $value) {
					if($value){
						$post_types[] = $key;					
					}
				}
			}
			
								
			$start = get_option('save_ccfwp_posts_offset') ? get_option('save_ccfwp_posts_offset') : 0 ;
			$batch = 30;
			$offset = $start * $batch;
			$posts = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT `ID` FROM $wpdb->posts WHERE post_status='publish' 
					AND post_type IN(%s) LIMIT %d, %d",
					implode("', '", $post_types) , $offset, $batch
				))
				, ARRAY_A);
									        
			if(!empty($posts)){
				$start = $start + 1;					
	            foreach($posts as $post){					
	                $this->insert_update_posts_url($post['ID']);									
	            }
	        }else{
				$start = 0;				
			}
			update_option('save_ccfwp_posts_offset', $start);	

	}

	public function save_others_urls(){
		
		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';

		$settings = critical_css_defaults();
		$urls_to  = array();
		if(isset($settings['ccfwp_on_home']) && $settings['ccfwp_on_home'] == 1){
			$urls_to[] = get_home_url(); //always purge home page if any other page is modified
			$urls_to[] = get_home_url()."/"; //always purge home page if any other page is modified
			$urls_to[] = home_url('/'); //always purge home page if any other page is modified
			$urls_to[] = site_url('/'); //always purge home page if any other page is modified
		}
		
		if(!empty($urls_to)){
			
			foreach ($urls_to as $key => $value) {
			
				$pid = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT `url` FROM $table_name WHERE `url`=%s limit 1", 
						$value
					)		
				);
				$id = ($key++) + 999999999;
				if(is_null($pid)){
					
					$wpdb->insert( 
						$table_name, 
						array(
							'url_id'          => $id,  
							'type'        	  => 'others',  
							'type_name'       => 'others', 
							'url'  			  => $value, 					
							'status'   		  => 'queue', 					
							'created_at'      => date('Y-m-d'), 					
						), 
						array('%d','%s', '%s', '%s', '%s', '%s') 
					);

				} else{
					$wpdb->query($wpdb->prepare(
						"UPDATE $table_name SET `url` = %s WHERE `url_id` = %d",
						$value,
						$id							
					));
					
				}
				
			}
		}


	}

	public function save_terms_urls(){

		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';

		$settings = critical_css_defaults();

		$taxonomy_types = array('category');
		
		if(!empty($settings['ccfwp_on_tax_type'])){
			foreach ($settings['ccfwp_on_tax_type'] as $key => $value) {
				if($value){
					$taxonomy_types[] = $key;					
				}
			}
		}
					
			
			$start = get_option('save_ccfwp_terms_offset') ? get_option('save_ccfwp_terms_offset') : 0 ;
			$batch = 30;
			$offset = $start * $batch;
			$terms = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT `term_id`, `taxonomy` FROM $wpdb->term_taxonomy 
					WHERE taxonomy IN(%s) LIMIT %d, %d",
					implode("', '", $taxonomy_types) , $offset, $batch
				))
				, ARRAY_A);
									        			
			if(!empty($terms)){
				$start = $start + 1;				
	            foreach($terms as $term){										
	                $term = get_term( $term['term_id']);					
					if(!is_wp_error($term)){
						$this->insert_update_terms_url($term);					
					}						                
	            }
	        }else{
				$start = 0;				
			}
			update_option('save_ccfwp_terms_offset', $start);	

	}

	public function ccfwp_save_critical_css_in_dir_php($current_url){
		
		$targetUrl = $current_url;		
	    $user_dirname = $this->cachepath();
		$content = file_get_contents($targetUrl);
		
		$regex = '/<link(.*?)href=["|\'](.*?)["|\'] (.*?)>/';
		preg_match_all( $regex, $content, $matches , PREG_SET_ORDER );
				
		$rowcss = '';
		$all_css = [];
		
		if($matches){        
			
			foreach($matches as $mat){						
				if(strpos($mat[2], '.css') !== false) {
					$all_css[]  = $mat[2];					
					$rowcssdata = @file_get_contents($mat[2]);             
					$regexn = '/@import\s*(url)?\s*\(?([^;]+?)\)?;/';

					preg_match_all( $regexn, $rowcssdata, $matchen , PREG_SET_ORDER );
					
					if(!empty($matchen)){
						foreach($matchen as $matn){
							if(isset($matn[2])){								
								$explod = explode('/',$matn[2]);
								if(is_array($explod)){
									$style = trim(end($explod),'"');
									if(strpos($style, '.css') !== false) {
										$pthemestyle = get_template_directory_uri().'/'.$style;
										$rowcss     .= @file_get_contents($pthemestyle);
									}																		
								}								
							}
						}
					}

					$rowcss .= $rowcssdata;
				}				
				
			}
		}
		
		$d = new DOMDocument;
		$mock = new DOMDocument;
		libxml_use_internal_errors(true);
		$d->loadHTML($content);
		$body = $d->getElementsByTagName('body')->item(0);
		foreach ($body->childNodes as $child){
			$mock->appendChild($mock->importNode($child, true));
		}
		
		$rawHtml =  $mock->saveHTML();	

		require_once CRITICAL_CSS_FOR_WP_PLUGIN_DIR."css-extractor/vendor/autoload.php";
				
		$extracted_css_arr = array();

		$page_specific = new \PageSpecificCss\PageSpecificCss();
		$page_specific->addBaseRules($rowcss);
		$page_specific->addHtmlToStore($rawHtml);
		$extractedCss = $page_specific->buildExtractedRuleSet();							
		$extracted_css_arr[] = $extractedCss;		
		
		preg_match_all( "/@media [^{]*+{([^{}]++|{[^{}]*+})*+}/", $rowcss, $matchess , PREG_SET_ORDER );

		if($matchess){
		
			foreach ($matchess as $key => $value) {
												
				if(isset($value[0])){
					$explod = explode("{", $value[0]);
					if($explod[0]){
						$value[0] = str_replace($explod[0]."{", "",  $value[0]);
						$value[0] = str_replace($explod[0]." {", "",  $value[0]);
						$value[0] = str_replace($explod[0]."  {", "",  $value[0]);					
						$value[0] = substr($value[0], 0, -1);	
	
						if($value[0]){		
							$page_specific = new \PageSpecificCss\PageSpecificCss();											
							$page_specific->addBaseRules($value[0]);
							$page_specific->addHtmlToStore($rawHtml);
							$extractedCss = $page_specific->buildExtractedRuleSet();												
							if($extractedCss){
								$extractedCss   = $explod[0]."{".$extractedCss."}";
								$extracted_css_arr[] = $extractedCss;
							}						
						}
						
					}	
				}
				
			}
		}
				
		if(!empty($extracted_css_arr) && is_array($extracted_css_arr)){

				$critical_css = implode("", $extracted_css_arr);
			    
				$critical_css = str_replace("url('wp-content/", "url('".get_site_url()."/wp-content/", $critical_css); 
				$critical_css = str_replace('url("wp-content/', 'url("'.get_site_url().'/wp-content/', $critical_css); 
							
				$new_file = $user_dirname."/".md5($targetUrl).".css";
				$ifp = @fopen( $new_file, 'w+' );
				if ( ! $ifp ) {
					return array('status' => false, 'message' => sprintf( __( 'Could not write file %s' ), $new_file ));					
				}
				$result = @fwrite( $ifp, $critical_css );
				fclose( $ifp );
				if($result){
					return array('status' => true, 'message' => 'Css creted sussfully');
				}else{
					return array('status' => false, 'message' => 'Could not write into css file');
				}

		}else{
			return array('status' => false , 'message' => 'critical css does not generated from server');	
		}
	    	    

	}
	public function generate_css_on_interval(){
		
		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';
		
		$result = $wpdb->get_results(
			stripslashes($wpdb->prepare(
				"SELECT * FROM $table_name WHERE `status` IN  (%s) LIMIT %d",
				'queue', 4
			))
		, ARRAY_A);
				
		if(!empty($result)){
			
			$user_dirname = $this->cachepath();
			if(!is_dir($user_dirname)) {
				wp_mkdir_p($user_dirname);
			}
			
			if(is_dir($user_dirname)){				
					
					foreach ($result as $value) {

						if($value['url']){
							$status       = 'inprocess';
							$cached_name  = '';
							$failed_error = '';
							$this->change_caching_status($value['url'], $status);							
							$result = $this->ccfwp_save_critical_css_in_dir_php($value['url']);											
							if($result['status']){
								$status      = 'cached';							
								$cached_name = md5($value['url']);
							}else{
								$status       = 'failed';
								$failed_error = $result['message'];
							}
			
							$this->change_caching_status($value['url'],$status, $cached_name, $failed_error);
						}						
		
					}							
			} 
						
		}

	}

	public function change_caching_status($url, $status, $cached_name=null, $failed_error = null){

		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';

		$result = $wpdb->query($wpdb->prepare(
			"UPDATE $table_name SET `status` = %s,  `cached_name` = %s,  `updated_at` = %s,  `failed_error` = %s WHERE `url` = %s",
			$status,
			$cached_name,
			date('Y-m-d h:i:sa'),
			$failed_error,
			$url								
		));

	}

	public function every_one_minutes_event_func_crtlcss() {
		
		$this->save_posts_url();
		$this->save_terms_urls();
		$this->save_others_urls();
		$this->generate_css_on_interval();

	}

	public function append_slash_permalink($permalink){

		$permalink_structure = get_option( 'permalink_structure' );
		$append_slash = substr($permalink_structure, -1) == "/" ? true : false;
		if($append_slash){
			$permalink = trailingslashit($permalink);
		}else{
			$permalink = $permalink.$append_slash;
		}

		return $permalink;
	}

	function print_style_cc(){
		
		$user_dirname = $this->cachepath();		
		$settings = critical_css_defaults();		
		global $wp, $wpdb, $table_prefix;
			   $table_name = $table_prefix . 'critical_css_for_wp_urls';	
		$url = home_url( $wp->request );
		$url = trailingslashit($url);		
		
		if(file_exists($user_dirname.md5($url).'.css')){			
			$css =  file_get_contents($user_dirname.'/'.md5($url).'.css');			
		 	echo "<style type='text/css' id='critical-css-for-wp'>$css</style>";
		}else{
			$wpdb->query($wpdb->prepare(
				"UPDATE $table_name SET `status` = %s,  `cached_name` = %s WHERE `url` = %s",
				'queue',
				'',
				$url							
			));			
		}
	}

	
	public function ccfwp_resend_single_url_for_cache(){

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_POST['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}

		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';

		$url_id = $_POST['url_id'] ? intval($_POST['url_id']) : null;
		
		if($url_id){
			
			$result = $wpdb->query($wpdb->prepare(
				"UPDATE $table_name SET `status` = %s, `cached_name` = %s, `failed_error` = %s WHERE `id` = %d",
				'queue',
				'',
				'',			
				$url_id
			));
						
			if($result){
				echo json_encode(array('status' => true));
			}else{
				echo json_encode(array('status' => false));
			}

		}else{
			echo json_encode(array('status' => false));	
		}			    
		
		die;
	}

	public function ccfwp_recheck_urls_cache(){

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_POST['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}
		
		$limit = 100;
		$page  = $_POST['page'] ? intval($_POST['page']) : 0;
		$offset = $page * $limit;
		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';

		$result = $wpdb->get_results(
			stripslashes($wpdb->prepare(
				"SELECT * FROM $table_name WHERE `status` = %s LIMIT %d, %d",
				'cached', $offset, $limit
			))
		, ARRAY_A);
		
		if($result && count($result) > 0){
			$user_dirname = $this->cachepath();		
			foreach($result as $value){
					
				if(!file_exists($user_dirname.$value['cached_name'].'.css') ){
				$updated = $wpdb->query($wpdb->prepare(
						"UPDATE $table_name SET `status` = %s,  `cached_name` = %s WHERE `url` = %s",
						'queue',
						'',
						$value['url']							
					));						
				}
			}

			echo json_encode(array('status' => true, 'count' => count($result)));die;
		}else{
			echo json_encode(array('status' => true, 'count' => 0));die;
		}
						
	}	

	public function ccfwp_resend_urls_for_cache(){

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_POST['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}

		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';

		$result = $wpdb->query($wpdb->prepare(
			"UPDATE $table_name SET `status` = %s, `cached_name` = %s, `failed_error` = %s WHERE `status` = %s",
			'queue',
			'',
			'',			
			'failed'	
		));
	    if($result){
			echo json_encode(array('status' => true));
		}else{
			echo json_encode(array('status' => false));
		}
		
		die;
	}

	public function ccfwp_reset_urls_cache(){

		if ( ! isset( $_POST['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_POST['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}

		global $wpdb;	
		$table = $wpdb->prefix.'critical_css_for_wp_urls';
	    $result = $wpdb->query( "TRUNCATE TABLE {$table}" );

		$dir = $this->cachepath();				
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->rmdir($dir, true);

		echo json_encode(array('status' => true));die;
		
	}

	public function ccfwp_showdetails_data(){
		

		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_GET['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}

		$page   = isset($_GET['start']) && $_GET['start']> 0 ? $_GET['start']/$_GET['length'] : 1;
		$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
		$page   = ($page + 1);
		$offset = isset($_GET['start']) ? intval($_GET['start']) : 0;
		$draw = intval($_GET['draw']);						
		
		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';
																
		if($_GET['search']['value']){
			$search = sanitize_text_field($_GET['search']['value']);
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE `url` LIKE %s ",
			'%' . $wpdb->esc_like($search) . '%'
			),			
			);
			
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name WHERE `url` LIKE %s LIMIT %d, %d",
					'%' . $wpdb->esc_like($search) . '%', $offset, $length
				))
			, ARRAY_A);
		}else
		{
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name"));
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name LIMIT %d, %d", $offset, $length
				))
			, ARRAY_A);
		}
		
		$formated_result = array();

		if(!empty($result)){

			foreach ($result as $value) {
				
				if($value['status'] == 'cached'){
					$user_dirname = $this->cachepath();
					$size = filesize($user_dirname.'/'.md5($value['url']).'.css');					
					if(!$size){
						$size = '<abbr title="File is not in cached directory. Please recheck in advance option">Deleted</abbr>';
					}
				}
					
				$formated_result[] = array(
									'<div><abbr title="'.$value['cached_name'].'">'.$value['url'].'</abbr>'.($value['status'] == 'failed' ? '<a href="#" data-section="all" data-id="'.$value['id'].'" class="cwvpb-resend-single-url dashicons dashicons-controls-repeat"></a>' : '').' </div>',								   
									'<span class="cwvpb-status-t">'.$value['status'].'</span>',
									$size,
									$value['updated_at']
							);
			}				

		}	
				
		$retuernData = array(	
		    "draw"            => $draw,
			"recordsTotal"    => $total_count,
			"recordsFiltered" => $total_count,
			"data"            => $formated_result
		);

		echo json_encode($retuernData);die;

	}

	public function ccfwp_showdetails_data_completed(){
		
		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_GET['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}

		$page   = isset($_GET['start']) && $_GET['start']> 0 ? $_GET['start']/$_GET['length'] : 1;
		$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
		$page   = ($page + 1);
		$offset = isset($_GET['start']) ? intval($_GET['start']) : 0;
		$draw = intval($_GET['draw']);						
		
		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';
																
		if($_GET['search']['value']){
			$search = sanitize_text_field($_GET['search']['value']);
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE `url` LIKE %s AND `status`='cached'",
			'%' . $wpdb->esc_like($search) . '%'
			),			
			);
			
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name WHERE `url` LIKE %s AND `status`='cached' LIMIT %d, %d",
					'%' . $wpdb->esc_like($search) . '%', $offset, $length
				))
			, ARRAY_A);
		}else
		{
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name Where `status`='cached'"));
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name Where `status`='cached' LIMIT %d, %d", $offset, $length
				))
			, ARRAY_A);
		}
		
		$formated_result = array();

		if(!empty($result)){

			foreach ($result as $value) {
				
				if($value['status'] == 'cached'){
					$user_dirname = $this->cachepath();
					$size = filesize($user_dirname.'/'.md5($value['url']).'.css');					
				}
					
				$formated_result[] = array(
									'<abbr title="'.$value['cached_name'].'">'.$value['url'].'</abbr>',
									'<span class="cwvpb-status-t">'.$value['status'].'</span>',
									$size,
									$value['updated_at']
							);
			}				

		}	
				
		$retuernData = array(	
		    "draw"            => $draw,
			"recordsTotal"    => $total_count,
			"recordsFiltered" => $total_count,
			"data"            => $formated_result
		);

		echo json_encode($retuernData);die;

	}


	public function ccfwp_showdetails_data_failed(){
		
		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_GET['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}

		$page   = isset($_GET['start']) && $_GET['start']> 0 ? $_GET['start']/$_GET['length'] : 1;
		$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
		$page   = ($page + 1);
		$offset = isset($_GET['start']) ? intval($_GET['start']) : 0;
		$draw = intval($_GET['draw']);						
		
		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';
																
		if($_GET['search']['value']){
			$search = sanitize_text_field($_GET['search']['value']);
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE `url` LIKE %s AND `status`='failed'",
			'%' . $wpdb->esc_like($search) . '%'
			),			
			);
			
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name WHERE `url` LIKE %s AND `status`='failed' LIMIT %d, %d",
					'%' . $wpdb->esc_like($search) . '%', $offset, $length
				))
			, ARRAY_A);
		}else
		{
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name Where `status`='failed'"));
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name Where `status`='failed' LIMIT %d, %d", $offset, $length
				))
			, ARRAY_A);
		}
		
		$formated_result = array();

		if(!empty($result)){

			foreach ($result as $value) {
				
				if($value['status'] == 'cached'){
					$user_dirname = $this->cachepath();
					$size = filesize($user_dirname.'/'.md5($value['url']).'.css');					
				}
					
				$formated_result[] = array(
									'<div>'.$value['url'].' <a href="#" data-section="failed" data-id="'.$value['id'].'" class="cwvpb-resend-single-url dashicons dashicons-controls-repeat"></a></div>',
									'<span class="cwvpb-status-t">'.$value['status'].'</span>',
									$value['updated_at'],
									'<div><a data-id="id-'.$value['id'].'" href="#" class="cwb-copy-urls-error button button-secondary">Copy Error</a><input id="id-'.$value['id'].'" class="cwb-copy-urls-text" type="hidden" value="'.$value['failed_error'].'"></div>'									
							);
			}				

		}	
				
		$retuernData = array(	
		    "draw"            => $draw,
			"recordsTotal"    => $total_count,
			"recordsFiltered" => $total_count,
			"data"            => $formated_result
		);

		echo json_encode($retuernData);die;

	}
	public function ccfwp_showdetails_data_queue(){
		
		if ( ! isset( $_GET['ccfwp_security_nonce'] ) ){
			return; 
		}
		if ( !wp_verify_nonce( $_GET['ccfwp_security_nonce'], 'ccfwp_ajax_check_nonce' ) ){
			return;  
		}

		$page   = isset($_GET['start']) && $_GET['start']> 0 ? $_GET['start']/$_GET['length'] : 1;
		$length = isset($_GET['length']) ? intval($_GET['length']) : 10;
		$page   = ($page + 1);
		$offset = isset($_GET['start']) ? intval($_GET['start']) : 0;
		$draw = intval($_GET['draw']);						
		
		global $wpdb, $table_prefix;
		$table_name = $table_prefix . 'critical_css_for_wp_urls';
																
		if($_GET['search']['value']){
			$search = sanitize_text_field($_GET['search']['value']);
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE `url` LIKE %s AND `status`='queue'",
			'%' . $wpdb->esc_like($search) . '%'
			),			
			);
			
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name WHERE `url` LIKE %s AND `status`='queue' LIMIT %d, %d",
					'%' . $wpdb->esc_like($search) . '%', $offset, $length
				))
			, ARRAY_A);
		}else
		{
			$total_count  = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name Where `status`='queue'"));
			$result = $wpdb->get_results(
				stripslashes($wpdb->prepare(
					"SELECT * FROM $table_name Where `status`='queue' LIMIT %d, %d", $offset, $length
				))
			, ARRAY_A);
		}
		
		$formated_result = array();

		if(!empty($result)){

			foreach ($result as $value) {
				
				if($value['status'] == 'cached'){
					$user_dirname = $this->cachepath();
					$size = filesize($user_dirname.'/'.md5($value['url']).'.css');					
				}
					
				$formated_result[] = array(
									$value['url'],
									$value['status'],
									$size,
									$value['updated_at'],
																
							);
			}				

		}	
				
		$retuernData = array(	
		    "draw"            => $draw,
			"recordsTotal"    => $total_count,
			"recordsFiltered" => $total_count,
			"data"            => $formated_result
		);

		echo json_encode($retuernData);die;

	}	

} // class ends here ccfwp_

$ccfwpgeneralcriticalCss = new class_critical_css_for_wp();
$ccfwpgeneralcriticalCss->critical_hooks();