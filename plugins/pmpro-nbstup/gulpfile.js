const { src, dest, series, parallel, watch } = require('gulp');
const sass = require('gulp-sass')(require('sass'));
const sourcemaps = require('gulp-sourcemaps');
const postcss = require('gulp-postcss');
const autoprefixer = require('autoprefixer');
const cssnano = require('cssnano');
const uglify = require('gulp-uglify');
const concat = require('gulp-concat');
const rename = require('gulp-rename');
const zip = require('gulp-zip');
const del = require('del');
const path = require('path');

const paths = {
    scss: {
        src: 'assets/scss/frontend.scss',
        watch: 'assets/scss/**/*.scss',
        dest: 'assets/css/'
    },
    js: {
        src: [
            'assets/script/frontend.js'
        ],
        watch: 'assets/script/**/*.js',
        dest: 'assets/js/'
    },
    bundle: {
        root: 'dist/pmpro-nbstup',
        zipName: 'pmpro-nbstup.zip',
        src: [
            'pmpro-nbstup.php',
            'README.md',
            'includes/**',
            'assets/**',
            '!assets/scss/**',
            '!assets/script/**',
            '!**/*.map'
        ]
    }
};

function styles() {
    return src(paths.scss.src)
        .pipe(sourcemaps.init())
        .pipe(sass().on('error', sass.logError))
        .pipe(postcss([autoprefixer(), cssnano()]))
        .pipe(rename('frontend.css'))
        .pipe(sourcemaps.write('.'))
        .pipe(dest(paths.scss.dest));
}

function scripts() {
    return src(paths.js.src, { allowEmpty: true })
        .pipe(sourcemaps.init())
        .pipe(concat('frontend.js'))
        .pipe(uglify())
        .pipe(sourcemaps.write('.'))
        .pipe(dest(paths.js.dest));
}

function watcher() {
    watch(paths.scss.watch, styles);
    watch(paths.js.watch, scripts);
}

function cleanBundle() {
    var deleteAsync = del.deleteAsync || del;
    return deleteAsync(['dist/**', '!dist']);
}

function bundleCopy() {
    return src(paths.bundle.src, { base: '.', allowEmpty: true })
        .pipe(dest(paths.bundle.root));
}

function bundleZip() {
    return src(path.join('dist', 'pmpro-nbstup', '**'), { base: 'dist', allowEmpty: true })
        .pipe(zip(paths.bundle.zipName))
        .pipe(dest('dist'));
}

exports.styles = styles;
exports.scripts = scripts;
exports.watch = watcher;
exports.build = series(
    parallel(styles, scripts)
);
exports.bundle = series(
    cleanBundle,
    exports.build,
    bundleCopy,
    bundleZip
);
exports.default = exports.build;

