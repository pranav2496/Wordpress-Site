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
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'root' );

/** MySQL database password */
define( 'DB_PASSWORD', '' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'w!= ClJZ`B@]?/~Q$aDQ 5i{mM7@bYqqdpEApD`fP!|<P|1_NEMxNFKlmX!<}t_z' );
define( 'SECURE_AUTH_KEY',  'E9cTe~l]DqF>()Nvt)*fvreFh4kPWD[?_7YcZUXSxQlckt|jrSF2 LEPk-jOjjh2' );
define( 'LOGGED_IN_KEY',    ';nk(vp}M>6n+Ko8 J(H3dy2<@3[jp#5p.%c/_iDVR_Ux05O)eRnL;T1G`IP6tzTY' );
define( 'NONCE_KEY',        '?,x% Rc9ShAUh} 39Km#<E$?h3QE*slqbUsm4py9Cg=CN|yzAx$34bj_?b0yrrdS' );
define( 'AUTH_SALT',        '}tQ}-%k^w95$F7c*hDy.)><rxAY[E-Q%LrDUd>P4W)%7DHBkJD]FlmE8d:DRp)9w' );
define( 'SECURE_AUTH_SALT', 'vq=xZ/VAi(r]<`WGXh@txFwTfqU3NWyEyh8#ZpXlA7%= o_r0B=JcM*kDF5seJ]l' );
define( 'LOGGED_IN_SALT',   '&8Qly1Y8`6]Y7gG%47,7U%9E93V#M5]mDe5^W`eH12Z(&6{D:,972o%3 INF|E(w' );
define( 'NONCE_SALT',       ':tv2Z-yTZMR.g It<3}3ZOIx]-2)7U&Jru69Dw#UF1APjBZh<(St:,*V*t{~bf@4' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
}

/** Sets up WordPress vars and included files. */
require_once( ABSPATH . 'wp-settings.php' );
