<?php
if ( !defined( 'DOING_AJAX' ) )
	define( 'DOING_AJAX', true);
@header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ));

class GInlineAjax {
	function dispatch() {
		$action = isset( $_REQUEST['action'] )? $_REQUEST['action'] : '';
		add_action( 'wp_ajax_'.$action, $action );
		do_action( 'genesis_inline_ajax', $action );
		if ( is_callable( array( 'GInlineAjax', $action ) ) )
			call_user_func( array( 'GInlineAjax', $action ) );
		else
			die( '-1' );
		exit;
	}
	
	function get_post() {
		check_ajax_referer( 'ajaxnonce', '_inline_edit' );
		if ( !is_user_logged_in() )
			die( '<p>'.__( 'Error: not logged in.', 'genesis-inline' ).'</p>' );

		$post_id = $_GET['post_ID'];
		$post_id = substr( $post_id, strpos( $post_id, '-' ) + 1 );
		if ( !current_user_can( 'edit_post', $post_id ) )
			die( '<p>'.__( 'Error: not allowed to edit post.', 'genesis-inline' ).'</p>' );

		$post = get_post( $post_id );

		function get_tag_name( $tag ) {
			return $tag->name;
		}
		$tags = array_map( 'get_tag_name', wp_get_post_tags( $post_id ) );

		$categories = get_the_category( $post_id );
		$category_slug = ( isset( $categories[0] ) ) ? $categories[0]->slug : '';

		// handle page as post_type
		if ( 'page' == $post->post_type ) {
			$category_slug = 'page';
			$tags = '';			
		}

		echo json_encode( array(
			'title' => $post->post_title,
			'content' => $post->post_content,
			'type' => $category_slug,
			'tags' => $tags,
		) );
	}
	
	function tag_search() {
		global $wpdb;
		$term = $_GET['q'];
		if ( false !== strpos( $term, ',' ) ) {
			$term = explode( ',', $term );
			$term = $term[count( $term ) - 1];
		}

		$term = trim( $term );
		if ( strlen( $term ) < 2 )
			die(); // require 2 chars for matching
		$results = $wpdb->get_col( "SELECT t.name FROM $wpdb->term_taxonomy AS tt INNER JOIN $wpdb->terms AS t ON tt.term_id = t.term_id WHERE tt.taxonomy = 'post_tag' AND t.name LIKE ( '%". like_escape( $wpdb->escape( $term ) ) . "%' )" );
		echo join( $results, "\n" );
	}

	function logged_in_out() {
		check_ajax_referer( 'ajaxnonce', '_loggedin' );
		echo is_user_logged_in()? 'logged_in' : 'not_logged_in';
	}

	function save_post() {
		global $ginline_theme;

		check_ajax_referer( 'ajaxnonce', '_ajax_post' );
		if ( !is_user_logged_in() )
			die( '<p>'.__( 'Error: not logged in.', 'genesis-inline' ).'</p>' );

		$post_id = (int) $_POST['post_id'];

		if ( !current_user_can( 'edit_post', $post_id ) )
			die( '<p>'.__( 'Error: not allowed to edit post.', 'genesis-inline' ).'</p>' );

		$cat_id = (int) $_POST['post_cat'];
		$new_post_content = $_POST['posttext'];
		$new_tags = trim( $_POST['tags'] );
	
		/* Add the quote citation to the content if it exists */
		if ( !empty( $_POST['citation'] ) && 'quote' == $category_slug )
			$new_post_content = '<p>' . $new_post_content . '</p><cite>' . $_POST['citation'] . '</cite>';

		$post_status = 'draft';
		if( 'publish' == $_POST['post_status'] && current_user_can( 'publish_posts' ) )
			$post_status = 'publish';

		$post_title = isset( $_POST['post_title'] ) ? $_POST['post_title'] : '';
		if ( empty( $post_title ) || esc_js( __( 'Title', 'genesis-inline' ) ) == $post_title )
			$post_title = GInlineAjax::title_from_content( $post_content );

		self::init_kses();
		$post = wp_update_post( array(
			'post_title'	=> $post_title,
			'post_status'	=> $post_status,
			'post_content'	=> $new_post_content,
			'post_modified'	=> current_time( 'mysql' ),
			'post_modified_gmt'	=> current_time( 'mysql', 1),
			'ID' => $post_id
		));

		$tags = wp_set_post_tags( $post, $new_tags );
		if( term_exists( $cat_id, 'category' ) )
			wp_set_post_categories( $post, array( $cat_id ) );
		
		$post = get_post( $post );

		if ( !$post )
			die( '-1' );

		if( function_exists( 'set_post_format' ) && $ginline_theme->supports_format( $_POST['post_format'] ) )
			set_post_format( $post->ID, $_POST['post_format'] );

		echo $post->ID;
	}
	
	function new_post() {
		global $ginline_theme;

		if ( 'POST' != $_SERVER['REQUEST_METHOD'] || empty( $_POST['action'] ) || $_POST['action'] != 'new_post' )
		    die( '-1' );

		if ( !is_user_logged_in() )
			die( '<p>'.__( 'Error: not logged in.', 'genesis-inline' ).'</p>' );

		if ( !current_user_can( 'edit_posts' ) )
			die( '<p>'.__( 'Error: not allowed to post.', 'genesis-inline' ).'</p>' );

		check_ajax_referer( 'ajaxnonce', '_ajax_post' );
		$user = wp_get_current_user();
		$user_id	= $user->ID;
		$post_content	= $_POST['posttext'];
		$tags		= trim( $_POST['tags'] );
		$title = $_POST['post_title'];
		$post_status = 'draft';
		if( 'publish' == $_POST['post_status'] && current_user_can( 'publish_posts' ) )
			$post_status = 'publish';

		// Strip placeholder text for tags
		if ( __( 'Tag it', 'genesis-inline' ) == $tags )
			$tags = '';

		if ( empty( $title ) || esc_js( __( 'Title', 'genesis-inline' ) ) == $title )
			// For empty or placeholder text, create a nice title based on content
			$post_title = GInlineAjax::title_from_content( $post_content );
		else
			$post_title = $title;

		require_once ( ABSPATH . '/wp-admin/includes/taxonomy.php' );
		require_once ( ABSPATH . WPINC . '/category.php' );

		/* Add the quote citation to the content if it exists */
		if ( !empty( $_POST['post_citation'] ) && esc_js( __( 'Citation', 'genesis-inline' ) ) != $_POST['post_citation'] ) {
			$post_content = '<p>' . $post_content . '</p><cite>' . $_POST['post_citation'] . '</cite>';
		}
		
		self::init_kses();
		$post_id = wp_insert_post( array(
			'post_author'	=> $user_id,
			'post_title'	=> $post_title,
			'post_content'	=> $post_content,
			'post_type'		=> 'post',
			'tags_input'	=> $tags,
			'post_status'	=> $post_status
		) );

		if( function_exists( 'set_post_format' ) && !empty( $_POST['post_format'] ) && $ginline_theme->supports_format( $_POST['post_format'] ) )
			set_post_format( $post_id, $_POST['post_format'] );

		echo $post_id ? $post_id : '0';
	}

	function get_latest_posts() {
		global $post_request_ajax, $post;

		$load_time = $_GET['load_time'];
		$frontpage = $_GET['frontpage'];
		$num_posts = 10; // max amount of posts to load
		$number_of_new_posts = 0;
		$visible_posts = isset( $_GET['vp'] ) ? (array)$_GET['vp'] : array();
		$post_id = (int) $_GET['post_id'];
		if( $post_id > 0 ) {
			add_filter( 'genesis_post_title_text', array( 'GInlineAjax', 'post_draft' ) );
			$user = wp_get_current_user();
			query_posts( 'showposts=' . $num_posts . '&post_status=publish,draft&post_author=' . $user->ID );
		} else
			query_posts( 'showposts=' . $num_posts . '&post_status=publish' );
		ob_start();
		while ( have_posts() ) : the_post();
			// Avoid showing the same post if it's already on the page
			if ( in_array( get_the_ID(), $visible_posts ) || ( $post_id && $post->post_status == 'publish' ) )
				continue;

			// Only show posts with post dates newer than current timestamp
			if ( get_gmt_from_date( get_the_time( 'Y-m-d H:i:s' ) ) <= $load_time )
				continue;

			$number_of_new_posts++;
			genesis_before_post();
		?>
			<div <?php post_class(); ?>>

				<?php genesis_before_post_title(); ?>
				<?php genesis_post_title(); ?>
				<?php genesis_after_post_title(); ?>

				<?php genesis_before_post_content(); ?>
				<div class="entry-content">
					<?php genesis_post_content(); ?>
				</div><!-- end .entry-content -->
				<?php genesis_after_post_content(); ?>

			</div><!-- end .postclass -->
		<?php
			genesis_after_post();
	    endwhile;
		remove_filter( 'genesis_post_title_text', array( 'GInlineAjax', 'post_draft' ) );
	   	$posts_html = ob_get_clean();

	    if ( $number_of_new_posts != 0 ) {
			nocache_headers();
	    	echo json_encode( array(
				'numberofnewposts' => $number_of_new_posts,
				'html' => $posts_html,
				'lastposttime' => gmdate( 'Y-m-d H:i:s' )
			) );
		} else {
			header("HTTP/1.1 304 Not Modified");
	    }
	}
	
	// from prologue
	function title_from_content( $content ) {

			static $strlen =  null;
			if ( !$strlen )
				$strlen = function_exists( 'mb_strlen' )? 'mb_strlen' : 'strlen';

			$max_len = 40;
			$title = $strlen( $content ) > $max_len? wp_html_excerpt( $content, $max_len ) . '...' : $content;
			$title = trim( strip_tags( $title ) );
			$title = str_replace("\n", " ", $title);

		//Try to detect image or video only posts, and set post title accordingly
		if ( !$title ) {
			if ( preg_match("/<object|<embed/", $content ) )
				$title = __( 'Video Post', 'genesis-inline' );
			elseif ( preg_match( "/<img/", $content ) )
				$title = __( 'Image Post', 'genesis-inline' );
		}
			return $title;
	}
	function post_draft( $title ) {
		return $title . __( ' - Draft' );
	}
	function init_kses() {
		kses_remove_filters(); // start with a clean slate
		kses_init_filters(); // set up the filters
	}		
}