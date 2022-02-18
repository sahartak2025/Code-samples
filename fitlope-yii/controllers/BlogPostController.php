<?php

namespace app\controllers;

use app\models\{BlogPost, I18n};
use yii\data\Pagination;
use yii\web\NotFoundHttpException;
use app\components\utils\BlogUtils;
use app\components\helpers\Url;

class BlogPostController extends PublicController
{
    /**
     * Displays index page.
     * @param string|null $_by_ip
     * @param string|null $_by_currency
     * @return string
     * @throws \yii\mongodb\Exception
     */
    public function actionIndex(?string $_by_ip = null, ?string $_by_currency = null)
    {
        $pagination = new Pagination(['pageSize' => BlogPost::PER_PAGE]);

        $posts = BlogPost::getPostsPaginated($pagination, ['is_public' => true, 'manager_id' => ['$ne' => null]]);
        $posts = BlogUtils::preparePostsList($posts);

        $this->setPageTitle(I18n::t('public.title.blogs'));
        $this->setBreadcrumbs([I18n::t('blogs.bc')]);

        return $this->render('index', [
            'posts' => $posts,
            'pagination' => $pagination,
            'ip' => $_by_ip,
            'currency_code' => $_by_currency
        ]);
    }

    /**
     * Displays view page
     * @param string $slug
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionView(string $slug)
    {
        $post = BlogPost::getBySlug($slug, BlogUtils::getDisplaySelectFields());

        if (!$post || !$post->is_public) {
            throw new NotFoundHttpException('The requested page does not exist.');
        }

        $post = BlogUtils::preparePostView($post);

        $this->setPageTitle($post['title_i18n']);
        $this->setBreadcrumbs([
            Url::toPublic(['blog-post/index']) => I18n::t('blogs.bc'),
            $post['title_i18n']
        ]);
        return $this->render('view', [
            'post' => $post
        ]);
    }
}
