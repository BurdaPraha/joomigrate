# Joomigrate

Experimental migration module, using entities, *still in hard development*.

We are using modified [CSV export](http://www.individual-it.net/en/Instructions-for-K2-Import-Component.html) from Jommla 3.x K2.
Exported CSV you can upload at `domain.tld/joomigrate` (administrator permission needed)

[Example data from export](https://docs.google.com/spreadsheets/d/1UBDXlM2a7vT4wiriP7zhSmgu_1F8ehrTRjZwVVgwD5Y/edit?usp=sharing)

## Import
Form for upload CSV file is available on `domain.tld/admin/config/development` and find your kind.

## Drush commands
For develop and testing useful functions which can delete only imported content (you must have field_joomla_id)
- `bin/drush joomigrate:delete:all`
- `bin/drush joomigrate:delete:articles`
- `bin/drush joomigrate:delete:channels`
- `bin/drush joomigrate:delete:images`
- `bin/drush joomigrate:delete:galleries`
- `bin/drush joomigrate:delete:authors`

## Tools
- [Joomla - redirects export for .htaccess](/tools/joomla_redirects.php), you can use this file manually for your redirects export
- [Joomla - Move aliases & tags from SEF component to content table for better exporting to CSV](/tools/joomla_sefurls.php)
