<?php
// Load .env file if it exists
$env_file = __DIR__ . '/.env';
if ( file_exists( $env_file ) ) {
    $lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    foreach ( $lines as $line ) {
        // Skip comments
        if ( strpos( trim( $line ), '#' ) === 0 ) {
            continue;
        }
        // Parse VAR=value
        if ( strpos( $line, '=' ) !== false ) {
            list( $key, $value ) = explode( '=', $line, 2 );
            $key = trim( $key );
            $value = trim( $value );
            // Remove quotes if present
            $value = trim( $value, '"' );
            $value = trim( $value, "'" );

            // Set as environment variable
            if ( ! getenv( $key ) ) {
                putenv( "$key=$value" );
            }
        }
    }
}
