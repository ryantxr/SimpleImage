<?php
require __DIR__ . '/../src/ryantxr/Image.php';

// Ignore notices
error_reporting(E_ALL & ~E_NOTICE);

try {
  
  $parrotFile = __DIR__ . '/files/parrot.jpg';
  $flagFile = __DIR__ . '/files/flag.png';

  // Create a new SimpleImage object
  $image = new \Ryantxr\Image();

  // Manipulate it
  $image
    ->fromFile($parrotFile)              // load parrot.jpg
    ->autoOrient()                        // adjust orientation based on exif data
    ->bestFit(300, 600)                   // proportinoally resize to fit inside a 250x400 box
    ->flip('x')                           // flip horizontally
    ->colorize('DarkGreen')               // tint dark green
    ->border('black', 5)                  // add a 5 pixel black border
    ->overlay($flagFile, 'bottom right') // add a watermark image
    ->toScreen();                         // output to the screen

} catch(Exception $err) {
  // Handle errors
  echo $err->getMessage();
}
