<?php

namespace app\controllers;

use Yii;
use app\components\utils\{ShoppingListUtils, SystemUtils};
use app\models\{I18n, ShoppingList, User};
use yii\web\NotFoundHttpException;

class ShoppingListController extends PublicController
{

    /**
     * Public shopping list by shopping code
     * @param string $shopping_code
     * @param int $txt
     * @return string
     * @throws NotFoundHttpException
     */
    public function actionIndex(string $shopping_code, int $txt = 0)
    {
        $user = User::getByShoppingCode($shopping_code);

        if (!$user) {
            throw new NotFoundHttpException('Shopping list not found');
        }

        $shopping_lists = ShoppingList::getByUserId($user->getId(), ['ingredient_id', 'weight', 'is_bought']);
        $shopping_list_data = ShoppingListUtils::prepareShoppingList($shopping_lists, $user->measurement, Yii::$app->language);
        $shopping_list_data = ShoppingListUtils::columnizeShoppingListData($shopping_list_data);

        if ($txt == 1) {
            $content = ShoppingListUtils::generateShoppingListTxtContent($shopping_list_data, $user->measurement);
            SystemUtils::prepareHeadersForDownloadFile($content, 'shopping-list.txt');
            Yii::$app->response->content = $content;
            return Yii::$app->response;
        } else {
            $date_cache_sync = ShoppingListUtils::refreshSyncDate($user->getId(), 0, false);
            $this->setPageTitle(I18n::t('public.title.shopping_list'));
            $this->setBreadcrumbs([I18n::t('recipe.favourites.shopping_list')]);
            return $this->render('index', [
                'shopping_lists' => $shopping_list_data,
                'shopping_code' => $shopping_code,
                'user_name' => $user->name,
                'date_sync' => $date_cache_sync
            ]);
        }
    }
}
