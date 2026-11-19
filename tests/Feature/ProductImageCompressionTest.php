<?php

namespace Tests\Feature;

use App\Utils\Util;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageCompressionTest extends TestCase
{
    public function test_product_image_upload_is_resized_and_compressed()
    {
        Storage::fake('local');
        config([
            'filesystems.default' => 'local',
            'constants.product_img_path' => 'img',
            'constants.image_size_limit' => 5000000,
        ]);

        $image_path = tempnam(sys_get_temp_dir(), 'product-image-');
        $image = imagecreatetruecolor(2000, 1200);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 210));
        imagejpeg($image, $image_path, 100);
        imagedestroy($image);

        try {
            $original_size = filesize($image_path);
            $uploaded_file = new UploadedFile($image_path, 'product.jpg', 'image/jpeg', null, true);
            $request = Request::create('/', 'POST', [], [], ['image' => $uploaded_file]);

            $filename = (new Util())->uploadFile($request, 'image', 'img', 'image');
            $stored_contents = Storage::disk('local')->get('img/'.$filename);
            $dimensions = getimagesizefromstring($stored_contents);

            $this->assertLessThanOrEqual(1600, max($dimensions[0], $dimensions[1]));
            $this->assertLessThan($original_size, strlen($stored_contents));
        } finally {
            if (is_file($image_path)) {
                unlink($image_path);
            }
        }
    }
}
