<?php /*
  Plugin Name:  Smart Related Post Thumbnail
  Plugin URI:    http://wizcrew.com/smart-related-posts-thumbnail.htm 
  Description:  Showing related posts thumbnails under the post and also the short discription about the post and comment count on hover for a quick reading.
  Version:      2.1
  Author:       Wizcrew technologies
  Author URI:   http://wizcrew.com/
*/
class SmartRelatedPostsThumbnails {
	/* Default values. PHP 4 compatible */
	var $single_only = '1';
	var $auto = '1';
	var $top_text = '<h3>Related posts:</h3>';
	var $number = 3;
	var $relation = 'categories';
	var $is_title=1;
	var $text_length = '100';
	var $excerpt_length = '200';
	var $devmode = '0';
	var $relpoststh_sizes=64;
	function SmartRelatedPostsThumbnails() { // initialization
		$this->default_image = WP_PLUGIN_URL . '/smart-related-posts-thumbnails/images/default.jpg';
		if ( get_option( 'relpoststh_auto', $this->auto ) )
			add_filter( 'the_content', array( $this, 'auto_show' ) );
		//add_filter( 'the_content', array( $this, 'auto_show' ) );
		add_action( 'admin_menu',  array( $this, 'admin_menu' ) );
		add_shortcode( 'smart-related-posts-thumbnails' , array( $this, 'get_thumbs' ) );
	}
	
	
	
	function auto_show( $content ) { // Automatically displaying related posts under post body
		return $content . $this->get_thumbs();
	}

	function get_thumbs() { // Getting related posts HTML
		if ( $this->is_relpoststh_show() )
			return $this->get_thumbnails();
		return '';
	}

	function get_thumbnails( ) { // Retrieve Related Posts HTML for output
		$output					= '';
		$time					= microtime(true);
		$posts_number           = get_option( 'relpoststh_number', $this->number );
		$id						= get_the_ID();
		$text_length			= 100;
		$excerpt_length			= 200;
		$relpoststh_sizes       = get_option( 'relpoststh_sizes', $this->relpoststh_sizes );
		$is_title				= get_option('is_show_title',$this->is_title);
		$top_text				= get_option('top_text',$this->top_text);
		
		global $wpdb;
		$query = "SELECT distinct ID FROM $wpdb->posts ";
		$where = " WHERE post_type = 'post' AND post_status = 'publish' AND ID<>" . $id; // not the current post
		
		/* Get taxonomy terms */
		$join = '';
		$whichterm = '';
		$select_terms = array();
		
		//default relation  is categories
		$select_terms=wp_get_object_terms( $id, array('category'), array( 'fields' => 'ids' ) );
		$include_terms = "'" . implode( "', '", $select_terms ) . "'";
		$join = " INNER JOIN $wpdb->term_relationships ON ($wpdb->posts.ID = $wpdb->term_relationships.object_id) INNER JOIN $wpdb->term_taxonomy ON ($wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id) ";
		$whichterm = " AND $wpdb->term_taxonomy.term_id IN ($include_terms) ";
		$order = " ORDER BY rand(),ID DESC  LIMIT " . $posts_number;
		
		$whichterm = " AND $wpdb->term_taxonomy.term_id IN ($include_terms) ";
		
		$random_posts = $wpdb->get_results( $query . $join.$where.$whichterm . $order );

		/* Get posts by their IDs */
		$posts_in = array();
		if ( is_array( $random_posts ) && count( $random_posts ) ) {
			foreach ( $random_posts as $random_post )
				$posts_in[] = $random_post->ID;
		}
		else {
			return '<!--No posts matching relationships criteria-->';
		}
		$posts = array();
		$q = new WP_Query;
		$posts = $q->query( array( 'caller_get_posts' => true,
								   'post__in' => $posts_in,
								   'posts_per_page'   => $posts_number ) );
		if ( ! ( is_array( $posts ) && count( $posts ) > 0 ) ) { // no posts
			return '<!-- no related post found for this category -->';
		}
		
		/* Calculating sizes */
		
		
		$output .= stripslashes($top_text );
		$output .= '<div style="clear: both"></div><div style="border: 0pt none ; margin: 0pt; padding: 0pt;">';
		foreach( $posts as $post ) {
			$image = '';
			$url = '';
			$width=$relpoststh_sizes;
			$height=$relpoststh_sizes;
			$form_body=TRUE;
			// check if the theme support thumbnail
			if( current_theme_supports( 'post-thumbnails' ) ){
				$post_thumbnail_id = get_post_thumbnail_id( $post->ID );
				if ( !( empty( $post_thumbnail_id ) || $post_thumbnail_id === false ) ) { // post has thumbnail
						$image = wp_get_attachment_image_src( $post_thumbnail_id);
						$url = $image[0];
						$from_body = false;
					}
				
			}
						
			if($form_body){
				
				//getting the  thumbnail image from the body
				preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $post->post_content, $matches);
				// searching for the first uploaded image in text
					if ( isset( $matches ) ) {
					$url = $matches[1][0];
				}	
			
			}
			
			
			// check for the image is found or empty file
			if ( empty($url) || ( ini_get( 'allow_url_fopen' ) && false === @fopen( $url, 'r' ) ) ) { // parsed URL is empty or no file if can check
				
				$url = get_option( 'relpoststh_default_image', $this->default_image );
				
				}
			// resize the image as we needed for the thumbnails
			$url = WP_PLUGIN_URL.'/smart-related-posts-thumbnails/timthumb.php?w='.$width.'&h='.$height.'&zc=1&src='.$url;
			
			
			$title = $this->process_text_cut( $post->post_title, $text_length );
			$post_excerpt = ( empty( $post->post_excerpt ) ) ? $post->post_content : $post->post_excerpt;
			$excerpt = $this->process_text_cut( $post_excerpt, $excerpt_length );
			
					
			
			if ( !empty($title) && !empty($excerpt) ) {
				$title = '<b>' . $title . '</b>';
				$excerpt = '<br/>' . $excerpt;
			}
			//=============TIPSY TEXT====================//
			$flag = '0';
			$comment_count=$post->comment_count;
			$tipsy_excerpt=str_replace('"','',$excerpt);
			$tipsy_excerpt=str_replace("'",'',$excerpt);
			$title=str_replace('"','',$title);
			$title=str_replace("'",'',$title);
			$tipsy_text="title='".$title."<br/>Comments: (".$comment_count.")".$tipsy_excerpt."' class='smartpoststh'";
			//============END OF TIPSY================//
				// this part is showing	
				$output .= '<a '.$tipsy_text.' onmouseout="this.style.backgroundColor=\'#FFFFFF\' " onmouseover="this.style.backgroundColor=\'#EEEEEF\'" style="background-color: \'#FFFFFF\'; border-right: 1px solid \' #DDDDDD\'; border-bottom: medium none; margin: 0pt; padding: 6px; display: block; float: left; text-decoration: none; text-align: left; cursor: pointer;" href="' . get_permalink( $post->ID ) . '">';
				$output .= '<div style="border: 0pt none ; margin: 0pt; padding: 0pt; width: ' . $width . 'px; height: ' . $height . 'px;">';
				$output .= '<div style="border: 0pt none ; margin: 0pt; padding: 0pt; background: transparent url(' . $url . ') no-repeat scroll 0% 0%; -moz-background-clip: border; -moz-background-origin: padding; -moz-background-inline-policy: continuous; width: ' . $width . 'px; height: ' . $height . 'px;"></div>';
				if($is_title){
					$flag = '1';
					$output .= '<div style="border: 0pt none;display:block; margin: 3px 0pt 0pt; width: ' . $width . 'px; padding: 0pt; height: ' . $height . 'px; font-style: normal; font-variant: normal; font-weight: normal; font-size:12px; line-height: normal; font-size-adjust: none; font-stretch: normal; -x-system-font: none; color: #333333; overflow: hidden">' . $title. '</div>';
				}
				$output .= '</div>';
				$output .= '</a>';
			

		} // end foreach
		$output .= '</div>';
		$output .= '<div style="clear: both"></div>';
		if($flag == '1')
			$output .= '<div style = "padding-top:50px;">&nbsp;</div>';

		return $output;
	}
	function process_text_cut( $text, $length ) {
		if ($length == 0)
			return '';
		else {
			$text = strip_shortcodes( strip_tags( $text ) );
			return ( ( strlen( $text ) > $length ) ? substr( $text, 0, $length) . '...' : $text );
		}
	}
	
	
	function is_relpoststh_show() { // Checking display options
		if ( is_page() ||  ! is_single()  ) { // single only
			return false;
		}
		/* Check categories */
		$id = get_the_ID();
		$post_categories = wp_get_object_terms( $id, array( 'category' ), array( 'fields' => 'ids' ) );
		if ( !is_array( $post_categories ) ) // no categories were selcted or post doesn't belong to any
			return false;
				
		return true; 
	}

	function admin_menu() {
		$page = add_options_page( __( 'Smart  Related Posts Thumbnails', 'smart-related-posts-thumbnails' ), __( 'Smart Related Posts Thumbs', 'smart-related-posts-thumbnails' ), 'administrator', 'smart-related-posts-thumbnails', array( $this, 'admin_interface' ) );
	}

	function admin_interface() { // Admin interface
		if ( $_POST['action'] == 'update' ) {
			if ( !current_user_can( 'manage_options' ) ) {
				wp_die( __( 'No access', 'smart-related-posts-thumbnails' ) );
			}
			check_admin_referer( 'smart-related-posts-thumbnails' );
			$validation = true;
			
			if ( $validation ) {
				update_option( 'is_show_title', $_POST['is_title'] );
				update_option( 'relpoststh_number', $_POST['relpoststh_number']);
				update_option( 'relpoststh_default_image', $_POST['relpoststh_default_image'] );
				update_option( 'relpoststh_sizes', $_POST['relpoststh_sizes'] );
				update_option( 'top_text', $_POST['top_text'] );
				update_option( 'relpoststh_auto', $_POST['relpoststh_auto'] );
				
			
				
				echo "<div class='updated fade'><p>" . __( 'Settings updated', 'smart-related-posts-thumbnails' ) ."</p></div>";
			}
			else {
				echo "<div class='error fade'><p>" . __( 'Settings update failed', 'smart-related-posts-thumbnails' ) . '. '. $error . "</p></div>";
			}
		}
		$is_title=get_option('is_show_title',$this->is_title);
		$relpoststh_auto = get_option( 'relpoststh_auto', $this->auto );
		$relpoststh_sizes=get_option( 'relpoststh_sizes', $this->relpoststh_sizes );
		
		?>

<div class="wrap">
	<div class="icon32" id="icon-options-general"><br></div>
	<h2><?php _e( 'Smart Posts Thumbnails Settings', 'smart-related-posts-thumbnails' ); ?></h2>
	<form action="?page=smart-related-posts-thumbnails" method="POST">
		<input type="hidden" name="action" value="update" />
		<?php wp_nonce_field( 'smart-related-posts-thumbnails' ); ?>
		<div class="metabox-holder">
			<div class="postbox">
				<h3><?php _e( 'General Display Options', 'smart-related-posts-thumbnails' ); ?>:</h3>
				<table class="form-table">
					
					<tr valign="top">
						<th scope="row"><?php _e( 'Automatically append to the post content', 'smart-related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="checkbox" name="relpoststh_auto" id="relpoststh_auto" value="1" <?php if ( $relpoststh_auto ) echo 'checked="checked"'; ?>/>
							<label for="relpoststh_auto"><?php _e( 'Or use <b>&lt;?php get_smart_related_posts_thumbnails(); ?&gt;</b> in the single post page inside the loop', 'smart-related-posts-thumbnails' ); ?></label><br />
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><?php _e( 'Text To be Apear on Top', 'smart-related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="top_text" value="<?php echo get_option( 'top_text', $this->top_text); ?>" size="100"/>
						</td>
					</tr>
					
					<tr valign="top">
						<th scope="row"><?php _e( 'Show title below thumbnails', 'smart-related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="checkbox" name="is_title" id="relpoststh_auto" value="1" <?php if ( $is_title ) echo 'checked="checked"'; ?>/>
							<br />
						</td>
					</tr>
					
					<tr>
						<th scope="row"><?php _e( 'Number of similar posts to display', 'smart-related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_number" value="<?php echo get_option( 'relpoststh_number', $this->number ); ?>" size="2"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Default image URL', 'smart-related-posts-thumbnails' ); ?>:</th>
						<td>
							<input type="text" name="relpoststh_default_image" value="<?php echo get_option('relpoststh_default_image', $this->default_image );?>" size="50"/>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php _e( 'Thumbnails Sizes', 'smart-related-posts-thumbnails' ); ?>:</th>
						<td>
							<select name="relpoststh_sizes"  id="relpoststh_sizes">
                           <option value="64" <?php if ( $relpoststh_sizes == 64 ) echo 'selected'; ?>>64X64</option>
                           <option value="128" <?php if ( $relpoststh_sizes == 128 ) echo 'selected'; ?>>128X128</option>
                           <option value="150" <?php if ( $relpoststh_sizes == 128 ) echo 'selected'; ?>>150X150</option>
							</select>
						</td>
					</tr>
					
					
				</table>
			</div>
			
			<input name="Submit" value="<?php _e( 'Save Changes', 'smart-related-posts-thumbnails' ); ?>" type="submit">
		</div>
	</form>
</div>
<?php
	}
}

add_action( 'init', 'smart_related_posts_thumbnails' );

function smart_related_posts_thumbnails() {
	global $smart_related_posts_thumbnails;
	
	$smart_related_posts_thumbnails = new SmartRelatedPostsThumbnails();
}

function get_smart_related_posts_thumbnails()
{
	global $smart_related_posts_thumbnails;
	echo $smart_related_posts_thumbnails->get_thumbs();
}

/**
 * Smart Posts Widget, will be displayed on post page
 */
class SmartRelatedPostsThumbnailsWidget extends WP_Widget {
	function SmartRelatedPostsThumbnailsWidget() {
		parent::WP_Widget(false, $name = 'Smart Related Posts Thumbnails');
	}

	function widget($args, $instance) {
		if ( is_single() && !is_page() ) { // display on post page only
			extract( $args );
			$title = apply_filters('widget_title', $instance['title']);
			echo $before_widget;
			if ( $title )
				echo $before_title . $title . $after_title;
			get_smart_related_posts_thumbnails();
			echo $after_widget;
		}
	}

	function update($new_instance, $old_instance) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		return $instance;
	}

	function form($instance) {
		$title = esc_attr($instance['title']);
		?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
		<?php
	}

} // class SmartPostsThumbnailsWidget

add_action( 'widgets_init', create_function( '', 'return register_widget("SmartRelatedPostsThumbnailsWidget");' ) );

function smartpoststh_init(){
	// TODO check if all this stuff is actually needed
	if(!is_admin()){
		wp_enqueue_script('smartrelatedpoststh-jquery',WP_PLUGIN_URL.'/smart-related-posts-thumbnails/js/jquery.js', null, '0.1');
		wp_enqueue_script('smartrelatedpoststh-tipsyjs',WP_PLUGIN_URL.'/smart-related-posts-thumbnails/js/tipsy.js', null, '0.1');
		wp_enqueue_style('smartrelatedpoststh-css',WP_PLUGIN_URL.'/smart-related-posts-thumbnails/css/tipsy.css',null,'0.1','screen');
	}
}
add_action('init', 'smartpoststh_init');


function smartpoststh_footer(){
	?>
	<script type="text/javascript">$(function() {
		$('.smartpoststh').tipsy({gravity: 's',html: true });
		});</script>
		
		<?php
}
add_action('wp_footer', 'smartpoststh_footer');
?>
