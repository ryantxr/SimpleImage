<?php

require __DIR__ . '/../src/ryantxr/Image.php';

class cookbook {
    protected $imageDir;
    function __construct() {
        // Set up things we will reuse
        $this->imageDir = __DIR__ . '/files';
    }

    function rectangle() {
        try {  
            $outputFile = $this->imageDir . '/rectangle.jpg';

            // Create a new Image object with white background
            $image = new \Ryantxr\Image(480, 250, 'white');
            // Draw black rectangle
            $color = 'black';
            $image
                ->rectangle(5, 5, 470, 240, $color, \Ryantxr\Image::Filled)
                ->toFile($outputFile, 'image/png')  // output to file
            ;
        } catch(Exception $err) {
            // Handle errors
            echo $err->getMessage();
        }
    }

    function roundRectangle() {
        try {
            $outputFile = $this->imageDir . '/round-rectangle.jpg';

            // Create a new Image object with white background
            $image = new \Ryantxr\Image(480, 250, 'white');
            // Draw black rectangle
            $color = 'black';
            $image
                ->roundedRectangle(5, 5, 470, 240, 8, $color)
                ->toFile($outputFile, 'image/png')  // output to file
            ;
        } catch(Exception $err) {
            // Handle errors
            echo $err->getMessage();
        }
    }

    function polygon() {
        $vert = [
            ['x' => 220, 'y' => 50],
            ['x' => 230, 'y' => 100],
            ['x' => 310, 'y' => 70],
            ['x' => 410, 'y' => 170],
            ['x' => 290, 'y' => 200],
            ['x' => 230, 'y' => 210],
            ['x' => 190, 'y' => 220],
            ['x' => 120, 'y' => 210],
            ['x' => 50,  'y' => 110],
            
        ];
        try {
            $outputFile = $this->imageDir . '/polygon.jpg';

            // Create a new Image object with white background
            $image = new \Ryantxr\Image(480, 250, 'white');
            // Draw black rectangle
            $color = 'black';
            $image
                ->polygon($vert, $color)
                ->toFile($outputFile, 'image/jpeg')  // output to file
            ;
        } catch(Exception $err) {
            // Handle errors
            echo $err->getMessage();
        }
        
    }


}
$cookbook = new cookbook;
$cookbook->rectangle();
$cookbook->roundRectangle();
$cookbook->polygon();
