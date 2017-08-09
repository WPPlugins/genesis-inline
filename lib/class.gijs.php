<?php

class GIJS {
	
	function init() {
		if ( is_admin() )
			return;

		add_action( 'wp_print_scripts', array( 'GIJS', 'enqueue_scripts' ) );
		add_action( 'wp_print_styles', array( 'GIJS', 'enqueue_styles' ) );
		add_action( 'wp_head', array( 'GIJS', 'print_options' ));
	}

	function enqueue_styles() {
		if ( is_front_page() && is_user_logged_in() )
			wp_enqueue_style( 'thickbox' );
	}

	function enqueue_scripts() {
		global $wp_locale;

		wp_enqueue_script( 'utils' );
		wp_enqueue_script( 'jquery-color' );
		wp_enqueue_script( 'comment-reply' );

		if ( is_user_logged_in() ) {
			wp_enqueue_script( 'suggest' );
			wp_enqueue_script( 'jeditable', GENESISINLINE_JS . 'jquery.jeditable.js', array( 'jquery' )  );

			// media upload
			if ( is_home() ) {
				$media_upload_js = 'js/media-upload.js';
				wp_enqueue_script( 'media-upload', admin_url( $media_upload_js ), array( 'thickbox' ), filemtime( ABSPATH . '/wp-admin/' . $media_upload_js ) );
			}

		}

		//bust the cache here	
		wp_enqueue_script( 'genesis-inline-js', GENESISINLINE_JS . 'post.js', array( 'jquery', 'utils' ), GENESISINLINE_VERSION );
		wp_localize_script( 'genesis-inline-js', 'GInline', array(
			'tags' => __( '<br />Tags:' , 'genesis-inline' ),
		    'tagit' => __( 'Tag it', 'genesis-inline' ),
			'citation'=> __( 'Citation', 'genesis-inline' ),
			'title' => __( 'Post Title', 'genesis-inline' ),
		    'goto_homepage' => __( 'Go to homepage', 'genesis-inline' ),
		    // the number is calculated in the javascript in a complex way, so we can't use ngettext
		    'n_new_updates' => __( '%d new update(s)', 'genesis-inline' ),
		    'n_new_comments' => __( '%d new comment(s)', 'genesis-inline' ),
		    'jump_to_top' => __( 'Jump to top', 'genesis-inline' ),
		    'not_posted_error' => __( 'An error has occurred, your post was not posted', 'genesis-inline' ),
		    'update_posted' => __( 'Your update has been posted', 'genesis-inline' ),
		    'loading' => __( 'Loading...', 'genesis-inline' ),
		    'cancel' => __( 'Cancel', 'genesis-inline' ),
		    'save' => __( 'Save', 'genesis-inline' ),
		    'hide_threads' => __( 'Hide threads', 'genesis-inline' ),
		    'show_threads' => __( 'Show threads', 'genesis-inline' ),
			'unsaved_changes' => __( 'Your comments or posts will be lost if you continue.', 'genesis-inline' ),
			'date_time_format' => __( '%1$s <em>on</em> %2$s', 'genesis-inline' ),
			'date_format' => get_option( 'date_format' ),
			'time_format' => get_option( 'time_format' ),
			// if we don't convert the entities to characters, we can't get < and > inside
			'l10n_print_after' => 'try{convertEntities(GInline);}catch(e){};',
		));
			
		wp_enqueue_script( 'scrollit', GENESISINLINE_JS .'jquery.scrollTo-min.js', array( 'jquery' )  );

		wp_enqueue_script( 'wp-locale', GENESISINLINE_JS . 'wp-locale.js', array(), filemtime(GENESISINLINE_DIR . 'js/wp-locale.js' ) );

		// the localization functinality can't handle objects, that's why
		// we are using poor man's hash maps here -- using prefixes of the variable names
		$wp_locale_txt = array();
		
		foreach( $wp_locale->month as $key => $month ) $wp_locale_txt["month_$key"] = $month;
		$i = 1;
		foreach( $wp_locale->month_abbrev as $key => $month ) $wp_locale_txt["monthabbrev_".sprintf( '%02d', $i++)] = $month;
		foreach( $wp_locale->weekday as $key => $day ) $wp_locale_txt["weekday_$key"] = $day;
		$i = 1;
		foreach( $wp_locale->weekday_abbrev as $key => $day ) $wp_locale_txt["weekdayabbrev_".sprintf( '%02d', $i++)] = $day;
		wp_localize_script( 'wp-locale', 'wp_locale_txt', $wp_locale_txt);
	}
	
	function print_options() {
		get_currentuserinfo();
		$page_options['nonce']= wp_create_nonce( 'ajaxnonce' );
		$page_options['prologue_updates'] = 1;
		$page_options['prologue_comments_updates'] = 1;
		$page_options['prologue_tagsuggest'] = 1;
		$page_options['prologue_inlineedit'] = 1;
		$page_options['prologue_comments_inlineedit'] = 1;
		$page_options['is_single'] = (int)is_single();
		$page_options['is_page'] = (int)is_page();
		$page_options['is_front_page'] = (int)is_front_page();
		$page_options['is_first_front_page'] = (int)(is_front_page() && !is_paged() );
		$page_options['is_user_logged_in'] = (int)is_user_logged_in();
		$page_options['login_url'] = wp_login_url( ( ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
?>
	<script type="text/javascript" charset="<?php bloginfo( 'charset' ); ?>">
		// <![CDATA[
		// Prologue Configuration
		// TODO: add these int the localize block
		var ajaxUrl = "<?php echo esc_js( admin_url( 'admin-ajax.php?GI-ajax=true' ) ); ?>";
		var updateRate = "60000"; // 1 minute
		var nonce = "<?php echo esc_js( $page_options['nonce'] ); ?>";
		var login_url = "<?php echo $page_options['login_url'] ?>";
		var templateDir  = "<?php esc_js( bloginfo( 'template_directory' ) ); ?>";
		var isFirstFrontPage = <?php echo $page_options['is_first_front_page'] ?>;
		var isFrontPage = <?php echo $page_options['is_front_page'] ?>;
		var isSingle = <?php echo $page_options['is_single'] ?>;
		var isPage = <?php echo $page_options['is_page'] ?>;
		var isUserLoggedIn = <?php echo $page_options['is_user_logged_in'] ?>;
		var prologueTagsuggest = <?php echo $page_options['prologue_tagsuggest'] ?>;
		var prologuePostsUpdates = <?php echo $page_options['prologue_updates'] ?>;
		var prologueCommentsUpdates = <?php echo $page_options['prologue_comments_updates']; ?>;
		var getPostsUpdate = 0;
		var getCommentsUpdate = 0;
		var inlineEditPosts =  <?php echo $page_options['prologue_inlineedit'] ?>;
		var inlineEditComments =  <?php echo $page_options['prologue_comments_inlineedit'] ?>;
		var wpUrl = "<?php echo esc_js( get_bloginfo( 'wpurl' ) ); ?>";
		var rssUrl = "<?php esc_js( get_bloginfo( 'rss_url' ) ); ?>";
		var pageLoadTime = "<?php echo gmdate( 'Y-m-d H:i:s' ); ?>";
		var latestPermalink = "<?php echo esc_js( latest_post_permalink() ); ?>";
		var original_title = document.title;
		var commentsOnPost = new Array;
		var postsOnPage = new Array;
		var postsOnPageQS = '';
		var currPost = -1;
		var currComment = -1;
		var commentLoop = false;
		var lcwidget = false;
		var hidecomments = false;
		var commentsLists = '';
		var newUnseenUpdates = 0;
		 // ]]>
		</script>
<?php		
	}
}
