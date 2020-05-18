<?php
/**
 * Plugin Name:     Deploy & Backup
 * Plugin URI:      https://timnash.co.uk
 * Description:     Tooling for deploying and backing up sites
 * Author:          Tim Nash
 * Author URI:      https://timnash.co.uk
 * Version:         0.3.0
 *
 * @package         Tn_Deploy
 */
 /**
   *
   * @access public
   * @since 0.1.0
   * @return bool
  */
add_action( 'build_trigger',
  function( $args ){
    return update_option( 'tn_last_build', time() );
  }
);
/**
  *
  * @access public
  * @since 0.1.0
  * @return bool
 */
add_action( 'core_upgrade_preamble',
  function(){
    $last_build = get_option( 'tn_last_build' );
    if( ! isset($last_build )) return;
    echo '<h2>'. __( 'Git Deployment' ) .'</h2>';
    echo '<p>';
    printf( __( 'Last Deployed on %1$s at %2$s.' ),
          date_i18n(__( 'F j, Y' ), $last_build ),
          date_i18n(__( 'g:i a' ), $last_build )
    );
    echo '</p>';
  }
);

if ( defined( 'WP_CLI' ) && WP_CLI ) {
  /**
    *
    * @access public
    * @since 0.2.0
    * @return string
   */
  WP_CLI::add_command( 'deploy', function( $args, $assoc_args ){
      if( $args[0] == 'run' ){
        if( isset( $args[1]) && file_exists( $args[1]  ) ){
          $file = $args[1];
        }else{
          return WP_CLI::error('No File specified');
        }
        //let's open our plugin file
        $file = fopen( $file, 'r' );
        $plugin = [];
        $fetcher = new WP_CLI\Fetchers\Plugin();
        //Log The installs
        if( function_exists('wp_stream_get_instance') ){
          $stream = wp_stream_get_instance();
          $logger = new \WP_Stream\Connector_Installer( $stream->log );
        }
        //Get Plugin details
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        while( ! feof( $file ) ){
          $plugin = trim( fgets( $file ) );
          if( !$fetcher->get( $plugin ) && strlen( $plugin ) > 0 ){
            $cmd = WP_CLI::runcommand(
                    'plugin install '.$plugin,
                    [ 'launch' => true ]
                  );
            if (!empty( $cmd->stderr )) {
                 WP_CLI::warning( substr( $cmd->stderr, 9 ) );
            }
            $cmd = WP_CLI::runcommand(
                    'plugin activate '.$plugin,
                    [ 'launch' => true ]
                  );
            if (!empty($cmd->stderr)) {
                 WP_CLI::warning( substr( $cmd->stderr, 9 ) );
            }

            $details = get_plugins( '/' . $plugin );
            $details = reset( $details );
            $name = $details['Name'];
            $message ='"'.$name.'" Plugin Installed & Activated by Deploy script';
            //Log the install and activation
            if( issset($logger) ){
              $logger->log( $message,[$plugin],null,'plugins','Install' );
            }
          }
        }
        fclose( $file );
        WP_CLI::success("Deployment Complete: Plugins");
      }elseif( $args[0] == 'backup' ){
        /*
         * Backups should be DB / uploads Both
         */
        $type = 'both';
        $types = ['both','db','uploads'];
        $site = str_replace(['/','.'],'',strstr(get_option( 'home' ), '//'));
        $stamp = date("dmY-Hi");
        $tmp_folder = WP_CLI\Utils\get_temp_dir().$site.'-'.$stamp;
        $output = $site.'-'.$stamp.'.tar.gz';
        $dest = $tmp_folder.'/'.$output;
        $files = [];
        $success = true;
        if( isset( $args[1]) && in_array( $args[1], $types )) $type = $args[1];

        if( !mkdir( $tmp_folder ) ){
          $success = false;
          WP_CLI::error( 'Could not create backup folder' );
        }
        /*
         * Get List of Active Plugins
         */
        if( 'both' === $type ){
          $cmd = WP_CLI::runcommand(
               'plugin list --status=active --field=name',[
                 'launch' => true,
                 'return' => 'all'
               ]);
          if( isset($cmd->stdout)) {
              file_put_contents( $tmp_folder.'/plugins.txt', $cmd->stdout, LOCK_EX );
              $files[] = $tmp_folder.'/plugins.txt';
          }
        }
        if( 'both' === $type || 'db' === $type ){
          $cmd = WP_CLI::runcommand(
               'db export '.$tmp_folder.'/db.sql',[
                'launch'     => true,
                'return'     => 'all',
                'exit_error' => false
              ]);
          if (!empty( $cmd->stderr )) {
            $success = false;
            WP_CLI::warning( substr( $cmd->stderr, 9 ) );
          }
          if( ! file_exists( $tmp_folder.'/db.sql' ) ){
            $success = false;
            WP_CLI::warning( "DB Backup Failed: File does not exist!" );
          }else{
            $files[] = $tmp_folder.'/db.sql';
          }
        }
        if( 'both' === $type || 'uploads' === $type ){
            $upload_dir = wp_upload_dir();
            $files[] = $upload_dir['basedir'];
        }
        if( ! empty( $files )){
          $export_list = implode(' ', $files );
          $cmd = "tar -czf $dest $export_list";
          WP_CLI::launch( $cmd );
          if( ! file_exists( $dest ) ){
            $success = false;
            WP_CLI::warning( "Backup Failed: No Tar File" );
          }
        }else{
          $success = false;
          WP_CLI::warning( "DB Backup Failed: No Files to Backup" );
        }
        if( true === $success ){
          /*
           * Let's move it to B2 using rclone.
           */
          if( !defined('RCLONEPATH')) define('RCLONEPATH', '/usr/local/bin/rclone');
          if( !defined('RCLONECONFIG')) define('RCLONECONFIG', '~/rclone.conf');
          $cmd =  RCLONEPATH." move $tmp_folder backup:$site --config=".RCLONECONFIG;
          $cmd = WP_CLI::launch( $cmd, false, true );
          if( !empty( $cmd->stderr ) ){
            WP_CLI::warning( $cmd->stderr );
          }else{
            WP_CLI::success("Backup complete");
          }
        }else{
          WP_CLI::error("Backup Failed");
        }
      }else{
        WP_CLI::warning("Deploy options are: wp deploy [run]/[backup] only");
      }
    }
  );
}
