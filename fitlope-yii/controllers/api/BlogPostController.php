<?php

namespace app\controllers\api;

use app\components\utils\BlogUtils;
use app\components\utils\DateUtils;
use Yii;
use yii\data\Pagination;
use yii\filters\VerbFilter;
use app\components\api\{ApiErrorPhrase, ApiHttpException};
use app\components\utils\SystemUtils;
use app\components\validators\{BlogPostValidator};
use app\models\{BlogPost, I18n, Image};

class BlogPostController extends ApiController
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
                    'my' => ['GET'],
                    'create' => ['POST'],
                    'update' => ['PUT'],
                    'view' => ['GET'],
                    'delete' => ['DELETE'],
                ],
            ],
        ]);
    }

    /**
     * View user Blog posts
     * @param int $page
     * @return array
     * @throws ApiHttpException
     * @api {get} /blog-post/my Returns user posts
     * @apiName BlogPostMy
     * @apiGroup BlogPost
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound Blog not found
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "Not found"
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *         "data": [{
     *              "id": "5f2270529b2c00005a00115b", slug": "my-blog-post","is_public": true,"published_at": 1596097762, "title_i18n": "Post title",
     *              "content_i18n": "Post text", "image_url": "https://fitdev.s3.amazonaws.com/images/blog/5f2119f7a0a07.jpg"
     *          }, ...]
     *         "success": true,
     *     }
     */
    public function actionMy()
    {
        $pagination = new Pagination(['pageSize' => BlogPost::PER_PAGE]);
        $posts = BlogPost::getPostsPaginated($pagination, ['user_id' => Yii::$app->user->getId()]);;
        $posts = BlogUtils::preparePostsList($posts);
        return [
            'status' => true,
            'data' => $posts
        ];
    }

    /**
     * Add new blog post
     *
     * @api {post} /blog-post Add new blog post
     * @apiName BlogCreate
     * @apiGroup BlogPost
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} title_i18n Title of post
     * @apiParam {string} content_i18n Content of post
     * @apiParam {bool} [is_public] Is post published
     * @apiParam {string} [timage_id] Uploaded image ID
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 400
     *     {
     *         "message": {
     *              "name": "Invalid value"
     *           }
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *         "data": {
     *              "id": "5f2270529b2c00005a00115b", slug": "my-blog-post","is_public": true,"published_at": 1596097762, "title_i18n": "Post title",
     *              "content_i18n": "Post text", "thumbnail": "https://fitdev.s3.amazonaws.com/images/blog/5f2119f7a0a07.jpg"
     *          }
     *         "success": true,
     *     }
     */
    public function actionCreate()
    {
        $form = new BlogPostValidator();
        $form->load(Yii::$app->request->post(), '');
        if (!$form->validate()) {
            throw new ApiHttpException(400, $form->getErrorCodes());
        } else {
            $blog_post = $this->createFromRequest($form->getFilteredAttributes());
            if ($blog_post->save()) {
                // update name of images
                if ($blog_post->timage_id) {
                    Image::setNameByIds($blog_post->showLangField('title'), [$blog_post->timage_id]);
                }
                $response = BlogUtils::preparePostView($blog_post);
                return [
                    'data' => $response,
                    'success' => true
                ];
            } else {
                throw new ApiHttpException();
            }
        }
    }

    /**
     * Update BlogPost
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {put} /blog-post/:id Update existing BlogPost
     * @apiName BlogUpdate
     * @apiGroup BlogPost
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiParam {string} title_i18n Title of post
     * @apiParam {string} content_i18n Content of post
     * @apiParam {bool} [is_public] Is post published
     * @apiParam {string} [timage_id] Uploaded image ID
     * @apiError (400) BadRequest Request parameters are wrong
     * @apiError (404) NotFound BlogPost not found
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "Not found"
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *         "data": {
     *              "id": "5f2270529b2c00005a00115b", slug": "my-blog-post","is_public": true,"published_at": 1596097762, "title_i18n": "Post title",
     *              "content_i18n": "Post text", "image_url": "https://fitdev.s3.amazonaws.com/images/blog/5f2119f7a0a07.jpg"
     *          }
     *         "success": true,
     *     }
     */
    public function actionUpdate(string $id)
    {
        $blog_post = BlogPost::getById($id);
        if ($blog_post) {
            $this->hasAccess($blog_post);
            $form = new BlogPostValidator();
            $form->load(Yii::$app->request->post(), '');
            if (!$form->validate()) {
                throw new ApiHttpException(400, $form->getErrorCodes());
            }
            $blog_post = $this->updateFromRequest($form->getFilteredAttributes(), $blog_post);
            if ($blog_post->save()) {
                $response = BlogUtils::preparePostView($blog_post);
                return [
                    'data' => $response,
                    'success' => true
                ];
            } else {
                throw new ApiHttpException();
            }
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * View BlogPost
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {get} /blog-post/:id Returns BlogPost
     * @apiName BlogView
     * @apiGroup BlogPost
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound BlogPost not found
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "Not found"
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *         "data": {
     *              "id": "5f2270529b2c00005a00115b", slug": "my-blog-post","is_public": true,"published_at": 1596097762, "title_i18n": "Post title",
     *              "content_i18n": "Post text", "image_url": "https://fitdev.s3.amazonaws.com/images/blog/5f2119f7a0a07.jpg"
     *          }
     *         "success": true,
     *     }
     */
    public function actionView(string $id)
    {
        $blog_post = BlogPost::getById($id);
        if ($blog_post) {
            $response = BlogUtils::preparePostView($blog_post);
            return
                [
                    'data' => $response,
                    'success' => true
                ];
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * Delete BlogPost
     * @param string $id
     * @return array
     * @throws ApiHttpException
     * @api {delete} /blog-post/:id Delete BlogPost
     * @apiName BlogDelete
     * @apiGroup BlogPost
     * @apiPermission user
     * @apiHeader {string} Authorization Bearer JWT
     * @apiError (404) NotFound BlogPost not found
     * @apiErrorExample {json} Error-Response:
     *     HTTP/1.1 404
     *     {
     *         "message": "Not found"
     *     }
     * @apiSuccessExample {json} Response:
     *     HTTP/1.1 200
     *     {
     *         "success":true
     *     }
     */
    public function actionDelete(string $id)
    {
        $blog_post = BlogPost::getById($id);
        if ($blog_post) {
            $this->hasAccess($blog_post);
            if ($blog_post->delete()) {
                return ['success' => true];
            } else {
                throw new ApiHttpException();
            }
        }
        throw new ApiHttpException(404, ApiErrorPhrase::NOT_FOUND);
    }

    /**
     * Create from request data
     * @param array $fields
     * @return BlogPost
     */
    private function createFromRequest(array $fields): BlogPost
    {
        $fields = $this->prepareI18nFieldsFromRequest($fields, BlogPost::I18N_FIELDS);
        $blog_post = new BlogPost();
        foreach (BlogPost::I18N_FIELDS as $field) {
            if (!isset($fields[$field][I18n::PRIMARY_LANGUAGE])) {
                $fields[$field][I18n::PRIMARY_LANGUAGE] = '';
            }
        }
        $blog_post->setAttributes($fields);
        $blog_post->user_id = Yii::$app->user->getId();
        if ($blog_post->is_public) {
            $blog_post->published_at = DateUtils::getMongoTimeNow();
        }
        return $blog_post;
    }

    /**
     * Update form request
     * @param array $fields
     * @param BlogPost $blog_post
     * @return BlogPost
     */
    private function updateFromRequest(array $fields, BlogPost $blog_post): BlogPost
    {
        foreach (BlogPost::I18N_FIELDS as $field) {
            if (isset($fields[$field . '_i18n'])) {
                $name = $blog_post->$field;
                $name[Yii::$app->language] = $fields[$field . '_i18n'];
                $blog_post->$field = $name;
                unset($fields[$field . '_i18n']);
            }
        }
        $blog_post->load($fields, '');
        if ($blog_post->is_public && !$blog_post->published_at) {
            $blog_post->published_at = DateUtils::getMongoTimeNow();
        }
        return $blog_post;
    }

    /**
     * Check access to blog_post
     * @param BlogPost $blog_post
     * @throws ApiHttpException
     */
    private function hasAccess(BlogPost $blog_post): void
    {
        if (((string)$blog_post->user_id !== (string)Yii::$app->user->getId()) || !SystemUtils::isAppAdmin()) {
            throw new ApiHttpException(409, ApiErrorPhrase::ACCESS_DENIED);
        }
    }


}
