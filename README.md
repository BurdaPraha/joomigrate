# README #

This module should be only used by drupal developer.

### Saving a file images ###
If files not online you can upload them in directory like e.g.: `sites/all/default/files/tobeuploaded/`  
OR `/tmp/myimages` then somewhere use  
```
$file_data = file_get_contents(\Drupal::root() . "sites/all/default/files/tobeuploaded/{$data['image_url']}");
$file = file_save_data($file_data, 'public://druplicon.png', FILE_EXISTS_REPLACE);

$node = Node::create([
  'type'        => 'article',
  'title'       => 'Druplicon test',
  'field_image' => [
    'target_id' => $file->id(),
  ],
]);
```
