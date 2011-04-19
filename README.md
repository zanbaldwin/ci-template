CodeIgniter Template Library ![Project Status](http://stillmaintained.com/mynameiszanders/ci-template.png "Project Status")
===========================================================================================================================

A Template library for [CodeIgniter] [3], by [Alexander Baldwin] [1] and
licensed under the [MIT/X11] [2] open source license.

The CodeIgniter Template library enables you to organise multiple views
into complex, nested sections providing each one with seperate data sets,
controlling data propagation. These sections can be grouped, iterated and
linked with automatic embedding to create a fully functioning, yet modular,
page.

Here is a quick example of what you can do with the library from within your
[CodeIgniter] [3] controller; please refer to the [online copy of the user
guide] [4] for further examples and full documentation.

    // Detected end-user is using a mobile browser.
    $this->template->set_prefix('mobile_');

    // Create sections from views.
    $this->template->create(array(
      'shell' => 'document',
      'header',
      'content',
      'footer',
    ));

    // Create an anonymous group.
    $this->template->agroup(
      'featured',
      'featured_box',
      array(
        array('feature_name' => 'One'),
        array('feature_name' => 'Two'),
        array('feature_name' => 'Three'),
      )
    );

    // Set autolinking off, and enable section data to propagate.
    $this->template->autolink(false);
    $this->template->propagate(true);

    // Link sections together
    $this->template->link(array(
        'shell' => array('header', 'content', 'footer'),
        'content' => array('featured'),
    ));

    // Add some data to the shell section.
    $shell = $this->template->section('shell');
    $shell('title', 'Template Library');

    // Render template and append to output.
    $this->template->load('shell');

Enjoy!

[1]:  http://github.com/mynameiszanders
      "Alexander Baldwin on GitHub"
[2]:  http://www.opensource.org/licenses/mit-license.php
      "Massachusetts Institute of Technology License on the Open Source Initiative"
[3]:  http://codeigniter.com/
      "CodeIgniter open source Web Application Framework"
[4]:  http://mynameiszanders.github.com/ci-template/
      "Online version of CI Template's User Guide"
