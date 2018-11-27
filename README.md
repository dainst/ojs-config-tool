# ojs config tool
A very small command line tool wich can be used by th docker contain for example to basically set up the ojs.

## ojs2
Usage:
php ojs-config-tool ojs2.php --path=[OJS2 installion path]

## ojs3
Usage:
 * php ojs3config.php
 *
 * Parameters:
 * -- path=<path to OJS3 installton. defaults to  /var/www/html>
 * -- journal.path=<new journal path>
 * -- journal.title=<new journal title>
 * -- journal.plugins=<list of activate plugins> form:
 *  generic/dfm,pubIds/urnDNB (comma-separated list of plugins paths including plugin category
