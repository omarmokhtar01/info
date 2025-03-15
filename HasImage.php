<?php

namespace App\Traits;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait HasImage
{
    /**
     * حفظ الصورة في المسار المحدد
     *
     * @param UploadedFile $image
     * @param string $folder
     * @return string
     */
    public function saveImage(UploadedFile $image, $folder)
{
    $destinationPath = public_path('storage/' . $folder);
    if (!file_exists($destinationPath)) {
        mkdir($destinationPath, 0777, true);
    }

    $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
    $image->move($destinationPath, $imageName);

    return asset('storage/' . $folder . '/' . $imageName);
}


    /**
     * حذف الصورة القديمة إذا كانت موجودة
     *
     * @param string|null $imageUrl
     * @return void
     */
    public function updateImage(UploadedFile $newImage, $folder, $oldImageUrl = null)
    {
        // التأكد من حذف الصورة القديمة
        if ($oldImageUrl) {
            $this->deleteImage($oldImageUrl);
        }

        // حفظ الصورة الجديدة وإرجاع رابطها الصحيح
        return $this->saveImage($newImage, $folder);
    }

    // دالة لحذف الصورة القديمة
    public function deleteImage($imageUrl)
    {
        if ($imageUrl) {
            // استخراج اسم الملف من الرابط
            $path = parse_url($imageUrl, PHP_URL_PATH); // /storage/folder/image.jpg
            $path = str_replace('/storage/', '', $path); // folder/image.jpg

            // حذف الصورة إذا كانت موجودة
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
