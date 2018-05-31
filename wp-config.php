<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'edu');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         ';T(d0]?>-]*Db2M[`R@ Q3B%G%I1m4NWga;?3+95i1yW_R2wg`rG<:_}BS?f.|j}');
define('SECURE_AUTH_KEY',  'SGalS`-qal>aTkwKefUJ+7CHWH*v<]vaPlHc4,5w^8#=*`iN#a,-38>S2hPl@vSe');
define('LOGGED_IN_KEY',    'NBxslP*@`Y#5d=PO:um2)?6tl}v=B6oGddk5,H-k@i<y3d&oyT1!iC@g{8&p@^UW');
define('NONCE_KEY',        ' {k:Fl-SP;T_vzBdn@;O9~).nueV,$*V;nyJS};l&`x$VnGVaNy#Yi2sNtYZkl%#');
define('AUTH_SALT',        'NE/{_,_Hn|llu6/KLjH(1LHmXC;xhPXsa|zO&|h@;?r3tG]vpfbaZk^sCkvnumoY');
define('SECURE_AUTH_SALT', ']u)qKkLF13fC$D =&l(mQ0c&&khy,:`M]Pm;a1~uv)14DBmJ?.^woxG8;[,*|0W,');
define('LOGGED_IN_SALT',   'Y0hF-_5NS;wK9dAoYC*rbp.J~[>oH}Cxc>cVOJX8E%xMm~YI=&f],J3/.jS5#T3:');
define('NONCE_SALT',       '_dCX_<{X|4AUfC`}]YU5$Mcjg${izV/7t6(KrI^)*]59id_=?=Wfxt*Wr%l~3E3w');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
