<?php

namespace app\controllers\api;

use Yii;
use yii\filters\VerbFilter;
use app\models\Image;
use app\components\S3;
use app\components\api\ApiHttpException;
use app\components\validators\CreateImageRequestValidator;

class ImageController extends ApiController
{

    /**
     * @inheritDoc
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'create' => ['POST']
                ],
            ],
        ]);
    }

    /**
     * @api {post} https://fitdev.s3.amazonaws.com Upload image to AWS
     * @apiDescription To upload an image to AWS, it is needed to get data to call (#Image:ImageCreate)
     * also add parameter "file" to the end of the parameters list
     * @apiGroup Image
     * @apiHeader {string} Content-Type multipart/form-data
     * @apiParam {string} acl AWS ACL
     * @apiParam {string} key AWS filename with path
     * @apiParam {string} Content-Type Image mimeType
     * @apiParam {string} X-Amz-Credential AWS Signed post credentials
     * @apiParam {string} X-Amz-Algorithm AWS Signed post sign algorithm
     * @apiParam {string} X-Amz-Date AWS Signed post expiry date
     * @apiParam {string} X-Amz-Signature Aws Signed post signature
     * @apiParam {string} Policy AWS Signed post policy
     * @apiParam {file} file File to upload (must be the last)
     *
     * @apiSuccess (204) NotContent Success
     * @apiSampleRequest off
     *
     * @apiExample {js} jQuery example
     * const form = new FormData();
     * form.append("X-Amz-Credential", "AKIAVC4WRCO73AU6DZGV/20200716/us-east-1/s3/aws4_request");
     * form.append("X-Amz-Algorithm", "AWS4-HMAC-SHA256");
     * form.append("Policy", "eyJleHB...");
     * form.append("X-Amz-Signature", "a65d7778...");
     * form.append("X-Amz-Date", "20200716T140303Z");
     * form.append("Content-Type", "image/jpeg");
     * form.append("key", "images/recipe/5f105e174ac8c.jpg");
     * form.append("acl", "private");
     * form.append("file", fileInput.files[0], "170117-138968.jpg");
     * const settings = {
     *    "url": "https://fitdev.s3.amazonaws.com",
     *    "method": "POST",
     *    "timeout": 0,
     *    "processData": false,
     *    "mimeType": "multipart/form-data",
     *    "contentType": false,
     *    "data": form
     * };
     * $.ajax(settings).done(function (response) {
     *     console.log(response);
     * });
     */

    /**
     * Creates a new image for upload
     *
     * @api {post} /image/create Create aws signed post to upload
     * @apiDescription To upload an image to AWS
     * it is needed to submit POST form with "fields" to "action"
     * also add parameter "file" to the end of the parameters list
     *
     * @apiName ImageCreate
     * @apiGroup Image
     * @apiPermission User
     * @apiHeader {string} Authorization=Bearer
     * @apiHeader {string} Content-Type=application/json
     * @apiParam {string} category Image category
     * @apiParam {string} mime_type Image mimeType
     * @apiParam {string} size Image size
     * @apiSuccess {string} action Post action of form
     * @apiSuccess {string} url Full url of future image
     * @apiSuccess {string} image_id Image ID
     * @apiSuccess {object} fields Signed post fields
     * @apiSuccess {string} fields.acl AWS ACL
     * @apiSuccess {string} fields.key AWS filename with path
     * @apiSuccess {string} fields.Content-Type Image content type
     * @apiSuccess {string} fields.X-Amz-Credential AWS Signed post credentials
     * @apiSuccess {string} fields.X-Amz-Algorithm AWS Signed post sign algorithm
     * @apiSuccess {string} fields.X-Amz-Date AWS Signed post expiry date
     * @apiSuccess {string} fields.X-Amz-Signature Aws Signed post signature
     * @apiSuccess {string} fields.Policy AWS Signed post policy
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (401) Unauthorized Bad Token
     * @apiError (500) InternalServerError Something went wrong
     */
    public function actionCreate()
    {
        $req = new CreateImageRequestValidator();
        if ($req->load(Yii::$app->request->post(), '') && $req->validate()) {
            $s3 = new S3();
            $presigned_post = $s3->getPresignedPost(uniqid(), $req->mime_type, $s3->filepath_by_category[$req->category]);

            $image = new Image(
                [
                    'user_id' => Yii::$app->user->getId(),
                    'category' => $req->category,
                    'url' => ['en' => $presigned_post['url']]
                ]
            );
            $this->saveModel($image);

            return array_merge(
                $presigned_post,
                ['image_id' => (string)$image->_id]
            );
        } else {
            throw new ApiHttpException(400, $req->getErrorCodes());
        }
    }

}
