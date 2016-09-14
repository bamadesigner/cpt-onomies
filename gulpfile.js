// Require all the things (that we need)
var autoprefixer = require('gulp-autoprefixer');
var clean_css = require('gulp-clean-css');
var gulp = require('gulp');
var phpcs = require('gulp-phpcs');
var rename = require('gulp-rename');
var sass = require('gulp-sass');
var sort = require('gulp-sort');
var uglify = require('gulp-uglify');
var watch = require('gulp-watch');
var wp_pot = require('gulp-wp-pot');

// Define the source paths for each file type
var src = {
    scss: 	'assets/scss/*',
    js:		['assets/js/*.js','!assets/js/*.min.js']
};

// Sass is pretty awesome, right?
gulp.task('sass', function() {
    return gulp.src(src.scss)
        .pipe(sass({
			outputStyle: 'compressed'
		})
		.on('error', sass.logError))
        .pipe(autoprefixer({
        	browsers: ['last 2 versions'],
			cascade: false
		}))
		.pipe(rename({
			suffix: '.min'
		}))
		.pipe(gulp.dest('assets/css'));
});

// Minify the JS
gulp.task('js', function() {
    gulp.src(src.js)
        .pipe(uglify({
            mangle: false
        }))
        .pipe(rename({
			suffix: '.min'
		}))
        .pipe(gulp.dest('assets/js'))
});

// Create the .pot translation file
gulp.task('translate', function () {
    gulp.src('**/*.php')
        .pipe(sort())
        .pipe(wp_pot( {
            domain: 'cpt-onomies',
            destFile:'cpt-onomies.pot',
            package: 'cpt-onomies',
            bugReport: 'https://github.com/bamadesigner/cpt-onomies/issues',
            lastTranslator: 'Rachel Carden <bamadesigner@gmail.com>',
            team: 'Rachel Carden <bamadesigner@gmail.com>',
            headers: false
        } ))
        .pipe(gulp.dest('languages'));
});

// "Sniff" the code for PHP standards
gulp.task('sniff', function () {
    return gulp.src('**/*.php')
        // Validate files using PHP Code Sniffer
        .pipe(phpcs({
            bin: 'vendor/bin/phpcs',
            standard: 'WordPress',
            warningSeverity: 2
        }))
        // Log all problems that was found
        .pipe(phpcs.reporter('log'));
});

// I've got my eyes on you(r file changes)
gulp.task('watch', function() {
	gulp.watch(src.scss, ['sass']);
	gulp.watch(src.js, ['js']);
	gulp.watch('**/*.php', ['translate']);
});

// Let's get this party started
gulp.task('default', ['sass','js','translate','watch']);