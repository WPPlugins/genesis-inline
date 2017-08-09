<?php
class GInline_Theme {
	var $formats = null;
	var $post_format = 'standard';
	var $doing_list = null;
	var $doing_editor = null;

	function GInline_Theme() {
		return $this->__construct();
	}
	function  __construct() {
		add_action( 'after_setup_theme', array( $this, 'after_setup_theme' ) );
	}
	function after_setup_theme() {
		if( !function_exists( 'genesis_get_option' ) )
			return;
			
		$this->formats = array( 'standard' );
		if( ( $formats = get_theme_support( 'post-formats' ) ) && is_array( $formats ) && !empty( $formats ) ) {
			$this->formats = array_merge( $this->formats, current( $formats ) );
			asort( $this->formats );
		}
		add_action( 'init', array( $this, 'init' ) );
	}
	function init() {
		add_action( 'genesis_before_post', array( $this, 'before_post' ), 0 );
		add_action( 'genesis_after_post', array( $this, 'after_post' ), 99 );
		$this->doing_editor = current_user_can( 'edit_posts' );
		require( GENESISINLINE_LIB . 'class.ginline.php' );
		GInline::init();

		add_filter( 'post_class', array( $this, 'post_class' ), 10, 3 );
		if( !$this->doing_editor )
			return;

		add_action( 'genesis_meta', array( $this, 'genesis_meta' ), 2 );
	}
	function genesis_meta() {
		if( !is_front_page() )
			return;

		require( GENESISINLINE_LIB . 'class.gijs.php' );
		GIJS::init();
		wp_enqueue_style( 'ginline-post-form', GENESISINLINE_CSS . 'post-form.css', false, GENESISINLINE_VERSION );
		add_action( 'genesis_before_loop', array( &$this, 'post_form' ), 999 );
		add_action( 'genesis_after_loop', array( &$this, 'after_loop' ), 0 );
	}
	function post_form() {
		$title_text = __( 'Title', 'genesis-inline' );
?>
<script type="text/javascript" charset="utf-8">
		jQuery(document).ready(function($) {
			jQuery('#post_format').val($('#post-formats a.selected').attr('id'));
			$('#post-formats a').click(function(e) {
				var id = $(this).attr('id');
				$('.post-input').hide();
				$('#post-formats a').removeClass('selected');
				$(this).addClass('selected');
				$('#posttitle').blur();
				$('#postbox-type-' + id).show();
				if(id != 'quote' && id != 'status' && id != 'link') {
					$('#postbox-type-standard').show();
				}
				$('#post_format').val(id);
				return false;
			});
			$('#post-status a').click(function(e) {
				$('#post-status a').removeClass('selected');
				jQuery(this).addClass('selected');
				jQuery('#post_status').val($(this).attr('id'));
				return false;
			});
			$('#ajaxActivity').hide();
		});
</script>
<div id="postbox">
		<?php $this->list_post_formats(); ?>
		<div class="avatar">
			<?php GInline::user_avatar(); ?>
		</div>

		<div class="inputarea">

			<form id="new_post" name="new_post" method="post" action="<?php echo site_url(); ?>/">

				<div id="postbox-type-standard" class="post-input <?php $this->select_post_format( array( 'quote', 'status' ), true ); ?>">
					<input type="text" name="posttitle" id="posttitle" tabindex="1" value="<?php echo esc_attr( $title_text ); ?>"
						onfocus="this.value=(this.value=='<?php echo esc_js( $title_text ); ?>') ? '' : this.value;"
						onblur="this.value=(this.value=='') ? '<?php echo esc_js( $title_text ); ?>' : this.value;" />
				</div>
				<?php wp_dropdown_categories( array( 'name' => 'post_cat', 'tab_index' => 2, 'selected' => get_option( 'default_category' ), 'orderby' => 'name' ) ); ?>
				<?php if ( current_user_can( 'upload_files' ) ): ?>
				<div id="media-buttons" class="hide-if-no-js">
					<?php echo GInline::media_buttons(); ?>
				</div>
				<?php endif; ?>
				<textarea class="expand70-200" name="posttext" id="posttext" tabindex="1" rows="6" cols="60"></textarea>
				<div id="postbox-type-quote" class="post-input<?php $this->select_post_format( 'quote' ); ?>">
					<label for="postcitation"><?php _e( 'Citation', 'genesis-inline' ); ?></label>
						<input id="postcitation" name="postcitation" type="text" tabindex="3"
							value=""
							onfocus="this.value=(this.value=='<?php echo esc_js( __( 'Citation', 'genesis-inline' ) ); ?>') ? '' : this.value;"
							onblur="this.value=(this.value=='') ? '<?php echo esc_js( __( 'Citation', 'genesis-inline' ) ); ?>' : this.value;" />
				</div>
				<label class="post-error" for="posttext" id="posttext_error"></label>
				<div class="postrow">
					<input id="tags" name="tags" type="text" tabindex="5" autocomplete="off"
						value="<?php esc_attr_e( 'Tag it', 'genesis-inline' ); ?>"
						onfocus="this.value=(this.value=='<?php echo esc_js( __( 'Tag it', 'genesis-inline' ) ); ?>') ? '' : this.value;"
						onblur="this.value=(this.value=='') ? '<?php echo esc_js( __( 'Tag it', 'genesis-inline' ) ); ?>' : this.value;" />
					<span class="alignright">
						<?php $this->list_post_stati(); ?>
					</span>
					<br />
					<div id="tag-suggest" class="term-suggest"></div>
				</div>
				<input type="hidden" name="post_format" id="post_format" value="<?php echo esc_attr( $this->post_format ); ?>" />
				<span class="progress" id="ajaxActivity">
					<img src="<?php echo GENESISINLINE_CSS . 'images/indicator.gif'; ?>"
						alt="<?php esc_attr_e( 'Loading...', 'genesis-inline' ); ?>" title="<?php esc_attr_e( 'Loading...', 'genesis-inline' ); ?>"/>
				</span>
				<input type="hidden" name="action" value="post" />
				<?php wp_nonce_field( 'new-post' ); ?>
			</form>

		</div>

		<div class="clear"></div>

</div> <!-- // postbox -->
<ul id="inline-sleeve">
<?php	}
	function before_post() {
		global $post;
		
		$ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;
		$this->doing_list = $this->doing_editor && ( $ajax || !is_single() );
		if( !$this->doing_list )
			return;

		$style = '';
		if( $ajax )
			$style = 'style="display:none"';

		echo "<li $style id='post-{$post->ID}'>";
	}
	function after_post() {
		if( $this->doing_list )
			echo '</li>';
	}
	function after_loop() {
		echo '</ul>';
	}
	function post_class( $classes, $class, $post_id ) {
		if( current_user_can( 'edit_post', $post_id ) )
			$classes[] = 'editarea';
		return $classes;
	}
	function list_post_formats() {
		$style = '';
		if( count( $this->formats ) < 2 )
			$style = ' style="display:none"';

		$post_format = 'standard';
		echo "<div class=\"nav\"><ul id=\"post-formats\"{$style}>\n";
		foreach( $this->formats as $f ) {
			$f_text = ( function_exists( 'get_post_format_string' ) ? get_post_format_string( $f ) : __( 'Blog Post', 'genesis-inline' ) );
			echo "<li><a id=\"$f\"" . ( ( $post_format == $f ) ? ' class="selected"' : '' ) . ' href="' . site_url( "?p=$f" ) . "\" title=\"{$f_text}\">{$f_text}</a></li>\n";
		}
		echo "</ul><div class='clear'></div>\n</div>";
	}
	function list_post_stati() { ?>
		<input type="hidden" name="post_status" id="post_status" value="publish" />
		<input type="hidden" name="post_id" id="post_id" value="" />
		<input id="draft" type="submit" class="searchsubmit" tabindex="3" value="<?php esc_attr_e( 'Save', 'genesis-inline' ); ?>" />
<?php		if( current_user_can( 'publish_posts' ) ) { ?>
		<input id="publish" type="submit" class="searchsubmit" tabindex="4" value="<?php esc_attr_e( 'Publish', 'genesis-inline' ); ?>" />
<?php		}
	}
	function select_post_format( $current, $exclude = false ) {
		if( $exclude xor $this->is_format( $current ) )
			echo ' selected';
	}
	function is_format( $current ) {
		if( is_array( $current ) )
			return in_array( $this->post_format, $current );

		return ( $current == $this->post_format );
	}
	function supports_format( $format ) {
		return !empty( $format ) && ( $format != $this->post_format ) && in_array( $format, $this->formats );
	}	
}

global $ginline_theme;
$ginline_theme = new GInline_Theme();