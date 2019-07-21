<?php
/**
 * The class responsible for finding template file paths and related functionality
 *
 * Looks for the files in default wordpress theme first and falls back to plugin's version if unavailable
 *
 * @link       https://e-leven.net/
 * @since      1.1.0
 *
 * @package    My_movie_database
 * @subpackage My_movie_database/public
 * @author     Kostas Stathakos <info@e-leven.net>
 */
namespace MyMovieDatabase;

use MyMovieDatabase\Admin\AdminController;

class TemplateFiles {

    const SMALL_PLACEHOLDER = 'cinema';
    const MEDIUM_PLACEHOLDER = 'cinema185';
    const LARGE_PLACEHOLDER = 'cinema300';
    const ASSETS_FOLDER = 'assets/';
    const TEMPLATES_FOLDER = 'mmdb_templates/';
    const ASSETS_PATH = self::TEMPLATES_FOLDER . self::ASSETS_FOLDER;
    const IMG_PATH = 'img';

    /**
     * Return the correct private template file path
     * Check if file exists in the theme folder, if not load the plugin template file
     *
     * @since    1.0.0
     * @param      string   $path       The relative to the plugin's templates folder path
     * @param      string   $filename   The full filename (with extension)
     * @return     string
     */
    public static function getPrivateFile($path, $filename) {
        $filePath = self::TEMPLATES_FOLDER . $path . '/' . $filename;

        if ($theme_file = locate_template([$filePath])) {
            $file = $theme_file;
        }
        else {
            $file = plugins_url( $filePath, dirname(__FILE__) );
        }
        self::validateFileExists($file);

        return $file;
    }

    /**
     * Return the correct public template file url ( for css, js, placeholder img, etc)
     * Check if file exists in the theme folder, if not load the plugin template file
     *
     * @since      1.0.8
     * @param      string        $file_name  The file name.
     * @param      string        $path       The file folder path.
     * @param      null | string $ext        The file extension - if null defaults to folder path $path
     * @param      bool          $assets     Whether to prefix the assets folder in path
     * @return     string
     */
    public static function getPublicFile($file_name, $path, $ext = null, $assets = true) {

        if(!$ext) {$ext = $path;}
        if($assets) {
            $folder = self::ASSETS_PATH;
        } else {
            $folder = self::TEMPLATES_FOLDER;
        }
        if($path === '') {
            $path =  $folder . $file_name . '.' . $ext;
        } else {
            $path =  $folder . $path . '/' . $file_name . '.' . $ext;
        }

        if (locate_template(array($path))) {
            $file = get_theme_file_uri('/' . $path);
        }
        else {
            $file = plugin_dir_url( dirname(__FILE__)) . $path;
        }
        self::validateFileExists($file, $path);

        return $file;
    }

    /**
     * Validate a file exists
     *
     * @since      2.0.0
     * @param      string   $file   The file full path.
     * @param      string   $type   The file folder path and/or type/extension.
     * @return     void
     */
    private static function validateFileExists($file, $type = '') {

        try{
            $openFile = @fopen($file,'r');
            if( !$openFile ) {
                throw new \Exception("The My Movie Database $type file $file could not be found",404);
            }
        }
        catch( \Exception $e ){
            echo "Error : " . $e->getMessage();
            return;
        }
    }

    /**
     * Register the JavaScript and CSS for both public-facing and admin sides of the site.
     *
     *
     * @since    1.0.0
     * @param    $activeScreen  boolean
     */
    public static function enqueueCommonFiles($activeScreen) {

        $css_file =
            AdminController::getMmdbOption('mmdb_css_file', MMDB_ADVANCED_OPTION_GROUP, 'yes');
        $bootstrap =
            AdminController::getMmdbOption('mmdb_bootstrap', MMDB_ADVANCED_OPTION_GROUP, 'yes');

        // To load only on mmdb active post type pages.
        if($activeScreen) {
            /** The TMDB API (TheMovieDatabase) wrapper used */
            wp_register_script( MMDB_NAME, plugin_dir_url( __FILE__ ) . MMDB_CAMEL_NAME . '.js', array(), 0.1, true);
            /**
             * - vue and vuex : lovable js framework and it's state management (vuex)
             * - httpVueLoader : use vue template files (SFCs) without a node.js environment or build step.
             */
            wp_enqueue_script(
                'vue', MMDB_PLUGIN_URL . 'vendor/Vue/vue-min.js', array( 'jquery' ), '2.6.10', true );
            wp_enqueue_script(
                'vuex', MMDB_PLUGIN_URL . 'vendor/Vue/vuex-min.js', array( 'vue' ), '2.0.0', true );
            wp_enqueue_script(
                'httpVueLoader', MMDB_PLUGIN_URL . 'vendor/Vue/httpVueLoader.js', array( 'vue' ), 0.1, true);
            wp_enqueue_script( MMDB_NAME );
            wp_add_inline_script(
                MMDB_NAME, '
                var mmdb_conf = {
                    locale: "' . get_locale() . '",
                    debug: ' . AdminController::getMmdbOption("mmdb_debug", "mmdb_opt_advanced", false) . ',
                    date_format: "' . get_option( 'date_format' ) . '",
                    overviewOnHover: ' . AdminController::getMmdbOption("mmdb_overview_on_hover", "mmdb_opt_advanced", true) .'
                }',
                'before'
            );
            if( $css_file === 'yes') {
                $css_file = 'all';
            }
            if ($bootstrap === 'yes') {
                $bootstrap = 'all';
            }
        }
        if ($bootstrap === 'all'){
            wp_enqueue_style(
                'bootstrap', TemplateFiles::getPublicFile('bootstrap', 'css'), [], '3.3.7' );
        }
        if( $css_file === 'all') {
            wp_enqueue_style(
                MMDB_NAME, TemplateFiles::getPublicFile(MMDB_CAMEL_NAME, 'css'), [], '2.0.0', 'all' );
        }
        // Load for all wp pages below.
    }

    /**
     * Get the contents of a template file
     *
     * @since      2.0.0
     * @param      string $path
     * @param      string $filename
     * @return     string | null
     */
    public static function getJsonFileContents($path, $filename) {
        return file_get_contents(self::getPrivateFile($path, $filename));
    }

    /**
     * Get the contents of a I18n Javascript settings file
     *
     * @since      2.0.0
     * @param      string $type
     * @return     string | null
     */
    public static function getJavascriptI18nSetting($type) {
        return self::getJsonFileContents('settings/i18nForJavascript', 'jsI18n-' . $type . '.json');
    }

    /**
     * Get the contents of a vue components to load settings file
     *
     * @since      2.0.0
     * @param      string $type
     * @return     string | null
     */

    public static function getVueComponentsToLoadSetting($type) {
        return self::getJsonFileContents('settings/componentsToLoad', 'components-' . $type . '.json');
    }

    /**
     * Get the image placeholder url.
     *
     * @since      2.0.0
     * @param      string $placeholderSize
     * @param      string $type
     * @param      string $ext
     * @return     string
     */
    public static function getImagePlaceholder(
        $placeholderSize = 'medium',
        $type = self::IMG_PATH,
        $ext = 'png'
    ) {

        return self::getPublicFile(self::getPlaceholderImage($placeholderSize), $type, $ext);
    }
    /**
     * Get the small image placeholder url.
     *
     * @since      2.0.0
     * @param      string $type
     * @param      string $ext
     * @return     string
     */
    public static function getSmallImagePlaceholder($type = self::IMG_PATH, $ext = 'png') {

        return self::getImagePlaceholder('small', $type, $ext);
    }
    /**
     * Get the large image placeholder url.
     *
     * @since      2.0.0
     * @param      string $type
     * @param      string $ext
     * @return     string
     */
    public static function getLargeImagePlaceholder($type = self::IMG_PATH, $ext = 'png') {

        return self::getImagePlaceholder('large', $type, $ext);
    }

    /**
     * Get the image placeholder file based on size.
     *
     * @since      1.0.0
     * @param      string $placeholderSize
     * @return     string
     */
    public static function getPlaceholderImage($placeholderSize) {

        if ($placeholderSize === 'small') {
            return self::SMALL_PLACEHOLDER;
        } else if($placeholderSize === 'medium') {
            return self::MEDIUM_PLACEHOLDER;
        } else if($placeholderSize === 'large') {
            return self::LARGE_PLACEHOLDER;
        } else {
            return self::SMALL_PLACEHOLDER;
        }
    }

}