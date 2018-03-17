<?php

require __DIR__ . '/../src/ryantxr/Image.php';
use \Ryantxr\Image;

class cookbook {
    protected $imageDir;
    function __construct() {
        // Set up things we will reuse
        $this->imageDir = __DIR__ . '/files';
        $this->fonts = [
            'San Francisco Display' => '/System/Library/Fonts/SFNSDisplay.ttf',
            'San Francisco Text' => '/System/Library/Fonts/SFNSText.ttf'];
    }

    function rectangle() {
        try {  
            $outputFile = $this->imageDir . '/rectangle.jpg';

            // Create a new Image object with white background
            $image = new \Ryantxr\Image(480, 250, Image::ColorWhite);
            // Draw black rectangle
            $color = 'black';
            $image
                ->rectangle(5, 5, 200, 120, $color, Image::Filled)
                ->rectangle(205, 125, 400, 220, $color, 1)
                ->toFile($outputFile, 'image/jpeg')  // output to file
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
                ->roundedRectangle(5, 5, 105, 115, 8, $color)
                ->roundedRectangle(110, 125, 215, 235, 8, $color, Image::Filled)
                ->toFile($outputFile, 'image/jpeg')  // output to file
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

    function boxedText() {
        $image = new \Ryantxr\Image(600, 400, Image::ColorBlack);
        $font = $this->fonts['San Francisco Display'];
        $image
            ->text('Quickly', [
                'fontFile' => $font,
                'size' => 50,
                'color' => 'white',
                'anchor' => 'top left',
                'yOffset' => 8,
                'xOffset' => 8
            ], $box)
            // Draws a rectangle around the text
            ->rectangle($box['left'], $box['top'], $box['right'], $box['bottom'], 'red');
        $image
            ->text('Putting', [
                'fontFile' => $font,
                'size' => 50,
                'color' => 'white',
                'anchor' => 'top left',
                'yOffset' => 8,
                'xOffset' => $box['right']
            ], $box)
            // Draws a rectangle around the text
            ->rectangle($box['left'], $box['top'], $box['right'], $box['bottom'], 'red');
            
        $image
            ->text('Tagjaly', [
                'fontFile' => $font,
                'size' => 50,
                'color' => 'white',
                'anchor' => 'top left',
                'yOffset' => 8,
                'xOffset' => $box['right']
            ], $box)
            // Draws a rectangle around the text
            ->rectangle($box['left'], $box['top'], $box['right'], $box['bottom'], 'red');
        $image->toFile('box.png', 'image/png');
    }

    function circle() {
        $font = $this->fonts['San Francisco Display'];
        $outputFile = $this->imageDir . '/circle.jpg';        
        $image = new \Ryantxr\Image(600, 400, Image::ColorBlack);
        $width = 50;
        $height = 50;
        $x = $width / 2;
        $y = $height / 2 + 20;
        $start = 0;
        $end = 0;
        $color = Image::ColorWhite;
        $thickness = 1;

        $space = 8;

        $image//->arc($x, $y, $width, $height, $start, $end, $color, $thickness)
            ->arc($x+$width + $space, $y, $width, $height, 0, 0, $color, $thickness)
            ->arc($x+($width*2.5) + $space, $y, $width, $height, 0, 0, $color, $thickness)
            ->arc($x+$width + $space, $y, 5, 5, 0, 0, $color, $thickness)
            ->arc($x+($width*2.5) + $space, $y, 5, 5, 0, 0, $color, $thickness)

            ->arc($x+($width*4.5) + $space, $y, $width, $height, 90, 270, $color, $thickness)
            ->arc($x+($width*4.5) + $space, $y+100, $width*2, $height, 0, 0, $color, $thickness)
            ->toFile($outputFile, 'image/jpeg');

    }

    function ellipse() {
        $outputFile = $this->imageDir . '/ellipse.jpg';        
        $image = new Image(900, 120, Image::ColorBlack);
        $width = 80;
        $height = 50;
        $x = 0;
        $y = $height / 2 + 40;
        $start = 0;
        $end = 0;
        $color = Image::ColorWhite;
        $thickness = 1;

        $space = 8;
        $angle = 0;

        for($i=0; $i<8; $i++) {
            $angle = 360 / 8 * $i;
            $x += $width;
            $image->ellipse($x, $y, $width, $height, $angle, $color, $thickness);
            $x += 10;
        }

        $image->toFile($outputFile, 'image/jpeg');
    }
}
$cookbook = new cookbook;
$cookbook->rectangle();
$cookbook->roundRectangle();
$cookbook->polygon();
$cookbook->boxedText();
$cookbook->circle();
$cookbook->ellipse();
