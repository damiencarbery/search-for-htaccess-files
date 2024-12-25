<?php
/*
Plugin Name: Search for .htaccess files
Plugin URI: https://www.damiencarbery.com/2024/10/search-for-htaccess-files/
Description: Quickly find hacker files - get a list of .htaccess files in wp-includes and wp-content and php files in wp-content.
Author: Damien Carbery
Author URI: https://www.damiencarbery.com/
Version: 0.4.20241225
*/


class SearchForHtaccessFiles {
	
	private $wpincludes, $wpcontent, $uploads_dir;


	// Returns an instance of this class. 
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		} 
		return self::$instance;
	}


	// Initialize the plugin variables.
	public function __construct() {
		$this->init();
	}


	// Set up WordPress specfic actions.
	public function init() {
		$this->wpincludes = ABSPATH . WPINC;
		$this->wpcontent = WP_CONTENT_DIR;
		$this->uploads_dir = wp_get_upload_dir()['basedir'];

		add_action( 'admin_menu', array( $this, 'search_htaccess_files_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_js' ) );
		
		// AJAX to view the file.
		add_action( 'wp_ajax_view_htaccess', array( $this, 'ajax_view_file' ) );  // Only logged in users can access this.
	}


	public function search_htaccess_files_menu() {
		// ToDo: Consider a capability check to limit this to admins or editors.
		add_management_page( 'Search for .htaccess files', 'Search for .htaccess files', 'manage_options', 'search-for-htaccess-files', array( $this, 'search_htaccess_files' ) );
	}


// ToDo: Maybe move JS file into this PHP file.
	public function enqueue_js() {
		wp_enqueue_script( 'dcwd-htaccess', plugin_dir_url( __FILE__ ). 'search-for-htaccess-files.js', array('jquery-ui-dialog') );
		wp_enqueue_style( 'wp-jquery-ui-dialog' );
		wp_localize_script( 'dcwd-htaccess', 'view_htaccess', array(
                      'ajax_url' => admin_url( 'admin-ajax.php' ),
                      'view_nonce' => wp_create_nonce('view_nonce') 
		));
	}


	public function ajax_view_file() {
		check_ajax_referer( 'view_nonce', 'nonce' );  // This function will die if nonce is not correct.

		// The path must start with wp-includes or wp-content so reject paths that don't.
		$path = sanitize_text_field( $_POST['path'] );
		if ( 0 !== strpos( $path, 'wp-includes' ) && 0 !== strpos( $path, 'wp-content' ) ) {
			wp_send_json( array( 'statusText' => 'Invalid path.', 'status' => 'Error', 'fileContent' => null ) );
		}
		
		// Add the ABSPATH prefix back and get the real path (without '..' etc).
		$path = realpath( ABSPATH . $path );

		// ToDo: Check that path ends in .htaccess or .php.

		// Verify that real path is still below wp-content or wp-includes.
		if ( 0 !== strpos( $path, ABSPATH . 'wp-includes' ) && 0 !== strpos( $path, ABSPATH . 'wp-content' ) ) {
			wp_send_json( array( 'statusText' => 'Prohibitied path above wp-content or wp-includes area.', 'status' => 'Error', 'fileContent' => null ) );
		}
		
		// ToDo: Maybe exclude filename, maybe provide it via a different parameter.
		$contents = file_get_contents( $path );
		if ( $contents ) {
			wp_send_json( array( 'statusText' => 'OK', 'status' => 'OK', 'fileContent' => $contents ) );
		}
		else {
			wp_send_json( array( 'statusText' => 'Error reading file contents.', 'status' => 'Error', 'fileContent' => null ) );
		}
	}


	// Reduce the full path to the relative path under wp-includes, wp-content or wp-content/uploads.
	private function generate_link_markup( $path ) {
		// Remove the absolute path to leave the portion below wp-includes, wp-content or wp-content/uploads.
		$display_path = str_replace( array( $this->wpincludes, $this->uploads_dir, $this->wpcontent ), array( '', '', '' ), $path );
		// If the file size is zero do not create a link.
		$filesize = filesize( $path );
		// Trim the full path so as not to reveal the filesystem structure.
		$path = str_replace( ABSPATH, '', $path );
		
		if ( $filesize ) {
			return sprintf( '<a href="#" class="view-htaccess" data-path="%s" title="Click to view %s">%s</a> (%s bytes)', $path, $display_path, $display_path, number_format( $filesize ) );
		}
		else {
			return $display_path . ' (empty file)';
		}
	}


	public function search_htaccess_files() {
	?>
	<div class="wrap">
		<h2>Search for .htaccess files</h2>
		<p>This plugin searches wp-includes and wp-content directories for .htaccess files. Ideally your installation only has 2 copies (these prevent execution of PHP files in those directories and their subdirectories).
		<br>It also searches the wp-content/uploads directories for .php files. Ideally there aren't any.</p>
		<p><em>wp-includes</em> is at: <strong><?php echo $this->wpincludes; ?></strong>
		<br><em>wp-content</em> is at: <strong><?php echo $this->wpcontent; ?></strong>
		<br><em>wp-content/uploads</em> is at: <strong><?php echo $this->uploads_dir; ?></strong></p>
		
		<!-- Add markers to the list items to aid readability. -->
		<style>ul.found-files { list-style: disc; } ul.found-files li { margin-left: 20px; }</style>
	<?php
		$htaccess_regex = '/\.htaccess$/';

		// Search the wp-includes directory structure.
		$Iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( $this->wpincludes ));
		$Regex = new RegexIterator( $Iterator, $htaccess_regex, RegexIterator::GET_MATCH );
		$files = array_keys( iterator_to_array( $Regex ) );
		$files = array_map( [$this, 'generate_link_markup'], $files );
		sort(  $files );
		$count = count( $files );
		if ( $count == 1 ) {
			printf( '<p>One .htaccess file found in <strong>%s</strong></p>', $this->wpincludes );
			echo '<ul class="found-files"><li>', implode( '</li><li>', $files ), '</li></ul>';
		}
		elseif ( $count > 1 ) {
			printf( '<p>%d .htaccess files found in <strong>%s</strong>. Please review their contents.</p>', $count, $this->wpincludes );
			echo '<ul class="found-files"><li>', implode( '</li><li>', $files ), '</li></ul>';
		}
		else {
			echo '<p>No .htaccess files found in the <strong>wp-includes</strong> directory structure. Consider adding one to "deny from all"</p>';
		}

		// Search the wp-content directory structure.
		$Iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( $this->wpcontent ));
		$Regex = new RegexIterator( $Iterator, $htaccess_regex, RegexIterator::GET_MATCH );
		$files = array_keys( iterator_to_array( $Regex ) );
		$files = array_map( [$this, 'generate_link_markup'], $files );
		sort(  $files );
		$count = count( $files );
		if ( $count == 1 ) {
			printf( '<p>One .htaccess file found in <strong>%s</strong></p>', $this->wpcontent );
			echo '<ul class="found-files"><li>', implode( '</li><li>', $files ), '</li></ul>';
		}
		elseif ( $count > 1 ) {
			printf( '<p>%d .htaccess files found in <strong>%s</strong>. Please review their contents.</p>', $count, $this->wpcontent );
			echo '<ul class="found-files"><li>', implode( '</li><li>', $files ), '</li></ul>';
		}
		else {
			echo '<p>No .htaccess files found in the <strong>wp-content</strong> directory structure.</p>';
		}

		// Search the wp-content/uploads directory structure for php files.
		$php_regex = '/\.php$/';
		$Iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator( $this->uploads_dir ));
		$Regex = new RegexIterator( $Iterator, $php_regex, RegexIterator::GET_MATCH );
		$files = array_keys( iterator_to_array( $Regex ) );

		$files = array_map( [$this, 'generate_link_markup'], $files );
		sort(  $files );
		$count = count( $files );
		if ( $count == 1 ) {
			printf( '<p>One PHP file found in <strong>%s</strong></p>', $this->uploads_dir );
			echo '<ul class="found-files"><li>', implode( '</li><li>', $files ), '</li></ul>';
		}
		elseif ( $count > 1 ) {
			printf( '<p>%d PHP files found in <strong>%s</strong>. Please review their contents.</p>', $count, $this->uploads_dir );
			echo '<ul class="found-files"><li>', implode( '</li><li>', $files ), '</li></ul>';
		}
		else {
			echo '<p>No php files found in the <strong>wp-content/uploads</strong> directory structure.</p>';
		}

	?>
		<div id="view-htaccess" style="display:none" title="View file">
			<p>Inside the #view-htaccess dialog.</p>
		</div>
	</div>
	<?php
	}
}

$SearchForHtaccessFiles = new SearchForHtaccessFiles;
