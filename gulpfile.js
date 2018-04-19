'use strict';

var gulp         = require('gulp'),
	sass         = require('gulp-sass'),
	cleanCSS     = require('gulp-clean-css'),
	autoprefixer = require('gulp-autoprefixer'),
	sourcemaps   = require('gulp-sourcemaps'),
	gulpsync 	 = require('gulp-sync')(gulp);

var path = {
	src: {
		scss: 'scss/*.scss',
	},
	dest: {
		css: 'css',
	}
}
/****************************
Task for scss build
****************************/

gulp.task('sass', function(){
	return gulp.src(path.src.scss)
		.pipe(sourcemaps.init())
		.pipe(sass({outputStyle: 'expanded'}).on('error', sass.logError))
		.pipe(autoprefixer(['last 15 versions', '> 1%'], { cascade: true }))
		.pipe(cleanCSS({keepSpecialComments: 0}))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest(path.dest.css))
});

/****************************
	Watch task
****************************/

gulp.task('watch', gulpsync.sync(['sass']), function() {
	gulp.watch('scss/**/*.+(scss|sass)', ['sass']);
});
