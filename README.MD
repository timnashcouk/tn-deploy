====TN Deploy====
Simple Deployment script for my sites

`wp deploy run [filename]` parses filename and installs named plugins and activates
`wp deply backup [files]|[db]` takes a backup of files, db or both and moved with rclone

====Setup====
rclone needs to be available and set in PATH accessible to WP-CLI OR defined in wp-config.php
Should be set with a config entry for backup and bucket should be named after site name 
