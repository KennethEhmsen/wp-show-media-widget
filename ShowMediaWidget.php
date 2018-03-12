<?php
/*
Plugin Name: Show Media Widget
Description: List media files in a widget filtered by categories
Version:     1.0.1
Author:      ole1986
Author URI:  https://profiles.wordpress.org/ole1986
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: mediawidget
*/

class MediaWidget extends WP_Widget {
  
  private $maxItems = 5;
  private $openInTab = 1;
  
  public function __construct() {       
	parent::__construct('mediawidget', __('Media Widget', 'mediawidget'), ['description' => __( 'Show media in widget filtered by category', 'mediawidget' ) ] );
	
	add_action( 'wp_ajax_mediawidget_loadmore', [$this, 'loadmore'] );
	add_action( 'wp_ajax_nopriv_mediawidget_loadmore', [$this, 'loadmore'] );
	add_action('wp_head', [$this, 'js']);
  }
  
  private function getMedia($category,$offset = 0, $take = 5){
	$a = ['showposts' => $take, 'offset' => $offset, 'post_type' => 'attachment', 'tax_query' => [ [ 'taxonomy' => 'media_category', 'terms' => $category, 'field' => 'ID'] ]];
	return get_posts($a);
  }
  
  
  public function loadmore() {
	$media = $this->getMedia($_POST['category'], $_POST['offset'], $_POST['maxitems']);
	$this->showMedia($media);
	
	wp_die();
  }
  
  public function generateThumbnailFromPDF($m){
	// skip if no imagick is available
	if (!extension_loaded('imagick')) return;
	
	$filepath = get_attached_file( $m->ID );
	$destPath = preg_replace("/\.pdf$/i", '-image.png', $filepath);
	
	$url = wp_get_attachment_url($m->ID);
	$thumbnailUrl = preg_replace("/\.pdf$/i", '-image.png', $url);
	
	if(file_exists($destPath))
	  return $thumbnailUrl;
	

	$imagick = new Imagick($filepath);
	$imagick->setIteratorIndex(0);
	$imagick->setImageOpacity(1); 
	$imagick->setImageCompressionQuality(40);
	$imagick->thumbnailImage(200,null); 
	$imagick->setImageFormat('png');
	
	$success = $imagick->writeImage($destPath);
	
	// make it visible in media center
	$attachment_id = wp_insert_attachment( ['post_title' => $m->post_title . ' (thumbnail)', 'post_mime_type' => 'image/png' ], $destPath, $m->ID);
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	$metadata = wp_generate_attachment_metadata($attachment_id, $destPath);
	wp_update_attachment_metadata($attachment_id, $metadata);
	
	
	return $thumbnailUrl;
  }
  
  public function showMedia($media){
	foreach($media as $m) {
	  $previewImg = '';
	  if($m->post_mime_type == 'application/pdf')
	  {
		$thumbnail = $this->generateThumbnailFromPDF($m);
		$previewImg = '<img src="'.$thumbnail.'" style=""><br />';
	  }
	  
	  $blank = ($this->openInTab)?'target="_blank"':'';
	  echo "<div align=\"center\"><a href=\"" . wp_get_attachment_url($m->ID) ."\" {$blank}>{$previewImg}{$m->post_title}</a></div>";
	}
  }
  
  public function widget($args, $instance) {
	global $post;

	$this->maxItems = (empty($instance['maxitems']))? $this->maxItems : intval($instance['maxitems']);
	$this->openInTab = (!empty($instance['newwindow']))? 1 : 0;
	
	// before and after widget arguments are defined by themes
	echo $args['before_widget'];
	echo $args['before_title'] .  $instance['title'] . $args['after_title'];
	
	
	echo '<div id="mediawidget-'.$instance['category'].'">';
	$media = $this->getMedia($instance['category'], 0, $this->maxItems);
	
	$this->showMedia($media);
	
	echo '</div>';
	
	echo '<div style="margin-top: 1em;text-align: center; font-size: small;"><a href="javascript:void(0)" class="mediawidget-readmore" data-category="'.$instance['category'].'" data-offset="'.$this->maxItems.'" data-maxitems="'.$this->maxItems.'">'. __("Show More", 'mediawidget') .'</a></div>';
	
	echo $args['after_widget'];
  }
  
  public function form($instance) {
	$title = isset($instance['title'])? esc_attr($instance['title']) : "";
	$this->maxItems = isset($instance['maxitems'])? intval($instance['maxitems']) : $this->maxItems;
	$this->openInTab = (!empty($instance['newwindow']))? 1 : 0;
	
	?>
	<p>
	  <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'mediawidget'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title ?>" />
	</p>
	<?php
	  $cats = get_categories( ['taxonomy' => 'media_category']);
	?>
	<p>
	  <label for="<?php echo $this->get_field_id('category'); ?>"><?php _e('Category:', 'mediawidget'); ?></label>
	  <select class="widefat" id="<?php echo $this->get_field_id('category'); ?>" name="<?php echo $this->get_field_name('category'); ?>">
		<?php foreach($cats as $c) { ?>
		<option value="<?php echo $c->cat_ID ?>" <?php selected( $instance['category'], $c->cat_ID ); ?>><?php echo $c->name ?></option>
		<?php } ?>
	  </select>
	</p>
	<p>
	  <label for="<?php echo $this->get_field_id('maxitems'); ?>"><?php _e('Max items to show:', 'mediawidget'); ?></label>
      <input class="widefat" id="<?php echo $this->get_field_id('maxitems'); ?>" name="<?php echo $this->get_field_name('maxitems'); ?>" type="number" value="<?php echo $this->maxItems ?>" />
	</p>
    <p>
	  <label for="<?php echo $this->get_field_id('newwindow'); ?>"><?php _e('Open in new tab:', 'mediawidget'); ?></label>
	  <input class="widefat" id="<?php echo $this->get_field_id('newwindow'); ?>" name="<?php echo $this->get_field_name('newwindow'); ?>" type="checkbox" value="1" <?php echo ($this->openInTab)?"checked":"" ?> />
	</p>
	<?php
	  
  }
  
  public function update($new, $old) {
	return $new;
  }
  
  public function js(){
	?>
	<script>
	  jQuery(function(){
		var $ = jQuery;
		
		$('.mediawidget-readmore').click(function(){
		  var category = $(this).data('category');
		  var offset = parseInt($(this).data('offset'));
		  var maxitems = parseInt($(this).data('maxitems'));
		  
		  $(this).data('offset', (offset + maxitems));
		  
		  
		  $.post('<?php echo admin_url('admin-ajax.php'); ?>', { action: 'mediawidget_loadmore', category: category, offset: offset, maxitems: maxitems }).done(function(data){
			jQuery('#mediawidget-' + category).append(data);
		  });
		  
		});
	  });
	</script>
	<?php
  }
  
  public static function load(){
	load_plugin_textdomain( 'mediawidget', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	register_widget( get_class() );
  }
  
}

add_action('widgets_init', ['MediaWidget', 'load']);
?>