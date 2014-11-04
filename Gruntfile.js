module.exports = function (grunt) {
    grunt.initConfig({
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
};