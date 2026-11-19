<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    protected $appends = ['display_name', 'display_url'];

    /**
     * Get all of the owning mediable models.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    /**
     * Get display name for the media
     */
    public function getDisplayNameAttribute()
    {
        $array = explode('_', $this->file_name, 3);

        return ! empty($array[2]) ? $array[2] : $array[1];
    }

    /**
     * Get display link for the media
     */
    public function getDisplayUrlAttribute()
    {
        $path = asset('/uploads/media/'.rawurlencode($this->file_name));

        return $path;
    }

    /**
     * Get display path for the media
     */
    public function getDisplayPathAttribute()
    {
        $path = public_path('uploads/media').'/'.rawurlencode($this->file_name);

        return $path;
    }

    /**
     * Get display link for the media
     */
    public function thumbnail($size = [60, 60], $class = null)
    {
        $html = '<img';
        $html .= ' src="'.$this->display_url.'"';
        $html .= ' width="'.$size[0].'"';
        $html .= ' height="'.$size[1].'"';

        if (! empty($class)) {
            $html .= ' class="'.$class.'"';
        }

        $html .= '>';

        return $html;
    }

    /**
     * Uploads files from the request and add's medias to the supplied model.
     *
     * @param  int  $business_id, obj $model, $obj $request, string $file_name
     */
    public static function uploadMedia($business_id, $model, $request, $file_name, $is_single = false, $model_media_type = null)
    {
        //If app environment is demo return null
        if (config('app.env') == 'demo') {
            return null;
        }

        $uploaded_files = [];

        if ($request->hasFile($file_name)) {
            $files = $request->file($file_name);

            //If multiple files present
            if (is_array($files)) {
                foreach ($files as $file) {
                    $uploaded_file = Media::uploadFile($file);

                    if (! empty($uploaded_file)) {
                        $uploaded_files[] = $uploaded_file;
                    }
                }
            } else {
                $uploaded_file = Media::uploadFile($files);
                if (! empty($uploaded_file)) {
                    $uploaded_files[] = $uploaded_file;
                }
            }
        }

        //check if base64
        $base64_input = $request->input($file_name);
        if (is_string($base64_input) && $base64_input !== '') {
            $base64_array = explode(',', $base64_input);

            $base64_string = $base64_array[1] ?? $base64_array[0];

            if (Media::is_base64($base64_string)) {
                $uploaded_files[] = Media::uploadBase64Image($base64_string);
            }
        }

        if (! empty($uploaded_files)) {
            //If one to one relationship upload single file
            if ($is_single) {
                $uploaded_files = $uploaded_files[0];
            }
            // attach media to model
            Media::attachMediaToModel($model, $business_id, $uploaded_files, $request, $model_media_type);
        }
    }

    public static function is_base64($s)
    {
        return (bool) preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $s);
    }

    /**
     * Uploads requested file to storage.
     */
    public static function uploadFile($file)
    {
        $file_name = null;
        if (! $file->isValid()) {
            throw new \InvalidArgumentException('Invalid uploaded file.');
        }

        $allowed_mime_extensions = [];
        foreach (config('constants.document_upload_mimes_types', []) as $mime_type => $extension) {
            $allowed_mime_extensions[$mime_type] = ltrim($extension, '.');
        }
        $allowed_mime_extensions += [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
        ];

        $mime_type = $file->getMimeType();
        if (! isset($allowed_mime_extensions[$mime_type])) {
            throw new \InvalidArgumentException('Unsupported media file type.');
        }

        $max_size = strpos($mime_type, 'image/') === 0
            ? config('constants.image_size_limit')
            : config('constants.document_size_limit');
        if ($file->getSize() > $max_size) {
            throw new \InvalidArgumentException('The uploaded media exceeds the maximum allowed size.');
        }

        $original_name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safe_name = substr(Str::slug($original_name) ?: 'upload', 0, 100);
        $extension = $allowed_mime_extensions[$mime_type];
        $new_file_name = time().'_'.mt_rand().'_'.$safe_name.'.'.$extension;
        if ($file->storeAs('/media', $new_file_name)) {
            $file_name = $new_file_name;
        }

        return $file_name;
    }

    public static function uploadBase64Image($base64_string)
    {
        $image_data = base64_decode($base64_string, true);
        if ($image_data === false || strlen($image_data) > config('constants.image_size_limit')) {
            throw new \InvalidArgumentException('Invalid or oversized base64 image.');
        }

        $image_info = getimagesizefromstring($image_data);
        $image_mime_extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
        ];
        if ($image_info === false || ! isset($image_mime_extensions[$image_info['mime']])) {
            throw new \InvalidArgumentException('Unsupported base64 image type.');
        }

        $file_name = time().'_'.mt_rand().'_media.'.$image_mime_extensions[$image_info['mime']];

        $output_file = public_path('uploads').'/media/'.$file_name;

        if (file_put_contents($output_file, $image_data) === false) {
            throw new \RuntimeException('Unable to store uploaded base64 image.');
        }

        return $file_name;
    }

    /**
     * Deletes resource from database and storage
     */
    public static function deleteMedia($business_id, $media_id)
    {
        $media = Media::where('business_id', $business_id)
                        ->findOrFail($media_id);

        $media_path = public_path('uploads/media/'.$media->file_name);

        if (file_exists($media_path)) {
            unlink($media_path);
        }
        $media->delete();
    }

    public function uploaded_by_user()
    {
        return $this->belongsTo(\App\User::class, 'uploaded_by');
    }

    public static function attachMediaToModel($model, $business_id, $uploaded_files, $request = null, $model_media_type = null)
    {
        if (! empty($uploaded_files)) {
            if (is_array($uploaded_files)) {
                $media_obj = [];
                foreach ($uploaded_files as $value) {
                    $media_obj[] = new \App\Media([
                        'file_name' => $value,
                        'business_id' => $business_id,
                        'description' => ! empty($request->description) ? $request->description : null,
                        'uploaded_by' => ! empty($request->uploaded_by) ? $request->uploaded_by : auth()->user()->id,
                        'model_media_type' => $model_media_type,
                    ]);
                }

                $model->media()->saveMany($media_obj);
            } else {
                //delete previous media if exists
                $model->media()->delete();

                $media_obj = new \App\Media([
                    'file_name' => $uploaded_files,
                    'business_id' => $business_id,
                    'description' => ! empty($request->description) ? $request->description : null,
                    'uploaded_by' => ! empty($request->uploaded_by) ? $request->uploaded_by : auth()->user()->id,
                    'model_media_type' => $model_media_type,
                ]);
                $model->media()->save($media_obj);
            }
        }
    }
}
