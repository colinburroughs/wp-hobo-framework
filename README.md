# wp-hobo-framework
A simple framework for creating WordPress plugins.

The classes are named this way to ensure loading order when you add them directly to the "must-use" folder. Alternatively upload the entire wp-hobo-framework folder to your "must-use" folder and include the classes via a simple <i>mu-loader.php</i> php file the contents of which would be: 

<i>
    <pre>
    include 'wp-hobo-framework/Class.01.Autoloader.php';
    include 'wp-hobo-framework/Class.01.HTML.Helper.php';
    include 'wp-hobo-framework/Class.01.Plugin.Settings.php';
    include 'wp-hobo-framework/Class.01.Request.Cache.php';
    include 'wp-hobo-framework/Class.01.Singleton.php';
    include 'wp-hobo-framework/Class.01.Upload.Helper.php';
    include 'wp-hobo-framework/Class.01.Util.php';
    include 'wp-hobo-framework/Class.01.Validation.php';
    include 'wp-hobo-framework/Class.02.Ajax.php';
    include 'wp-hobo-framework/Class.02.Plugin.php';
    include 'wp-hobo-framework/Class.02.Queries.php';
    include 'wp-hobo-framework/Class.02.Shortcodes.php';
    include 'wp-hobo-framework/Class.02.Template.Redirect.php';
    include 'wp-hobo-framework/Class.02.Widgets.php';
    include 'wp-hobo-framework/Class.03.Model.php';
    include 'wp-hobo-framework/Class.03.MVC.php';
    include 'wp-hobo-framework/Class.03.Plugin.Admin.php';
    include 'wp-hobo-framework/Class.04.Plugin.Disable.php';
    </pre>
</i>
