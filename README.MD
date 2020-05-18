# TN Deploy
Simple Deployment and backup script for my sites

- `wp deploy run [filename]` parses filename and installs named plugins and activates
- `wp deploy backup [files]|[db]` takes a backup of files, db or both and moved with rclone

### Setup
rclone needs to be available and set in PATH accessible to WP-CLI OR defined in wp-config.php
Should be set with a config entry for backup and bucket should be named after site name. 
For example:
```
[backup]
type = b2
account = {B2ID}
key = {B2Key}
```
Backups always take list of current plugins and stores them in bucket.

For deployments main git repo holds plugins.txt and then deployment occurs on post recieve hook
```
composer -d/var/www/vhosts/timnash.co.uk/httpdocs/ update
if [ -f /var/www/vhosts/timnash.co.uk/httpdocs/plugins.txt ]; then
  wp deploy run /var/www/vhosts/timnash.co.uk/httpdocs/plugins.txt --path=/var/www/vhosts/timnash.co.uk/httpdocs/wp/
  rm /var/www/vhosts/timnash.co.uk/httpdocs/plugins.txt
fi
```
