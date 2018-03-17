<?php

require __DIR__ . '/../src/ryantxr/Image.php';

try {
  
  $parrotFile = __DIR__ . '/files/parrot.jpg';
  $flagFile = __DIR__ . '/files/flag.png';

  // Create a new Image object
  $image = new \Ryantxr\Image(480, 250, 'white');
  // Manipulate it
  $color = 'black';
  $image
    ->rectangle(5, 5, 470, 240, $color)
    ->toFile(__DIR__ . '/files/card.png', 'image/png')  // output to file
;
} catch(Exception $err) {
  // Handle errors
  echo $err->getMessage();
}