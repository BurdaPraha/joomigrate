# Joomigrate

Experimental migration module, using entities, *still in hard development*.

We are using modified [CSV export](http://www.individual-it.net/en/Instructions-for-K2-Import-Component.html) from Jommla 3.x K2.
Exported CSV you can upload at `domain.tld/joomigrate` (administrator permission needed)

## Our roadmap:
- [ ] import from Joomla 3.x K2
- [ ] import from Drupal 7
- [ ] import from WordPress

## Drush commands
For develop and testing useful functions which can delete only imported content (you must have field_joomla_id)
- `drush delete-all`
- `drush delete-articles`
- `drush delete-channels`
- `drush delete-images`
- `drush delete-galleries`