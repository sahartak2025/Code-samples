<?php

/**
 * S3 API abstraction class
 */

namespace app\components;

use GuzzleHttp\Client;
use Yii;
use yii\base\BaseObject;
use yii\web\UploadedFile;
use app\models\{Image, Setting};
use Aws\S3\{S3Client, Exception\S3Exception, PostObjectV4};

class S3 extends BaseObject
{

    const PRESIGNED_TTL = '+10 minutes';

    /**
     * File paths in aws bucket
     * @var string[]
     */
    public array $filepath_by_category = [
        Image::CATEGORY_INGREDIENT => 'images/ingredient',
        Image::CATEGORY_RECIPE => 'images/recipe',
        Image::CATEGORY_BLOG => 'images/blog',
        Image::CATEGORY_GLOBAL => 'images/global',
        Image::CATEGORY_MANAGER => 'images/manager',
        Image::CATEGORY_USER => 'images/user',
        Image::CATEGORY_CUISINE => 'images/cuisine',
        Image::CATEGORY_STORY => 'images/story',
        Image::CATEGORY_REVIEW => 'images/review',
        Image::CATEGORY_RECALL => 'images/recall',
    ];

    /**
     * Extenstion by image mimeType
     * @var string[]
     */
    public array $ext_by_mimetype = [
        'image/jpg' => 'jpg',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    public S3Client $s3;

    /**
     * S3 class initialization
     */
    public function init()
    {
        $this->s3 = new S3Client([
            'version' => '2006-03-01',
            'region' => $this->getDefaultRegion(),
            'credentials' => array(
                'key' => $this->getAccessKey(),
                'secret' => $this->getAccessSecret()
            )
        ]);

    }
    
    /**
     * S3 put file to aws storage by given file url
     * @param string $file_url
     * @param string $filename
     * @param string|null $filepath
     * @param string|null $bucket
     * @param string $acl
     * @return \Aws\Result|null
     */
    public function putByUrl(string $file_url, string $filename, ?string $filepath = '', ?string $bucket = null, $acl = 'public-read')
    {
        $ext_parts = explode('.', $file_url);
        $ext = array_pop($ext_parts);
        if ($ext == 'jpeg') {
            $ext = 'jpg';
        }
        $type = array_search($ext, $this->ext_by_mimetype);
        if (!$type) {
            Yii::error([$ext, $file_url], 'S3FilePutByUrlType');
            return null;
        }
        $key = $filename . '.' . $ext;
        if ($filepath) {
            $key = $filepath . '/' . $key;
        }
    
        $client = new Client();
        try {
            $content = $client->get($file_url)->getBody()->getContents();
            $result = $this->s3->putObject([
                'Bucket' => $bucket ? $bucket : $this->getDefaultBucket(),
                'Key' => $key,
                'Body' => $content,
                'ACL' => $acl,
                'ContentType' => $type
            ]);
        } catch (\Exception $e) {
            Yii::error([$file_url, $e->getMessage()], 'S3FilePutByUrl');
            return null;
        }
        return $result;
        
    }
    
    /**
     * S3 put file to aws storage
     * @param UploadedFile $file
     * @param string $filename
     * @param string|null $filepath
     * @param string|null $bucket
     * @param string $acl
     * @return \Aws\Result
     */
    public function put(UploadedFile $file, string $filename, ?string $filepath = '', ?string $bucket = null, $acl = 'public-read')
    {
        $key = $filename . ".{$this->ext_by_mimetype[$file->type]}";
        if ($filepath) {
            $key = $filepath . '/' . $key;
        }

        try {
            return $this->s3->putObject([
                'Bucket' => $bucket ? $bucket : $this->getDefaultBucket(),
                'Key' => $key,
                'Body' => fopen($file->tempName, 'r'),
                'ACL' => $acl,
                'ContentType' => $file->type
            ]);
        } catch (S3Exception $e) {
            Yii::error($e->getMessage(), 'S3FilePut');
        }
    }

    /**
     * S3 delete file from aws storage
     * @param string $filename
     * @param string|null $filepath
     * @param string|null $bucket
     * @return \Aws\Result
     */
    public function delete(string $filename, ?string $filepath = '', ?string $bucket = null)
    {
        $key = $filename;
        if ($filepath) {
            $key = $filepath . '/' . $key;
        }

        try {
            return $this->s3->deleteObject([
                'Bucket' => $bucket ? $bucket : $this->getDefaultBucket(),
                'Key' => $key
            ]);
        } catch (S3Exception $e) {
            Yii::error($e->getMessage(), 'S3FileDelete');
        }
    }

    /**
     * Downloads a file from s3 and saves to a file
     *
     * @param string $filename
     * @param string|null $bucket
     * @param string|null $local_file_name
     * @return mixed
     */
    public function download(string $filename, ?string $bucket = null, ?string $local_file_name = null)
    {
        try {
            return $this->s3->getObject([
                'Bucket' => $bucket ? $bucket : $this->getDefaultBucket(),
                'Key' => $filename,
                'SaveAs' => $local_file_name
            ]);
        } catch (S3Exception $e) {
            Yii::error($e->getMessage());
            echo "There was an error downloading the file.\n";
        }
    }

    /**
     * Returns presigned POST request for uploads
     * @param string $filename
     * @param string $mime_type
     * @param string|null $filepath
     * @param string|null $bucket
     * @return array
     */
    public function getPresignedPost(string $filename, string $mime_type, ?string $filepath = '', ?string $bucket = null)
    {
        $acl = 'private';

        $key = $filename . ".{$this->ext_by_mimetype[$mime_type]}";
        if ($filepath) {
            $key = $filepath . '/' . $key;
        }

        if (!$bucket) {
            $bucket = $this->getDefaultBucket();
        }

        // Construct an array of conditions for policy
        $opts = [
            ['acl' => $acl],
            ['bucket' => $bucket],
            ['key' => $key],
            ['starts-with', '$Content-Type', $mime_type],
            ['content-length-range', Image::MIN_SIZE, Image::MAX_SIZE],
            ['x-amz-algorithm' => 'AWS4-HMAC-SHA256']
        ];

        $post_obj = new PostObjectV4($this->s3, $bucket, ['acl' => $acl], $opts,self::PRESIGNED_TTL);

        $attrs = $post_obj->getFormAttributes();
        return [
            'action' => $attrs['action'],
            'url' => $attrs['action'] . '/' . $key,
            'fields' => array_merge(
                $post_obj->getFormInputs(),
                [
                    'key' => $key,
                    'Content-Type' => $mime_type
                ]
            )
        ];
    }

    /**
     * Returns S3 key
     * @return string
     */
    public function getAccessKey()
    {
        return Setting::getValue('aws_access_key');
    }

    /**
     * Returns S3 secret
     * @return string
     */
    public function getAccessSecret()
    {
        return Setting::getValue('aws_access_secret');
    }

    /**
     * Returns S3 default bucket
     * @return string
     */
    public function getDefaultBucket()
    {
        return Setting::getValue('aws_default_bucket');
    }

    /**
     * Returns S3 region
     * @return string
     */
    public function getDefaultRegion()
    {
        return Setting::getValue('aws_default_region');
    }

}
