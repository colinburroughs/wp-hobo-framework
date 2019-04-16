module.exports = function (grunt) {

    // Project configuration.
    grunt.initConfig({
        pkg: grunt.file.readJSON('package.json'),
        clean: {
            folder: ['dest']
        },
        uglify: {
            options: {
                banner: '/*! <%= pkg.name %> - v<%= pkg.version %> - <%= grunt.template.today("yyyy-mm-dd H:MM:ss") %> */\n',
                mangle: {
                    reserved: ['jQuery']
                }
            },
            target: {
                files: [{
                    expand: true,
                    cwd: 'js',
                    src: ['*.js', '!*.min.js'],
                    dest: 'dest/built/js',
                    ext: '.min.js'
                },{
                    expand: true,
                    cwd: 'views/js',
                    src: ['*.js', '!*.min.js'],
                    dest: 'dest/built/views/js',
                    ext: '.min.js'
                }]
            }
        },
        cssmin: {
            target: {
                files: [{
                    expand: true,
                    cwd: 'css',
                    src: ['*.css', '!*.min.css'],
                    dest: 'dest/built/css',
                    ext: '.min.css'
                }]
            }
        },
        copy: {
            main: {
                files: [
                    {expand: true, src: ['*.php'], dest: 'dest/built'},
                    {expand: true, src: ['views/*.php'], dest: 'dest/built'},
                ],
            },
        },
        version: {
            options: {
                prefix: 'Version:       '
            },
            defaults: {
                src: ['dest/built/*.php']
            }
        },
        compress: {
            main: {
                options: {
                    archive: 'dest/<%= pkg.name %>.zip'
                },
                files: [{
                    expand: true,
                    cwd: 'dest/built',
                    src: '**',
                    dest: ''
                }]
            }
        }
    });

    // Load the plugin that provides the "clean" task.
    grunt.loadNpmTasks('grunt-contrib-clean');

    // Load the plugin that provides the "version" task.
    grunt.loadNpmTasks('grunt-version');

    // Load the plugin that provides the "uglify" task.
    grunt.loadNpmTasks('grunt-contrib-uglify');

    // Load the plugin that provides the "cssmin" task.
    grunt.loadNpmTasks('grunt-contrib-cssmin');

    // Load the plugin that provides the "copy" task.
    grunt.loadNpmTasks('grunt-contrib-copy');

    // Load the plugin that provides the "compress" task.
    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('start', 'world task description', function(){
        console.log('** BUILD START **');
    });

    // Default tasks.
    grunt.registerTask('default', ['start', 'clean', 'uglify', 'cssmin', 'copy', 'version', 'compress']);

};
