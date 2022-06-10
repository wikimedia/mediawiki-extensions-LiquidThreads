STATUS:
======

This software is beta. There are bugs and missing features. But it is
basically usable, and you are encouraged to try it (with caution). Also, please
hack Liquid Threads and make it more awesome.

INSTALLATION:
============

1. This extension depends on WikiEditor, make sure you have the dependency
   already installed.
2. Rename this directory to extensions/LiquidThreads inside your
   MediaWiki directory.
3. Add database tables from lqt.sql using the sql.php MediaWiki tool.
   (On Unix, if the current directory is the MediaWiki root directory, you can
   say "php maintenance/sql.php extensions/LiquidThreads/sql/lqt.sql".)
   If you haven't created the AdminSettings.php file, you will have to do that
   first; see https://www.mediawiki.org/wiki/Manual:AdminSettings.php
   Alternatively, you can run lqt.sql manually (you can use the command
   "mysql -u $USER -p -e 'source sql/lqt.sql'" on Unix), but you might have to
   edit it first, and replace the /*$wgDBprefix*/ and /*$wgDBTableOptions*/
   strings with the corresponding settings.
4. Add this line to the end of your LocalSettings.php:
   wfLoadExtension( 'LiquidThreads' );

Liquid Threads uses namespace numbers 90, 91, 92, and 93.

CREDITS:
=======

Originally written by David McCabe, sponsered by COL.org, Wikia.com, and the Google
Summer of Code, with lots of help from Erik Möller, Brion Vibber, and the kind
folks on #mediawiki.
