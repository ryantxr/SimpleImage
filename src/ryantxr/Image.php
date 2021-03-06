<?php
//
// Image
//
//  A PHP class for working with images.
//  Wraps the PHP GD functions to implement a class interface.
// 
//  
//  Originally developed and maintained by Cory LaViska <https://github.com/claviska>.
//
//  Source: https://github.com/ryantxr/php-image
//
//  Licensed under the MIT license <http://opensource.org/licenses/MIT>
//

namespace Ryantxr;

class Image {

    const
        ERR_FILE_NOT_FOUND = 1,
        ERR_FONT_FILE = 2,
        ERR_FREETYPE_NOT_ENABLED = 3,
        ERR_GD_NOT_ENABLED = 4,
        ERR_INVALID_COLOR = 5,
        ERR_INVALID_DATA_URI = 6,
        ERR_INVALID_IMAGE = 7,
        ERR_LIB_NOT_LOADED = 8,
        ERR_UNSUPPORTED_FORMAT = 9,
        ERR_WEBP_NOT_ENABLED = 10,
        ERR_WRITE = 11,
        ERR_OVERLAY = 12;

    const Filled = 'filled';

    const ColorWhite = 'white';
    const ColorBlack = 'black';

    protected $image, $mimeType, $exif;
    public $fonts = [];
    

    /**
     * Create an Image object.
     * @param int width
     * @param int height
     * @param color color (string | array)
     */
    public function __construct($width=null, $height=null, $color = 'transparent') {
        // Check for the required GD extension
        if (  extension_loaded('gd') ) {
            // Ignore JPEG warnings that cause imagecreatefromjpeg() to fail
            ini_set('gd.jpeg_ignore_warning', 1);
        } else {
            throw new \Exception('Required extension GD is not loaded.', self::ERR_GD_NOT_ENABLED);
        }
        if ( $width !== null && $height !== null ) {
            $this->image = imageCreateTrueColor($width, $height);

            // Use PNG for dynamically created images because it's lossless and supports transparency
            $this->mimeType = 'image/png';

            // Fill the image with color
            $this->fill($color);
        }
    }

    /**
     * Creates an Image object from a file
     * @param string image - An image file or a data URI to load.
     * 
     */
    public function load($image = null) {

        // Load an image through the constructor
        if ( preg_match('/^data:(.*?);/', $image)) {
            $this->fromDataUri($image);
        } elseif ( $image) {
            $this->fromFile($image);
        }
        return $this;
    }

    //
    // Destroys the image resource
    //
    public function __destruct() {
        if ( $this->image !== null && get_resource_type($this->image) === 'gd') {
            imageDestroy($this->image);
        }
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Loaders
    //////////////////////////////////////////////////////////////////////////////////////////////////

    //
    // Loads an image from a data URI.
    //
    //  $uri* (string) - A data URI.
    //
    // @return object this
    //
    public function fromDataUri($uri) {
        // Basic formatting check
        preg_match('/^data:(.*?);/', $uri, $matches);
        if ( ! count($matches)) {
            throw new \Exception('Invalid data URI.', self::ERR_INVALID_DATA_URI);
        }

        // Determine mime type
        $this->mimeType = $matches[1];
        if ( ! preg_match('/^image\/(gif|jpeg|png)$/', $this->mimeType)) {
            throw new \Exception(
                'Unsupported format: ' . $this->mimeType,
                self::ERR_UNSUPPORTED_FORMAT
            );
        }

        // Get image data
        $uri = base64_decode(preg_replace('/^data:(.*?);base64,/', '', $uri));
        $this->image = imageCreateFromString($uri);
        if (  ! $this->image ) {
            throw new \Exception("Invalid image data.", self::ERR_INVALID_IMAGE);
        }

        return $this;
    }

    //
    // Loads an image from a file.
    //
    //  $file* (string) - The image file to load.
    //
    // @return object this
    //
    public function fromFile($file) {
        // Check if the file exists and is readable. We're using fopen() instead of file_exists()
        // because not all URL wrappers support the latter.
        $handle = @fopen($file, 'r');
        if ( $handle === false ) {
            throw new \Exception("File not found: $file", self::ERR_FILE_NOT_FOUND);
        }
        fclose($handle);

        // Get image info
        $info = getImageSize($file);
        if ( $info === false ) {
            throw new \Exception("Invalid image file: $file", self::ERR_INVALID_IMAGE);
        }
        $this->mimeType = $info['mime'];

        // Create image object from file
        switch($this->mimeType) {
        case 'image/gif':
        // Load the gif
        $gif = imageCreateFromGif($file);
        if ( $gif ) {
            // Copy the gif over to a true color image to preserve its transparency. This is a
            // workaround to prevent imagepalettetruecolor() from borking transparency.
            $width = imagesx($gif);
            $height = imagesy($gif);
            $this->image = imageCreateTrueColor($width, $height);
            $transparentColor = imageColorAllocateAlpha($this->image, 0, 0, 0, 127);
            imageColorTransparent($this->image, $transparentColor);
            imageFill($this->image, 0, 0, $transparentColor);
            imageCopy($this->image, $gif, 0, 0, 0, 0, $width, $height);
            imageDestroy($gif);
        }
        break;
        case 'image/jpeg':
            $this->image = imageCreateFromJpeg($file);
            break;
        case 'image/png':
            $this->image = imageCreateFromPng($file);
            break;
        case 'image/webp':
            $this->image = imageCreateFromWebp($file);
            break;
        }
        if ( ! $this->image ) {
            throw new \Exception("Unsupported image: $file", self::ERR_UNSUPPORTED_FORMAT);
        }

        // Convert pallete images to true color images
        imagePaletteToTrueColor($this->image);

        // Load exif data from JPEG images
        if ( $this->mimeType === 'image/jpeg' && function_exists('exif_read_data')) {
            $this->exif = @exif_read_data($file);
        }

        return $this;
    }

    /**
     * Creates a new image from a string.
     * Example:
     * $string = file_get_contents('image.jpg');
     * 
     * @param string string - The raw image data as a string. 
     * @return Image object.
     */
    public function fromString($string) {
        return $this->fromFile('data://;base64,' . base64_encode($string));
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Savers
    //////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Generates an image.
     *
     *  @param string $mimeType - The image format to output as a mime type (defaults to the original mime type).
     *  @param int $quality - Image quality as a percentage (default 100).
     *
     * @return array containing the image data and mime type.
     */
    private function generate($mimeType = null, $quality = 100) {
        // Format defaults to the original mime type
        $mimeType = $mimeType ?: $this->mimeType;

        // Ensure quality is a valid integer
        if ( $quality === null ) $quality = 100;
        $quality = self::keepWithin((int) $quality, 0, 100);

        // Capture output
        ob_start();

        // Generate the image
        switch($mimeType) {
        case 'image/gif':
            imageSaveAlpha($this->image, true);
            imageGif ( $this->image, null);
            break;
        case 'image/jpeg':
            imageInterlace($this->image, true);
            imageJpeg($this->image, null, $quality);
            break;
        case 'image/png':
            imageSaveAlpha($this->image, true);
            imagePng($this->image, null, round(9 * $quality / 100));
            break;
        case 'image/webp':
            // Not all versions of PHP will have webp support enabled
            if ( ! function_exists('imagewebp')) {
                throw new \Exception(
                'WEBP support is not enabled in your version of PHP.',
                self::ERR_WEBP_NOT_ENABLED
                );
            }
            imageSaveAlpha($this->image, true);
            imageWebp($this->image, null, $quality);
            break;
        default:
            throw new \Exception('Unsupported format: ' . $mimeType, self::ERR_UNSUPPORTED_FORMAT);
        }

        // Stop capturing
        $data = ob_get_contents();
        ob_end_clean();

        return [
        'data' => $data,
        'mimeType' => $mimeType
        ];
    }

    /**
     * Generates a data URI.
     *
     * @param string mimeType - The image format to output as a mime type (defaults to the original mime type).
     * @param int quality - Image quality as a percentage (default 100).
     *
     * @return string containing a data URI.
     */
    public function toDataUri($mimeType = null, $quality = 100) {
        $image = $this->generate($mimeType, $quality);

        return 'data:' . $image['mimeType'] . ';base64,' . base64_encode($image['data']);
    }

    /**
     * Forces the image to be downloaded to the clients machine. Must be called before any output is sent to the screen.
     * @param string filename - The filename (without path) to send to the client (e.g. 'image.jpeg').
     * @param string mimeType - The image format to output as a mime type (defaults to the original mime type).
     * @param int quality - Image quality as a percentage (default 100).
     * @return object this
     */
    public function toDownload($filename, $mimeType = null, $quality = 100) {
        $image = $this->generate($mimeType, $quality);

        // Set download headers
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Description: File Transfer');
        header('Content-Length: ' . strlen($image['data']));
        header('Content-Transfer-Encoding: Binary');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        echo $image['data'];

        return $this;
    }

    //
    // Writes the image to a file.
    //
    //  $mimeType (string) - The image format to output as a mime type (defaults to the original mime
    //    type).
    //  $quality (int) - Image quality as a percentage (default 100).
    //
    // @return object this
    //
    public function toFile($file, $mimeType = null, $quality = 100) {
        $image = $this->generate($mimeType, $quality);

        // Save the image to file
        if ( ! file_put_contents($file, $image['data']) ) {
            throw new \Exception("Failed to write image to file: $file", self::ERR_WRITE);
        }

        return $this;
    }

    //
    // Outputs the image to the screen. Must be called before any output is sent to the screen.
    //
    //  $mimeType (string) - The image format to output as a mime type (defaults to the original mime
    //    type).
    //  $quality (int) - Image quality as a percentage (default 100).
    //
    // @return object this
    //
    public function toScreen($mimeType = null, $quality = 100) {
        $image = $this->generate($mimeType, $quality);

        // Output the image to stdout
        header('Content-Type: ' . $image['mimeType']);
        echo $image['data'];

        return $this;
    }

    //
    // Generates an image string.
    //
    //  $mimeType (string) - The image format to output as a mime type (defaults to the original mime
    //    type).
    //  $quality (int) - Image quality as a percentage (default 100).
    //
    // @return object this
    //
    public function toString($mimeType = null, $quality = 100) {
        return $this->generate($mimeType, $quality)['data'];
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Utilities
    //////////////////////////////////////////////////////////////////////////////////////////////////

    //
    // Ensures a numeric value is always within the min and max range.
    //
    //  $value* (int|float) - A numeric value to test.
    //  $min* (int|float) - The minimum allowed value.
    //  $max* (int|float) - The maximum allowed value.
    //
    // Returns an int|float value.
    //
    private static function keepWithin($value, $min, $max) {
        if ( $value < $min) return $min;
        if ( $value > $max) return $max;
        return $value;
    }

    //
    // Gets the image's current aspect ratio.
    //
    // Returns the aspect ratio as a float.
    //
    public function getAspectRatio() {
        return $this->getWidth() / $this->getHeight();
    }

    //
    // Gets the image's exif data.
    //
    // Returns an array of exif data or null if no data is available.
    //
    public function getExif() {
        return isset($this->exif) ? $this->exif : null;
    }

    //
    // Gets the image's current height.
    //
    // Returns the height as an integer.
    //
    public function getHeight() {
        return (int) imagesy($this->image);
    }

    //
    // Gets the mime type of the loaded image.
    //
    // Returns a mime type string.
    //
    public function getMimeType() {
        return $this->mimeType;
    }

    //
    // Gets the image's current orientation.
    //
    // Returns a string: 'landscape', 'portrait', or 'square'
    //
    public function getOrientation() {
        $width = $this->getWidth();
        $height = $this->getHeight();

        if ( $width > $height) return 'landscape';
        if ( $width < $height) return 'portrait';
        return 'square';
    }

    //
    // Gets the image's current width.
    //
    // Returns the width as an integer.
    //
    public function getWidth() {
        return (int) imagesx($this->image);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Manipulation
    //////////////////////////////////////////////////////////////////////////////////////////////////

    //
    // Same as PHP's imagecopymerge, but works with transparent images. Used internally for overlay.
    //
    private static function imageCopyMergeAlpha($destinationImage, $sourceImage, $destinationX, $destinationY, $sourceX, $sourceY, $sourceW, $sourceH, $percent) {
        // Are we merging with transparency?
        if ( $percent < 100 ) {
            // Disable alpha blending and "colorize" the image using a transparent color
            imageAlphaBlending($sourceImage, false);
            imageFilter($sourceImage, IMG_FILTER_COLORIZE, 0, 0, 0, 127 * ((100 - $percent) / 100));
        }

        imageCopy($destinationImage, $sourceImage, $destinationX, $destinationY, $sourceX, $sourceY, $sourceW, $sourceH);

        return true;
    }

    /**
     * Copy some other image onto this one. Can copy parts of another area into this image.
     * Specify different source and destination sizes to resize the image that is copied.
     * @param Image fromImage
     * @param int toX - left of destination area
     * @param int toY - top of destination area
     * @param int toWidth - width of destination area
     * @param int toHeight - height of destination area
     * @param int fromX - left of source area
     * @param int fromY - top of source area
     * @param int fromWidth - width of source area
     * @param int fromHeight - height of source area
     * @return object this
     */
    public function copyResized($fromImage, $toX, $toY, $toWidth, $toHeight, $fromX, $fromY, $fromWidth, $fromHeight) {
        @ImageCopyResized($this->image, $fromImage->image, $toX, $toY, $fromX, $fromY, $toWidth, $toHeight, $fromWidth, $fromHeight);
        return $this;
    }

    /**
     * Turn on alpha blending
     * @return object this
     */
    public function alphaBlending() {
        imageAlphaBlending($this->image, true);
        imageSaveAlpha($this->image, true);
        return $this;
    }

    /**
     * Rotates an image so the orientation will be correct based on its exif data. It is safe to call
     * this method on images that don't have exif data (no changes will be made).
     *
     * @return object this
     */
    public function autoOrient() {
        $exif = $this->getExif();

        if ( ! $exif || ! isset($exif['Orientation']) ){
            return $this;
        }

        switch($exif['Orientation']) {
        case 1: // Do nothing!
            break;
        case 2: // Flip horizontally
            $this->flip('x');
            break;
        case 3: // Rotate 180 degrees
            $this->rotate(180);
            break;
        case 4: // Flip vertically
            $this->flip('y');
            break;
        case 5: // Rotate 90 degrees clockwise and flip vertically
            $this->flip('y')->rotate(90);
            break;
        case 6: // Rotate 90 clockwise
            $this->rotate(90);
            break;
        case 7: // Rotate 90 clockwise and flip horizontally
            $this->flip('x')->rotate(90);
            break;
        case 8: // Rotate 90 counterclockwise
            $this->rotate(-90);
            break;
        }

        return $this;
    }

    //
    // Proportionally resize the image to fit inside a specific width and height.
    //
    //  $maxWidth* (int) - The maximum width the image can be.
    //  $maxHeight* (int) - The maximum height the image can be.
    //
    // @return object this
    //
    public function bestFit($maxWidth, $maxHeight) {
        // If the image already fits, there's nothing to do
        if ( $this->getWidth() <= $maxWidth && $this->getHeight() <= $maxHeight) {
            return $this;
        }

        // Calculate max width or height based on orientation
        if ( $this->getOrientation() === 'portrait') {
            $height = $maxHeight;
            $width = $maxHeight * $this->getAspectRatio();
        } else {
            $width = $maxWidth;
            $height = $maxWidth / $this->getAspectRatio();
        }

        // Reduce to max width
        if ( $width > $maxWidth) {
            $width = $maxWidth;
            $height = $width / $this->getAspectRatio();
        }

        // Reduce to max height
        if ( $height > $maxHeight) {
            $height = $maxHeight;
            $width = $height * $this->getAspectRatio();
        }

        return $this->resize($width, $height);
    }

    //
    // Crop the image.
    //
    //  $x1 - Top left x coordinate.
    //  $y1 - Top left y coordinate.
    //  $x2 - Bottom right x coordinate.
    //  $y2 - Bottom right x coordinate.
    //
    // @return object this
    //
    public function crop($x1, $y1, $x2, $y2) {
        // Keep crop within image dimensions
        $x1 = self::keepWithin($x1, 0, $this->getWidth());
        $x2 = self::keepWithin($x2, 0, $this->getWidth());
        $y1 = self::keepWithin($y1, 0, $this->getHeight());
        $y2 = self::keepWithin($y2, 0, $this->getHeight());

        // Crop it
        $this->image = imageCrop($this->image, [
        'x' => min($x1, $x2),
        'y' => min($y1, $y2),
        'width' => abs($x2 - $x1),
        'height' => abs($y2 - $y1)
        ]);

        return $this;
    }

    //
    // Applies a duotone filter to the image.
    //
    //  $lightColor* (string|array) - The lightest color in the duotone.
    //  $darkColor* (string|array) - The darkest color in the duotone.
    //
    // @return object this
    //
    function duotone($lightColor, $darkColor) {
        $lightColor = self::normalizeColor($lightColor);
        $darkColor = self::normalizeColor($darkColor);

        // Calculate averages between light and dark colors
        $redAvg = $lightColor['red'] - $darkColor['red'];
        $greenAvg = $lightColor['green'] - $darkColor['green'];
        $blueAvg = $lightColor['blue'] - $darkColor['blue'];

        // Create a matrix of all possible duotone colors based on gray values
        $pixels = [];
        for($i = 0; $i <= 255; $i++) {
        $grayAvg = $i / 255;
        $pixels['red'][$i] = $darkColor['red'] + $grayAvg * $redAvg;
        $pixels['green'][$i] = $darkColor['green'] + $grayAvg * $greenAvg;
        $pixels['blue'][$i] = $darkColor['blue'] + $grayAvg * $blueAvg;
        }

        // Apply the filter pixel by pixel
        for($x = 0; $x < $this->getWidth(); $x++) {
            for($y = 0; $y < $this->getHeight(); $y++) {
                $rgb = $this->getColorAt($x, $y);
                $gray = min(255, round(0.299 * $rgb['red'] + 0.114 * $rgb['blue'] + 0.587 * $rgb['green']));
                $this->dot($x, $y, [
                'red' => $pixels['red'][$gray],
                'green' => $pixels['green'][$gray],
                'blue' => $pixels['blue'][$gray]
                ]);
            }
        }

        return $this;
    }

    //
    // Proportionally resize the image to a specific height.
    //
    // **DEPRECATED:** This method was deprecated in version 3.2.2 and will be removed in version 4.0.
    // Please use `resize(null, $height)` instead.
    //
    //  $height* (int) - The height to resize the image to.
    //
    // @return object this
    //
    public function fitToHeight($height) {
        return $this->resize(null, $height);
    }

    //
    // Proportionally resize the image to a specific width.
    //
    // **DEPRECATED:** This method was deprecated in version 3.2.2 and will be removed in version 4.0.
    // Please use `resize($width, null)` instead.
    //
    //  $width* (int) - The width to resize the image to.
    //
    // @return object this
    //
    public function fitToWidth($width) {
        return $this->resize($width, null);
    }

    //
    // Flip the image horizontally or vertically.
    //
    //  $direction* (string) - The direction to flip: x|y|both
    //
    // @return object this
    //
    public function flip($direction) {
        switch($direction) {
        case 'x':
            imageFlip($this->image, IMG_FLIP_HORIZONTAL);
            break;
        case 'y':
            imageFlip($this->image, IMG_FLIP_VERTICAL);
            break;
        case 'both':
            imageFlip($this->image, IMG_FLIP_BOTH);
            break;
        }

        return $this;
    }

    //
    // Reduces the image to a maximum number of colors.
    //
    //  $max* (int) - The maximum number of colors to use.
    //  $dither (bool) - Whether or not to use a dithering effect (default true).
    //
    // @return object this
    //
    public function maxColors($max, $dither = true) {
        imageTrueColorToPalette($this->image, $dither, max(1, $max));

        return $this;
    }

    //
    // Place an image on top of the current image.
    //
    //  $overlay* (string|SimpleImage) - The image to overlay. This can be a filename, a data URI, or
    //    a SimpleImage object.
    //  $anchor (string) - The anchor point: 'center', 'top', 'bottom', 'left', 'right', 'top left',
    //    'top right', 'bottom left', 'bottom right' (default 'center')
    //  $opacity (float) - The opacity level of the overlay 0-1 (default 1).
    //  $xOffset (int) - Horizontal offset in pixels (default 0).
    //  $yOffset (int) - Vertical offset in pixels (default 0).
    //
    // @return object this
    //
    public function overlay($overlay, $anchor = 'center', $opacity = 1, $xOffset = 0, $yOffset = 0) {
        // Load overlay image
        if ( is_string($overlay) ) {
            $overlayObj = new Image();
            $overlayObj->load($overlay);
        } elseif ( $overlay instanceof Image ) {
            $overlayObj = $overlay;
        } else {
            throw new \Exception("Overlay param 1 must be string or Image object", self::ERR_OVERLAY);
        }

        // Convert opacity
        $opacity = self::keepWithin($opacity, 0, 1) * 100;

        // Determine placement
        switch($anchor) {
        case 'top left':
            $x = $xOffset;
            $y = $yOffset;
            break;
        case 'top right':
            $x = $this->getWidth() - $overlayObj->getWidth() + $xOffset;
            $y = $yOffset;
            break;
        case 'top':
            $x = ($this->getWidth() / 2) - ($overlayObj->getWidth() / 2) + $xOffset;
            $y = $yOffset;
            break;
        case 'bottom left':
            $x = $xOffset;
            $y = $this->getHeight() - $overlayObj->getHeight() + $yOffset;
            break;
        case 'bottom right':
            $x = $this->getWidth() - $overlayObj->getWidth() + $xOffset;
            $y = $this->getHeight() - $overlayObj->getHeight() + $yOffset;
            break;
        case 'bottom':
            $x = ($this->getWidth() / 2) - ($overlayObj->getWidth() / 2) + $xOffset;
            $y = $this->getHeight() - $overlayObj->getHeight() + $yOffset;
            break;
        case 'left':
            $x = $xOffset;
            $y = ($this->getHeight() / 2) - ($overlayObj->getHeight() / 2) + $yOffset;
            break;
        case 'right':
            $x = $this->getWidth() - $overlayObj->getWidth() + $xOffset;
            $y = ($this->getHeight() / 2) - ($overlayObj->getHeight() / 2) + $yOffset;
            break;
        default:
            $x = ($this->getWidth() / 2) - ($overlayObj->getWidth() / 2) + $xOffset;
            $y = ($this->getHeight() / 2) - ($overlayObj->getHeight() / 2) + $yOffset;
            break;
        }

        // Perform the overlay
        self::imageCopyMergeAlpha(
            $this->image,
            $overlayObj->image,
            $x, $y,
            0, 0,
            $overlayObj->getWidth(),
            $overlayObj->getHeight(),
            $opacity
        );

        return $this;
    }

    //
    // Resize an image to the specified dimensions. If only one dimension is specified, the image will
    // be resized proportionally.
    //
    //  $width* (int) - The new image width.
    //  $height* (int) - The new image height.
    //
    // @return object this
    //
    public function resize($width = null, $height = null) {
        // No dimentions specified
        if ( ! $width && !$height) {
            return $this;
        }

        // Resize to width
        if ( $width && !$height) {
            $height = $width / $this->getAspectRatio();
        }

        // Resize to height
        if ( ! $width && $height) {
            $width = $height * $this->getAspectRatio();
        }

        // If the dimensions are the same, there's no need to resize
        if ( $this->getWidth() === $width && $this->getHeight() === $height) {
            return $this;
        }

        // We can't use imagescale because it doesn't seem to preserve transparency properly. The
        // workaround is to create a new truecolor image, allocate a transparent color, and copy the
        // image over to it using imageCopyResampled.
        $newImage = imageCreateTrueColor($width, $height);
        $transparentColor = imageColorAllocateAlpha($newImage, 0, 0, 0, 127);
        imageColorTransparent($newImage, $transparentColor);
        imageFill($newImage, 0, 0, $transparentColor);
        imageCopyResampled(
            $newImage,
            $this->image,
            0, 0, 0, 0,
            $width,
            $height,
            $this->getWidth(),
            $this->getHeight()
        );

        // Swap out the new image
        $this->image = $newImage;

        return $this;
    }

    //
    // Rotates the image.
    //
    // $angle* (int) - The angle of rotation (-360 - 360).
    // $backgroundColor (string|array) - The background color to use for the uncovered zone area
    //   after rotation (default 'transparent').
    //
    // @return object this
    //
    public function rotate($angle, $backgroundColor = 'transparent') {
        // Rotate the image on a canvas with the desired background color
        $backgroundColor = $this->allocateColor($backgroundColor);

        $this->image = imageRotate(
            $this->image,
            -(self::keepWithin($angle, -360, 360)),
            $backgroundColor
        );

        return $this;
    }

    //
    // Adds text to the image.
    //
    //  $text* (string) - The desired text.
    //  $options (array) - An array of options.
    //    - fontFile* (string) - The TrueType (or compatible) font file to use.
    //    - size (int) - The size of the font in pixels (default 12).
    //    - color (string|array) - The text color (default black).
    //    - anchor (string) - The anchor point: 'center', 'top', 'bottom', 'left', 'right',
    //      'top left', 'top right', 'bottom left', 'bottom right' (default 'center').
    //    - xOffset (int) - The horizontal offset in pixels (default 0).
    //    - yOffset (int) - The vertical offset in pixels (default 0).
    //    - shadow (array) - Text shadow params.
    //      - x* (int) - Horizontal offset in pixels.
    //      - y* (int) - Vertical offset in pixels.
    //      - color* (string|array) - The text shadow color.
    //  $boundary (array) - If passed, this variable will contain an array with coordinates that
    //    surround the text: [x1, y1, x2, y2, width, height]. This can be used for calculating the
    //    text's position after it gets added to the image.
    //
    // @return object this
    //
    public function text($text, $options, &$boundary = null) {
        // Check for freetype support
        if ( ! function_exists('imageTtfText')) {
            throw new \Exception(
                'Freetype support is not enabled in your version of PHP.',
                self::ERR_FREETYPE_NOT_ENABLED
            );
        }

        // Default options
        $options = array_merge([
            'fontFile' => null,
            'size' => 12,
            'color' => 'black',
            'anchor' => 'center',
            'xOffset' => 0,
            'yOffset' => 0,
            'shadow' => null
        ], $options);

        // Extract and normalize options
        $fontFile = $options['fontFile'];
        $size = ($options['size'] / 96) * 72; // Convert px to pt (72pt per inch, 96px per inch)
        $color = $this->allocateColor($options['color']);
        $anchor = $options['anchor'];
        $xOffset = $options['xOffset'];
        $yOffset = $options['yOffset'];
        $angle = 0;

        // Calculate the bounding box dimensions
        //
        // Since imagettfbox() returns a bounding box from the text's baseline, we can end up with
        // different heights for different strings of the same font size. For example, 'type' will often
        // be taller than 'text' because the former has a descending letter.
        //
        // To compensate for this, we create two bounding boxes: one to measure the cap height and
        // another to measure the descender height. Based on that, we can adjust the text vertically
        // to appear inside the box with a reasonable amount of consistency.
        //
        // See: https://github.com/claviska/SimpleImage/issues/165
        //
        $box = imageTtfBBox($size, $angle, $fontFile, $text);
        if ( ! $box) {
            throw new \Exception("Unable to load font file: $fontFile", self::ERR_FONT_FILE);
        }
        $boxWidth = abs($box[6] - $box[2] )+4;
        $boxHeight = $options['size'];

        // Determine cap height
        $box = imageTtfBBox($size, $angle, $fontFile, 'X');
        $capHeight = abs($box[7] - $box[1]);

        // Determine descender height
        $box = imageTtfBBox($size, $angle, $fontFile, 'X Qgjpqy');
        $fullHeight = abs($box[7] - $box[1]);
        $descenderHeight = $fullHeight - $capHeight;

        // Determine position
        switch($anchor) {
        case 'top left':
            $x = $xOffset;
            $y = $yOffset + $boxHeight;
            break;
        case 'top right':
            $x = $this->getWidth() - $boxWidth + $xOffset;
            $y = $yOffset + $boxHeight;
            break;
        case 'top':
            $x = ($this->getWidth() / 2) - ($boxWidth / 2) + $xOffset;
            $y = $yOffset + $boxHeight;
            break;
        case 'bottom left':
            $x = $xOffset;
            $y = $this->getHeight() - $boxHeight + $yOffset + $boxHeight;
            break;
        case 'bottom right':
            $x = $this->getWidth() - $boxWidth + $xOffset;
            $y = $this->getHeight() - $boxHeight + $yOffset + $boxHeight;
            break;
        case 'bottom':
            $x = ($this->getWidth() / 2) - ($boxWidth / 2) + $xOffset;
            $y = $this->getHeight() - $boxHeight + $yOffset + $boxHeight;
            break;
        case 'left':
            $x = $xOffset;
            $y = ($this->getHeight() / 2) - (($boxHeight / 2) - $boxHeight) + $yOffset;
            break;
        case 'right';
            $x = $this->getWidth() - $boxWidth + $xOffset;
            $y = ($this->getHeight() / 2) - (($boxHeight / 2) - $boxHeight) + $yOffset;
            break;
        default: // center
            $x = ($this->getWidth() / 2) - ($boxWidth / 2) + $xOffset;
            $y = ($this->getHeight() / 2) - (($boxHeight / 2) - $boxHeight) + $yOffset;
            break;
        }

        $x = (int) round($x);
        $y = (int) round($y);

        // Pass the boundary back by reference
        $boundary = [
            'left' => $x,
            'top' => $y - $boxHeight, // $y is the baseline, not the top!
            'right' => $x + $boxWidth,
            'bottom' => $y,
            'width' => $boxWidth,
            'height' => $boxHeight
        ];

        // Text shadow
        if ( is_array($options['shadow'])) {
            imageTtfText(
                $this->image,
                $size,
                $angle,
                $x + $options['shadow']['x'],
                $y + $options['shadow']['y'] - $descenderHeight,
                $this->allocateColor($options['shadow']['color']),
                $fontFile,
                $text
            );
        }

        // Draw the text
        imageTtfText($this->image, $size, $angle, $x, $y - $descenderHeight, $color, $fontFile, $text);

        return $this;
    }

    //
    // Creates a thumbnail image. This function attempts to get the image as close to the provided
    // dimensions as possible, then crops the remaining overflow to force the desired size. Useful
    // for generating thumbnail images.
    //
    //  $width* (int) - The thumbnail width.
    //  $height* (int) - The thumbnail height.
    //  $anchor (string) - The anchor point: 'center', 'top', 'bottom', 'left', 'right', 'top left',
    //    'top right', 'bottom left', 'bottom right' (default 'center').
    //
    // @return object this
    //
    public function thumbnail($width, $height, $anchor = 'center') {
        // Determine aspect ratios
        $currentRatio = $this->getHeight() / $this->getWidth();
        $targetRatio = $height / $width;

        // Fit to height/width
        if ( $targetRatio > $currentRatio) {
            $this->resize(null, $height);
        } else {
            $this->resize($width, null);
        }

        switch($anchor) {
        case 'top':
            $x1 = floor(($this->getWidth() / 2) - ($width / 2));
            $x2 = $width + $x1;
            $y1 = 0;
            $y2 = $height;
            break;
        case 'bottom':
            $x1 = floor(($this->getWidth() / 2) - ($width / 2));
            $x2 = $width + $x1;
            $y1 = $this->getHeight() - $height;
            $y2 = $this->getHeight();
            break;
        case 'left':
            $x1 = 0;
            $x2 = $width;
            $y1 = floor(($this->getHeight() / 2) - ($height / 2));
            $y2 = $height + $y1;
            break;
        case 'right':
            $x1 = $this->getWidth() - $width;
            $x2 = $this->getWidth();
            $y1 = floor(($this->getHeight() / 2) - ($height / 2));
            $y2 = $height + $y1;
            break;
        case 'top left':
            $x1 = 0;
            $x2 = $width;
            $y1 = 0;
            $y2 = $height;
            break;
        case 'top right':
            $x1 = $this->getWidth() - $width;
            $x2 = $this->getWidth();
            $y1 = 0;
            $y2 = $height;
            break;
        case 'bottom left':
            $x1 = 0;
            $x2 = $width;
            $y1 = $this->getHeight() - $height;
            $y2 = $this->getHeight();
            break;
        case 'bottom right':
            $x1 = $this->getWidth() - $width;
            $x2 = $this->getWidth();
            $y1 = $this->getHeight() - $height;
            $y2 = $this->getHeight();
            break;
        default:
            $x1 = floor(($this->getWidth() / 2) - ($width / 2));
            $x2 = $width + $x1;
            $y1 = floor(($this->getHeight() / 2) - ($height / 2));
            $y2 = $height + $y1;
            break;
        }

        // Return the cropped thumbnail image
        return $this->crop($x1, $y1, $x2, $y2);
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Drawing
    //////////////////////////////////////////////////////////////////////////////////////////////////

    //
    // Draws an arc.
    //
    //  $x* (int) - The x coordinate of the arc's center.
    //  $y* (int) - The y coordinate of the arc's center.
    //  $width* (int) - The width of the arc.
    //  $height* (int) - The height of the arc.
    //  $start* (int) - The start of the arc in degrees.
    //  $end* (int) - The end of the arc in degrees.
    //  $color* (string|array) - The arc color.
    //  $thickness (int|string) - Line thickness in pixels or Image::Filled (default 1).
    //
    // @return object this
    //
    public function arc($x, $y, $width, $height, $start, $end, $color, $thickness = 1) {
        // Allocate the color
        $color = $this->allocateColor($color);

        // Draw an arc
        if ( $thickness === self::Filled ) {
            imageSetThickness($this->image, 1);
            imageFilledArc($this->image, $x, $y, $width, $height, $start, $end, $color, IMG_ARC_PIE);
        } else {
            imageSetThickness($this->image, $thickness);
            imageArc($this->image, $x, $y, $width, $height, $start, $end, $color);
        }

        return $this;
    }

    //
    // Draws a border around the image.
    //
    //  $color (string|array) - The border color.
    //  $thickness (int) - The thickness of the border (default 1).
    //
    // @return object this
    //
    public function border($color, $thickness = 1) {
        $x1 = 0;
        $y1 = 0;
        $x2 = $this->getWidth() - 1;
        $y2 = $this->getHeight() - 1;

        // Draw a border rectangle until it reaches the correct width
        for($i = 0; $i < $thickness; $i++) {
            $this->rectangle($x1++, $y1++, $x2--, $y2--, $color);
        }

        return $this;
    }

    //
    // Draws a single pixel dot.
    //
    //  $x (int) - The x coordinate of the dot.
    //  $y (int) - The y coordinate of the dot.
    //  $color (string|array) - The dot color.
    //
    // @return object this
    //
    public function dot($x, $y, $color) {
        $color = $this->allocateColor($color);
        imageSetPixel($this->image, $x, $y, $color);

        return $this;
    }

    /**
     * Draws an ellipse.
     * @param $x (int) - The x coordinate of the center.
     * @param $y (int) - The y coordinate of the center.
     * @param $width (int) - The ellipse width.
     * @param $height (int) - The ellipse height.
     * @param $color (string|array) - The ellipse color.
     * @param $thickness (int|string) - Line thickness in pixels or Image::Filled (default 1).
     * @return object this
     */

    public function ellipse($x, $y, $width, $height, $angle, $color, $thickness = 1) {
        // Allocate the color
        $color = $this->allocateColor($color);
        $angle = $angle % 360;
        // Draw an ellipse
        if ( ($angle % 90) == 0 ) {
            if ( $angle == 90 || $angle == 270 ) {
                $swap = $width;
                $width = $height;
                $height = $swap;
            }
            if ( $thickness === self::Filled ) {
                imageSetThickness($this->image, 1);
                imageFilledEllipse($this->image, $x, $y, $width, $height, $color);
            } else {
                // imageSetThickness doesn't appear to work with imageellipse, so we work around it.
                imageSetThickness($this->image, 1);
                $i = 0;
                while($i++ < $thickness * 2 - 1) {
                    imageEllipse($this->image, $x, $y, --$width, $height--, $color);
                }
            }
        } else {
            $filled = $thickness === self::Filled ? true : false;
            $this->rotatedEllipse($x, $y, $width, $height, $angle, $color, $filled);
        }

        return $this;
    }

    /**
     * Internal function to draw a rotated ellipse.
     */
    private function rotatedEllipse($cx, $cy, $width, $height, $angle, $color, $filled=false) {
        // modified here from nojer's version
        // Rotates from the three o-clock position clockwise with increasing angle.
        // Arguments are compatible with imageellipse.
      
        $width = $width/2;
        $height = $height/2;
      
        // This affects how coarse the ellipse is drawn.
        $step = 3;
      
        $cosangle = cos(deg2rad($angle));
        $sinangle = sin(deg2rad($angle));
      
        // $px and $py are initialised to values corresponding to $angle=0.
        $px = $width * $cosangle;
        $py = $width * $sinangle;
        
        for ($angle=$step; $angle<=(180+$step); $angle+=$step) {
          
            $ox = $width * cos(deg2rad($angle));
            $oy = $height * sin(deg2rad($angle));
            
            $x = ($ox * $cosangle) - ($oy * $sinangle);
            $y = ($ox * $sinangle) + ($oy * $cosangle);
      
            if ( $filled ) {
                $this->triangle($cx, $cy, $cx+$px, $cy+$py, $cx+$x, $cy+$y, $color);
                $this->triangle($cx, $cy, $cx-$px, $cy-$py, $cx-$x, $cy-$y, $color);
            } else {
                imageLine($this->image, $cx+$px, $cy+$py, $cx+$x, $cy+$y, $color);
                imageLine($this->image, $cx-$px, $cy-$py, $cx-$x, $cy-$y, $color);
            }
            $px = $x;
            $py = $y;
        }
    }
      
    function triangle($x1, $y1, $x2, $y2, $x3, $y3, $color) {
        $coords = array($x1, $y1, $x2, $y2, $x3, $y3);
        imageFilledPolygon($this->image, $coords, 3, $color);
    }


    //
    // Fills the entire image with a solid color.
    //
    //  $color (string|array) - The fill color.
    //
    // @return object this
    //
    public function fill($color) {
        // Draw a filled rectangle over the entire image
        $this->rectangle(0, 0, $this->getWidth(), $this->getHeight(), 'white', self::Filled);

        // Now flood it with the appropriate color
        $color = $this->allocateColor($color);
        imageFill($this->image, 0, 0, $color);

        return $this;
    }

    //
    // Draws a line.
    //
    //  $x1 (int) - The x coordinate for the first point.
    //  $y1 (int) - The y coordinate for the first point.
    //  $x2 (int) - The x coordinate for the second point.
    //  $y2 (int) - The y coordinate for the second point.
    //  $color (string|array) - The line color.
    //  $thickness (int) - The line thickness (default 1).
    //
    // @return object this
    //
    public function line($x1, $y1, $x2, $y2, $color, $thickness = 1) {
        // Allocate the color
        $color = $this->allocateColor($color);

        // Draw a line
        imageSetThickness($this->image, $thickness);
        imageLine($this->image, $x1, $y1, $x2, $y2, $color);

        return $this;
    }

    //
    // Draws a polygon.
    //
    //  $vertices* (array) - The polygon's vertices in an array of x/y arrays. Example:
    //    [
    //      ['x' => x1, 'y' => y1],
    //      ['x' => x2, 'y' => y2],
    //      ['x' => xN, 'y' => yN]
    //    ]
    //  $color* (string|array) - The polygon color.
    //  $thickness (int|string) - Line thickness in pixels or Image::Filled (default 1).
    //
    // @return object this
    //
    public function polygon($vertices, $color, $thickness = 1) {
        // Allocate the color
        $color = $this->allocateColor($color);

        // Convert [['x' => x1, 'y' => x1], ['x' => x1, 'y' => y2], ...] to [x1, y1, x2, y2, ...]
        $points = [];
        foreach($vertices as $vals) {
            $points[] = $vals['x'];
            $points[] = $vals['y'];
        }

        // Draw a polygon
        if ( $thickness === self::Filled ) {
            imageSetThickness($this->image, 1);
            imageFilledPolygon($this->image, $points, count($vertices), $color);
        } else {
            imageSetThickness($this->image, $thickness);
            imagePolygon($this->image, $points, count($vertices), $color);
        }

        return $this;
    }

    //
    // Draws a rectangle.
    //
    //  $x1 (int) - The upper left x coordinate.
    //  $y1 (int) - The upper left y coordinate.
    //  $x2 (int) - The bottom right x coordinate.
    //  $y2 (int) - The bottom right y coordinate.
    //  $color* (string|array) - The rectangle color.
    //  $thickness (int|string) - Line thickness in pixels or self::Filled (default 1).
    //
    // @return object this
    //
    public function rectangle($x1, $y1, $x2, $y2, $color, $thickness = 1) {
        // Allocate the color
        $color = $this->allocateColor($color);

        // Draw a rectangle
        if ( $thickness === self::Filled ) {
            imageSetThickness($this->image, 1);
            imageFilledRectangle($this->image, $x1, $y1, $x2, $y2, $color);
        } else {
            imageSetThickness($this->image, $thickness);
            imageRectangle($this->image, $x1, $y1, $x2, $y2, $color);
        }

        return $this;
    }

    //
    // Draws a rounded rectangle.
    //
    //  $x1 (int) - The upper left x coordinate.
    //  $y1 (int) - The upper left y coordinate.
    //  $x2 (int) - The bottom right x coordinate.
    //  $y2 (int) - The bottom right y coordinate.
    //  $radius* (int) - The border radius in pixels.
    //  $color* (string|array) - The rectangle color.
    //  $thickness (int|string) - Line thickness in pixels or self::Filled (default 1).
    //
    // @return object this
    //
    public function roundedRectangle($x1, $y1, $x2, $y2, $radius, $color, $thickness = 1) {
        if ( $thickness === self::Filled) {
            // Draw the filled rectangle without edges
            $this->rectangle($x1 + $radius + 1, $y1, $x2 - $radius - 1, $y2, $color, self::Filled);
            $this->rectangle($x1, $y1 + $radius + 1, $x1 + $radius, $y2 - $radius - 1, $color, self::Filled);
            $this->rectangle($x2 - $radius, $y1 + $radius + 1, $x2, $y2 - $radius - 1, $color, self::Filled);
            // Fill in the edges with arcs
            $this->arc($x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, self::Filled);
            $this->arc($x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, self::Filled);
            $this->arc($x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, self::Filled);
            $this->arc($x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 360, 90, $color, self::Filled);
        } else {
            // Draw the rectangle outline without edges
            $this->line($x1 + $radius, $y1, $x2 - $radius, $y1, $color, $thickness);
            $this->line($x1 + $radius, $y2, $x2 - $radius, $y2, $color, $thickness);
            $this->line($x1, $y1 + $radius, $x1, $y2 - $radius, $color, $thickness);
            $this->line($x2, $y1 + $radius, $x2, $y2 - $radius, $color, $thickness);
            // Fill in the edges with arcs
            $this->arc($x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color, $thickness);
            $this->arc($x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color, $thickness);
            $this->arc($x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color, $thickness);
            $this->arc($x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 360, 90, $color, $thickness);
        }

        return $this;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Filters
    //////////////////////////////////////////////////////////////////////////////////////////////////

    //
    // Applies the blur filter.
    //
    //  $type (string) - The blur algorithm to use: 'selective', 'gaussian' (default 'gaussian').
    //  $passes (int) - The number of time to apply the filter, enhancing the effect (default 1).
    //
    // @return object this
    //
    public function blur($type = 'selective', $passes = 1) {
        $filter = $type === 'gaussian' ? IMG_FILTER_GAUSSIAN_BLUR : IMG_FILTER_SELECTIVE_BLUR;

        for($i = 0; $i < $passes; $i++) {
            imageFilter($this->image, $filter);
        }

        return $this;
    }

    //
    // Applies the brightness filter to brighten the image.
    //
    //  $percentage* (int) - Percentage to brighten the image (0 - 100).
    //
    // @return object this
    //
    public function brighten($percentage) {
        $percentage = self::keepWithin(255 * $percentage / 100, 0, 255);

        imageFilter($this->image, IMG_FILTER_BRIGHTNESS, $percentage);

        return $this;
    }

    //
    // Applies the colorize filter.
    //
    //  $color (string|array) - The filter color.
    //
    // @return object this
    //
    public function colorize($color) {
        $color = self::normalizeColor($color);

        imageFilter(
            $this->image,
            IMG_FILTER_COLORIZE,
            $color['red'],
            $color['green'],
            $color['blue'],
            127 - ($color['alpha'] * 127)
        );

        return $this;
    }

    //
    // Applies the contrast filter.
    //
    //  $percentage (int) - Percentage to adjust (-100 - 100).
    //
    // @return object this
    //
    public function contrast($percentage) {
        imageFilter($this->image, IMG_FILTER_CONTRAST, self::keepWithin($percentage, -100, 100));

        return $this;
    }

    //
    // Applies the brightness filter to darken the image.
    //
    //  $percentage (int) - Percentage to darken the image (0 - 100).
    //
    // @return object this
    //
    public function darken($percentage) {
        $percentage = self::keepWithin(255 * $percentage / 100, 0, 255);

        imageFilter($this->image, IMG_FILTER_BRIGHTNESS, -$percentage);

        return $this;
    }

    //
    // Applies the desaturate (grayscale) filter.
    //
    // @return object this
    //
    public function desaturate() {
        imageFilter($this->image, IMG_FILTER_GRAYSCALE);

        return $this;
    }

    //
    // Applies the edge detect filter.
    //
    // @return object this
    //
    public function edgeDetect() {
        imageFilter($this->image, IMG_FILTER_EDGEDETECT);

        return $this;
    }

    //
    // Applies the emboss filter.
    //
    // @return object this
    //
    public function emboss() {
        imageFilter($this->image, IMG_FILTER_EMBOSS);

        return $this;
    }

    //
    // Inverts the image's colors.
    //
    // @return object this
    //
    public function invert() {
        imageFilter($this->image, IMG_FILTER_NEGATE);

        return $this;
    }

    //
    // Changes the image's opacity level.
    //
    //  $opacity (float) - The desired opacity level (0 - 1).
    //
    // @return object this
    //
    public function opacity($opacity) {
        // Create a transparent image
        $newImage = new SimpleImage();
        $newImage->fromNew($this->getWidth(), $this->getHeight());

        // Copy the current image (with opacity) onto the transparent image
        self::imageCopyMergeAlpha(
            $newImage->image,
            $this->image,
            $x, $y,
            0, 0,
            $this->getWidth(),
            $this->getHeight(),
            self::keepWithin($opacity, 0, 1) * 100
        );

        return $this;
    }

    //
    // Applies the pixelate filter.
    //
    //  $size (int) - The size of the blocks in pixels (default 10).
    //
    // @return object this
    //
    public function pixelate($size = 10) {
        imageFilter($this->image, IMG_FILTER_PIXELATE, $size, true);

        return $this;
    }

    //
    // Simulates a sepia effect by desaturating the image and applying a sepia tone.
    //
    // @return object this
    //
    public function sepia() {
        imageFilter($this->image, IMG_FILTER_GRAYSCALE);
        imageFilter($this->image, IMG_FILTER_COLORIZE, 70, 35, 0);

        return $this;
    }

    //
    // Sharpens the image.
    //
    // @return object this
    //
    public function sharpen() {
        $sharpen = [
            [0, -1, 0],
            [-1, 5, -1],
            [0, -1, 0]
        ];
        $divisor = array_sum(array_map('array_sum', $sharpen));

        imageConvolution($this->image, $sharpen, $divisor, 0);

        return $this;
    }

    //
    // Applies the mean remove filter to produce a sketch effect.
    //
    // @return object this
    //
    public function sketch() {
        imageFilter($this->image, IMG_FILTER_MEAN_REMOVAL);

        return $this;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////
    // Color utilities
    //////////////////////////////////////////////////////////////////////////////////////////////////

    //
    // Converts a "friendly color" into a color identifier for use with GD's image functions.
    //
    //  $image (resource) - The target image.
    //  $color (string|array) - The color to allocate.
    //
    // Returns a color identifier.
    //
    private function allocateColor($color) {
        $color = self::normalizeColor($color);

        // Was this color already allocated?
        $index = imageColorExactAlpha(
            $this->image,
            $color['red'],
            $color['green'],
            $color['blue'],
            127 - ($color['alpha'] * 127)
        );
        if ( $index > -1) {
            // Yes, return this color index
            return $index;
        }

        // Allocate a new color index
        return imageColorAllocateAlpha(
            $this->image,
            $color['red'],
            $color['green'],
            $color['blue'],
            127 - ($color['alpha'] * 127)
        );
    }

    //
    // Adjusts a color by increasing/decreasing red/green/blue/alpha values independently.
    //
    //  $color (string|array) - The color to adjust.
    //  $red (int) - Red adjustment (-255 - 255).
    //  $green (int) - Green adjustment (-255 - 255).
    //  $blue (int) - Blue adjustment (-255 - 255).
    //  $alpha (float) - Alpha adjustment (-1 - 1).
    //
    // Returns an RGBA color array.
    //
    public static function adjustColor($color, $red, $green, $blue, $alpha) {
        // Normalize to RGBA
        $color = self::normalizeColor($color);

        // Adjust each channel
        return self::normalizeColor([
            'red' => $color['red'] + $red,
            'green' => $color['green'] + $green,
            'blue' => $color['blue'] + $blue,
            'alpha' => $color['alpha'] + $alpha
        ]);
    }

    //
    // Darkens a color.
    //
    //  $color (string|array) - The color to darken.
    //  $amount (int) - Amount to darken (0 - 255).
    //
    // Returns an RGBA color array.
    //
    public static function darkenColor($color, $amount) {
        return self::adjustColor($color, -$amount, -$amount, -$amount, 0);
    }

    //
    // Extracts colors from an image like a human would do.™ This method requires the third-party
    // library \League\ColorExtractor. If you're using Composer, it will be installed for you
    // automatically.
    //
    //  $count (int) - The max number of colors to extract (default 5).
    //  $backgroundColor (string|array) - By default any pixel with alpha value greater than zero will
    //    be discarded. This is because transparent colors are not perceived as is. For example, fully
    //    transparent black would be seen white on a white background. So if you want to take
    //    transparency into account, you have to specify a default background color.
    //
    // Returns an array of RGBA colors arrays.
    //
    public function extractColors($count = 5, $backgroundColor = null) {
        // Check for required library
        if ( ! class_exists('\League\ColorExtractor\ColorExtractor') ) {
            throw new \Exception(
                'Required library \League\ColorExtractor is missing.',
                self::ERR_LIB_NOT_LOADED
            );
        }

        // Convert background color to an integer value
        if ( $backgroundColor) {
            $backgroundColor = self::normalizeColor($backgroundColor);
            $backgroundColor = \League\ColorExtractor\Color::fromRgbToInt([
                'r' => $backgroundColor['red'],
                'g' => $backgroundColor['green'],
                'b' => $backgroundColor['blue']
            ]);
        }

        // Extract colors from the image
        $palette = \League\ColorExtractor\Palette::fromGD($this->image, $backgroundColor);
        $extractor = new \League\ColorExtractor\ColorExtractor($palette);
        $colors = $extractor->extract($count);

        // Convert colors to an RGBA color array
        foreach($colors as $key => $value) {
            $colors[$key] = self::normalizeColor(\League\ColorExtractor\Color::fromIntToHex($value));
        }

        return $colors;
    }

    //
    // Gets the RGBA value of a single pixel.
    //
    //  $x (int) - The horizontal position of the pixel.
    //  $y (int) - The vertical position of the pixel.
    //
    // Returns an RGBA color array or false if the x/y position is off the canvas.
    //
    public function getColorAt($x, $y) {
        // Coordinates must be on the canvas
        if ( $x < 0 || $x > $this->getWidth() || $y < 0 || $y > $this->getHeight() ) {
            return false;
        }

        // Get the color of this pixel and convert it to RGBA
        $color = imageColorat($this->image, $x, $y);
        $rgba = imageColorsforindex($this->image, $color);
        $rgba['alpha'] = 127 - ($color >> 24) & 0xFF;

        return $rgba;
    }

    //
    // Lightens a color.
    //
    //  $color (string|array) - The color to lighten.
    //  $amount (int) - Amount to darken (0 - 255).
    //
    // Returns an RGBA color array.
    //
    public static function lightenColor($color, $amount) {
        return self::adjustColor($color, $amount, $amount, $amount, 0);
    }

    //
    // Normalizes a hex or array color value to a well-formatted RGBA array.
    //
    //  $color (string|array) - A CSS color name, hex string, or an array [red, green, blue, alpha].
    //    You can pipe alpha transparency through hex strings and color names. For example:
    //
    //      #fff|0.50 <-- 50% white
    //      red|0.25 <-- 25% red
    //
    // Returns an array: [red, green, blue, alpha]
    //
    public static function normalizeColor($color) {
        // 140 CSS color names and hex values
        $cssColors = [
        'aliceblue' => '#f0f8ff', 'antiquewhite' => '#faebd7', 'aqua' => '#00ffff',
        'aquamarine' => '#7fffd4', 'azure' => '#f0ffff', 'beige' => '#f5f5dc', 'bisque' => '#ffe4c4',
        'black' => '#000000', 'blanchedalmond' => '#ffebcd', 'blue' => '#0000ff',
        'blueviolet' => '#8a2be2', 'brown' => '#a52a2a', 'burlywood' => '#deb887',
        'cadetblue' => '#5f9ea0', 'chartreuse' => '#7fff00', 'chocolate' => '#d2691e',
        'coral' => '#ff7f50', 'cornflowerblue' => '#6495ed', 'cornsilk' => '#fff8dc',
        'crimson' => '#dc143c', 'cyan' => '#00ffff', 'darkblue' => '#00008b', 'darkcyan' => '#008b8b',
        'darkgoldenrod' => '#b8860b', 'darkgray' => '#a9a9a9', 'darkgrey' => '#a9a9a9',
        'darkgreen' => '#006400', 'darkkhaki' => '#bdb76b', 'darkmagenta' => '#8b008b',
        'darkolivegreen' => '#556b2f', 'darkorange' => '#ff8c00', 'darkorchid' => '#9932cc',
        'darkred' => '#8b0000', 'darksalmon' => '#e9967a', 'darkseagreen' => '#8fbc8f',
        'darkslateblue' => '#483d8b', 'darkslategray' => '#2f4f4f', 'darkslategrey' => '#2f4f4f',
        'darkturquoise' => '#00ced1', 'darkviolet' => '#9400d3', 'deeppink' => '#ff1493',
        'deepskyblue' => '#00bfff', 'dimgray' => '#696969', 'dimgrey' => '#696969',
        'dodgerblue' => '#1e90ff', 'firebrick' => '#b22222', 'floralwhite' => '#fffaf0',
        'forestgreen' => '#228b22', 'fuchsia' => '#ff00ff', 'gainsboro' => '#dcdcdc',
        'ghostwhite' => '#f8f8ff', 'gold' => '#ffd700', 'goldenrod' => '#daa520', 'gray' => '#808080',
        'grey' => '#808080', 'green' => '#008000', 'greenyellow' => '#adff2f',
        'honeydew' => '#f0fff0', 'hotpink' => '#ff69b4', 'indianred ' => '#cd5c5c',
        'indigo ' => '#4b0082', 'ivory' => '#fffff0', 'khaki' => '#f0e68c', 'lavender' => '#e6e6fa',
        'lavenderblush' => '#fff0f5', 'lawngreen' => '#7cfc00', 'lemonchiffon' => '#fffacd',
        'lightblue' => '#add8e6', 'lightcoral' => '#f08080', 'lightcyan' => '#e0ffff',
        'lightgoldenrodyellow' => '#fafad2', 'lightgray' => '#d3d3d3', 'lightgrey' => '#d3d3d3',
        'lightgreen' => '#90ee90', 'lightpink' => '#ffb6c1', 'lightsalmon' => '#ffa07a',
        'lightseagreen' => '#20b2aa', 'lightskyblue' => '#87cefa', 'lightslategray' => '#778899',
        'lightslategrey' => '#778899', 'lightsteelblue' => '#b0c4de', 'lightyellow' => '#ffffe0',
        'lime' => '#00ff00', 'limegreen' => '#32cd32', 'linen' => '#faf0e6', 'magenta' => '#ff00ff',
        'maroon' => '#800000', 'mediumaquamarine' => '#66cdaa', 'mediumblue' => '#0000cd',
        'mediumorchid' => '#ba55d3', 'mediumpurple' => '#9370db', 'mediumseagreen' => '#3cb371',
        'mediumslateblue' => '#7b68ee', 'mediumspringgreen' => '#00fa9a',
        'mediumturquoise' => '#48d1cc', 'mediumvioletred' => '#c71585', 'midnightblue' => '#191970',
        'mintcream' => '#f5fffa', 'mistyrose' => '#ffe4e1', 'moccasin' => '#ffe4b5',
        'navajowhite' => '#ffdead', 'navy' => '#000080', 'oldlace' => '#fdf5e6', 'olive' => '#808000',
        'olivedrab' => '#6b8e23', 'orange' => '#ffa500', 'orangered' => '#ff4500',
        'orchid' => '#da70d6', 'palegoldenrod' => '#eee8aa', 'palegreen' => '#98fb98',
        'paleturquoise' => '#afeeee', 'palevioletred' => '#db7093', 'papayawhip' => '#ffefd5',
        'peachpuff' => '#ffdab9', 'peru' => '#cd853f', 'pink' => '#ffc0cb', 'plum' => '#dda0dd',
        'powderblue' => '#b0e0e6', 'purple' => '#800080', 'rebeccapurple' => '#663399',
        'red' => '#ff0000', 'rosybrown' => '#bc8f8f', 'royalblue' => '#4169e1',
        'saddlebrown' => '#8b4513', 'salmon' => '#fa8072', 'sandybrown' => '#f4a460',
        'seagreen' => '#2e8b57', 'seashell' => '#fff5ee', 'sienna' => '#a0522d',
        'silver' => '#c0c0c0', 'skyblue' => '#87ceeb', 'slateblue' => '#6a5acd',
        'slategray' => '#708090', 'slategrey' => '#708090', 'snow' => '#fffafa',
        'springgreen' => '#00ff7f', 'steelblue' => '#4682b4', 'tan' => '#d2b48c', 'teal' => '#008080',
        'thistle' => '#d8bfd8', 'tomato' => '#ff6347', 'turquoise' => '#40e0d0',
        'violet' => '#ee82ee', 'wheat' => '#f5deb3', 'white' => '#ffffff', 'whitesmoke' => '#f5f5f5',
        'yellow' => '#ffff00', 'yellowgreen' => '#9acd32'
        ];

        // Parse alpha from '#fff|.5' and 'white|.5'
        if ( is_string($color) && strstr($color, '|') ) {
            $color = explode('|', $color);
            $alpha = (float) $color[1];
            $color = trim($color[0]);
        } else {
            $alpha = 1;
        }

        // Translate CSS color names to hex values
        if ( is_string($color) && array_key_exists(strtolower($color), $cssColors) ) {
            $color = $cssColors[strtolower($color)];
        }

        // Translate transparent keyword to a transparent color
        if ( $color === 'transparent' ) {
            $color = ['red' => 0, 'green' => 0, 'blue' => 0, 'alpha' => 0];
        }

        // Convert hex values to RGBA
        if ( is_string($color) ) {
            // Remove #
            $hex = preg_replace('/^#/', '', $color);

            // Support short and standard hex codes
            if ( strlen($hex) === 3) {
                list($red, $green, $blue) = [
                $hex[0] . $hex[0],
                $hex[1] . $hex[1],
                $hex[2] . $hex[2]
                ];
            } elseif ( strlen($hex) === 6 ) {
                list($red, $green, $blue) = [
                $hex[0] . $hex[1],
                $hex[2] . $hex[3],
                $hex[4] . $hex[5]
                ];
            } else {
                throw new \Exception("Invalid color value: $color", self::ERR_INVALID_COLOR);
            }

            // Turn color into an array
            $color = [
                'red' => hexdec($red),
                'green' => hexdec($green),
                'blue' => hexdec($blue),
                'alpha' => $alpha
            ];
        }

        // Enforce color value ranges
        if ( is_array($color) ) {
            // RGB default to 0
            $color['red'] = isset($color['red']) ? $color['red'] : 0;
            $color['green'] = isset($color['green']) ? $color['green'] : 0;
            $color['blue'] = isset($color['blue']) ? $color['blue'] : 0;

            // Alpha defaults to 1
            $color['alpha'] = isset($color['alpha']) ? $color['alpha'] : 1;

            return [
                'red' => (int) self::keepWithin((int) $color['red'], 0, 255),
                'green' => (int) self::keepWithin((int) $color['green'], 0, 255),
                'blue' => (int) self::keepWithin((int) $color['blue'], 0, 255),
                'alpha' => self::keepWithin($color['alpha'], 0, 1)
            ];
        }

        throw new \Exception("Invalid color value: $color", self::ERR_INVALID_COLOR);
    }

}
