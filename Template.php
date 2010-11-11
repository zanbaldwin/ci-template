<?php

/**
 * CodeIgniter
 *
 * An open source application development framework for PHP 4.3.2 or newer
 *
 * @package   CodeIgniter
 * @author    ExpressionEngine Dev Team
 * @copyright Copyright (c) 2008 - 2010, EllisLab, Inc.
 * @license   http://codeigniter.com/user_guide/license.html
 * @link      http://codeigniter.com
 * @since     Version 1.0
 * @filesource
 */

  if(!defined('BASEPATH')) {
    if(!headers_sent()) {
      header('HTTP/1.1 404 Not Found', true, 404);
    }
    exit('No direct script access allowed');
  }

// -----------------------------------------------------------------------------

  /**
   * CodeIgniter Template Base Class
   *
   * Common methods and properties to be shared between Template and
   * Template_Section. See Template DocComment for more details about this
   * library.
   *
   * @package    CodeIgniter
   * @subpackage Application
   * @category   Libraries
   * @author     Alexander Baldwin
   * @copyright  (c) 2010 Alexander Baldwin
   * @license    http://www.opensource.org/licenses/mit-license.php MIT License
   * @link       http://github.com/mynameiszanders/ci-template/blob/master/Template.php
   * @version    1.0
   */
  class Template_Base {

    // The following are static so they are common between each object that
    // extends this base.
    protected static $subdir = '',
                     $prefix = '';
    // The following is the regex to check that a variable name is
    // valid for PHP, as there is no point allowing a variable that
    // can't be used within a view (we won't go into the
    // technicalities of quoted brace variables!).
    protected $valid_name = '[a-zA-Z_][a-zA-Z0-9_]*';

    /**
     * Is Varname
     *
     * @access protected
     * @param  string $varname
     * @return boolean
     */
    protected function is_varname($varname) {
      if(!is_string($varname) || !preg_match('/^' . $this->valid_name . '$/', $varname)) {
        return false;
      }
      return true;
    }

    /**
     * View Exists
     *
     * @access public
     * @param  string $view
     * @return boolean
     */
    public function view_exists($view) {
      if(!is_string($view)) {
        return false;
      }
      $file = 'views/'
            . self::$subdir
            . self::$prefix
            . $view
            . EXT;
      // The following is to make this library work with Wiredesignz HMVC
      // extension. Same for Template_Base::view_path().
      $file = defined('MODBASE')
            ? MODBASE . $this->CI->uri->segment(2) . '/' . $file
            : APPPATH . $file;
      return file_exists($file);
    }

    /**
     * View Path
     *
     * Return the full path to the view, including theme folder, sub directory
     * and file prefix. Return false if the does not exist.
     *
     * @access protected
     * @param  string $view
     * @return string|false
     */
    protected function view_path($view) {
      if(!$this->view_exists($view)) {
        return false;
      }
      $file = '/views/'
            . self::$subdir
            . self::$prefix
            . $view
            . EXT;
      $file = defined('MODBASE')
            ? MODBASE . $this->CI->uri->segment(2) . '/' . $file
            : APPPATH . $file;
      return realpath($file);
    }

    /**
     * Set Subdirectory
     *
     * @access public
     * @param  string $dir
     * @return boolean
     */
    public function set_dir($dir) {
      if(!is_string($dir)) {
        return false;
      }
      $dir = trim($dir, '/');
      $path = APPPATH . '/views/' . $dir;
      if(!is_dir($path)) {
        return false;
      }
      self::$subdir = $dir;
      return true;
    }

    /**
     * Set File Prefix
     *
     * Just to be arsey, we're only going to allow valid variable names to be
     * prefixes!
     *
     * @access public
     * @param  string $prefix
     * @return boolean
     */
    public function set_prefix($prefix) {
      if(!$this->is_varname($prefix)) {
        return false;
      }
      self::$prefix = $prefix;
      return true;
    }

    /**
     * Load View
     *
     * This performs exactly the same as CodeIgniters Loader::view() function,
     * except that it returns instead of echos by default, and does NOT cache
     * variables. Returns false if the view does not exist.
     *
     * We do not need to bother with OB levels, because this library defeats the
     * object of calling views from within views.
     * Nor do we have to bother with rewriting PHP short tags. Why? BECAUSE YOU
     * SHOULDN'T BE WORKING WITH THEM.
     *
     * @access protected
     * @param string $view
     * @param array $vars
     * @param boolean $return
     * @return string|false
     */
    protected function load_view($view, $vars = array(), $return = true) {
      if(!is_string($view) || !$this->view_exists($view)) {
        return false;
      }
      // Obscure the variables we don't want overwritten. And yeah, that is a
      // number at the beginning of the variable :D
      ${'1view'} = $this->view_path($view);
      ${'1return'} = $return;
      unset($view, $return);
      // We can allow the extract() function to overwrite variable because we
      // already know they have been checked with Template::is_varname().
      extract($vars, EXTR_OVERWRITE);
      ob_start();
      include ${'1view'};
      log_message('debug', 'File loaded (as view): ' . ${'1view'});
      $output = ob_get_contents();
      ob_end_clean();
      if(${'1return'}) {
        return $output;
      }
      echo $output;
    }

  }

// -----------------------------------------------------------------------------

  /**
   * CodeIgniter Template Class
   *
   * This class enables you to organise multiple views into complex, nested
   * sections and provide each one with separate data, group sections together
   * and link these entities together to create a page.
   *
   * @package    CodeIgniter
   * @subpackage Application
   * @category   Libraries
   * @author     Alexander Baldwin
   * @copyright  (c) 2010 Alexander Baldwin
   * @license    http://www.opensource.org/licenses/mit-license.php MIT License
   * @link       http://github.com/mynameiszanders/ci-template/blob/master/Template.php
   * @version    1.0
   */
  class Template extends Template_Base {

    protected $links = array(),
              $sections = array(),
              $active = false,
              $autolink = true,
              $propagate = false,
              // The following is hard-coded, and should not be changed.
              $section_class = 'Template_Section';
    // This library was developed whilst a version of CodeIgniter using
    // wiredesignz' HMVC Modular Extentions, which threw a hissy fit if the $CI
    // variable was made protected.
    public $CI;

    /**
     * Constructor Function
     * 
     * Enter some amazing description here ...
     * 
     * @access public
     * @param array $params
     * @return void
     */
    public function __construct($params = false) {
      log_message('debug', 'Template Class Initialized');
      $this->CI =& get_instance();
      // If parameters have been passed, set them now so that the user does not
      // have to call the individual methods later.
      if(is_array($params)) {
        if(isset($params['config'])) {
          $this->autoload($params['config']);
        }
        if(isset($params['subdir'])) {
          $this->set_dir($params['subdir']);
        }
        if(isset($params['prefix'])) {
          $this->set_prefix($params['prefix']);
        }
        if(isset($params['autolink'])) {
          $this->autolink($params['autolink']);
        }
      }
    }

    /**
     * Section (or Group) Exists
     *
     * @access public
     * @param  string $section
     * @return boolean
     */
    public function section_exists($section) {
      if(is_object($section)) {
        $section = $this->section_name($section);
      }
      if(!is_string($section)) {
        return false;
      }
      return isset($this->sections[$section]);
    }

    /**
     * Section Name
     *
     * Return the section name as a string.
     *
     * @access protected
     * @param  object|string $section
     * @return string|false
     */
    protected function section_name($section) {
      // If the section is already passed as a string, return it straight away.
      if(is_string($section)) {
        return $section;
      }
      if(!is_object($section)
      || !($section instanceof $this->section_class)
      || get_class($section) != $this->section_class) {
        return false;
      }
      return $section->name();
    }

    /**
     * Set Autolink
     *
     * Decide whether you want section links to be combined automatically, or
     * manually via the Template::link() function.
     *
     * @access public
     * @param boolean $bool
     * @return void
     */
    public function autolink($bool) {
      $this->autolink = $bool ? true : false;
    }

    /**
     * Set Data Propagation
     *
     * Decide whether you want a sections added data to pass down throughout
     * it's children (until it gets overwritten in the child section).
     *
     * For this to work, we cannot use CodeIgniters Loader::view() method, as it
     * caches every single variable that it gets passed. To implement data
     * propagation properly, we must write our own (the whole point of this
     * library is to NOT touch a single line of core code).
     *
     * @access public
     * @param boolean $bool
     * @return void
     */
    public function propagate($bool) {
      $this->propagate = $bool ? true : false;
    }

    /**
     * Autoload Template Config
     *
     * Autoload a config file to pre-call create, group, link and add functions.
     * This saves time and space when quite a few controllers are going to use
     * the same structure.
     * You may also pass your config directly as an array, but this kinda
     * defeats the point... Shorthand I guess...
     *
     * @access public
     * @param string|array $config
     * @return boolean
     */
    public function autoload($config = 'template') {
      if(!is_array($config)) {
        if(!is_string($config) || !is_array($config = get_config($config))) {
          return false;
        }
      }
      // Autoload, in this order: set_dir(), set_prefix(), create(), group(),
      //                          autolink() || link().
      if(isset($config['set_dir']) && is_string($config['set_dir'])) {
        $this->set_dir($config['set_dir']);
      }
      if(isset($config['set_prefix']) && is_string($config['set_prefix'])) {
        $this->set_prefix($config['set_prefix']);
      }
      if(isset($config['create']) && is_array($config['create'])) {
        $this->create($config['create']);
      }
      if(isset($config['group']) && is_array($config['group'])) {
        foreach($config['group'] as $group_name => $group_sections) {
          if(is_string($group_name) && is_array($group_sections)) {
            $this->group($group_name, $group_sections);
          }
        }
      }
      if(isset($config['add']) && is_array($config['add'])) {
        foreach($config['add'] as $section_name => $section_data) {
          if(is_string($section_name)
             && $this->section_exists($section_name)
             && is_array($section_data)
          ) {
            $this->section($section_name)->add($section_data);
          }
        }
      }
      if(isset($config['autolink']) && is_bool($config['autolink'])) {
        $this->autolink($config['autolink']);
      }
      if(!$this->autolink && isset($config['link']) && is_array($config['link'])) {
        $this->link($config['link']);
      }
      return true;
    }

    /**
     * Create Section(s)
     *
     * Create one or more sections from views.
     *
     * @access public
     * @param array|object OR string+string
     * @return void
     */
    public function create() {
      $args = func_get_args();
      switch(func_num_args()) {
        case 1:
          if(!is_array($args[0])) {
            if(!is_object($args[0]) && !is_string($args[0])) {
              return;
            }
            $args[0] = (array) $args[0];
          }
          break;
        case 2:
          if(!is_string($args[0]) || !is_string($args[1])) {
            return;
          }
          $args[0] = array($args[0] => $args[1]);
          break;
        default:
          return false;
          break;
      }
      foreach($args[0] as $name => $view) {
        // If the section already exists, there is no point creating a new one;
        // you'd lose all your data!
        if($this->section_exists($name)) {
          continue;
        }
        // Shortcut for lazy people, if no array key is given, use the view as
        // the name. If something other than a valid string is passed as the key
        // just continue.
        $name = is_int($name) ? $view : $name;
        if(!$this->is_varname($name)) {
          continue;
        }
        // You can't make a section if the view doesn't exist!
        if(!$this->view_exists($view)) {
          continue;
        }
        // All checks have passed, let's create that section!
        $this->sections[$name] = new $this->section_class($name, $view);
        $this->active = $name;
      }
    }

    /**
     * Anonymous Section(s)
     *
     * Create sections from content strings, rather than loading from a view.
     * Sections created with this method cannot have data added to them,
     * however.
     *
     * @access public
     * @params array|object OR string+string
     * @return void
     */
    public function acreate() {
      $args = func_get_args();
      switch(func_num_args()) {
        case 1:
          if(!is_array($args[0])) {
            if(!is_object($args[0])) {
              return;
            }
            $args[0] = (array) $args[0];
          }
          break;
        case 2:
          if(!is_string($args[0]) || !is_string($args[1])) {
            return;
          }
          $args[0] = array($args[0] => $args[1]);
          break;
        default:
          return false;
          break;
      }
      foreach($args[0] as $name => $content) {
        if(!is_string($name)
           || !is_string($content)
           || $this->section_exists($name)
           || !$this->is_varname($name)
        ) {
          continue;
        }
        $this->sections[$name] = new $this->section_class($name, false, $content);
        // We won't make this the active section because it is pointless adding
        // data to it.
      }
    }

    /**
     * Set Active
     *
     * @access public
     * @param  string|object $section
     * @return boolean
     */
    public function active($section) {
      $section = $this->section_name($section);
      if(!$this->section_exists($section)) {
        return false;
      }
      $this->active = $section;
      return true;
    }

    /**
     * Get Section
     *
     * Returns the section specified, else returns false. If you stick with the
     * default value, it will return the last activated section.
     *
     * @access public
     * @param  string|true $section
     * @return object|void
     */
    public function section($section = true) {
      if($section === true && isset($this->sections[$this->active])) {
        return $this->sections[$this->active];
      }
      $section = $this->section_name($section);
      if(isset($this->sections[$section])) {
        return $this->sections[$section];
      }
      // If we can't find either, give up.
      return false;
    }

    /**
     * Link Sections
     *
     * @access public
     * @param  array $links
     * @return void
     */
    public function link($links) {
      if(!is_array($links)) {
        return;
      }
      foreach($links as $section => $imports) {
        if(!$this->section_exists($section)) {
          continue;
        }
        $section = $this->section_name($section);
        // Make sure that it is an array!
        $imports = (array) $imports;
        if(!isset($this->links[$section]) || !is_array($this->links[$section])) {
          $this->links[$section] = array();
        }
        // Loop through the imports, making sure each one exists.
        foreach($imports as $import) {
          $import = $this->section_name($import);
          if(!$this->section_exists($import)
             || in_array($import, $this->links[$section])
          ) {
            continue;
          }
          $this->links[$section][] = $import;
        }
      }
    }

    /**
     * Group Sections
     *
     * @access public
     * @param  string  $name
     * @param  array   $sections
     * @return boolean
     */
    public function group($name, $sections) {
      if($this->section_exists($name)
      || !$this->is_varname($name)
      || !is_array($sections)) {
        return false;
      }
      $this->sections[$name] = array();
      foreach($sections as $section) {
        $section = $this->section_name($section);
        if($this->section_exists($section) && is_object($this->section($section))) {
          $this->sections[$name][] = $section;
        }
      }
      return true;
    }

    /**
     * Anonymous Group
     *
     * When you don't particulary care about the names of the sections of the
     * group. This is a quick way of just dumping data into a group where all
     * the sections will use the same view. This is quite common.
     *
     * @access public
     * @param string $name
     * @param string $view
     * @param array $data
     * @return boolean
     */
    public function agroup($name, $view, $data = array()) {
      if($this->section_exists($name) || !$this->is_varname($name) || !is_array($data)) {
        return false;
      }
      if(!$this->view_exists($view)) {
        return false;
      }
      $this->sections[$name] = array();
      foreach($data as $section_data) {
        if(is_object($section_data)) {
          $section_data = (array) $section_data;
        }
        // If the data isn't usable, then just skip this one and move on.
        if(!is_array($section_data)) {
          continue;
        }
        do {
          $section_hash = '_section_' . md5(microtime());
        }
        while($this->section_exists($section_hash));
        $this->create(array($section_hash => $view));
        $this->section($section_hash)->add($section_data);
        $this->sections[$name][] = $section_hash;
      }
      return true;
    }

    /**
     * Join Group
     *
     * Join the passed sections with the already existing group
     *
     * @access public
     * @param string $group
     * @param string|array $sections
     * @return boolean
     */
    public function join($group, $sections) {
      if(is_string($sections)) {
        $sections = array($sections);
      }
      if(!$this->section_exists($group)
      || !is_array($this->sections[$group])
      || !is_array($sections)
      ) {
        return false;
      }
      foreach($sections as $section) {
        if($this->section_exists($section)) {
          $this->sections[$group][] = $section;
        }
      }
      return true;
    }

    /**
     * Anonymous Join
     *
     * When you don't particulary care about the section that is getting added
     * to the group. This is just a quick way to dump data into multiple
     * sections of the same view to a group.
     *
     * @access public
     * @param string $group
     * @param string $view
     * @param array $data
     * @return boolean
     */
    public function ajoin($group, $view, $data = array()) {
      if(!isset($this->sections[$group])
         || !is_array($this->sections[$group])
         || !is_array($data)
         || !$this->view_exists($view)
      ) {
        return false;
      }
      foreach($data as $section_data) {
        if(is_object($section_data)) {
          $section_data = (array) $section_data;
        }
        if(!is_array($section_data)) {
          continue;
        }
        do {
          $section_hash = '_section_' . md5(microtime());
        }
        while($this->section_exists($section_hash));
        $this->create(array($section_hash => $view));
        $this->section($section_hash)->add($section_data);
        $this->sections[$group][] = $section_hash;
      }
      return true;
    }

    /**
     * Combine Sections
     *
     * 
     *
     * @access protected
     * @param string $section
     * @param integer $limit
     * @param array $data
     * @return false|string
     */
    protected function combine($section, $limit = 1, $data = array()) {
      if(!$this->section_exists($section)
         || !is_int($limit)
         || $limit < 0
         || !is_array($data)
      ) {
        return false;
      }
      // If an individual section is passed, make it into a group. It's easier
      // to handle just one type, rather than two.
      $sections = is_array($this->sections[$section])
                ? $this->sections[$section]
                : array($this->section($section)->name());
      // Allow zero to mean unlimited.
      if($limit === 0) {
        $limit = count($sections);
      }
      // Enforce the limit set in the template reference tag by only keeping
      // $limit number of sections in the array.
      array_splice($sections, $limit);
      $render = array();
      foreach($sections as $section) {
        // Check section, autolink, propagate data, grab content and render.
        // The section must exist, and not be a group, to be able to use it.
        if(!$this->section_exists($section) || !is_object($this->section($section))) {
          continue;
        }
        // Autolink sections. Because we're too lazy to do it in our
        // controllers!
        if($this->autolink) {
          $this->_autolink($section);
        }
        // Propagate data (take data from parent and merge it), if the setting
        // is enabled.
        $section_data = $this->propagate
                      ? $this->section($section)->data($data)
                      : array();
        // Render the section with its data.
        $content = $this->section($section)->content();
        // Search for template reference tags, and call this method again on
        // them, remembering to pass this sections data to its children. Then
        // insert the child sections where the template reference tags are.
        if(isset($this->links[$section]) && is_array($this->links[$section])) {

          foreach($this->links[$section] as $link) {
            if(!$this->section_exists($link)) {
              continue;
            }
            // PCRE string to match template reference tags
            $regex = '/\<\!--\s*\{('
                   . preg_quote($link, '/')
                   . ')(\[([0-9]+)?\])?\}\s*--\>/';
            // We have to use preg_match_all() instead of preg_match() incase
            // there is a difference in the Group Max Sections setting.
            if(preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
              foreach($matches as $match) {
                // Figure out the Group Max Sections setting.
                $n = 1;
                if(isset($match[2])) {
                  $n = isset($match[3]) ? (int) $match[3] : 0;
                }
                // Grab the content of the child group/section, remembering to
                // pass this sections data to the child.
                $content = str_replace(
                  $match[0],
                  $this->combine($link, $n, $section_data),
                  $content
                );
              }
            }
          }
        }
        // That's another group section done. Add the HTML content to the
        // rendered array ready for compilation.
        $render[] = $content;
      }
      // Now we have rendered each section, and gave birth to all its children,
      // combine them and return the HTML.
      return implode('', $render);
    }

    /**
     * Autolink Sections
     *
     * Searches each section as it is called, and automatically links
     * pseudo-links to the appropriate sections.
     *
     * @access protected
     * @return boolean
     */
    protected function _autolink($section) {
      if(!$this->section_exists($section)) {
        return false;
      }
      $section = $this->section_name($section);
      $content = $this->section($section)->content();
      // Now we have the content of the section, search using regex for
      // pseudo-links.
      $regex = '/\<\!--\s*\{('
             . $this->valid_name
             . ')(\[([0-9]+)?\])?\}\s*--\>/';
      if(preg_match_all($regex, $content, $matches, PREG_SET_ORDER)) {
        // We have found a minimum of one match. Start linking.
        $links = array();
        foreach($matches as $match) {
          $links[] = $match[1];
        }
        // No need for checks because we already know, due to the regex, that
        // the variable names are valid, and Template::link() will do checks
        // to see if the sections exist anyway.
        $this->link(array(
          $section => $links,
        ));
      }
      // We didn't run into any errors, return with a positive vibe.
      return true;
    }

    /**
     * Load Section Tree
     *
     * @access public
     * @param string|object $section
     * @param boolean       $append
     * @return boolean
     */
    public function load($section, $return = false) {
      $section = $this->section_name($section);
      // You are required to pass a valid section, groups are not allowed.
      if(!$this->section_exists($section)
         || !is_object($this->section($section))
      ) {
        return false;
      }
      $start = microtime(true);
      $rendered = $this->combine($section);
      if(!is_string($rendered)) {
        return false;
      }
      if(is_float($start)) {
        $rendered = str_replace(
          '{render_time}',
          (string) round(microtime(true) - $start, 4),
          $rendered
        );
      }
      if($return) {
        log_message('debug', 'Template Class Returned Output' . $section);
        return $rendered;
      }
      if(isset($this->CI->output)
         && is_object($this->CI->output)
         && method_exists($this->CI->output, 'append_output')
      ) {
        $this->CI->output->append_output($rendered);
      }
      else {
        echo $rendered;
      }
      log_message('debug', 'Template Class Sent Output: ' . $section);
      return true;
    }
  
}

//------------------------------------------------------------------------------

  /**
   * CodeIgniter Template Section Class
   *
   * A class for createing section (not group) objects for the Template library.
   * Please see the Template library DocComment for more details on this
   * library.
   *
   * @package    CodeIgniter
   * @subpackage Application
   * @category   Libraries
   * @author     Alexander Baldwin
   * @copyright  (c) 2010 Alexander Baldwin
   * @license    http://www.opensource.org/licenses/mit-license.php MIT License
   * @link       http://github.com/mynameiszanders/ci-template/blob/master/Template.php
   * @version    1.0
   */
  class Template_Section extends Template_Base {
  
    protected $name = false,
              $data = array(),
              $view = false,
              $content = false,
              $CI;

    /**
     * Constructor Function
     *
     * Defines $view and $path, and links to CodeIgniter's super object.
     *
     * @param string $view
     * @param  $path
     * @return void
     */
    public function __construct($name, $view, $content = false) {
      // We do not want the user to be able to call this method because they
      // could change the name of the section, which would screw up linking.
      if(is_string($this->name)) {
        return;
      }
      $this->name = $name;
      if(is_string($view)) {
        $this->view = $view;
      }
      else {
        $this->content = $content;
      }
      $this->CI =& get_instance();
    }
  
    /**
     * Section Name
     *
     * Returns the name of the section.
     *
     * @access public
     * @return string
     */
    public function name() {
      return $this->name;
    }

    /**
     * Section View
     *
     * Returns the name of the view that the section is using.
     * An anonymous section will return a boolean false.
     *
     * @access public
     * @return string|false
     */
    public function view() {
      return $this->view;
    }

    /**
     * Get Section Data
     *
     * Return a copy of the data that has been passed to this section.
     *
     * @access public
     * @param array $parent_data
     * @return array
     */
    public function data($parent_data = false) {
      // If the section's parent's data is passed, merge it into our own, but
      // making sure that our data takes priority it there is a variable name
      // clash.
      if(is_array($parent_data)) {
        foreach($parent_data as $varname => $value) {
          if(!isset($this->data[$varname]) && $this->is_varname($varname)) {
            $this->data[$varname] = $value;
          }
        }
      }
      return $this->data;
    }

    /**
     * Section Content
     *
     * Returns a views content, with data passed to it.
     *
     * @access public
     * @return string
     */
    public function content() {
      // $content = $this->load_view($this->path, $this->data);
      $content = is_string($this->view)
               ? $this->CI->load->view($this->view, $this->data, true)
               : $this->content;
      $content = str_replace(
        array(
          '{section_name}',
          '{view_name}'
        ),
        array(
          $this->name,
          $this->view
        ),
        $content
      );
      return $content;
    }
  
    /**
     * Add Data
     *
     * Add data to be included in the view.
     *
     * @access public
     * @params mixed
     * @return boolean
     */
    public function add() {
      $args = func_get_args();
      switch(func_num_args()) {
        case 1:
          if(!is_array($args[0])) {
            if(!is_object($args[0])) {
              return false;
            }
            $args[0] = (array) $args[0];
          }
          break;
        case 2:
          $args[0] = array($args[0] => $args[1]);
          break;
        default:
          // Incorrect number of arguments!
          return false;
          break;
      }
      foreach($args[0] as $varname => $vardata)
      {
        if(!$this->is_varname($varname)) {
          continue;
        }
        $this->data[$varname] = $vardata;
      }
      return true;
    }

    /**
     * To String Magic Method
     *
     * If the section object is treated as a string, treat it as if they were
     * trying to grab a copy of the content.
     *
     * @access public
     * @return string
     */
    public function __toString() {
      return $this->CI->template->load($this->name, true);
    }

    /**
     * Invoke Magic Method
     *
     * If the section object is treated as a function, treat it as if they were
     * calling the add() method.
     * Only works for PHP versions 5.3+
     *
     * @access public
     * @params mixed
     * @return boolean
     */
    public function __invoke() {
      return call_user_func_array(array($this, 'add'), func_get_args());
    }

  }
