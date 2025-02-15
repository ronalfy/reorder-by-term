module.exports = function (grunt) {
	grunt.initConfig({
	  compress: {
		main: {
		  options: {
			archive: 'reorder-by-term.zip'
		  },
		  files: [
			{ src: ['reorder-by-term.php'], dest: '/', filter: 'isFile' },
			{ src: ['js/**'], dest: '/' },
			{ src: ['languages/**'], dest: '/' },
			{ src: ['class-reorder-term-builder.php'], dest: '/', filter: 'isFile' },
			{ src: ['class-reorder-term-helper.php'], dest: '/', filter: 'isFile' },
			{ src: ['index.php'], dest: '/', filter: 'isFile' },
			{ src: ['readme.txt'], dest: '/', filter: 'isFile' },
			{ src: ['uninstall.php'], dest: '/', filter: 'isFile' },
		  ]
		}
	  }
	})
	grunt.registerTask('default', ['compress'])
  
	grunt.loadNpmTasks('grunt-contrib-compress')
  }
  