module.exports = function (grunt) {
    grunt.initConfig({
        /* autoprefixer: {
            dist: {
                files: {
                    'style.css': '_style.css'
                }
            }
        }, */
        /* watch: {
			options: {
				livereload: true
			},
            styles: {
                files: ['_style.css'],
                tasks: ['autoprefixer']
            }
        } */
		makepot: {
			target: {
				options: {
					type: 'wp-plugin',
					domainPath: 'i18n/languages',
					potFilename: 'mg-wc-stripe.pot',
					potHeaders: {
						'report-msgid-bugs-to': 'http://mgiulio.info/contact',
						'language-team': 'LANGUAGE <EMAIL@ADDRESS>'
					}
				}
			}
		}
    });
    
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	//grunt.loadNpmTasks( 'grunt-checktextdomain' );
	//grunt.loadNpmTasks('grunt-autoprefixer');
    //grunt.loadNpmTasks('grunt-contrib-watch');
};