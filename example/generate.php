<?php
require __DIR__ . '/../src/ryantxr/Image.php';

try {
  
  $parrotFile = __DIR__ . '/parrot.jpg';
  $flagFile = __DIR__ . '/flag.png';

  // Create a new Image object
  $image = new \Ryantxr\Image();
  // Manipulate it
  
  $image
    ->fromFile($parrotFile)               // load parrot.jpg
    ->autoOrient()                        // adjust orientation based on exif data
    ->bestFit(300, 600)                   // proportinoally resize to fit inside a 250x400 box
    ->flip('x')                           // flip horizontally
    ->colorize('DarkGreen')               // tint dark green
    ->border('black', 5)                  // add a 5 pixel black border
    ->overlay($flagFile, 'bottom right')  // add a watermark image
    ->toFile('new-image.png', 'image/png')  // output to file
;
} catch(Exception $err) {
  // Handle errors
  echo $err->getMessage();
}
