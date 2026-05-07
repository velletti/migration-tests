<?php

namespace App\Service;

class ImageDiffService
{
    public function compare(string $img1, string $img2): float
    {
        $image1 = imagecreatefrompng($img1);
        $image2 = imagecreatefrompng($img2);

        $width = imagesx($image1);
        $height = imagesy($image1);

        $diff = 0;
        $total = $width * $height;

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {

                if (!$this->colorsAreSimilar(
                    imagecolorat($image1, $x, $y),
                    imagecolorat($image2, $x, $y)
                )) {
                    $diff++;
                }

            }
        }

        imagedestroy($image1);
        imagedestroy($image2);

        return 100 - (($diff / $total) * 100);
    }

    public function createDiffImage(string $img1, string $img2, string $output): void
    {
        $image1 = imagecreatefrompng($img1);
        $image2 = imagecreatefrompng($img2);

        $width = imagesx($image1);
        $height = imagesy($image1);

        $diffImg = imagecreatetruecolor($width, $height);

        // Farben
        $transparent = imagecolorallocatealpha($diffImg, 0, 0, 0, 127);
        imagefill($diffImg, 0, 0, $transparent);
        imagesavealpha($diffImg, true);

        $red = imagecolorallocate($diffImg, 255, 0, 0);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {

                $color1 = imagecolorat($image1, $x, $y);
                $color2 = imagecolorat($image2, $x, $y);


                if (!$this->colorsAreSimilar($color1, $color2, 10)) {
                    imagesetpixel($diffImg, $x, $y, $red);
                }

            }
        }

        imagepng($diffImg, $output);

        imagedestroy($image1);
        imagedestroy($image2);
        imagedestroy($diffImg);
    }

    private function colorsAreSimilar(int $c1, int $c2, int $tolerance = 10): bool
    {
        $r1 = ($c1 >> 16) & 0xFF;
        $g1 = ($c1 >> 8) & 0xFF;
        $b1 = $c1 & 0xFF;

        $r2 = ($c2 >> 16) & 0xFF;
        $g2 = ($c2 >> 8) & 0xFF;
        $b2 = $c2 & 0xFF;

        return (
            abs($r1 - $r2) <= $tolerance &&
            abs($g1 - $g2) <= $tolerance &&
            abs($b1 - $b2) <= $tolerance
        );
    }
}