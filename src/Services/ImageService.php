<?php

namespace Ozerich\FileStorage\Services;

use Ozerich\FileStorage\Models\File;
use Ozerich\FileStorage\Structures\Scenario;
use Ozerich\FileStorage\Structures\Thumbnail;
use Ozerich\FileStorage\Utils\FileNameHelper;

class ImageService
{
    private static function createTempFile(File $file, Scenario $scenario)
    {
        $fileName = FileNameHelper::get(
            $file->hash, $file->ext, null, false, $scenario->shouldSaveOriginalFilename() ? $file->name : null
        );

        $temp_file = new TempFile($file->ext);

        if (!$scenario->getStorage()->download($fileName, $file->hash, $temp_file->getPath())) {
            throw new \Exception('Failed download file ' . $fileName);
        }

        return $temp_file;
    }

    /**
     * @param File $image
     * @param Scenario $scenario
     * @param Thumbnail|null $thumbnail
     *
     * @return bool
     */
    public static function prepareThumbnails(File $image, Scenario $scenario, ?Thumbnail $thumbnail = null)
    {
        $originalFileName = $scenario->shouldSaveOriginalFilename() ? $image->name : null;

        $fileName = FileNameHelper::get($image->hash, $image->ext, null, false, $originalFileName);
        if ($scenario->getStorage()->exists($fileName, $image->hash) == false) {
            return;
        }

        $temp_file = self::createTempFile($image, $scenario);

        $thumbnails = $thumbnail ? [$thumbnail] : $scenario->getThumbnails();
        foreach ($thumbnails as $thumbnail) {

            $temp_thumbnail = new TempFile($image->ext);

            if (self::prepareThumbnailBySize($temp_file->getPath(), $thumbnail, $temp_thumbnail->getPath(), $scenario->getQuality())) {
                if ($scenario->getStorage()->upload(
                    $temp_thumbnail->getPath(),
                    FileNameHelper::get($image->hash, $image->ext, $thumbnail, false, $originalFileName),
                    $image->hash
                )) {
                    $image->addThumbnail($thumbnail->getDatabaseValue(false, false))->save();
                }
            }

            if ($thumbnail->is2xSupport()) {
                $temp_thumbnail = new TempFile($image->ext);
                if (self::prepareThumbnailBySize($temp_file->getPath(), $thumbnail, $temp_thumbnail->getPath(), $scenario->getQuality(), true, false)) {
                    if ($scenario->getStorage()->upload(
                        $temp_thumbnail->getPath(),
                        FileNameHelper::get($image->hash, $image->ext, $thumbnail, true, $originalFileName),
                        $image->hash
                    )) {
                        $image->addThumbnail($thumbnail->getDatabaseValue(true, false))->save();
                    }
                }
            }

            if ($thumbnail->isWebpSupport()) {
                $temp_thumbnail = new TempFile($image->ext);
                if (self::prepareThumbnailBySize($temp_file->getPath(), $thumbnail, $temp_thumbnail->getPath(), $scenario->getQuality(), false, true)) {
                    if ($scenario->getStorage()->upload(
                        $temp_thumbnail->getPath(),
                        FileNameHelper::get($image->hash, 'webp', $thumbnail, false, $originalFileName),
                    $image->hash,
                    )) {
                        $image->addThumbnail($thumbnail->getDatabaseValue(false, true))->save();
                    }
                }

                if ($thumbnail->is2xSupport()) {
                    $temp_thumbnail = new TempFile($image->ext);
                    if (self::prepareThumbnailBySize($temp_file->getPath(), $thumbnail, $temp_thumbnail->getPath(), $scenario->getQuality(), true, true)) {
                        if ($scenario->getStorage()->upload(
                            $temp_thumbnail->getPath(),
                            FileNameHelper::get($image->hash, 'webp', $thumbnail, true, $originalFileName), $image->hash
                        )) {
                            $image->addThumbnail($thumbnail->getDatabaseValue(true, true))->save();
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $file_path
     * @param Thumbnail $thumbnail
     * @param $thumbnail_file_path
     * @param int $quality
     * @param boolean $is_2x
     * @param boolean $is_webp
     *
     * @return boolean
     */
    private static function prepareThumbnailBySize($file_path, Thumbnail $thumbnail, $thumbnail_file_path, $quality = 100, $is_2x = false, $is_webp = false)
    {
        try {
            $image = new ResizeImage($file_path);
        } catch (\Throwable) {
            return false;
        }

        if (!$image || $image->isValid() == false) {
            return false;
        }

        $width = $thumbnail->getWidth();
        $height = $thumbnail->getHeight();

        if ($width || $height) {
            if ($is_2x) {
                $width = $width ? $width * 2 : null;
                $height = $height ? $height * 2 : null;

                if ($thumbnail->isForce2xSize() == false) {
                    if ($image->getWidth() < $width || $image->getHeight() < $height) {
                        return false;
                    }
                }
            }

            if ($thumbnail->getCrop()) {
                $image->resizeImage($width, $height, 'crop', $thumbnail->isForceSize());
            } else if ($thumbnail->getExact()) {
                $image->resizeImage($width, $height, 'exact', $thumbnail->isForceSize());
            } else {
                $image->resizeImage($width, $height, 'auto', $thumbnail->isForceSize());
            }
        }

        $image->fixExifOrientation();

        if ($is_webp) {
            $image->saveImageAsWebp($thumbnail_file_path, $quality);
        } else {
            $image->saveImage($thumbnail_file_path, $quality);
        }

        return true;
    }

    /**
     * @param $filepath
     * @return array|null
     */
    public static function getImageInfo($filepath)
    {
        if (!is_file($filepath)) {
            return null;
        }

        $thumbnail = getimagesize($filepath);

        return [
            'size' => filesize($filepath),
            'width' => $thumbnail[0],
            'height' => $thumbnail[1],
            'mime' => $thumbnail['mime']
        ];
    }

    public static function mime2ext($mime)
    {
        $all_mimes = '{"png":["image\/png","image\/x-png"],"bmp":["image\/bmp","image\/x-bmp","image\/x-bitmap","image\/x-xbitmap","image\/x-win-bitmap","image\/x-windows-bmp","image\/ms-bmp","image\/x-ms-bmp","application\/bmp","application\/x-bmp","application\/x-win-bitmap"],"gif":["image\/gif"],"jpeg":["image\/jpeg","image\/pjpeg"],"xspf":["application\/xspf+xml"],"vlc":["application\/videolan"],"wmv":["video\/x-ms-wmv","video\/x-ms-asf"],"au":["audio\/x-au"],"ac3":["audio\/ac3"],"flac":["audio\/x-flac"],"ogg":["audio\/ogg","video\/ogg","application\/ogg"],"kmz":["application\/vnd.google-earth.kmz"],"kml":["application\/vnd.google-earth.kml+xml"],"rtx":["text\/richtext"],"rtf":["text\/rtf"],"jar":["application\/java-archive","application\/x-java-application","application\/x-jar"],"zip":["application\/x-zip","application\/zip","application\/x-zip-compressed","application\/s-compressed","multipart\/x-zip"],"7zip":["application\/x-compressed"],"xml":["application\/xml","text\/xml"],"svg":["image\/svg+xml"],"3g2":["video\/3gpp2"],"3gp":["video\/3gp","video\/3gpp"],"mp4":["video\/mp4"],"m4a":["audio\/x-m4a"],"f4v":["video\/x-f4v"],"flv":["video\/x-flv"],"webm":["video\/webm"],"aac":["audio\/x-acc"],"m4u":["application\/vnd.mpegurl"],"pdf":["application\/pdf","application\/octet-stream"],"pptx":["application\/vnd.openxmlformats-officedocument.presentationml.presentation"],"ppt":["application\/powerpoint","application\/vnd.ms-powerpoint","application\/vnd.ms-office","application\/msword"],"docx":["application\/vnd.openxmlformats-officedocument.wordprocessingml.document"],"xlsx":["application\/vnd.openxmlformats-officedocument.spreadsheetml.sheet","application\/vnd.ms-excel"],"xl":["application\/excel"],"xls":["application\/msexcel","application\/x-msexcel","application\/x-ms-excel","application\/x-excel","application\/x-dos_ms_excel","application\/xls","application\/x-xls"],"xsl":["text\/xsl"],"mpeg":["video\/mpeg"],"mov":["video\/quicktime"],"avi":["video\/x-msvideo","video\/msvideo","video\/avi","application\/x-troff-msvideo"],"movie":["video\/x-sgi-movie"],"log":["text\/x-log"],"txt":["text\/plain"],"css":["text\/css"],"html":["text\/html"],"wav":["audio\/x-wav","audio\/wave","audio\/wav"],"xhtml":["application\/xhtml+xml"],"tar":["application\/x-tar"],"tgz":["application\/x-gzip-compressed"],"psd":["application\/x-photoshop","image\/vnd.adobe.photoshop"],"exe":["application\/x-msdownload"],"js":["application\/x-javascript"],"mp3":["audio\/mpeg","audio\/mpg","audio\/mpeg3","audio\/mp3"],"rar":["application\/x-rar","application\/rar","application\/x-rar-compressed"],"gzip":["application\/x-gzip"],"hqx":["application\/mac-binhex40","application\/mac-binhex","application\/x-binhex40","application\/x-mac-binhex40"],"cpt":["application\/mac-compactpro"],"bin":["application\/macbinary","application\/mac-binary","application\/x-binary","application\/x-macbinary"],"oda":["application\/oda"],"ai":["application\/postscript"],"smil":["application\/smil"],"mif":["application\/vnd.mif"],"wbxml":["application\/wbxml"],"wmlc":["application\/wmlc"],"dcr":["application\/x-director"],"dvi":["application\/x-dvi"],"gtar":["application\/x-gtar"],"php":["application\/x-httpd-php","application\/php","application\/x-php","text\/php","text\/x-php","application\/x-httpd-php-source"],"swf":["application\/x-shockwave-flash"],"sit":["application\/x-stuffit"],"z":["application\/x-compress"],"mid":["audio\/midi"],"aif":["audio\/x-aiff","audio\/aiff"],"ram":["audio\/x-pn-realaudio"],"rpm":["audio\/x-pn-realaudio-plugin"],"ra":["audio\/x-realaudio"],"rv":["video\/vnd.rn-realvideo"],"jp2":["image\/jp2","video\/mj2","image\/jpx","image\/jpm"],"tiff":["image\/tiff"],"eml":["message\/rfc822"],"pem":["application\/x-x509-user-cert","application\/x-pem-file"],"p10":["application\/x-pkcs10","application\/pkcs10"],"p12":["application\/x-pkcs12"],"p7a":["application\/x-pkcs7-signature"],"p7c":["application\/pkcs7-mime","application\/x-pkcs7-mime"],"p7r":["application\/x-pkcs7-certreqresp"],"p7s":["application\/pkcs7-signature"],"crt":["application\/x-x509-ca-cert","application\/pkix-cert"],"crl":["application\/pkix-crl","application\/pkcs-crl"],"pgp":["application\/pgp"],"gpg":["application\/gpg-keys"],"rsa":["application\/x-pkcs7"],"ics":["text\/calendar"],"zsh":["text\/x-scriptzsh"],"cdr":["application\/cdr","application\/coreldraw","application\/x-cdr","application\/x-coreldraw","image\/cdr","image\/x-cdr","zz-application\/zz-winassoc-cdr"],"wma":["audio\/x-ms-wma"],"vcf":["text\/x-vcard"],"srt":["text\/srt"],"vtt":["text\/vtt"],"ico":["image\/x-icon","image\/x-ico","image\/vnd.microsoft.icon"],"csv":["text\/x-comma-separated-values","text\/comma-separated-values","application\/vnd.msexcel"],"json":["application\/json","text\/json"]}';
        $all_mimes = json_decode($all_mimes, true);
        foreach ($all_mimes as $key => $value) {
            if (array_search($mime, $value) !== false) return $key;
        }
        return false;
    }
}
